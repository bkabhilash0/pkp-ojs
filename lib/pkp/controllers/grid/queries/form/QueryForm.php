<?php

/**
 * @file controllers/grid/queries/form/QueryForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryForm
 *
 * @ingroup controllers_grid_users_queries_form
 *
 * @brief Form for adding/editing a new query
 */

namespace PKP\controllers\grid\queries\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\controllers\grid\queries\traits\StageMailable;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\query\QueryDAO;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\submission\reviewAssignment\ReviewAssignment;

class QueryForm extends Form
{
    use StageMailable;

    /** @var int Application::ASSOC_TYPE_... */
    public $_assocType;

    /** @var int Assoc ID (per _assocType) */
    public $_assocId;

    /** @var int The stage id associated with the query being edited */
    public $_stageId;

    /** @var Query The query being edited */
    public $_query;

    /** @var bool True iff this is a newly-created query */
    public $_isNew;

    /**
     * Constructor.
     *
     * @param Request $request
     * @param int $assocType Application::ASSOC_TYPE_...
     * @param int $assocId Assoc ID (per assocType)
     * @param int $stageId WORKFLOW_STAGE_...
     * @param int $queryId Optional query ID to edit. If none provided, a
     *  (potentially temporary) query will be created.
     */
    public function __construct($request, $assocType, $assocId, $stageId, $queryId = null)
    {
        parent::__construct('controllers/grid/queries/form/queryForm.tpl');
        $this->setStageId($stageId);

        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        if (!$queryId) {
            $this->_isNew = true;

            // Create a query
            $query = $queryDao->newDataObject();
            $query->setAssocType($assocType);
            $query->setAssocId($assocId);
            $query->setStageId($stageId);
            $query->setSequence(REALLY_BIG_NUMBER);
            $queryDao->insertObject($query);
            $queryDao->resequence($assocType, $assocId);

            // Add the current user as a participant by default.
            $queryDao->insertParticipant($query->getId(), $request->getUser()->getId());

            // Create a head note
            $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
            $headNote = $noteDao->newDataObject();
            $headNote->setUserId($request->getUser()->getId());
            $headNote->setAssocType(Application::ASSOC_TYPE_QUERY);
            $headNote->setAssocId($query->getId());
            $headNote->setDateCreated(Core::getCurrentDate());
            $noteDao->insertObject($headNote);
        } else {
            $query = $queryDao->getById($queryId, $assocType, $assocId);
            assert(isset($query));
            // New queries will not have a head note.
            $this->_isNew = !$query->getHeadNote();
        }

        $this->setQuery($query);

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'users', 'required', 'stageParticipants.notify.warning', function ($users) {
            return count($users) > 1;
        }));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'subject', 'required', 'submission.queries.subjectRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'comment', 'required', 'submission.queries.messageRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    //
    // Getters and Setters
    //
    /**
     * Set the flag indicating whether the query is new (i.e. creates a placeholder that needs deleting on cancel)
     *
     */
    public function setIsNew(bool $isNew)
    {
        $this->_isNew = $isNew;
    }

    /**
     * Get the query
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->_query;
    }

    /**
     * Set the query
     *
     * @param Query $query
     */
    public function setQuery($query)
    {
        $this->_query = $query;
    }

    /**
     * Get the stage id
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->_stageId;
    }

    /**
     * Set the stage id
     *
     * @param int $stageId
     */
    public function setStageId($stageId)
    {
        $this->_stageId = $stageId;
    }

    /**
     * Get assoc type
     *
     * @return int Application::ASSOC_TYPE_...
     */
    public function getAssocType()
    {
        return $this->getData('assocType');
    }

    /**
     * Set assoc type
     *
     * @param int $assocType Application::ASSOC_TYPE_...
     */
    public function setAssocType($assocType)
    {
        $this->setData('assocType', $assocType);
    }

    /**
     * Get assoc id
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getData('assocId');
    }

    /**
     * Set assoc id
     *
     * @param int $assocId
     */
    public function setAssocId($assocId)
    {
        $this->setData('assocId', $assocId);
    }


    //
    // Overridden template methods
    //
    /**
     * Initialize form data from the associated author.
     */
    public function initData()
    {
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        if ($query = $this->getQuery()) {
            $headNote = $query->getHeadNote();
            $this->_data = [
                'queryId' => $query->getId(),
                'subject' => $headNote ? $headNote->getTitle() : null,
                'comment' => $headNote ? $headNote->getContents() : null,
                'userIds' => $queryDao->getParticipantIds($query->getId()),
                'template' => null,
            ];
        } else {
            // set initial defaults for queries.
        }
        // in order to be able to use the hook
        return parent::initData();
    }

    /**
     * Fetch the form.
     *
     * @see Form::fetch()
     *
     * @param PKPRequest $request
     * @param array $actionArgs Optional list of additional arguments
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false, $actionArgs = [])
    {
        $query = $this->getQuery();
        $headNote = $query->getHeadNote();
        $user = $request->getUser();
        $context = $request->getContext();

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'isNew' => $this->_isNew,
            'noteId' => $headNote->getId(),
            'actionArgs' => $actionArgs,
            'csrfToken' => $request->getSession()->getCSRFToken(),
            'stageId' => $this->getStageId(),
            'assocId' => $query->getAssocId(),
            'assocType' => $query->getAssocType(),
        ]);

        // Queries only support Application::ASSOC_TYPE_SUBMISSION so far
        if ($query->getAssocType() !== PKPApplication::ASSOC_TYPE_SUBMISSION) {
            return parent::fetch($request, $template, $display);
        }

        $submission = Repo::submission()->get($query->getAssocId());

        // Add the templates that can be used for this discussion
        $templateKeySubjectPairs = [];
        if ($user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT], $context->getId())) {
            $mailable = $this->getStageMailable($context, $submission);
            $data = $mailable->getData();
            $defaultTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());
            $templateKeySubjectPairs = [$mailable::getEmailTemplateKey() => $defaultTemplate->getLocalizedData('name')];
            $alternateTemplates = Repo::emailTemplate()->getCollector($context->getId())
                ->alternateTo([$mailable::getEmailTemplateKey()])
                ->getMany();
            foreach ($alternateTemplates as $alternateTemplate) {
                $templateKeySubjectPairs[$alternateTemplate->getData('key')] = Mail::compileParams(
                    $alternateTemplate->getLocalizedData('name'),
                    $data
                );
            }
        }

        $templateMgr->assign('templates', $templateKeySubjectPairs);

        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */

        // Get currently selected participants in the query
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $assignedParticipants = $query->getId() ? $queryDao->getParticipantIds($query->getId()) : [];

        // Always include current user, even if not with a stage assignment
        $includeUsers[] = $user->getId();
        $excludeUsers = null;

        // When in review stage, include/exclude users depending on the current users role
        $reviewAssignments = [];
        // Get current users roles
        $assignedRoles = (function () use ($stageAssignmentDao, $query, $user) {
            $assignedRoles = [];
            $usersAssignments = $stageAssignmentDao->getBySubmissionAndStageId($query->getAssocId(), $query->getStageId(), null, $user->getId());
            while ($usersAssignment = $usersAssignments->next()) {
                $userGroup = Repo::userGroup()->get($usersAssignment->getUserGroupId());
                $assignedRoles[] = $userGroup->getRoleId();
            }
            return $assignedRoles;
        })();

        if ($query->getStageId() == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW || $query->getStageId() == WORKFLOW_STAGE_ID_INTERNAL_REVIEW) {
            // Get all review assignments for current submission
            $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
            $reviewAssignments = $reviewAssignmentDao->getBySubmissionId($submission->getId());

            // if current user is editor/journal manager/site admin and not have author role , add all reviewers
            if (array_intersect($assignedRoles, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]) || (empty($assignedRoles) && ($user->hasRole([Role::ROLE_ID_MANAGER], $context->getId()) || $user->hasRole([Role::ROLE_ID_SITE_ADMIN], \PKP\core\PKPApplication::CONTEXT_SITE)))) {
                foreach ($reviewAssignments as $reviewAssignment) {
                    $includeUsers[] = $reviewAssignment->getReviewerId();
                }
            }

            // if current user is an anonymous reviewer, filter out authors
            foreach ($reviewAssignments as $reviewAssignment) {
                if ($reviewAssignment->getReviewerId() == $user->getId()) {
                    if ($reviewAssignment->getReviewMethod() != ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN) {
                        $authorAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($query->getAssocId(), Role::ROLE_ID_AUTHOR);
                        while ($assignment = $authorAssignments->next()) {
                            $excludeUsers[] = $assignment->getUserId();
                        }
                    }
                }
            }

            // if current user is author, add open reviewers who have accepted the request
            if (array_intersect([Role::ROLE_ID_AUTHOR], $assignedRoles)) {
                foreach ($reviewAssignments as $reviewAssignment) {
                    if ($reviewAssignment->getReviewMethod() == ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN && $reviewAssignment->getDateConfirmed() && !$reviewAssignment->getDeclined()) {
                        $includeUsers[] = $reviewAssignment->getReviewerId();
                    }
                }
            }
        }

        $usersIterator = Repo::user()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->limit(100)
            ->offset(0)
            ->assignedTo($query->getAssocId(), $query->getStageId())
            ->excludeUserIds($excludeUsers)
            ->getMany();

        $includedUsersIterator = Repo::user()->getCollector()->filterByUserIds($includeUsers)->getMany();
        $usersIterator = $usersIterator->merge($includedUsersIterator);

        $allParticipants = [];
        foreach ($usersIterator as $user) {
            $allUserGroups = Repo::userGroup()->userUserGroups($user->getId(), $context->getId());

            $userRoles = [];
            $userAssignments = $stageAssignmentDao->getBySubmissionAndStageId($query->getAssocId(), $query->getStageId(), null, $user->getId())->toArray();
            foreach ($userAssignments as $userAssignment) {
                foreach ($allUserGroups as $userGroup) {
                    if ($userGroup->getId() == $userAssignment->getUserGroupId()) {
                        $userRoles[] = $userGroup->getLocalizedName();
                    }
                }
            }
            foreach ($reviewAssignments as $assignment) {
                if ($assignment->getReviewerId() == $user->getId()) {
                    $userRoles[] = __('user.role.reviewer') . ' (' . __($assignment->getReviewMethodKey()) . ')';
                }
            }
            if (!count($userRoles)) {
                $userRoles[] = __('submission.status.unassigned');
            }
            $allParticipants[$user->getId()] = __('submission.query.participantTitle', [
                'fullName' => $user->getFullName(),
                'userGroup' => join(__('common.commaListSeparator'), $userRoles),
            ]);
        }

        // Notify assistants, authors and reviewers that they have x minutes to update their own discussion
        $allowedEditTimeNotice = ['show' => false, 'limit' => 60];
        if (array_intersect($assignedRoles, [Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_REVIEWER])) {
            $allowedEditTimeNotice['show'] = true;
            $allowedEditTimeNotice['limit'] = (int) ($allowedEditTimeNotice['limit'] - (time() - strtotime($headNote->getDateCreated())) / 60);
        }

        $templateMgr->assign([
            'allParticipants' => $allParticipants,
            'assignedParticipants' => $assignedParticipants,
            'allowedEditTimeNotice' => $allowedEditTimeNotice,
        ]);

        return parent::fetch($request, $template, $display);
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
            'comment',
            'users',
            'template',
        ]);
    }

    /**
     * @copydoc Form::validate()
     */
    public function validate($callHooks = true)
    {
        // Display error if anonymity is impacted in a review stage:
        // 1) several blind reviewers are selected, or
        // 2) a blind reviewer and another participant (other than editor or assistant) are selected.
        // Editors and assistants are ignored, they can see everything.
        // Also admin and manager, if they are creating the discussion, are ignored -- they can see everything.
        // In other stages validate that participants are assigned to that stage.
        $query = $this->getQuery();
        // Queries only support Application::ASSOC_TYPE_SUBMISSION so far (see above)
        if ($query->getAssocType() == Application::ASSOC_TYPE_SUBMISSION) {
            $request = Application::get()->getRequest();
            $user = $request->getUser();
            $context = $request->getContext();
            $submissionId = $query->getAssocId();
            $stageId = $query->getStageId();

            $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */

            // get the selected participants
            $newParticipantIds = (array) $this->getData('users');
            $participantsToConsider = $blindReviewerCount = 0;
            foreach ($newParticipantIds as $participantId) {
                // get participant roles in this workflow stage
                $assignedRoles = [];
                $usersAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submissionId, $stageId, null, $participantId);
                while ($usersAssignment = $usersAssignments->next()) {
                    $userGroup = Repo::userGroup()->get($usersAssignment->getUserGroupId());
                    $assignedRoles[] = $userGroup->getRoleId();
                }

                if ($stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW || $stageId == WORKFLOW_STAGE_ID_INTERNAL_REVIEW) {
                    // validate the anonymity
                    // get participant review assignments
                    $reviewAssignments = $reviewAssignmentDao->getBySubmissionReviewer($submissionId, $participantId, $stageId);
                    // if participant has no role in this stage and is not a reviewer
                    if (empty($assignedRoles) && empty($reviewAssignments)) {
                        // if participant is current user and the user has admin or manager role, ignore participant
                        if (($participantId == $user->getId()) && ($user->hasRole([Role::ROLE_ID_SITE_ADMIN], \PKP\core\PKPApplication::CONTEXT_SITE) || $user->hasRole([Role::ROLE_ID_MANAGER], $context->getId()))) {
                            continue;
                        } else {
                            $this->addError('users', __('editor.discussion.errorNotStageParticipant'));
                            $this->addErrorField('users');
                            break;
                        }
                    }
                    // is participant a blind reviewer
                    $blindReviewer = false;
                    foreach ($reviewAssignments as $reviewAssignment) {
                        if ($reviewAssignment->getReviewMethod() != ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN) {
                            $blindReviewerCount++;
                            $blindReviewer = true;
                            break;
                        }
                    }
                    // if participant is not a blind reviewer and has a role different than editor or assistant
                    if (!$blindReviewer && !array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT], $assignedRoles)) {
                        $participantsToConsider++;
                    }
                    // if anonymity is impacted, display error
                    if (($blindReviewerCount > 1) || ($blindReviewerCount > 0 && $participantsToConsider > 0)) {
                        $this->addError('users', __('editor.discussion.errorAnonymousParticipants'));
                        $this->addErrorField('users');
                        break;
                    }
                } else {
                    // if participant has no role/assignment in the current stage
                    if (empty($assignedRoles)) {
                        // if participant is current user and the user has admin or manager role, ignore participant
                        if (($participantId == $user->getId()) && ($user->hasRole([Role::ROLE_ID_SITE_ADMIN], \PKP\core\PKPApplication::CONTEXT_SITE) || $user->hasRole([Role::ROLE_ID_MANAGER], $context->getId()))) {
                            continue;
                        } else {
                            $this->addError('users', __('editor.discussion.errorNotStageParticipant'));
                            $this->addErrorField('users');
                            break;
                        }
                    }
                }
            }
        }
        return parent::validate($callHooks);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $query = $this->getQuery();

        $headNote = $query->getHeadNote();
        $headNote->setTitle($this->getData('subject'));
        $headNote->setContents($this->getData('comment'));

        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $noteDao->updateObject($headNote);

        $queryDao->updateObject($query);

        // Update participants
        $oldParticipantIds = $queryDao->getParticipantIds($query->getId());
        $newParticipantIds = $this->getData('users');
        $queryDao->removeAllParticipants($query->getId());
        foreach ($newParticipantIds as $userId) {
            $queryDao->insertParticipant($query->getId(), $userId);
        }

        $removed = array_diff($oldParticipantIds, $newParticipantIds);
        foreach ($removed as $userId) {
            // Delete this users' notifications relating to this query
            $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
            $notificationDao->deleteByAssoc(Application::ASSOC_TYPE_QUERY, $query->getId(), $userId);
        }

        // Stamp the submission status modification date.
        if ($query->getAssocType() == Application::ASSOC_TYPE_SUBMISSION) {
            $submission = Repo::submission()->get($query->getAssocId());
            $submission->stampLastActivity();
            Repo::submission()->dao->update($submission);
        }

        parent::execute(...$functionArgs);
    }
}
