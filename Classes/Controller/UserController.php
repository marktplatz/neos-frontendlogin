<?php

namespace UpAssist\Neos\FrontendLogin\Controller;

/*                                                                             *
 * This script belongs to the Neos Flow package "UpAssist.Neos.FrontendLogin".*
 *                                                                             */

use Flownative\DoubleOptIn\UnknownPresetException;
use GuzzleHttp\Psr7\Uri;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Error\Messages\Notice;
use Neos\Error\Messages\Result;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Session\Exception\SessionNotStartedException;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Domain\Exception;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Service\LinkingService;
use UpAssist\Neos\FrontendLogin\Domain\Model\Dto\NewPasswordDto;
use UpAssist\Neos\FrontendLogin\Domain\Model\PasswordResetToken;
use UpAssist\Neos\FrontendLogin\Domain\Repository\PasswordResetTokenRepository;
use UpAssist\Neos\FrontendLogin\Domain\Service\FrontendUserService;
use UpAssist\Neos\FrontendLogin\Domain\Model\Dto\UserRegistrationDto;
use Neos\Flow\I18n\Service as I18nService;
use UpAssist\Neos\FrontendLogin\Service\EmailService;

/**
 * Controller for displaying a simple user profile for frontend users
 */
class UserController extends ActionController
{
    /**
     * @var string
     */
    protected $defaultViewObjectName = FusionView::class;

    /**
     * @var FrontendUserService
     * @Flow\Inject
     */
    protected FrontendUserService $userService;

    /**
     * @Flow\InjectConfiguration(path="roleIdentifiers")
     * @var array
     */
    protected array $roleIdentifiers;

    /**
     * @var I18nService $i18nService
     * @Flow\Inject
     */
    protected I18nService $i18nService;

    /**
     * @var EmailService $emailService
     * @Flow\Inject
     */
    protected EmailService $emailService;

    /**
     * @var PasswordResetTokenRepository $passwordResetTokenRepository
     * @Flow\Inject
     */
    protected PasswordResetTokenRepository $passwordResetTokenRepository;


    /**
     * @var bool
     * @Flow\InjectConfiguration(path="enableDoubleOptin")
     */
    protected bool $enableDoubleOptin;

    /**
     * @var Translator $translator
     * @Flow\Inject
     */
    protected Translator $translator;

    /**
     *
     * @var string $translationPackage
     * @Flow\InjectConfiguration (path="translationPackage", package="UpAssist.Neos.FrontendLogin")
     */
    protected string $translationPackage = 'UpAssist.Neos.FrontendLogin';


    /**
     * @return void
     */
    public function showAction(User $user = null)
    {
        $user = $user ?? $this->userService->getCurrentUser();
        $this->view->assign('namespace', $this->request->getArgumentNamespace());
        $this->view->assign('user', $user);
        $this->view->assign('electronicAddresses', $user->getElectronicAddresses());
        $this->view->assign('account', $user->getAccounts()->get(0));
        $this->view->assign('roleIdentifiers', $this->roleIdentifiers);
        $this->view->assign('flashMessages', $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush());
    }

    /**
     * @param User $user
     * @return void
     */
    public function editAction(User $user): void
    {
        $this->view->assign('flashMessages', $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush());
        $this->view->assign('roleIdentifiers', $this->roleIdentifiers);
        $this->view->assign('user', $user);
    }

    /**
     * @param User $user
     * @param string $password
     * @return void
     * @throws StopActionException
     */
    public function updateAction(User $user, string $password = null, string $roleIdentifiers = null)
    {
        if ($password) {
            try {
                $this->userService->setUserPassword($user, $password);
            } catch (IllegalObjectTypeException|SessionNotStartedException $e) {
            }
        }

        if ($roleIdentifiers) {
            $this->userService->setRolesForAccount($user->getAccounts()->get(0), [$roleIdentifiers]);
        }

        $this->userService->updateUser($user);
        $this->forward($this->request->getInternalArgument('__action') ?? 'show', null, null, ['user' => $user]);
    }

    /**
     * @return void
     */
    public function newAction()
    {
        $this->view->assign('roleIdentifiers', $this->roleIdentifiers);
        $this->view->assign('flashMessages', $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush());
    }

    /**
     * @return void
     */
    public function newByEmailAction()
    {
        $this->view->assign('roleIdentifiers', $this->roleIdentifiers);
        $this->view->assign('flashMessages', $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush());
    }

