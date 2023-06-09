<?php

/**
 * @file classes/mail/mailables/DecisionDeclineNotifyAuthor.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionDeclineNotifyAuthor
 *
 * @brief Email sent to the author(s) when the following decisions are made:
 *      Decision::DECLINE
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\ReviewerComments;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

class DecisionDeclineNotifyAuthor extends Mailable
{
    use Configurable;
    use Recipient;
    use ReviewerComments;
    use Sender;

    protected static ?string $name = 'mailable.decision.decline.notifyAuthor.name';
    protected static ?string $description = 'mailable.decision.decline.notifyAuthor.description';
    protected static ?string $emailTemplateKey = 'EDITOR_DECISION_DECLINE';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [Role::ROLE_ID_SUB_EDITOR];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    /**
     * @param array<ReviewAssignment> $reviewAssignments
     */
    public function __construct(Context $context, Submission $submission, Decision $decision, array $reviewAssignments)
    {
        parent::__construct(array_slice(func_get_args(), 0, -1));
        $this->setupReviewerCommentsVariable($reviewAssignments, $submission);
    }

    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        return self::addReviewerCommentsDescription($variables);
    }
}
