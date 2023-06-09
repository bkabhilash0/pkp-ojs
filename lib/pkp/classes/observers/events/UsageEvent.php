<?php

/**
 * @file classes/observers/events/UsageEvent.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageEvent
 *
 * @ingroup observers_events
 *
 * @brief Base class for the usage event used to record usage logs.
 */

namespace PKP\observers\events;

use APP\core\Application;
use APP\core\Request;
use APP\submission\Submission;
use Exception;
use Illuminate\Foundation\Events\Dispatchable;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\Registry;
use PKP\submission\Representation;
use PKP\submissionFile\SubmissionFile;

abstract class UsageEvent
{
    use Dispatchable;

    /** Current time */
    public string $time;

    /** Viewed/downloaded object, one of the Application::ASSOC_TYPE_... constants */
    public int $assocType;

    /** Canonical URL for the pub object */
    public string $canonicalUrl;

    public Request $request;

    public Context $context;

    public ?Submission $submission;

    /** Representation (galley or publication format) */
    public ?Representation $representation;

    public ?SubmissionFile $submissionFile;

    /** Application's complete version string */
    public string $version;

    /**
     * Create a new usage event instance.
     */
    public function __construct(
        int $assocType,
        Context $context,
        Submission $submission = null,
        Representation $representation = null,
        SubmissionFile $submissionFile = null
    ) {
        $this->time = Core::getCurrentDate();
        $this->assocType = $assocType;
        $this->context = $context;
        $this->submission = $submission;
        $this->representation = $representation;
        $this->submissionFile = $submissionFile;
        $this->version = Registry::get('appVersion');
        $this->request = Application::get()->getRequest();
        $this->canonicalUrl = $this->getCanonicalUrl();
    }

    /**
     * Get the canonical URL for the usage object
     *
     * @throws Exception
     */
    protected function getCanonicalUrl(): string
    {
        $canonicalUrlPage = $canonicalUrlOp = null;
        $canonicalUrlParams = [];

        switch ($this->assocType) {
            case Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER:
            case Application::ASSOC_TYPE_SUBMISSION_FILE:
                $canonicalUrlOp = 'download';
                $canonicalUrlParams = [$this->submission->getId()];
                $router = $this->request->getRouter(); /** @var PageRouter $router */
                $op = $router->getRequestedOp($this->request);
                $args = $router->getRequestedArgs($this->request);
                if ($op == 'download' && count($args) > 1) {
                    if ($args[1] == 'version' && count($args) == 5) {
                        $publicationId = (int) $args[2];
                        $canonicalUrlParams[] = 'version';
                        $canonicalUrlParams[] = $publicationId;
                    }
                }
                $canonicalUrlParams[] = $this->representation->getId();
                $canonicalUrlParams[] = $this->submissionFile->getId();
                break;
            case Application::ASSOC_TYPE_SUBMISSION:
                $canonicalUrlOp = 'view';
                if (Application::get()->getName() == 'omp') {
                    $canonicalUrlOp = 'book';
                }
                $canonicalUrlParams = [$this->submission->getId()];
                $router = $this->request->getRouter(); /** @var PageRouter $router */
                $op = $router->getRequestedOp($this->request);
                $args = $router->getRequestedArgs($this->request);
                if ($op == $canonicalUrlOp && count($args) > 1) {
                    if ($args[1] == 'version' && count($args) == 3) {
                        $publicationId = (int) $args[2];
                        $canonicalUrlParams[] = 'version';
                        $canonicalUrlParams[] = $publicationId;
                    }
                }
                break;
            case Application::getContextAssocType():
                $canonicalUrlOp = '';
                $canonicalUrlPage = 'index';
                break;
            default:
                throw new Exception('Not recognized assoc type that we can create the canonical URL for.');
        }
        $canonicalUrl = $this->getRouterCanonicalUrl($this->request, $canonicalUrlPage, $canonicalUrlOp, $canonicalUrlParams);
        return $canonicalUrl;
    }

    /**
     * Construct the URL from page, op, and params
     */
    protected function getRouterCanonicalUrl(Request $request, string $canonicalUrlPage = null, string $canonicalUrlOp = null, array $canonicalUrlParams = null): string
    {
        $router = $request->getRouter(); /** @var PageRouter $router */
        $context = $router->getContext($request);

        if (!isset($canonicalUrlPage)) {
            $canonicalUrlPage = $router->getRequestedPage($request);
        }
        if (!isset($canonicalUrlOp)) {
            $canonicalUrlOp = $router->getRequestedOp($request);
        }
        if (!isset($canonicalUrlParams)) {
            $canonicalUrlParams = $router->getRequestedArgs($request);
        }

        $canonicalUrl = $router->url(
            $request,
            null,
            $canonicalUrlPage,
            $canonicalUrlOp,
            $canonicalUrlParams
        );

        // Make sure we log the server name and not aliases.
        $configBaseUrl = Config::getVar('general', 'base_url');
        $requestBaseUrl = $request->getBaseUrl();
        if ($requestBaseUrl !== $configBaseUrl) {
            // Make sure it's not an url override (no alias on that case).
            if (!in_array($requestBaseUrl, Config::getContextBaseUrls()) &&
                    $requestBaseUrl !== Config::getVar('general', 'base_url[index]')) {
                // Alias found, replace it by base_url from config file.
                // Make sure we use the correct base url override value for the context, if any.
                $baseUrlReplacement = Config::getVar('general', 'base_url[' . $context->getPath() . ']');
                if (!$baseUrlReplacement) {
                    $baseUrlReplacement = $configBaseUrl;
                }
                $canonicalUrl = str_replace($requestBaseUrl, $baseUrlReplacement, $canonicalUrl);
            }
        }
        return $canonicalUrl;
    }
}
