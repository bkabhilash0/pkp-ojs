<?php

/**
 * @file classes/core/PKPComponentRouter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPComponentRouter
 *
 * @ingroup core
 *
 * @brief Class mapping an HTTP request to a component handler operation.
 *
 *  We are using an RPC style URL-to-endpoint mapping. Our approach follows
 *  a simple "convention-over-configuration" paradigm. If necessary the
 *  router can be subclassed to implement more complex URL-to-endpoint mappings.
 *
 *  For servers with path info enabled the component URL has the following elements:
 *
 *       .../index.php/context1/context2/$$$call$$$/path/to/handler-class/operation-name?arg1=...&arg2=...
 *
 *  where "$$$call$$$" is a non-mutable literal string and "path/to" is
 *  by convention the directory path below the "controllers" folder leading to the
 *  component. The next element ("handler-class" in this example) will be mapped to a
 *  component class file by "camelizing" the string to "HandlerClassHandler" and adding
 *  ".php" to the end. The "operation-name" is transformed to "operationName"
 *  and represents the name of the handler method to be called. Finally "arg1", "arg2",
 *  etc. are parameters to be passed along to the handler method.
 *
 *  For servers with path info disabled the component URL looks like this:
 *
 *       .../index.php?component=path.to.handler-class&op=operation-name&arg1=...&arg2=...
 *
 *  The router will sanitize the request URL to a certain amount to make sure that
 *  random code inclusions are prevented. User authorization and parameter validation
 *  are however not the router's concern. These must be implemented on handler level.
 *
 *  NB: Component and operation names may only contain a-z, 0-9 and hyphens. Numbers
 *  are not allowed at the beginning of a name or after a hyphen.
 *
 *  NB: Component handlers must implement an initialize() method that will be called
 *  before the request is routed. The initialization method must enforce authorization
 *  and request validation.
 */

namespace PKP\core;

// The string to be found in the URL to mark this request as a component request
define('COMPONENT_ROUTER_PATHINFO_MARKER', '$$$call$$$');

// The parameter to be found in the query string for servers with path info disabled
define('COMPONENT_ROUTER_PARAMETER_MARKER', 'component');

// This is the maximum directory depth allowed within the component directory. Set
// it to something reasonable to avoid DoS or overflow attacks
define('COMPONENT_ROUTER_PARTS_MAXDEPTH', 9);

// This is the maximum/minimum length of the name of a sub-directory or
// handler class name.
define('COMPONENT_ROUTER_PARTS_MAXLENGTH', 50);
define('COMPONENT_ROUTER_PARTS_MINLENGTH', 2);

use Exception;

use PKP\config\Config;
use PKP\plugins\Hook;

class PKPComponentRouter extends PKPRouter
{
    //
    // Internal state cache variables
    // NB: Please do not access directly but
    // only via their respective getters/setters
    //
    /** @var string the requested component handler */
    public $_component;
    /** @var string the requested operation */
    public $_op;
    /** @var array the rpc service endpoint parts from the request */
    public $_rpcServiceEndpointParts = false;
    /** @var callable the rpc service endpoint the request was routed to */
    public $_rpcServiceEndpoint = false;


    /**
     * Determines whether this router can route the given request.
     *
     * @param PKPRequest $request
     *
     * @return bool true, if the router supports this request, otherwise false
     */
    public function supports($request): bool
    {
        // See whether this looks like a component router request.
        // NOTE: this is prone to false positives i.e. when a class
        // name cannot be matched, but this laxity permits plugins to
        // extend the system by registering against the
        // LoadComponentHandler hook.
        return $this->_retrieveServiceEndpointParts($request) !== null;
    }

