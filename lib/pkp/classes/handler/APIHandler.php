<?php

/**
 * @file lib/pkp/classes/handler/APIHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIHandler
 *
 * @ingroup handler
 *
 * @brief Base request API handler
 */

namespace PKP\handler;

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use PKP\core\APIResponse;
use PKP\plugins\Hook;
use PKP\security\authorization\internal\ApiAuthorizationMiddleware;
use PKP\security\authorization\internal\ApiCsrfMiddleware;

use PKP\security\authorization\internal\ApiTokenDecodingMiddleware;
use PKP\statistics\PKPStatisticsHelper;
use PKP\validation\ValidatorFactory;

use Slim\App;

class APIHandler extends PKPHandler
{
    protected $_app;
    protected $_request;
    protected $_endpoints = [];
    protected $_slimRequest = null;

    /** @var string The endpoint pattern for this handler */
    protected $_pathPattern;

    /** @var string The unique endpoint string for this handler */
    protected $_handlerPath = null;

    /** @var bool Define if all the path building for admin api */
    protected $_apiForAdmin = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->_app = new \Slim\App([
            // Load custom response handler
            'response' => function ($c) {
                return new APIResponse();
            },
            'settings' => [
                // we need access to route within middleware
                'determineRouteBeforeAppMiddleware' => true,
            ]
        ]);
        $this->_app->add(new ApiAuthorizationMiddleware($this));
        $this->_app->add(new ApiCsrfMiddleware($this));
        $this->_app->add(new ApiTokenDecodingMiddleware($this));
        // remove trailing slashes
        $this->_app->add(function ($request, $response, $next) {
            $uri = $request->getUri();
            $path = $uri->getPath();
            if ($path != '/' && substr($path, -1) == '/') {
                // path with trailing slashes to non-trailing counterpart
                $uri = $uri->withPath(substr($path, 0, -1));
                if ($request->getMethod() == 'GET') {
                    return $response->withRedirect((string)$uri, 301);
                } else {
                    return $next($request->withUri($uri), $response);
                }
            }
            return $next($request, $response);
        });
        // if pathinfo is disabled, rewrite URI to match Slim's expectation
        $app = $this->getApp();
        $handler = $this;
        $this->_app->add(function ($request, $response, $next) use ($app, $handler) {
            $uri = $request->getUri();
            $endpoint = trim($request->getQueryParam('endpoint') ?? '');
            $path = $uri->getPath();
            // pkp/pkp-lib#4919: PKP software routes with PATH_INFO (unaffected by
            // mod_rewrite) but Slim relies on REQUEST_URI. Inject PATH_INFO into
            // Slim for consistent behavior in URL rewriting scenarios.
            $newUri = $uri->withPath($_SERVER['PATH_INFO']);
            if ($uri != $newUri) {
                $handler->_slimRequest = $request->withUri($newUri);
                return $app->process($handler->_slimRequest, $response);
            }
            return $next($request, $response);
        });
        // Allow remote requests to the API
        $this->_app->add(function ($request, $response, $next) {
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
            return $next($request, $response);
        });
        $this->_request = Application::get()->getRequest();
        $this->setupEndpoints();
    }

    /**
     * Return PKP request object
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * Return Slim request object
     *
     * @return SlimRequest|null
     */
    public function getSlimRequest()
    {
        return $this->_slimRequest;
    }

    /**
     * Set Slim request object
     *
     */
    public function setSlimRequest($slimRequest)
    {
        return $this->_slimRequest = $slimRequest;
    }

    /**
     * Get the Slim application.
     *
     * @return App
     */
    public function getApp()
    {
        return $this->_app;
    }

    /**
     * Get the endpoint pattern for this handler
     *
     * Compiles the URI path pattern from the context, api version and the
     * unique string for the this handler.
     *
     * @return string
     */
    public function getEndpointPattern()
    {
        if (isset($this->_pathPattern)) {
            return $this->_pathPattern;
        }

        if ($this->_apiForAdmin) {
            $this->_pathPattern = '/index/api/{version}/' . $this->_handlerPath;
            return $this->_pathPattern;
        }

        $this->_pathPattern = '/{contextPath}/api/{version}/' . $this->_handlerPath;
        return $this->_pathPattern;
    }

    /**
     * Get the entity ID for a specified parameter name.
     * (Parameter names are generally defined in authorization policies
     *
     * @return int|string|null
     */
    public function getEntityId($parameterName)
    {
        assert(false);
        return null;
    }

    /**
     * setup endpoints
     */
    public function setupEndpoints()
    {
        $app = $this->getApp();
        $endpoints = $this->getEndpoints();
        Hook::call('APIHandler::endpoints', [&$endpoints, $this]);
        foreach ($endpoints as $method => $definitions) {
            foreach ($definitions as $parameters) {
                $method = strtolower($method);
                $pattern = $parameters['pattern'];
                $handler = $parameters['handler'];
                $roles = $parameters['roles'] ?? null;
                $app->$method($pattern, $handler)->setName($handler[1]);
                if (!is_null($roles) && is_array($roles)) {
                    $this->addRoleAssignment($roles, $handler[1]);
                }
            }
        }
    }

    /**
     * Returns the list of endpoints
     *
     * @return array
     */
    public function getEndpoints()
    {
        return $this->_endpoints;
    }

    /**
     * Fetches parameter value
     *
     * @param string $parameterName
     * @param null|mixed $default
     *
     */
    public function getParameter($parameterName, $default = null)
    {
        $slimRequest = $this->getSlimRequest();
        if ($slimRequest == null) {
            return $default;
        }

        $route = $slimRequest->getAttribute('route');

        // we probably have an invalid url if route is null
        if (!is_null($route)) {
            $arguments = $route->getArguments();
            if (isset($arguments[$parameterName])) {
                return $arguments[$parameterName];
            }

            $queryParams = $slimRequest->getQueryParams();
            if (isset($queryParams[$parameterName])) {
                return $queryParams[$parameterName];
            }
        }

        return $default;
    }

    /**
     * Convert a query parameter to an array
     *
     * This method will convert a query parameter to an array, and
     * supports a comma-separated list of values
     */
    protected function paramToArray($value): array
    {
        if (is_array($value)) {
            return $value;
        } elseif (is_string($value)) {
            return explode(',', $value);
        }
        return [$value];
    }

    /**
     * Convert string values in boolean, integer and number parameters to their
     * appropriate type when the string is in a recognizable format.
     *
     * Converted booleans: False: "0", "false". True: "true", "1"
     * Converted integers: Anything that passes ctype_digit()
     * Converted floats: Anything that passes is_numeric()
     *
     * Empty strings will be converted to null.
     *
     * @param string $schema One of the SCHEMA_... constants
     * @param array $params Key/value parameters to be validated
     *
     * @return array Converted parameters
     */
    public function convertStringsToSchema($schema, $params)
    {
        $schema = Services::get('schema')->get($schema);

        foreach ($params as $paramName => $paramValue) {
            if (!property_exists($schema->properties, $paramName)) {
                continue;
            }
            if (!empty($schema->properties->{$paramName}->multilingual)) {
                foreach ($paramValue as $localeKey => $localeValue) {
                    $params[$paramName][$localeKey] = $this->_convertStringsToSchema(
                        $localeValue,
                        $schema->properties->{$paramName}->type,
                        $schema->properties->{$paramName}
                    );
                }
            } else {
                $params[$paramName] = $this->_convertStringsToSchema(
                    $paramValue,
                    $schema->properties->{$paramName}->type,
                    $schema->properties->{$paramName}
                );
            }
        }

        return $params;
    }

    /**
     * Helper function to convert a string to a specified type if it meets
     * certain conditions.
     *
     * This function can be called recursively on nested objects and arrays.
     *
     * @see self::convertStringsToTypes
     *
     * @param string $type One of boolean, integer or number
     */
    private function _convertStringsToSchema($value, $type, $schema)
    {
        // Convert all empty strings to null except arrays (see note below)
        if (is_string($value) && !strlen($value) && $type !== 'array') {
            return null;
        }
        switch ($type) {
            case 'boolean':
                if (is_string($value)) {
                    if ($value === 'true' || $value === '1') {
                        return true;
                    } elseif ($value === 'false' || $value === '0') {
                        return false;
                    }
                }
                break;
            case 'integer':
                if (is_string($value) && ctype_digit($value)) {
                    return (int) $value;
                }
                break;
            case 'number':
                if (is_string($value) && is_numeric($value)) {
                    return floatval($value);
                }
                break;
            case 'array':
                if (is_array($value)) {
                    $newArray = [];
                    if (is_array($schema->items)) {
                        foreach ($schema->items as $i => $itemSchema) {
                            $newArray[$i] = $this->_convertStringsToSchema($value[$i], $itemSchema->type, $itemSchema);
                        }
                    } else {
                        foreach ($value as $i => $v) {
                            $newArray[$i] = $this->_convertStringsToSchema($v, $schema->items->type, $schema->items);
                        }
                    }
                    return $newArray;

                // An empty string is accepted as an empty array. This addresses the
                // issue where browsers strip empty arrays from post data before sending.
                // See: https://bugs.jquery.com/ticket/6481
                } elseif (is_string($value) && !strlen($value)) {
                    return [];
                }
                break;
            case 'object':
                if (is_array($value)) {
                    // In some cases a property may be defined as an object but it may not
                    // contain specific details about that object's properties. In these cases,
                    // leave the properties alone.
                    if (!property_exists($schema, 'properties')) {
                        return $value;
                    }
                    $newObject = [];
                    foreach ($schema->properties as $propName => $propSchema) {
                        if (!isset($value[$propName])) {
                            continue;
                        }
                        $newObject[$propName] = $this->_convertStringsToSchema($value[$propName], $propSchema->type, $propSchema);
                    }
                    return $newObject;
                }
                break;
        }
        return $value;
    }

    /**
     * A helper method to validate start and end date params for stats
     * API handlers
     *
     * 1. Checks the date formats
     * 2. Ensures a start date is not earlier than PKPStatisticsHelper::STATISTICS_EARLIEST_DATE
     * 3. Ensures an end date is no later than yesterday
     * 4. Ensures the start date is not later than the end date
     *
     * @param array $params The params to validate
     * @param string $dateStartParam Where the find the start date in the array of params
     * @param string $dateEndParam Where to find the end date in the array of params
     *
     * @return bool|string True if they validate, or a string which
     *   contains the locale key of an error message.
     */
    protected function _validateStatDates($params, $dateStartParam = 'dateStart', $dateEndParam = 'dateEnd')
    {
        $validator = ValidatorFactory::make(
            $params,
            [
                $dateStartParam => [
                    'date_format:Y-m-d',
                    'after_or_equal:' . PKPStatisticsHelper::STATISTICS_EARLIEST_DATE,
                    'before_or_equal:' . $dateEndParam,
                ],
                $dateEndParam => [
                    'date_format:Y-m-d',
                    'before_or_equal:yesterday',
                    'after_or_equal:' . $dateStartParam,
                ],
            ],
            [
                '*.date_format' => 'invalidFormat',
                $dateStartParam . '.after_or_equal' => 'tooEarly',
                $dateEndParam . '.before_or_equal' => 'tooLate',
                $dateStartParam . '.before_or_equal' => 'invalidRange',
                $dateEndParam . '.after_or_equal' => 'invalidRange',
            ]
        );

        if ($validator->fails()) {
            $errors = $validator->errors()->getMessages();
            if ((!empty($errors[$dateStartParam]) && in_array('invalidFormat', $errors[$dateStartParam]))
                    || (!empty($errors[$dateEndParam]) && in_array('invalidFormat', $errors[$dateEndParam]))) {
                return 'api.stats.400.wrongDateFormat';
            }
            if (!empty($errors[$dateStartParam]) && in_array('tooEarly', $errors[$dateStartParam])) {
                return 'api.stats.400.earlyDateRange';
            }
            if (!empty($errors[$dateEndParam]) && in_array('tooLate', $errors[$dateEndParam])) {
                return 'api.stats.400.lateDateRange';
            }
            if ((!empty($errors[$dateStartParam]) && in_array('invalidRange', $errors[$dateStartParam]))
                    || (!empty($errors[$dateEndParam]) && in_array('invalidRange', $errors[$dateEndParam]))) {
                return 'api.stats.400.wrongDateRange';
            }
        }

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\handler\APIHandler', '\APIHandler');
}
