<?php

namespace UpAssist\Neos\FrontendLogin\Controller;

/*                                                                             *
 * This script belongs to the Neos Flow package "UpAssist.Neos.FrontendLogin". *
 *                                                                             */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\View\ViewInterface;
use Neos\Flow\Security\Authentication\Controller\AbstractAuthenticationController;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;
use Neos\Fusion\View\FusionView;
use Neos\Error\Messages as Error;

/**
 * Controller for displaying login/logout forms and a profile for authenticated users
 */
class LoginController extends AbstractAuthenticationController
{
    /**
     * @var string
     */
    protected $defaultViewObjectName = FusionView::class;

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
    public function loginAction()
    {
        $this->view->assign('flashMessages', $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush());
        $this->view->assign('account', $this->securityContext->getAccount());
        $this->view->assign('usernameLabel', $this->request->getInternalArgument('__usernameLabel'));
    }

    /**
     *
     * @Flow\SkipCsrfProtection
     * @return void
     * @throws \Neos\Flow\Mvc\Exception\InvalidActionNameException
     * @throws \Neos\Flow\Mvc\Exception\InvalidArgumentNameException
     * @throws \Neos\Flow\Mvc\Exception\InvalidArgumentTypeException
     * @throws \Neos\Flow\Mvc\Exception\InvalidControllerNameException
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     * @throws \Neos\Flow\Mvc\Exception\UnsupportedRequestTypeException
     * @throws \Neos\Flow\Security\Exception\InvalidArgumentForHashGenerationException
     * @throws \Neos\Flow\Security\Exception\InvalidHashException
     */
    public function logoutAction()
    {
        if ($this->request->getInternalArgument('__suppressFlashMessage') !== true) {
            $this->controllerContext->getFlashMessageContainer()->addMessage(
                new Error\Notice(
                    $this->translator->translateById('flashMessage.login.logout-success.msg', [], null, null, 'Main', $this->translationPackage),
                    null, [],
                    $this->translator->translateById('flashMessage.login.logout-success.title', [], null, null, 'Main', $this->translationPackage)
                )
            );
        }

        /** @var NodeInterface $logoutRedirectNode */
        $logoutRedirectNode = $this->request->getInternalArgument('__logoutRedirectNode');
        if ($logoutRedirectNode !== null) {
            $referer = $this->request->getReferringRequest();
            if ($referer->getControllerPackageKey() === 'Neos.Neos') {

                $this->authenticationManager->logout();
                $this->redirect($referer->getControllerActionName(), $referer->getControllerName(), $referer->getControllerPackageKey(), $referer->getArguments());
            }

            $this->authenticationManager->logout();
            header('Location: ' . $logoutRedirectNode);
            exit();
        }

        $this->authenticationManager->logout();
        $this->redirect('login');
    }

    /**
     * @param ActionRequest $originalRequest The request that was intercepted by the security framework, NULL if there was none
     * @return string
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     * @throws \Neos\Flow\Mvc\Exception\UnsupportedRequestTypeException
     */
    protected function onAuthenticationSuccess(ActionRequest $originalRequest = NULL)
    {
        if ($this->request->getInternalArgument('__suppressFlashMessage') !== true) {
            $this->controllerContext->getFlashMessageContainer()->addMessage(
                new Error\Notice(
                    $this->translator->translateById('flashMessage.login.login-success.msg', [], null, null, 'Main', $this->translationPackage),
                    null, [],
                    $this->translator->translateById('flashMessage.login.login-success.title', [], null, null, 'Main', $this->translationPackage)
                )
            );
        }

        /** @var NodeInterface $redirectNode */
        $redirectNode = $this->request->getInternalArgument('__redirectNode');

        if ($redirectNode !== null) {
            $this->redirectToUri($redirectNode);
        }

        $this->redirect('status');
    }

    /**
     * @param AuthenticationRequiredException|null $exception
     * @return void
     */
    protected function onAuthenticationFailure(AuthenticationRequiredException $exception = null)
    {
        $messageId = 'flashMessage.login.login-error.msg';
        if ($exception && $exception->getPrevious() && $exception->getPrevious() instanceof \Neos\Flow\Security\Exception\AccountExpiredException) {
            $messageId = 'flashMessage.login.account-expired.msg';
        }

        $this->controllerContext->getFlashMessageContainer()->addMessage(
            new Error\Error(
                $this->translator->translateById($messageId, [], null, null, 'Main', $this->translationPackage),
                null, [],
                $this->translator->translateById('flashMessage.login.login-error.title', [], null, null, 'Main', $this->translationPackage)
            )
        );
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
