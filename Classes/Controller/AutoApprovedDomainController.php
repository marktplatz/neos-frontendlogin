<?php

namespace UpAssist\Neos\FrontendLogin\Controller;

/*
 * This file is part of the UpAssist.Neos.FrontendLogin package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use UpAssist\Neos\FrontendLogin\Domain\Model\AutoApprovedDomain;
use UpAssist\Neos\FrontendLogin\Domain\Repository\AutoApprovedDomainRepository;

/**
 * @Flow\Scope("singleton")
 */
class AutoApprovedDomainController extends AbstractModuleController
{

    /**
     * @var string
     */
    protected $defaultViewObjectName = FusionView::class;

    /**
     * @Flow\Inject
     * @var AutoApprovedDomainRepository
     */
    protected $autoApprovedDomainRepository;

    /**
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    /**
     * @return void
     */
    public function indexAction(): void
    {
        $this->view->assign('domains', $this->autoApprovedDomainRepository->findAll());
    }

    /**
     * @param string $domain
     * @return void
     * @throws StopActionException
     * @throws IllegalObjectTypeException
     */
    public function createAction(string $domain): void
    {
        $domains = preg_split('/\r\n|\r|\n/', $domain);
        $addedDomains = [];
        $duplicateCount = 0;
        $invalidCount = 0;

        $addedDomainsCountInRequest = [];
        foreach ($domains as $domainItem) {
            $domainItem = trim($domainItem);
            if (empty($domainItem)) {
                continue;
            }

            if (preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/i', $domainItem) !== 1) {
                $invalidCount++;
                continue;
            }

            if (in_array($domainItem, $addedDomainsCountInRequest)) {
                $duplicateCount++;
                continue;
            }

            $existingDomain = $this->autoApprovedDomainRepository->findOneByDomain($domainItem);
            if ($existingDomain !== null) {
                $duplicateCount++;
                continue;
            }

            $newDomain = new AutoApprovedDomain($domainItem);
            $this->autoApprovedDomainRepository->add($newDomain);
            $addedDomains[] = $domainItem;
            $addedDomainsCountInRequest[] = $domainItem;
        }

        if (count($addedDomains) > 0) {
            if ($invalidCount === 0 && $duplicateCount === 0) {
                $this->addFlashMessage(
                    $this->translator->translateById('backend.autoApprovedDomains.flashMessage.create.success', [count($addedDomains)], null, null, 'Main', 'UpAssist.Neos.FrontendLogin') ?? 'Domains added'
                );
            } elseif ($invalidCount > 0 && $duplicateCount > 0) {
                $this->addFlashMessage(
                    $this->translator->translateById('backend.autoApprovedDomains.flashMessage.create.partialSuccess', [count($addedDomains), $invalidCount, $duplicateCount], null, null, 'Main', 'UpAssist.Neos.FrontendLogin') ?? 'Some domains added'
                );
            } elseif ($invalidCount > 0) {
                $this->addFlashMessage(
                    $this->translator->translateById('backend.autoApprovedDomains.flashMessage.create.partialSuccess.invalid', [count($addedDomains), $invalidCount], null, null, 'Main', 'UpAssist.Neos.FrontendLogin') ?? 'Some domains added'
                );
            } else {
                $this->addFlashMessage(
                    $this->translator->translateById('backend.autoApprovedDomains.flashMessage.create.partialSuccess.duplicates', [count($addedDomains), $duplicateCount], null, null, 'Main', 'UpAssist.Neos.FrontendLogin') ?? 'Some domains added'
                );
            }
        } else {
            $this->addFlashMessage(
                $this->translator->translateById('backend.autoApprovedDomains.flashMessage.create.error.noneAdded', [], null, null, 'Main', 'UpAssist.Neos.FrontendLogin') ?? 'No domains were added',
                $this->translator->translateById('backend.autoApprovedDomains.flashMessage.error.title', [], null, null, 'Main', 'UpAssist.Neos.FrontendLogin') ?? 'Error',
                \Neos\Error\Messages\Message::SEVERITY_ERROR
            );
        }

        $this->redirect('index');
    }

    /**
     * @param string $domain
     * @return void
     * @throws StopActionException
     * @throws IllegalObjectTypeException
     */
    public function deleteAction(string $domain): void
    {
        // In backend module requests, passing persistence identifiers can be brittle depending on the
        // rendering context. Use the plain domain string and resolve the entity explicitly.
        $domainObject = $this->autoApprovedDomainRepository->findOneByDomain($domain);
        if ($domainObject !== null) {
            $this->autoApprovedDomainRepository->remove($domainObject);
            $this->addFlashMessage(
                $this->translator->translateById('backend.autoApprovedDomains.flashMessage.delete.success', [], null, null, 'Main', 'UpAssist.Neos.FrontendLogin') ?? 'Domain removed'
            );
        } else {
            $this->addFlashMessage(
                $this->translator->translateById('backend.autoApprovedDomains.flashMessage.delete.error.notFound', [], null, null, 'Main', 'UpAssist.Neos.FrontendLogin') ?? 'Domain not found',
                $this->translator->translateById('backend.autoApprovedDomains.flashMessage.error.title', [], null, null, 'Main', 'UpAssist.Neos.FrontendLogin') ?? 'Error',
                \Neos\Error\Messages\Message::SEVERITY_ERROR
            );
        }
        $this->redirect('index');
    }

    /**
     * @return void
     * @throws StopActionException
     */
    public function deleteAllAction(): void
    {
        $this->autoApprovedDomainRepository->removeAll();
        $this->addFlashMessage(
            $this->translator->translateById('backend.autoApprovedDomains.flashMessage.deleteAll.success', [], null, null, 'Main', 'UpAssist.Neos.FrontendLogin') ?? 'All domains removed'
        );
        $this->redirect('index');
    }
}