    /**
     * Retrieve the requested component from the request.
     *
     * NB: This can be a component that not actually exists
     * in the code base.
     *
     * @param PKPRequest $request
     *
     * @return string the requested component or an empty string
     *  if none can be found.
     */
    public function getRequestedComponent($request)
    {
        if (is_null($this->_component)) {
            $this->_component = '';

            // Retrieve the service endpoint parts from the request.
            if (is_null($rpcServiceEndpointParts = $this->_getValidatedServiceEndpointParts($request))) {
                // Endpoint parts cannot be found in the request
                return '';
            }

            // Pop off the operation part
            array_pop($rpcServiceEndpointParts);

            // Construct the fully qualified component class name from the rest of it.
            $handlerClassName = PKPString::camelize(array_pop($rpcServiceEndpointParts), PKPString::CAMEL_CASE_HEAD_UP) . 'Handler';

            // camelize remaining endpoint parts
            $camelizedRpcServiceEndpointParts = [];
            foreach ($rpcServiceEndpointParts as $part) {
                $camelizedRpcServiceEndpointParts[] = PKPString::camelize($part, PKPString::CAMEL_CASE_HEAD_DOWN);
            }
            $handlerPackage = implode('.', $camelizedRpcServiceEndpointParts);

            $this->_component = $handlerPackage . '.' . $handlerClassName;
        }

        return $this->_component;
    }

    /**
     * Retrieve the requested operation from the request
     *
     * NB: This can be an operation that not actually
     * exists in the requested component.
     *
     * @param PKPRequest $request
     *
     * @return string the requested operation or an empty string
     *  if none can be found.
     */
    public function getRequestedOp($request)
    {
        if (is_null($this->_op)) {
            $this->_op = '';

            // Retrieve the service endpoint parts from the request.
            if (is_null($rpcServiceEndpointParts = $this->_getValidatedServiceEndpointParts($request))) {
                // Endpoint parts cannot be found in the request
                return '';
            }

            // Pop off the operation part
            $this->_op = PKPString::camelize(array_pop($rpcServiceEndpointParts), PKPString::CAMEL_CASE_HEAD_DOWN);
        }

        return $this->_op;
    }

    /**
     * Get the (validated) RPC service endpoint from the request.
     * If no such RPC service endpoint can be constructed then the method
     * returns null.
     *
     * @param PKPRequest $request the request to be routed
     *
     * @return callable an array with the handler instance
     *  and the handler operation to be called by call_user_func().
     */
    public function &getRpcServiceEndpoint($request)
    {
        if ($this->_rpcServiceEndpoint === false) {
            // We have not yet resolved this request. Mark the
            // state variable so that we don't try again next
            // time.
            $this->_rpcServiceEndpoint = $nullVar = null;

            // Retrieve requested component operation
            $op = $this->getRequestedOp($request);
            assert(!empty($op));

            //
            // Component Handler
            //
            // Retrieve requested component handler
            $component = $this->getRequestedComponent($request);
            $componentInstance = null;
            $allowedPackages = null;

            // Give plugins a chance to intervene
            if (!Hook::call('LoadComponentHandler', [&$component, &$op, &$componentInstance])) {
                if (empty($component)) {
                    return $nullVar;
                }

                // Construct the component handler file name and test its existence.
                $component = 'controllers.' . $component;
                $componentFileNamePart = str_replace('.', '/', $component);
                switch (true) {
                    case file_exists("{$componentFileNamePart}.php"):
                        $className = 'APP\\' . strtr($componentFileNamePart, '/', '\\');
                        $componentInstance = new $className();
                        break;

                    case file_exists("{$componentFileNamePart}.inc.php"):
                        // This behaviour is DEPRECATED as of 3.4.0.
                        break;

                    case file_exists(PKP_LIB_PATH . "/{$componentFileNamePart}.php"):
                        $className = 'PKP\\' . strtr($componentFileNamePart, '/', '\\');
                        $componentInstance = new $className();
                        break;

                    case file_exists(PKP_LIB_PATH . "/{$componentFileNamePart}.inc.php"):
                        // This behaviour is DEPRECATED as of 3.4.0.
                        $component = 'lib.pkp.' . $component;
                        break;

                    default:
                        // Request to non-existent handler
                        return $nullVar;
                }

                // We expect the handler to be part of one
                // of the following packages:
                $allowedPackages = [
                    'controllers',
                    'lib.pkp.controllers'
                ];
            }

            // A handler at least needs to implement the
            // following methods:
            $requiredMethods = [
                $op, 'authorize', 'validate', 'initialize'
            ];

            if (!$componentInstance) {
                $componentInstance = instantiate($component, 'PKPHandler', $allowedPackages, $requiredMethods);
            }
            if (!is_object($componentInstance)) {
                return $nullVar;
            }
            $this->setHandler($componentInstance);

            //
            // Callable service endpoint
            //
            // Construct the callable array
            $this->_rpcServiceEndpoint = [$componentInstance, $op];
        }

        return $this->_rpcServiceEndpoint;
    }


