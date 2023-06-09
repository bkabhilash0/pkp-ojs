<?php

/**
 * @file plugins/blocks/browse/BrowseBlockPlugin.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class BrowseBlockPlugin
 * @brief Class for browse block plugin
 */

namespace APP\plugins\blocks\browse;

use APP\facades\Repo;
use PKP\plugins\BlockPlugin;

class BrowseBlockPlugin extends BlockPlugin
{
    /**
     * Get the display name of this plugin.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.block.browse.displayName');
    }

    /**
     * Get a description of the plugin.
     */
    public function getDescription()
    {
        return __('plugins.block.browse.description');
    }

    /**
     * Get the HTML contents of the browse block.
     *
     * @param PKPTemplateManager $templateMgr
     * @param null|mixed $request
     *
     * @return string
     */
    public function getContents($templateMgr, $request = null)
    {
        $context = $request->getContext();
        if (!$context) {
            return '';
        }
        $router = $request->getRouter();

        $requestedCategoryPath = null;
        if ($router->getRequestedPage($request) . '/' . $router->getRequestedOp($request) == 'catalog/category') {
            $args = $router->getRequestedArgs($request);
            $requestedCategoryPath = reset($args);
        }
        $templateMgr->assign([
            'browseBlockSelectedCategory' => $requestedCategoryPath,
            'browseCategories' => Repo::category()->getCollector()
                ->filterByContextIds([$context->getId()])
                ->getMany()
        ]);
        return parent::getContents($templateMgr);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\blocks\browse\BrowseBlockPlugin', '\BrowseBlockPlugin');
}
