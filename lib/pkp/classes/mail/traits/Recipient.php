<?php

/**
 * @file classes/mail/traits/Recipient.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Recipient
 *
 * @ingroup mail_traits
 *
 * @brief Mailable trait to set the recipients to an array of Users
 */

namespace PKP\mail\traits;

use BadMethodCallException;
use InvalidArgumentException;
use PKP\identity\Identity;
use PKP\mail\Mailable;
use PKP\mail\variables\RecipientEmailVariable;

trait Recipient
{
    /**
     * @copydoc Illuminate\Mail\Mailable::setAddress()
     *
     * @param null|mixed $name
     */
    abstract protected function setAddress($address, $name = null, $property = 'to');

    /**
     * @copydoc Illuminate\Mail\Mailable::to()
     *
     * @param null|mixed $name
     */
    public function to($address, $name = null)
    {
        throw new BadMethodCallException(static::class . ' doesn\'t support ' . __FUNCTION__ . '(), use recipients() instead');
    }

    /**
     * Set recipients of the email and set values for related template variables
     *
     * @param Identity[] $recipients
     * @param ?string $locale Optional. A locale key to use when setting the recipient names. Default: current locale
     */
    public function recipients(array $recipients, ?string $locale = null): Mailable
    {
        $to = [];
        foreach ($recipients as $recipient) {
            if (!is_a($recipient, Identity::class)) {
                throw new InvalidArgumentException('Expecting an array consisting of instances of ' . Identity::class . ' to be passed to ' . static::class . '::' . __FUNCTION__);
            }
            $to[] = [
                'email' => $recipient->getEmail(),
                'name' => $recipient->getFullName(true, false, $locale),
            ];
        }

        // Override the existing recipient data
        $this->to = [];
        $this->variables = array_filter($this->variables, function ($variable) {
            return !is_a($variable, RecipientEmailVariable::class);
        });

        $this->setAddress($to);
        $this->variables[] = new RecipientEmailVariable($recipients, $this);
        return $this;
    }
}
