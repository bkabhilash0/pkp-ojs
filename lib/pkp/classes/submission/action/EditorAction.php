<?php

/**
 * @file classes/submission/action/EditorAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorAction
 *
 * @ingroup submission_action
 *
 * @brief Editor actions.
 */

namespace PKP\submission\action;

use APP\facades\Repo;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\PKPServices;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\log\PKPSubmissionEventLogEntry;
use PKP\log\SubmissionLog;
use PKP\mail\mailables\ReviewRequest;
use PKP\mail\mailables\ReviewRequestSubsequent;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\notification\PKPNotification;
use PKP\notification\PKPNotificationManager;
use PKP\plugins\Hook;
use PKP\security\AccessKeyManager;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\user\User;
use Symfony\Component\Mailer\Exception\TransportException;

class EditorAction
{
    /**
     * Constructor.
     */
    public function __construct()
    {
    }

    //
    // Actions.
    //

    /**
     * Assigns a reviewer to a submission.
     *
     * @param PKPRequest $request
     * @param object $submission
     * @param int $reviewerId
     * @param ReviewRound $reviewRound
     * @param string $reviewDueDate
     * @param string $responseDueDate
     * @param null|mixed $reviewMethod
     */
    public function addReviewer($request, $submission, $reviewerId, &$reviewRound, $reviewDueDate, $responseDueDate, $reviewMethod = null)
    {
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewer = Repo::user()->get($reviewerId);

        // Check to see if the requested reviewer is not already
        // assigned to review this submission.

        $assigned = $reviewAssignmentDao->reviewerExists($reviewRound->getId(), $reviewerId);

        // Only add the reviewer if he has not already
        // been assigned to review this submission.
        $stageId = $reviewRound->getStageId();
        $round = $reviewRound->getRound();
        if (!$assigned && isset($reviewer) && !Hook::call('EditorAction::addReviewer', [&$submission, $reviewerId])) {
            $reviewAssignment = $reviewAssignmentDao->newDataObject();
            $reviewAssignment->setSubmissionId($submission->getId());
            $reviewAssignment->setReviewerId($reviewerId);
            $reviewAssignment->setDateAssigned(Core::getCurrentDate());
            $reviewAssignment->setStageId($stageId);
            $reviewAssignment->setRound($round);
            $reviewAssignment->setReviewRoundId($reviewRound->getId());
            if (isset($reviewMethod)) {
                $reviewAssignment->setReviewMethod($reviewMethod);
            }
            $reviewAssignmentDao->insertObject($reviewAssignment);

            $this->setDueDates($request, $submission, $reviewAssignment, $reviewDueDate, $responseDueDate);
            // Add notification
            $notificationMgr = new NotificationManager();
            $notificationMgr->createNotification(
                $request,
                $reviewerId,
                PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT,
                $submission->getContextId(),
                PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT,
                $reviewAssignment->getId(),
                Notification::NOTIFICATION_LEVEL_TASK
            );

            // Add log
            SubmissionLog::logEvent($request, $submission, PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_ASSIGN, 'log.review.reviewerAssigned', ['reviewAssignmentId' => $reviewAssignment->getId(), 'reviewerName' => $reviewer->getFullName(), 'submissionId' => $submission->getId(), 'stageId' => $stageId, 'round' => $round]);

            // Send mail
            if (!$request->getUserVar('skipEmail')) {
                $context = PKPServices::get('context')->get($submission->getData('contextId'));
                $user = $request->getUser();
                $emailTemplate = Repo::emailTemplate()->getByKey($submission->getData('contextId'), $request->getUserVar('template'));
                $emailBody = $request->getUserVar('personalMessage');
                $emailSubject = $emailTemplate->getLocalizedData('subject');
                $mailable = $this->createMail($submission, $reviewAssignment, $reviewer, $user, $emailBody, $emailSubject, $context);

                try {
                    Mail::send($mailable);
                } catch (TransportException $e) {
                    $notificationMgr = new PKPNotificationManager();
                    $notificationMgr->createTrivialNotification(
                        $request->getUser()->getId(),
                        PKPNotification::NOTIFICATION_TYPE_ERROR,
                        ['contents' => __('email.compose.error')]
                    );
                    trigger_error('Failed to send email: ' . $e->getMessage(), E_USER_WARNING);
                }
            }
        }
    }

