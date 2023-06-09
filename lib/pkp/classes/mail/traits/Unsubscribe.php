<?php

/**
 * @file classes/mail/traits/Unsubscribe.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Unsubscribe
 *
 * @ingroup mail_traits
 *
 * @brief trait to embed footer with unsubscribe link to notification emails
 */

namespace PKP\mail\traits;

use APP\core\Application;
use APP\mail\variables\ContextEmailVariable;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use Exception;
use PKP\mail\Mailable;
use PKP\mail\variables\ContextEmailVariable as PKPContextEmailVariable;

trait Unsubscribe
{
    protected static string $unsubscribeUrl = 'unsubscribeUrl';

    // Notification to generate unsubscribe link
    protected Notification $notification;

    // Locale key used by default in the footer if none is specified by Mailable::setupUnsubscribeFooter()
    protected string $defaultUnsubscribeLocaleKey = 'emails.footer.unsubscribe';

    /**
     * @var string[] template variables required for the unsubscribe footer
     */
    protected static array $requiredVariables = [PKPContextEmailVariable::class];

    abstract public function addData(array $data): Mailable;
    abstract public function getVariables(): array;

    /**
     * Use this public method to set footer for this email
     */
    public function allowUnsubscribe(Notification $notification): self
    {
        $this->notification = $notification;

        if (!$this->hasRequiredVariables()) {
            throw new Exception(
                'Mailable should include the following template variables: ' .
                implode(',', static::getRequiredVariables())
            );
        }
        return $this;
    }

    /**
     * Setup footer with unsubscribe link if notification is deliberately set with self::allowUnsubscribe()
     *
     * @param null|mixed $localeKey
     */
    protected function setupUnsubscribeFooter(string $locale, $context, $localeKey = null): void
    {
        if (!isset($this->notification)) {
            return;
        }

        $this->footer = $this->renameContextVariables($this->setFooterText($locale, $localeKey));

        $notificationManager = new NotificationManager(); /** @var NotificationManager $notificationManager */
        $request = Application::get()->getRequest();
        $this->addData([
            self::$unsubscribeUrl => $notificationManager->getUnsubscribeNotificationUrl(
                $request,
                $this->notification,
                $context
            ),
        ]);
    }

    /**
     * @return bool whether mailable contains variables requires for the footer
     */
    protected function hasRequiredVariables(): bool
    {
        $included = [];
        $requiredVariables = static::getRequiredVariables();
        foreach ($requiredVariables as $requiredVariable) {
            foreach ($this->getVariables() as $variable) {
                if (is_a($variable, $requiredVariable, true)) {
                    $included[] = $requiredVariable;
                    break;
                }
            }
        }

        return count($included) === count($requiredVariables);
    }

    /**
     * Replace email template variables in the locale string, so they correspond to the application,
     * e.g., contextName => journalName/pressName/serverName
     */
    protected function renameContextVariables(string $footer): string
    {
        $map = [
            '{$' . PKPContextEmailVariable::CONTEXT_NAME . '}' => '{$' . ContextEmailVariable::CONTEXT_NAME . '}',
            '{$' . PKPContextEmailVariable::CONTEXT_URL . '}' => '{$' . ContextEmailVariable::CONTEXT_URL . '}',
            '{$' . PKPContextEmailVariable::CONTEXT_SIGNATURE . '}' => '{$' . ContextEmailVariable::CONTEXT_SIGNATURE . '}',
        ];

        return str_replace(array_keys($map), array_values($map), $footer);
    }

    /**
     * Set the message to be displayed in the footer
     */
    protected function setFooterText(string $locale, string $localeKey = null): string
    {
        if (is_null($localeKey)) {
            $localeKey = $this->defaultUnsubscribeLocaleKey;
        }

        return __($localeKey, [], $locale);
    }

    protected static function getRequiredVariables(): array
    {
        return static::$requiredVariables;
    }
}
