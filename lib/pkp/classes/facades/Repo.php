<?php

/**
 * @file classes/facades/Repo.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repo
 *
 * @brief This facade provides access to all Repositories in the application.
 *
 * A Repo contains all the methods needed to interact with an entity, such
 * as CRUD operations as well as utility methods to check status, locate items
 * and perform bulk actions.
 *
 * A Repository is a wrapper around an entity's DAO where additional business
 * logic can be performed. Use the Repository to coordinate actions across the
 * application, such as firing events, writing activity logs, or refreshing
 * cached data. The Repository should hand off data to the DAO to perform
 * basic crud operations.
 */

namespace PKP\facades;

use PKP\announcement\Repository as AnnouncementRepository;
use PKP\author\Repository as AuthorRepository;
use PKP\category\Repository as CategoryRepository;
use PKP\decision\Repository as DecisionRepository;
use PKP\emailTemplate\Repository as EmailTemplateRepository;
use PKP\institution\Repository as InstitutionRepository;
use PKP\job\repositories\FailedJob as FailedJobRepository;
use PKP\job\repositories\Job as JobRepository;
use PKP\submissionFile\Repository as SubmissionFileRepository;
use PKP\userGroup\Repository as UserGroupRepository;

class Repo
{
    public static function announcement(): AnnouncementRepository
    {
        return app(AnnouncementRepository::class);
    }

    public static function author(): AuthorRepository
    {
        return app(AuthorRepository::class);
    }

    public static function decision(): DecisionRepository
    {
        return app()->make(DecisionRepository::class);
    }

    public static function emailTemplate(): EmailTemplateRepository
    {
        return app(EmailTemplateRepository::class);
    }

    public static function category(): CategoryRepository
    {
        return app(CategoryRepository::class);
    }

    public static function submissionFile(): SubmissionFileRepository
    {
        return app(SubmissionFileRepository::class);
    }

    public static function job(): JobRepository
    {
        return app()->make(JobRepository::class);
    }

    public static function failedJob(): FailedJobRepository
    {
        return app()->make(FailedJobRepository::class);
    }

    public static function institution(): InstitutionRepository
    {
        return app()->make(InstitutionRepository::class);
    }

    public static function userGroup(): UserGroupRepository
    {
        return app(UserGroupRepository::class);
    }
}