    /**
     * Sets the due date for a review assignment.
     *
     * @param PKPRequest $request
     * @param Submission $submission
     * @param ReviewAssignment $reviewAssignment
     * @param string $reviewDueDate
     * @param string $responseDueDate
     * @param bool $logEntry
     */
    public function setDueDates($request, $submission, $reviewAssignment, $reviewDueDate, $responseDueDate, $logEntry = false)
    {
        $context = $request->getContext();

        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!isset($reviewer)) {
            return false;
        }

        if ($reviewAssignment->getSubmissionId() == $submission->getId() && !Hook::call('EditorAction::setDueDates', [&$reviewAssignment, &$reviewer, &$reviewDueDate, &$responseDueDate])) {
            // Set the review due date
            $defaultNumWeeks = $context->getData('numWeeksPerReview');
            $reviewAssignment->setDateDue($reviewDueDate);

            // Set the response due date
            $defaultNumWeeks = $context->getData('numWeeksPerResponse');
            $reviewAssignment->setDateResponseDue($responseDueDate);

            // update the assignment (with both the new dates)
            $reviewAssignment->stampModified();
            $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
            $reviewAssignmentDao->updateObject($reviewAssignment);

            // N.B. Only logging Date Due
            if ($logEntry) {
                // Add log
                SubmissionLog::logEvent(
                    $request,
                    $submission,
                    PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_SET_DUE_DATE,
                    'log.review.reviewDueDateSet',
                    [
                        'reviewAssignmentId' => $reviewAssignment->getId(),
                        'reviewerName' => $reviewer->getFullName(),
                        'dueDate' => date(
                            PKPString::convertStrftimeFormat($context->getLocalizedDateFormatShort()),
                            strtotime($reviewAssignment->getDateDue())
                        ),
                        'submissionId' => $submission->getId(),
                        'stageId' => $reviewAssignment->getStageId(),
                        'round' => $reviewAssignment->getRound()
                    ]
                );
            }
        }
    }

    /**
     * Create an email representation based on data entered by the editor to the ReviewerForm
     * Associated templates: REVIEW_REQUEST, REVIEW_REQUEST_SUBSEQUENT
     */
    protected function createMail(
        PKPSubmission $submission,
        ReviewAssignment $reviewAssignment,
        User $reviewer,
        User $sender,
        string $emailBody,
        string $emailSubject,
        Context $context
    ): ReviewRequest|ReviewRequestSubsequent {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRound = $reviewRoundDao->getById($reviewAssignment->getReviewRoundId());
        $mailable = $reviewRound->getRound() == 1 ?
            new ReviewRequest($context, $submission, $reviewAssignment) :
            new ReviewRequestSubsequent($context, $submission, $reviewAssignment);

        if ($context->getData('reviewerAccessKeysEnabled')) {
            $accessKeyManager = new AccessKeyManager();
            $expiryDays = ($context->getData('numWeeksPerReview') + 4) * 7;
            $accessKey = $accessKeyManager->createKey($context->getId(), $reviewer->getId(), $reviewAssignment->getId(), $expiryDays);
            $mailable->addData([
                ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL => PKPApplication::get()->getDispatcher()->url(
                    PKPApplication::get()->getRequest(),
                    PKPApplication::ROUTE_PAGE,
                    $context->getData('urlPath'),
                    'reviewer',
                    'submission',
                    null,
                    [
                        'submissionId' => $reviewAssignment->getSubmissionId(),
                        'reviewId' => $reviewAssignment->getId(),
                        'key' => $accessKey,
                    ]
                )
            ]);
        }

        $mailable
            ->body($emailBody)
            ->subject($emailSubject)
            ->sender($sender)
            ->recipients([$reviewer]);

        // Additional template variable
        $mailable->addData([
            'reviewerName' => $mailable->viewData['userFullName'] ?? null,
            'reviewerUserName' => $mailable->viewData['username'] ?? null,
        ]);

        return $mailable;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\action\EditorAction', '\EditorAction');
}
