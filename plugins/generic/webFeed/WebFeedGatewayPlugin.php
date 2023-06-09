<?php

/**
 * @file WebFeedGatewayPlugin.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebFeedGatewayPlugin
 *
 * @brief Gateway component of web feed plugin
 *
 */

namespace APP\plugins\generic\webFeed;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\section\Section;
use APP\submission\Collector;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Exception;
use PKP\category\Category;
use PKP\core\Registry;
use PKP\plugins\GatewayPlugin;

class WebFeedGatewayPlugin extends GatewayPlugin
{
    public const ATOM = 'atom';
    public const RSS = 'rss';
    public const RSS2 = 'rss2';

    public const FEED_MIME_TYPE = [
        self::ATOM => 'application/atom+xml',
        self::RSS => 'application/rdf+xml',
        self::RSS2 => 'application/rss+xml'
    ];

    public const DEFAULT_RECENT_ITEMS = 30;

    /**
     * Constructor
     */
    public function __construct(protected WebFeedPlugin $parentPlugin)
    {
        parent::__construct();
    }

    /**
     * Handle fetch requests for this plugin.
     *
     * @param array $args Arguments.
     * @param Request $request Request object.
     */
    public function fetch($args, $request): bool
    {
        $context = $request->getContext();
        if (!$context || !$this->parentPlugin->getEnabled($context->getId())) {
            return false;
        }

        // Make sure the feed type is specified and valid
        $feedType = array_shift($args);
        if (!in_array($feedType, array_keys(static::FEED_MIME_TYPE))) {
            throw new Exception('Invalid feed format');
        }

        // Get limit setting from web feeds plugin
        $displayItems = $this->parentPlugin->getSetting($context->getId(), 'displayItems');
        $recentItems = abs((int) $this->parentPlugin->getSetting($context->getId(), 'recentItems')) ?: static::DEFAULT_RECENT_ITEMS;

        $includeIdentifiers = (bool) $this->parentPlugin->getSetting($context->getId(), 'includeIdentifiers');
        $latestDate = null;
        $submissions = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->limit($recentItems)
            ->orderBy(Collector::ORDERBY_LAST_MODIFIED, Collector::ORDER_DIR_DESC);

        // OJS only feature
        // If the plugin is configured to display only the latest issue, so we filter by it and apply the needed changes to the query
        if (WebFeedPlugin::hasIssues() && $displayItems === 'issue') {
            $issue = Repo::issue()->getCurrent($context->getId(), true);
            $submissions->filterByIssueIds([$issue?->getId() ?? 0])
                ->limit(null)
                ->orderBy(Collector::ORDERBY_SEQUENCE, Collector::ORDER_DIR_ASC);
            $latestDate = $issue?->getData('datePublished');
        }

        $submissions = $submissions->getMany();
        $latestDate ??= $submissions->first()?->getData('lastModified');
        $submissions = $submissions->map(fn (Submission $submission) => ['submission' => $submission, 'identifiers' => $this->getIdentifiers($submission)]);
        $userGroups = Repo::userGroup()->getCollector()->filterByContextIds([$context->getId()])->getMany();

        $applicationIdentifier = strtolower(preg_replace('/[^a-z]/i', '', Application::getName()));
        TemplateManager::getManager($request)
            ->assign(
                [
                    'applicationName' => match ($applicationIdentifier) {
                        'ojs' => 'Open Journal Systems',
                        'omp' => 'Open Monograph Press',
                        'ops' => 'Open Preprint Systems'
                    },
                    'applicationVersion' => Registry::get('appVersion'),
                    'applicationIdentifier' => $applicationIdentifier,
                    'publicationPage' => match ($applicationIdentifier) {
                        'ojs' => 'article',
                        'omp' => 'catalog',
                        'ops' => 'preprint'
                    },
                    'publicationOp' => match ($applicationIdentifier) {
                        'ojs' => 'view',
                        'omp' => 'book',
                        'ops' => 'view'
                    },
                    'openAccess' => match ($applicationIdentifier) {
                        'ojs' => Submission::ARTICLE_ACCESS_OPEN,
                        'omp' => null,
                        'ops' => Submission::PREPRINT_ACCESS_OPEN
                    },
                    'submissions' => $submissions,
                    'context' => $context,
                    'latestDate' => $latestDate,
                    'feedUrl' => $request->getRequestUrl(),
                    'userGroups' => $userGroups,
                    'includeIdentifiers' => $includeIdentifiers
                ]
            )
            ->setHeaders(['content-type: ' . static::FEED_MIME_TYPE[$feedType] . '; charset=utf-8'])
            ->display($this->parentPlugin->getTemplateResource("{$feedType}.tpl"));

        return true;
    }

    /**
     * Retrieves the identifiers assigned to a submission
     *
     * @return array<array{'type':string,'label':string,'values':string[]}>
     */
    private function getIdentifiers(Submission $submission): array
    {
        $identifiers = [];
        if ($section = $this->getSection($submission->getSectionId())) {
            $identifiers[] = ['type' => 'section', 'label' => __('section.section'), 'values' => [$section->getLocalizedTitle()]];
        }

        $publication = $submission->getCurrentPublication();
        $categories = Repo::category()->getCollector()
            ->filterByPublicationIds([$publication->getId()])
            ->getMany()
            ->map(fn (Category $category) => $category->getLocalizedTitle())
            ->toArray();
        if (count($categories)) {
            $identifiers[] = ['type' => 'category', 'label' => __('category.category'), 'values' => $categories];
        }

        foreach (['keywords' => 'common.keywords', 'subjects' => 'common.subjects', 'disciplines' => 'search.discipline'] as $field => $label) {
            $values = $publication->getLocalizedData($field) ?? [];
            if (count($values)) {
                $identifiers[] = ['type' => $field, 'label' => __($label), 'values' => $values];
            }
        }

        return $identifiers;
    }

    /**
     * Retrieves a section
     */
    private function getSection(?int $sectionId): ?Section
    {
        static $sections = [];
        return $sectionId
            ? $sections[$sectionId] ??= Repo::section()->get($sectionId)
            : null;
    }

    /**
     * @copydoc Plugin::getHideManagement()
     */
    public function getHideManagement(): bool
    {
        return true;
    }

    /**
     * @copydoc Plugin::getName()
     */
    public function getName(): string
    {
        return substr(static::class, strlen(__NAMESPACE__) + 1);
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
     * @copydoc Plugin::getEnabled()
     *
     * @param null|mixed $contextId
     */
    public function getEnabled($contextId = null): bool
    {
        return $this->parentPlugin->getEnabled($contextId);
    }
}
