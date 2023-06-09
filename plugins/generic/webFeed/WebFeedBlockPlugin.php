<?php

/**
 * @file WebFeedBlockPlugin.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebFeedBlockPlugin
 *
 * @brief Class for block component of web feed plugin
 */

namespace APP\plugins\generic\webFeed;

class WebFeedBlockPlugin extends \PKP\plugins\BlockPlugin
{
    /**
     * Constructor
     */
    public function __construct(protected WebFeedPlugin $parentPlugin)
    {
        parent::__construct();
    }

    /**
     * @copydoc Plugin::getName()
     */
    public function getName(): string
    {
        return substr(static::class, strlen(__NAMESPACE__) + 1);
    }

    /**
     * @copydoc Plugin::getHideManagement()
     */
    public function getHideManagement(): bool
    {
        return true;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.webfeed.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription(): string
    {
        return __('plugins.generic.webfeed.description');
    }

    /**
     * @copydoc Plugin::getPluginPath()
     */
    public function getPluginPath(): string
    {
        return $this->parentPlugin->getPluginPath();
    }

    /**
     * @copydoc Plugin::getTemplatePath()
     */
    public function getTemplatePath($inCore = false): string
    {
        return "{$this->parentPlugin->getTemplatePath($inCore)}/templates";
    }
}
