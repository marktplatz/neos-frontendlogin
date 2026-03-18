<?php

namespace UpAssist\Neos\FrontendLogin\Domain\Service;

/*                                                                             *
 * This script belongs to the Neos Flow package "UpAssist.Neos.FrontendLogin".*
 *                                                                             */

use Flownative\DoubleOptIn\Helper;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Service as I18nService;
use Neos\Flow\Security\Account;
use Neos\Neos\Domain\Exception;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Service\UserService;
use UpAssist\Neos\FrontendLogin\Service\EmailService;

/**
 * Central authority to deal with "frontend users"
 *
 */
class FrontendUserService extends UserService
{
    protected $defaultAuthenticationProviderName = 'UpAssist.Neos.FrontendLogin:Frontend';

    /**
     * @var EmailService
     * @Flow\Inject
     */
    protected EmailService $emailService;

    /**
     * @var I18nService $i18nService
     * @Flow\Inject
     */
    protected I18nService $i18nService;

    /**
     * @var Helper
     * @Flow\Inject
     */
    protected Helper $doubleOptInHelper;

    /**
     * @var bool
     * @Flow\InjectConfiguration(path="enableDoubleOptin")
     */
    protected bool $enableDoubleOptin;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="autoApprovedDomains")
     */
    protected array $autoApprovedDomains;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="autoApprovedRole")
     */
    protected string $autoApprovedRole;

    /**
     * Returns the currently logged in user, if any
     *
     * @return Account The currently logged in user, or null
     * @api
     */
    public function getCurrentAccount()
    {
        if ($this->securityContext->canBeInitialized() === true) {
            $account = $this->securityContext->getAccount();
            if ($account !== null) {
                return $account;
            }
        }

        return null;
    }

    /**
     * @param User $user
     * @return Account|null
     */
    public function getAccountByUser(User $user): ?Account
    {
        return isset($user->getAccounts()->toArray()[0]) ? $user->getAccounts()->toArray()[0] : null;
    }

    /**
     * @param string $value
     * @return string
     * @throws \Neos\Neos\Domain\Exception
     */
    public function getAccountIdentifierByLastName($value)
    {
        $query = $this->accountRepository->createQuery();
        $accounts = $query->matching(
            $query->logicalAnd(
                $query->equals('authenticationProviderName', $this->defaultAuthenticationProviderName)
            )
        )->execute()->toArray();

        /** @var Account $account */
        foreach ($accounts as $account) {
            if ($this->getUser($account->getAccountIdentifier())->getName()->getLastName() === $value) {
                return $account->getAccountIdentifier();
            }
        }

        return null;
    }

    /**
     * @param string $emailAddress
     * @return Account|null
     * @throws Exception
     */
    public function getAccountByEmailAddress(string $emailAddress): ?Account
    {
        $query = $this->accountRepository->createQuery();
        $accounts = $query->matching(
            $query->logicalAnd(
                $query->equals('authenticationProviderName', $this->defaultAuthenticationProviderName)
            )
        )->execute();

        /** @var Account $account */
        foreach ($accounts as $account) {
            $user = $this->partyService->getAssignedPartyOfAccount($account);
            if (!$user instanceof User) {
                continue;
            }

            foreach ($user->getElectronicAddresses() as $electronicAddress) {
                if ($electronicAddress->getIdentifier() === $emailAddress) {
                    return $account;
                }
            }
        }

        return null;
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        $query = $this->accountRepository->createQuery();
        $accounts = $query->matching(
            $query->logicalAnd(
                $query->equals('authenticationProviderName', $this->defaultAuthenticationProviderName)
            )
        )->execute()->toArray();
        $users = [];

        /** @var Account $account */
        foreach ($accounts as $account) {
            $users[] = $this->partyService->getAssignedPartyOfAccount($account);
        }

        return $users;
    }

    /**
     * @param string $username
     * @param string $password
     * @param User $user
     * @param array|null $roleIdentifiers
     * @param null $authenticationProviderName
     * @param ActionRequest|null $request
     * @return User
     * @throws Exception
     */
    public function addUser($username, $password, User $user, array $roleIdentifiers = null, $authenticationProviderName = null, ActionRequest $request = null)
    {
        if ($this->getUser($username) !== null) {
            throw new Exception(sprintf('User with username "%s" already exists', $username), 1710756000);
        }

        $emailAddress = $user->getElectronicAddresses()->first();
        if ($emailAddress instanceof \Neos\Party\Domain\Model\ElectronicAddress && $this->getAccountByEmailAddress($emailAddress->getIdentifier()) !== null) {
            throw new Exception(sprintf('User with email address "%s" already exists', $emailAddress->getIdentifier()), 1710756001);
        }

        if ($request !== null) {
            $this->doubleOptInHelper->setRequest($request);
        }

        $user = parent::addUser($username, $password, $user, $roleIdentifiers, $authenticationProviderName);
        $this->assignAutoApprovedRoles($user);

        if ($this->enableDoubleOptin) {
            $account = $this->getAccountByUser($user);
            if ($account instanceof Account) {
                $account->setExpirationDate(new \DateTime());
                $this->setRolesForAccount($account, ['UpAssist.Neos.FrontendLogin:UnconfirmedUser']);
                $this->accountRepository->update($account);
            }

            $token = $this->doubleOptInHelper->generateToken($user->getElectronicAddresses()[0]->getIdentifier(), 'upassist-neos-frontendlogin', [
                'username' => $username,
                'locale' => $this->i18nService->getConfiguration()->getCurrentLocale()->getLanguage()
            ]);

            $this->emailService->sendEmail('confirmRegistration', [
                'recipient' => $user,
                'username' => $username,
                'link' => $this->doubleOptInHelper->getActivationLink($token),
                'locale' => $this->i18nService->getConfiguration()->getCurrentLocale()->getLanguage()
            ]);
        } else {
            if (!$this->isAutoApproved($user)) {
                $this->emailService->sendEmail('adminNotification', [
                    'username' => $username,
                    'locale' => $this->i18nService->getConfiguration()->getCurrentLocale()->getLanguage()
                ]);
            }
        }

        return $user;
    }

    /**
     * @param string $tokenHash
     * @return void
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Flownative\DoubleOptIn\UnknownPresetException
     */
    public function confirmUser(string $tokenHash): void
    {
        $token = $this->doubleOptInHelper->validateTokenHash($tokenHash);
        if ($token === null) {
            return;
        }

        $account = $this->getAccountByEmailAddress($token->getIdentifier());
        if ($account === null) {
            return;
        }

        $account->setExpirationDate(null);
        $this->setRolesForAccount($account, ['UpAssist.Neos.FrontendLogin:User']);
        $this->accountRepository->update($account);

        $user = $this->partyService->getAssignedPartyOfAccount($account);
        if ($user instanceof User) {
            $this->assignAutoApprovedRoles($user);
            $this->emitUserConfirmed($user);
        }

        if (!$this->isAutoApproved($user)) {
            $meta = $token->getMeta();
            $this->emailService->sendEmail('adminNotification', [
                'username' => $account->getAccountIdentifier(),
                'locale' => $meta['locale'] ?? $this->i18nService->getConfiguration()->getCurrentLocale()->getLanguage()
            ]);
        }
    }

    /**
     * Signals that the given user has been confirmed via Double Opt-in.
     *
     * @param User $user The confirmed user
     * @return void
     * @Flow\Signal
     * @api
     */
    public function emitUserConfirmed(User $user)
    {
    }

    /**
     * @param User $user
     * @return void
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     * @throws \Neos\Flow\Session\Exception\SessionNotStartedException
     */
    public function deleteUser(User $user)
    {
        $this->emitUserDeleteRequested($user);
        if ($user->getElectronicAddresses()->count()) {
            foreach ($user->getElectronicAddresses() as $electronicAddress) {
                $user->removeElectronicAddress($electronicAddress);
            }
        }
        parent::deleteUser($user); // TODO: Change the autogenerated stub
    }

    /**
     * Signals that the given user has been requested to delete.
     *
     * @param User $user The to be deleted user
     * @return void
     * @Flow\Signal
     * @api
     */
    public function emitUserDeleteRequested(User $user)
    {
    }

    /**
     * @param User $user
     * @return bool
     */
    protected function isAutoApproved(User $user): bool
    {
        if (empty($this->autoApprovedDomains)) {
            return false;
        }

        foreach ($user->getAccounts() as $account) {
            foreach ($account->getRoles() as $role) {
                if ($role->getIdentifier() === $this->autoApprovedRole) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param User $user
     * @return void
     */
    protected function assignAutoApprovedRoles(User $user): void
    {
        if (empty($this->autoApprovedDomains)) {
            return;
        }

        $userEmail = null;
        if ($user->getElectronicAddresses()->count() > 0) {
            $userEmail = $user->getElectronicAddresses()->first()->getIdentifier();
        }

        if ($userEmail !== null && str_contains($userEmail, '@')) {
            $domain = substr(strrchr($userEmail, "@"), 1);
            if (in_array($domain, $this->autoApprovedDomains)) {
                $roleIdentifiers = [];
                foreach ($user->getAccounts() as $account) {
                    foreach ($account->getRoles() as $role) {
                        $roleIdentifiers[] = $role->getIdentifier();
                    }
                }
                $roleIdentifiers = array_unique($roleIdentifiers);

                if (!in_array($this->autoApprovedRole, $roleIdentifiers)) {
                    $roleIdentifiers[] = $this->autoApprovedRole;
                    $roleIdentifiers = array_unique($roleIdentifiers);

                    foreach ($user->getAccounts() as $account) {
                        $this->setRolesForAccount($account, $roleIdentifiers);
                    }
                }
            }
        }
    }
}
