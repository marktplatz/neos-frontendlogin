<?php

namespace UpAssist\Neos\FrontendLogin\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Model\User;
use Neos\SwiftMailer\Message;

/**
 * @Flow\Scope("singleton")
 */
class EmailService
{

    /**
     * @var string
     * @Flow\InjectConfiguration(path="emailTemplates")
     */
    protected $template;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="defaultSenderEmail")
     */
    protected $defaultSenderEmail;

    /**
     * @param string $templateName
     * @param array $arguments
     * @return bool
     */
    public function sendEmail(string $templateName, array $arguments): bool
    {
        $mail = new Message();

        if (!isset($this->template[$templateName])) return false;

        $locale = $arguments['locale'] ?? 'en';

        $variables = match ($templateName) {
            'passwordReset' => [
                'recipient' => $arguments['recipient']->getName()->getFirstName(),
                'sender' => $arguments['sender']->getName()->getFirstName(),
                'domain' => $arguments['domain'],
                'link' => $arguments['link'],
                'locale' => $locale
            ],
            'adminNotification' => [
                'recipient' => $this->template[$templateName][$locale]['to'],
                'sender' => $this->template[$templateName][$locale]['from'],
                'username' => $arguments['username'],
                'locale' => $locale
            ],
            'confirmRegistration' => [
                'recipient' => $arguments['recipient']->getName()->getFirstName(),
                'sender' => $this->template[$templateName][$locale]['from'] ?? $this->template[$templateName]['en']['from'] ?? '',
                'username' => $arguments['username'],
                'link' => $arguments['link'],
                'locale' => $locale
            ],
            default => [
                'recipient' => $arguments['recipient']->getName()->getFirstName(),
                'sender' => $arguments['sender']->getName()->getFirstName(),
                'locale' => $locale
            ],
        };

        if (isset($this->template[$templateName][$locale])) {
            $template = $this->prepareTemplate($this->template[$templateName][$locale]['message'], $variables);
            $subject = $this->prepareTemplate($this->template[$templateName][$locale]['subject'], $variables);
        } else {
            $template = $this->prepareTemplate($this->template[$templateName]['en']['message'], $variables);
            $subject = $this->prepareTemplate($this->template[$templateName]['en']['subject'], $variables);
        }

        if (isset($this->template[$templateName][$locale]['to'])) {
            $recipient = $this->template[$templateName][$locale]['to'];
        } elseif (isset($arguments['recipient']) && $arguments['recipient'] instanceof User) {
            $recipient = [$arguments['recipient']->getElectronicAddresses()[0]->getIdentifier() => $arguments['recipient']->getName()];
        } else {
            $recipient = $this->template[$templateName]['en']['to'] ?? 'admin@example.com';
        }

        if (isset($this->template[$templateName][$locale]['from'])) {
            $sender = $this->template[$templateName][$locale]['from'];
        } elseif (isset($arguments['sender']) && $arguments['sender'] instanceof User) {
            $sender = [$arguments['sender']->getElectronicAddresses()[0]->getIdentifier() => $arguments['sender']->getName()];
        } else {
            $sender = $this->template[$templateName]['en']['from'] ?? $this->defaultSenderEmail;
        }

        $mail
            ->setFrom($sender)
            ->setTo($recipient)
            ->setSubject($subject);


        $mail->setBody($template, 'text/plain');

        $mail->send();

        return $mail->isSent();
    }

    /**
     * @param string $input
     * @param array $variables
     * @return array|string|null
     */
    private function prepareTemplate(string $input, array $variables): array|string|null
    {
        $regex = '/\{([^}]+)\}/';

        return preg_replace_callback($regex, function ($matches) use ($variables) {
            if (empty($matches)) return '';
            $variableName = $matches[1];
            return $variables[$variableName] ?? '';
        }, $input, -1, $count, PREG_UNMATCHED_AS_NULL);
    }
}