    /**
     * @param UserRegistrationDto $newUser
     * @return void
     * @throws StopActionException
     */
    public function createAction(UserRegistrationDto $newUser)
    {
        $this->userService->addUser($newUser->getUsername(), $newUser->getPassword(), $newUser->getUser(), [$newUser->getRoleIdentifier()], null, $this->request);

        $translationId = $this->enableDoubleOptin ? 'flashMessage.user.create.doubleOptin.msg' : 'flashMessage.user.create.msg';
        $this->controllerContext->getFlashMessageContainer()->addMessage(
            new Notice(
                $this->translator->translateById($translationId, [],null,null,'Main', $this->translationPackage),
                null, [],
                $this->translator->translateById('flashMessage.user.create.title', [],null,null,'Main', $this->translationPackage)
            )
        );

        /** @var NodeInterface $redirectNode */
        $redirectNode = $this->request->getInternalArgument('__node')->getProperty('redirectNode');

        if ($redirectNode !== null) {
            $linkingService = new LinkingService();
            try {
                $uri = $linkingService->createNodeUri($this->controllerContext, $redirectNode);
            } catch (\Neos\Flow\Http\Exception|\Neos\Flow\Property\Exception|MissingActionNameException|IllegalObjectTypeException|\Neos\Flow\Security\Exception|\Neos\Neos\Exception $e) {
            }
            $this->redirectToUri($uri);
        }

        $this->redirect('login', 'Login');
    }

    /**
     * @param UserRegistrationDto $newUser
     * @return void
     * @throws StopActionException
     */
    public function createByEmailAction(UserRegistrationDto $newUser)
    {
        $this->userService->addUser($newUser->getFirstEmailAddress(), $newUser->getPassword(), $newUser->getUser(), [$newUser->getRoleIdentifier()], null, $this->request);

        $translationId = $this->enableDoubleOptin ? 'flashMessage.user.create.doubleOptin.msg' : 'flashMessage.user.create.msg';
        $this->controllerContext->getFlashMessageContainer()->addMessage(
            new Notice(
                $this->translator->translateById($translationId, [],null,null,'Main', $this->translationPackage),
                null, [],
                $this->translator->translateById('flashMessage.user.create.title', [],null,null,'Main', $this->translationPackage)
            )
        );

        /** @var NodeInterface $redirectNode */
        $redirectNode = $this->request->getInternalArgument('__node')->getProperty('redirectNode');

        if ($redirectNode !== null) {
            $linkingService = new LinkingService();
            try {
                $uri = $linkingService->createNodeUri($this->controllerContext, $redirectNode);
            } catch (\Neos\Flow\Http\Exception|\Neos\Flow\Property\Exception|MissingActionNameException|IllegalObjectTypeException|\Neos\Flow\Security\Exception|\Neos\Neos\Exception $e) {
            }
            $this->redirectToUri($uri);
        }

        $this->redirect('login', 'Login');
    }

    /**
     * @param string $token
     * @throws StopActionException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws UnknownPresetException
     */
    public function confirmRegistrationAction(string $token)
    {
        $this->userService->confirmUser($token);
        $this->persistenceManager->persistAll();

        $this->addFlashMessage(
            $this->translator->translateById('flashMessage.user.confirm.success.msg', [],null,null,'Main', $this->translationPackage)
        );
        $this->redirect('login', 'Login');
    }

    /**
     * @return void
     */
    public function indexAction()
    {
        $this->view->assign('flashMessages', $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush());
        $this->view->assign('users', $this->userService->findAll());
    }

    /**
     * @param User $user
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     * @throws \Neos\Neos\Domain\Exception
     */
    public function deleteAction(User $user)
    {
        $this->userService->deleteUser($user);
        $this->persistenceManager->persistAll();
        $this->redirect('index');
    }

