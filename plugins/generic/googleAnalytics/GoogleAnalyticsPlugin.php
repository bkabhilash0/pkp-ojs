<?php

/**
 * @file plugins/generic/googleAnalytics/GoogleAnalyticsPlugin.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GoogleAnalyticsPlugin
 *
 * @ingroup plugins_generic_googleAnalytics
 *
 * @brief Google Analytics plugin class
 */

namespace APP\plugins\generic\googleAnalytics;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class GoogleAnalyticsPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (Application::isUnderMaintenance()) {
            return true;
        }
        if ($success && $this->getEnabled($mainContextId)) {
            // Insert Google Analytics page tag to footer
            Hook::add('TemplateManager::display', [$this, 'registerScript']);
        }
        return $success;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.googleAnalytics.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.googleAnalytics.description');
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb)
    {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $verb)
        );
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->registerPlugin('function', 'plugin_url', [$this, 'smartyPluginUrl']);

                $form = new GoogleAnalyticsSettingsForm($this, $context->getId());

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    /**
     * Register the Google Analytics script tag
     *
     * @param string $hookName
     * @param array $params
     */
    public function registerScript($hookName, $params)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return false;
        }
        $router = $request->getRouter();
        if (!is_a($router, 'PKPPageRouter')) {
            return false;
        }

        $googleAnalyticsSiteId = $this->getSetting($context->getId(), 'googleAnalyticsSiteId');
        if (empty($googleAnalyticsSiteId)) {
            return false;
        }

        $googleAnalyticsCode = "
(function (w, d, s, l, i) { w[l] = w[l] || []; var f = d.getElementsByTagName(s)[0],
j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; j.async = true;
j.src = 'https://www.googletagmanager.com/gtag/js?id=' + i + dl; f.parentNode.insertBefore(j, f);
function gtag(){dataLayer.push(arguments)}; gtag('js', new Date()); gtag('config', i); })
(window, document, 'script', 'dataLayer', '{$googleAnalyticsSiteId}');
";

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->addJavaScript(
            'googleanalytics',
            $googleAnalyticsCode,
            [
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                'inline' => true,
            ]
        );

        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\googleAnalytics\GoogleAnalyticsPlugin', '\GoogleAnalyticsPlugin');
}
