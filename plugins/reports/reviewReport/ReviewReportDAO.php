<?php

/**
 * @file plugins/reports/reviewReport/ReviewReportDAO.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewReportDAO
 *
 * @ingroup plugins_reports_review
 *
 * @see ReviewReportPlugin
 *
 * @brief Review report DAO
 */

namespace APP\plugins\reports\reviewReport;

use APP\core\Application;
use APP\facades\Repo;
use PKP\db\DAO;
use PKP\facades\Locale;
use PKP\submission\SubmissionComment;
use PKP\user\InterestManager;

class ReviewReportDAO extends DAO
{
    /**
     * Get the review report data.
     *
     * @param int $contextId Context ID
     *
     * @return array
     */
    public function getReviewReport($contextId)
    {
        $locale = Locale::getLocale();

        $commentsReturner = $this->retrieve(
            'SELECT sc.submission_id,
                sc.comments,
                sc.author_id
            FROM submission_comments sc
                JOIN submissions s ON (s.submission_id = sc.submission_id)
            WHERE comment_type = ?
                AND s.context_id = ?',
            [SubmissionComment::COMMENT_TYPE_PEER_REVIEW, (int) $contextId]
        );

        $site = Application::get()->getRequest()->getSite();
        $sitePrimaryLocale = $site->getPrimaryLocale();

        $reviewsReturner = $this->retrieve(
            'SELECT r.stage_id AS stage_id,
                r.review_id as review_id,
                r.round AS round,
                COALESCE(asl.setting_value, aspl.setting_value) AS submission,
                a.submission_id AS submission_id,
                u.user_id AS reviewer_id,
                u.username AS reviewer,
                u.email AS email,
                u.country AS country,
                us.setting_value AS orcid,
                COALESCE(uasl.setting_value, uas.setting_value) AS affiliation,
                COALESCE(ugsl.setting_value, ugs.setting_value) AS user_given,
                COALESCE(ufsl.setting_value, ufs.setting_value) AS user_family,
                r.date_assigned AS date_assigned,
                r.date_notified AS date_notified,
                r.date_confirmed AS date_confirmed,
                r.date_completed AS date_completed,
                r.date_acknowledged AS date_acknowledged,
                r.date_reminded AS date_reminded,
                r.date_due AS date_due,
                r.date_response_due AS date_response_due,
                (r.declined=1) AS declined,
                r.considered AS considered,
                (r.cancelled=1) AS cancelled,
                r.recommendation AS recommendation
            FROM review_assignments r
                LEFT JOIN submissions a ON r.submission_id = a.submission_id
                LEFT JOIN publications p ON a.current_publication_id = p.publication_id
                LEFT JOIN publication_settings asl ON (p.publication_id = asl.publication_id AND asl.locale = ? AND asl.setting_name = ?)
                LEFT JOIN publication_settings aspl ON (p.publication_id = aspl.publication_id AND aspl.locale = a.locale AND aspl.setting_name = ?)
                LEFT JOIN users u ON (u.user_id = r.reviewer_id)
                LEFT JOIN user_settings uas ON (u.user_id = uas.user_id AND uas.setting_name = ? AND uas.locale = a.locale)
                LEFT JOIN user_settings uasl ON (u.user_id = uasl.user_id AND uasl.setting_name = ? AND uasl.locale = ?)
                LEFT JOIN user_settings ugs ON (u.user_id = ugs.user_id AND ugs.setting_name = ? AND ugs.locale = a.locale)
                LEFT JOIN user_settings ugsl ON (u.user_id = ugsl.user_id AND ugsl.setting_name = ? AND ugsl.locale = ?)
                LEFT JOIN user_settings ufs ON (u.user_id = ufs.user_id AND ufs.setting_name = ? AND ufs.locale = a.locale)
                LEFT JOIN user_settings ufsl ON (u.user_id = ufsl.user_id AND ufsl.setting_name = ? AND ufsl.locale = ?)
                LEFT JOIN user_settings us ON (u.user_id = us.user_id AND us.setting_name = ?)
            WHERE  a.context_id = ?
            ORDER BY submission',
            [
                $locale, // Submission title
                'title',
                'title',
                'affiliation',
                'affiliation',
                $sitePrimaryLocale,
                'givenName',
                'givenName',
                $sitePrimaryLocale,
                'familyName',
                'familyName',
                $sitePrimaryLocale,
                'orcid',
                (int) $contextId
            ]
        );

        $interestManager = new InterestManager();
        $assignedReviewerIds = $this->retrieve(
            'SELECT r.reviewer_id
            FROM review_assignments r
                LEFT JOIN submissions a ON r.submission_id = a.submission_id
            WHERE a.context_id = ?
            ORDER BY r.reviewer_id',
            [(int) $contextId]
        );
        $interests = [];
        while ($row = $assignedReviewerIds->next()) {
            if (!array_key_exists($row['reviewer_id'], $interests)) {
                $user = Repo::user()->get($row['reviewer_id'], true);
                $reviewerInterests = $interestManager->getInterestsString($user);
                if (!empty($reviewerInterests)) {
                    $interests[$row['reviewer_id']] = $reviewerInterests;
                }
            }
        }
        return [$commentsReturner, $reviewsReturner, $interests];
    }
}