    /**
     * @throws NoSuchArgumentException
     * @throws Exception
     */
    public function resetPasswordAction()
    {
        $this->view->assign('flashMessages', $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush());

        // Upon email sent
        if ($this->request->hasArgument('emailSent') && $this->request->getArgument('emailSent')) {
            $this->view->assign('status','emailSent');
        }

        // Upon account not found
        if ($this->request->hasArgument('accountNotFound') && $this->request->getArgument('accountNotFound')) {
            $this->view->assign('status','accountNotFound');
        }

        // Upon successful password reset
        if ($this->request->hasArgument('success') && $this->request->getArgument('success')) {
            $this->view->assign('status', 'success');
        }

        // Upon link clicked in email
        if ($this->request->hasArgument('token') || ($this->request->getParentRequest() && $this->request->getParentRequest()->hasArgument('token'))) {
            $token = $this->request->hasArgument('token') ? $this->request->getArgument('token') : $this->request->getParentRequest()->getArgument('token');

            /** @var PasswordResetToken $passwordResetToken */
            $passwordResetToken = $this->passwordResetTokenRepository->findOneByToken($token);
            // Check token on time sensitivity
            // Set the threshold for key expiration (in seconds)
            $expirationThreshold = 3600; // 1 hour

            // Get the current timestamp
            $currentTimestamp = time();
            // Assume that $timeSensitiveKey is the time-sensitive secret key that we want to check
            // We can extract the timestamp from the key by splitting the key into two parts
            $timestampFromKey = (int)substr($token, -10);

            // Compare the difference between the timestamps with the expiration threshold
            if (($currentTimestamp - $timestampFromKey) > $expirationThreshold && $passwordResetToken !== null) {
                // The key has expired
                $this->view->assign('status','invalidToken');
                $this->passwordResetTokenRepository->remove($passwordResetToken);
            } else {
                // The key is still valid
                $this->view->assign('user', $this->userService->getUser($passwordResetToken->getAccount()->getAccountIdentifier()));
                /** @var Result $result */
                $result = $this->request->getInternalArgument('__submittedArgumentValidationResults');
                if ($result && $result->hasErrors()) {
                    $this->view->assign('errors', true);
                }
            }
        }

    }

    /**
     * @param NewPasswordDto $newPassword
     * @return void
     * @throws IllegalObjectTypeException
     * @throws SessionNotStartedException
     * @throws StopActionException
     */
    public function updatePasswordAction(NewPasswordDto $newPassword): void
    {
        $this->userService->setUserPassword($newPassword->getUser(), $newPassword->getPassword()[0]);
        $passwordToken = $this->passwordResetTokenRepository->findOneByAccount($this->userService->getAccountByUser($newPassword->getUser()));
        $this->passwordResetTokenRepository->remove($passwordToken);
        $this->redirect('resetPassword', null, null, ['success' => true]);
    }

    /**
     * @param string $emailAddress
     * @return null
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws StopActionException
     */
    public function sendPasswordResetEmailAction(string $emailAddress)
    {
        $account = $this->userService->getAccountByEmailAddress($emailAddress);
        if ($account) {

            // If a token exists for the current account, remove it
            $oldPasswordToken = $this->passwordResetTokenRepository->findOneByAccount($account);
            if ($oldPasswordToken) {
                $this->passwordResetTokenRepository->remove($oldPasswordToken);
                $this->persistenceManager->persistAll();
            }

            // Generate a random salt
            $salt = bin2hex(random_bytes(16));

            // Generate a secure key using SHA256 hash function
            $secure_key = hash('sha256', $account->getAccountIdentifier() . $salt) . time();
            $passwordResetToken = new PasswordResetToken($secure_key, $account);
            $this->passwordResetTokenRepository->add($passwordResetToken);

            // SEND EMAIL
            /** @var Uri $domain */
            $domain = $this->request->getHttpRequest()->getUri();
            $this->emailService->sendEmail('passwordReset', [
                'recipient' => $this->userService->getUser($passwordResetToken->getAccount()->getAccountIdentifier()),
                'sender' => $this->userService->getUser($passwordResetToken->getAccount()->getAccountIdentifier()),
                'link' => $this->uriBuilder
                    ->setCreateAbsoluteUri(true)
                    ->uriFor('resetPassword', ['token' => $secure_key]),
                'locale' => $this->i18nService->getConfiguration()->getCurrentLocale()->getLanguage(),
                'domain' => $domain->getScheme() . '://' . $domain->getHost() . ($domain->getPort() != '8080' ? ':' . $domain->getPort() : null) . $domain->getPath()
            ]);

            $this->redirect('resetPassword', null, null, ['emailSent' => true]);

        }

        return $this->redirect('resetPassword', null, null, ['accountNotFound' => true]);
    }

    /**
     * Disable the technical error flash message
     *
     * @return boolean
     */
    protected function getErrorFlashMessage()
    {
        return FALSE;
    }
}