    //
    // Implement template methods from PKPRouter
    //
    /**
     * @copydoc PKPRouter::route()
     */
    public function route($request)
    {
        // Determine the requested service endpoint.
        $rpcServiceEndpoint = & $this->getRpcServiceEndpoint($request);

        // Retrieve RPC arguments from the request.
        $args = $request->getUserVars();
        assert(is_array($args));

        // Remove the caller-parameter (if present)
        if (isset($args[COMPONENT_ROUTER_PARAMETER_MARKER])) {
            unset($args[COMPONENT_ROUTER_PARAMETER_MARKER]);
        }

        // Authorize, validate and initialize the request
        $this->_authorizeInitializeAndCallRequest($rpcServiceEndpoint, $request, $args);
    }

    /**
     * @copydoc PKPRouter::url()
     *
     * @param null|mixed $newContext
     * @param null|mixed $component
     * @param null|mixed $op
     * @param null|mixed $path
     * @param null|mixed $params
     * @param null|mixed $anchor
     */
    public function url(
        $request,
        $newContext = null,
        $component = null,
        $op = null,
        $path = null,
        $params = null,
        $anchor = null,
        $escape = false
    ) {
        if (!is_null($path)) {
            throw new Exception('Path must be null when calling PKPComponentRouter::url()');
        }

        //
        // Base URL and Context
        //
        [$baseUrl, $context] = $this->_urlGetBaseAndContext($request, $newContext);

        //
        // Component and Operation
        //
        // We only support component/op retrieval from the request
        // if this request is a component request.
        $currentRequestIsAComponentRequest = $request->getRouter() instanceof self;
        if ($currentRequestIsAComponentRequest) {
            if (empty($component)) {
                $component = $this->getRequestedComponent($request);
            }
            if (empty($op)) {
                $op = $this->getRequestedOp($request);
            }
        }
        assert(!empty($component) && !empty($op));

        // Encode the component and operation
        $componentParts = explode('.', $component);
        $componentName = array_pop($componentParts);
        assert(substr($componentName, -7) == 'Handler');
        $componentName = PKPString::uncamelize(substr($componentName, 0, -7));

        // uncamelize the component parts
        $uncamelizedComponentParts = [];
        foreach ($componentParts as $part) {
            $uncamelizedComponentParts[] = PKPString::uncamelize($part);
        }
        array_push($uncamelizedComponentParts, $componentName);
        $opName = PKPString::uncamelize($op);

        //
        // Additional query parameters
        //
        $additionalParameters = $this->_urlGetAdditionalParameters($request, $params, $escape);

        //
        // Anchor
        //
        $anchor = (empty($anchor) ? '' : '#' . rawurlencode($anchor));

        //
        // Assemble URL
        //
        // Context, page, operation and additional path go into the path info.
        $pathInfoArray = array_merge(
            $context,
            [COMPONENT_ROUTER_PATHINFO_MARKER],
            $uncamelizedComponentParts,
            [$opName]
        );

        // Query parameters
        $queryParametersArray = $additionalParameters;

        return $this->_urlFromParts($baseUrl, $pathInfoArray, $queryParametersArray, $anchor, $escape);
    }

    /**
     * @copydoc PKPRouter::handleAuthorizationFailure()
     */
    public function handleAuthorizationFailure(
        $request,
        $authorizationMessage,
        array $messageParams = []
    ) {
        $translatedAuthorizationMessage = __($authorizationMessage, $messageParams);

        // Add the router name and operation if show_stacktrace is enabled.
        if (Config::getVar('debug', 'show_stacktrace')) {
            $url = $request->getRequestUrl();
            $queryString = $request->getQueryString();
            if ($queryString) {
                $queryString = '?' . $queryString;
            }
            $translatedAuthorizationMessage .= ' [' . $url . $queryString . ']';
        }
        // Return a JSON error message.
        return new JSONMessage(false, $translatedAuthorizationMessage);
    }


