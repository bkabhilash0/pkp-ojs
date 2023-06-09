<?php

/**
 * @file classes/mail/mailables/UserCreated.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCreated
 *
 * @ingroup mail_mailables
 *
 * @brief Email sent when a new user is added from the user management screen.
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\user\User;

class UserCreated extends Mailable
{
    use Recipient {
        recipients as traitRecipients;
    }
    use Configurable;
    use Sender;

    protected static ?string $name = 'mailable.userRegister.name';
    protected static ?string $description = 'mailable.userRegister.description';
    protected static ?string $emailTemplateKey = 'USER_REGISTER';
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
    ];
    protected static array $toRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_AUTHOR,
        Role::ROLE_ID_READER,
        Role::ROLE_ID_REVIEWER,
        Role::ROLE_ID_SUBSCRIPTION_MANAGER,
    ];
    protected static ?string $variablePassword = 'password';

    public function __construct(Context $context)
    {
        parent::__construct(func_get_args());
    }

    /**
     * @copydoc Mailable::getDataDescriptions()
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        return static::addPasswordVariable($variables);
    }

    /**
     * Add a description to a new password variable
     */
    protected static function addPasswordVariable(array $variables): array
    {
        $variables[static::$variablePassword] = __('emailTemplate.variable.password');
        return $variables;
    }

    /**
     * Override trait's method to include user password variable
     */
    public function recipients(User $recipient, ?string $locale = null): Mailable
    {
        $this->traitRecipients([$recipient], $locale);
        $this->addData([
            static::$variablePassword => $recipient->getPassword()
        ]);

        return $this;
    }
}
