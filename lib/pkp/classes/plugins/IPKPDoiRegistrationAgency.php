<?php

/**
 * @file classes/plugins/IPKPDoiRegistrationAgency.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IPKPDoiRegistrationAgency
 *
 * @ingroup plugins
 *
 * @brief Interface that registration agency plugins must implement to support DOI registrations.
 */

namespace PKP\plugins;

use PKP\context\Context;
use PKP\doi\RegistrationAgencySettings;

interface IPKPDoiRegistrationAgency
{
    /**
     * Includes plugin in list of configurable registration agencies for DOI depositing functionality
     *
     * @param string $hookName DoiSettingsForm::setEnabledRegistrationAgencies
     * @param array $args [
     *
     *      @option $enabledRegistrationAgencies array
     * ]
     */
    public function addAsRegistrationAgencyOption(string $hookName, array $args);

    /**
     * Checks if plugin meets registration agency-specific requirements for being active and handling deposits
     *
     */
    public function isPluginConfigured(Context $context): bool;

    /**
     * Get configured registration agency display name for use in DOI management pages
     *
     */
    public function getRegistrationAgencyName(): string;

    /**
     * Get key for retrieving error message if one exists on DOI object
     *
     */
    public function getErrorMessageKey(): ?string;

    /**
     * Get key for retrieving registered message if one exists on DOI object
     *
     */
    public function getRegisteredMessageKey(): ?string;

    /**
     * Get settings management object. Handles saving and validating of form fields.
     *
     */
    public function getSettingsObject(): RegistrationAgencySettings;

    /**
     * Get an array of which types of pub objects are allowed to have DOIs assigned.
     * Should be composed of Repo::doi()::TYPE_* constants.
     * This will only dictate which pub object types are enabled and will not delete already assigned DOIs.
     */
    public function getAllowedDoiTypes(): array;
}
