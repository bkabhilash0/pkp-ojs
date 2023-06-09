<?php

/**
 * @file controllers/grid/settings/user/form/UserEmailForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserEmailForm
 *
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Form for sending an email to a user
 */

namespace PKP\controllers\grid\settings\user\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use Exception;
use Illuminate\Support\Facades\Mail;
use PKP\form\Form;
use PKP\mail\Mailable;
use PKP\notification\PKPNotification;

class UserEmailForm extends Form
{
    /** @var int The user id of user to send email to */
    public $userId;

    /**
     * Constructor.
     *
     * @param int $userId User ID to contact.
     */
    public function __construct($userId)
    {
        parent::__construct('controllers/grid/settings/user/form/userEmailForm.tpl');

        $this->userId = (int) $userId;

        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'subject', 'required', 'email.subjectRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'message', 'required', 'email.bodyRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars([
            'subject',
            'message',
        ]);
    }

    /**
     * @copydoc Form::Fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $user = Repo::user()->get($this->userId);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'userId' => $this->userId,
            'userFullName' => $user->getFullName(),
            'userEmail' => $user->getEmail(),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Send the email
     *
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $toUser = Repo::user()->get($this->userId);
        $request = Application::get()->getRequest();
        $fromUser = $request->getUser();

        $mailable = new Mailable();
        $mailable
            ->from($fromUser->getEmail(), $fromUser->getFullName())
            ->to($toUser->getEmail(), $toUser->getFullName())
            ->subject($this->getData('subject'))
            ->body($this->getData('message'));

        parent::execute(...$functionArgs);

        try {
            Mail::send($mailable);
        } catch (Exception $e) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $request->getUser()->getId(),
                PKPNotification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            error_log($e->getMessage());
        }
    }
}
