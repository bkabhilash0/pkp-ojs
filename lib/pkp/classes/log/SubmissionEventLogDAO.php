<?php

/**
 * @file classes/log/SubmissionEventLogDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionEventLogDAO
 *
 * @ingroup log
 *
 * @see EventLogDAO
 *
 * @brief Extension to EventLogDAO for submission-specific log entries.
 */

namespace PKP\log;

use APP\core\Application;
use APP\log\SubmissionEventLogEntry;

class SubmissionEventLogDAO extends EventLogDAO
{
    /**
     * Generate a new DataObject
     *
     * @return SubmissionEventLogEntry
     */
    public function newDataObject()
    {
        $returner = new SubmissionEventLogEntry();
        $returner->setAssocType(Application::ASSOC_TYPE_SUBMISSION);
        return $returner;
    }

    /**
     * Get submission event log entries by submission ID
     *
     * @param int $submissionId
     *
     * @return DAOResultFactory
     */
    public function getBySubmissionId($submissionId)
    {
        return $this->getByAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\SubmissionEventLogDAO', '\SubmissionEventLogDAO');
}