    //
    // Private helper methods
    //
    /**
     * Get the (validated) RPC service endpoint parts from the request.
     * If no such RPC service endpoint parts can be retrieved
     * then the method returns null.
     *
     * @param PKPRequest $request the request to be routed
     *
     * @return array a string array with the RPC service endpoint
     *  parts as values.
     */
    public function _getValidatedServiceEndpointParts($request)
    {
        if ($this->_rpcServiceEndpointParts === false) {
            // Mark the internal state variable so this
            // will not be called again.
            $this->_rpcServiceEndpointParts = null;

            // Retrieve service endpoint parts from the request.
            if (is_null($rpcServiceEndpointParts = $this->_retrieveServiceEndpointParts($request))) {
                // This is not an RPC request
                return null;
            }

            // Validate the service endpoint parts.
            if (is_null($rpcServiceEndpointParts = $this->_validateServiceEndpointParts($rpcServiceEndpointParts))) {
                // Invalid request
                return null;
            }

            // Assign the validated service endpoint parts
            $this->_rpcServiceEndpointParts = $rpcServiceEndpointParts;
        }

        return $this->_rpcServiceEndpointParts;
    }

    /**
     * Try to retrieve a (non-validated) array with the service
     * endpoint parts from the request. See the classdoc for the
     * URL patterns supported here.
     *
     * @param PKPRequest $request the request to be routed
     *
     * @return array an array of (non-validated) service endpoint
     *  parts or null if the request is not an RPC request.
     */
    public function _retrieveServiceEndpointParts($request)
    {
        if (!isset($_SERVER['PATH_INFO'])) {
            return null;
        }

        $pathInfoParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));

        // We expect at least the context + the component
        // router marker + 3 component parts (path, handler, operation)
        $application = $this->getApplication();
        if (count($pathInfoParts) < 5) {
            // This path info is too short to be an RPC request
            return null;
        }

        // Check the component router marker
        if ($pathInfoParts[1] != COMPONENT_ROUTER_PATHINFO_MARKER) {
            // This is not an RPC request
            return null;
        }

        // Remove context and component marker from the array
        $rpcServiceEndpointParts = array_slice($pathInfoParts, 2);

        return $rpcServiceEndpointParts;
    }

    /**
     * This method pre-validates the service endpoint parts before
     * we try to convert them to a file/method name. This also
     * converts all parts to lower case.
     *
     * @param array $rpcServiceEndpointParts
     *
     * @return array the validated service endpoint parts or null if validation
     *  does not succeed.
     */
    public function _validateServiceEndpointParts($rpcServiceEndpointParts)
    {
        // Do we have data at all?
        if (is_null($rpcServiceEndpointParts) || empty($rpcServiceEndpointParts)
                || !is_array($rpcServiceEndpointParts)) {
            return null;
        }

        // We require at least three parts: component directory, handler
        // and method name.
        if (count($rpcServiceEndpointParts) < 3) {
            return null;
        }

        // Check that the array dimensions remain within sane limits.
        if (count($rpcServiceEndpointParts) > COMPONENT_ROUTER_PARTS_MAXDEPTH) {
            return null;
        }

        // Validate the individual endpoint parts.
        foreach ($rpcServiceEndpointParts as $key => $rpcServiceEndpointPart) {
            // Make sure that none of the elements exceeds the length limit.
            $partLen = strlen($rpcServiceEndpointPart);
            if ($partLen > COMPONENT_ROUTER_PARTS_MAXLENGTH
                    || $partLen < COMPONENT_ROUTER_PARTS_MINLENGTH) {
                return null;
            }

            // Service endpoint URLs are case insensitive.
            $rpcServiceEndpointParts[$key] = strtolower_codesafe($rpcServiceEndpointPart);

            // We only allow letters, numbers and the hyphen.
            if (!PKPString::regexp_match('/^[a-z0-9-]*$/', $rpcServiceEndpointPart)) {
                return null;
            }
        }

        return $rpcServiceEndpointParts;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPComponentRouter', '\PKPComponentRouter');
}
