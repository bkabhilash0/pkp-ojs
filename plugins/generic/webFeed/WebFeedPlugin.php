<?php

/**
 * @file WebFeedPlugin.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebFeedPlugin
 *
 * @brief Web Feeds plugin class
 */

namespace APP\plugins\generic\webFeed;

use APP\core\Application;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\core\PKPPageRouter;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;

class WebFeedPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if ($this->getEnabled($mainContextId)) {
            $this->setupTemplateLinks();
            // Register the block plugin to display feed links at the application sidebar
            PluginRegistry::register('blocks', new WebFeedBlockPlugin($this), $this->getPluginPath());
            // Register the gateway plugin, which will handle the feed request
            PluginRegistry::register('gateways', new WebFeedGatewayPlugin($this), $this->getPluginPath());
        }
        return true;
    }

    /**
     * Add feed links to page <head> on select/all pages.
     */
    public function setupTemplateLinks(): void
    {
        Hook::add('TemplateManager::display', function (string $hookName, array $args): int {
            // Only page requests will be handled
            $request = Application::get()->getRequest();
            if (!($request->getRouter() instanceof PKPPageRouter)) {
                return Hook::CONTINUE;
            }

            /** @var TemplateManager */
            $templateManager = $args[0];
            $context = $request->getContext();
            if (is_null($context)) {
                return Hook::CONTINUE;
            }

            $displayPage = $this->getSetting($context->getId(), 'displayPage');

            // Define when the <link> elements should appear
            $contexts = match ($displayPage) {
                'homepage' => ['frontend-index', 'frontend-issue'],
                // Issue settings are only available for OJS
                'issue' => 'frontend-issue',
                default => 'frontend'
            };

            $className = explode('/', WebFeedGatewayPlugin::class);
            $className = end($className);
            foreach (WebFeedGatewayPlugin::FEED_MIME_TYPE as $feedType => $mimeType) {
                $url = $request->url(null, 'gateway', 'plugin', [$className, $feedType]);
                $templateManager->addHeader("webFeedPlugin{$feedType}", "<link rel=\"alternate\" type=\"{$mimeType}\" href=\"{$url}\">", ['contexts' => $contexts]);
            }

            return Hook::CONTINUE;
        });
    }

    /**
     * Retrieves whether the installation supports issues
     */
    public static function hasIssues(): bool
    {
        return str_contains(Application::getName(), 'ojs');
    }

    /**
     * @copydoc Plugin::getContextSpecificPluginSettingsFile()
     */
    public function getContextSpecificPluginSettingsFile(): string
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb): array
    {
        $actions = parent::getActions($request, $verb);
        if (!$this->getEnabled()) {
            return $actions;
        }

        $router = $request->getRouter();
        $url = $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']);
        array_unshift($actions, new LinkAction('settings', new AjaxModal($url, $this->getDisplayName()), __('manager.plugins.settings')));
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request): JSONMessage
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        $form = new SettingsForm($this, $request->getContext()->getId());
        if (!$request->getUserVar('save')) {
            $form->initData();
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->execute();
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification($request->getUser()->getId());
        return new JSONMessage(true);
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.webfeed.displayName');
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDescription(): string
    {
        return __('plugins.generic.webfeed.description');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\webFeed\WebFeedPlugin', '\WebFeedPlugin');
}
