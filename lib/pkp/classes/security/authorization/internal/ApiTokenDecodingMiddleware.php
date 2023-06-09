<?php

/**
 * @file classes/security/authorization/internal/ApiTokenDecodingMiddleware.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ApiTokenDecodingMiddleware
 *
 * @ingroup security_authorization
 *
 * @brief Slim middleware which decodes and validates JSON Web Tokens
 */

namespace PKP\security\authorization\internal;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use PKP\config\Config;

use PKP\handler\APIHandler;
use Slim\Http\Request as SlimRequest;

class ApiTokenDecodingMiddleware
{
    /** @var APIHandler $handler Reference to api handler */
    protected $_handler = null;

    /**
     * Constructor
     *
     */
    public function __construct(APIHandler $handler)
    {
        $this->_handler = $handler;
    }

    /**
     * Decodes the request's JSON Web Token
     *
     * @param SlimRequest $slimRequest
     *
     * @return bool|string
     */
    protected function _decode($slimRequest)
    {
        try {
            $jwt = $this->getJWT($slimRequest);
        } catch (Exception $e) {
            $request = $this->_handler->getRequest();
            return $request->getRouter()
                ->handleAuthorizationFailure(
                    $request,
                    $e->getMessage()
                );
        }
        if (!$jwt) {
            /**
             * If we don't have a token, it's for the authentication logic to handle if it's a problem.
             */

            return true;
        }

        $secret = Config::getVar('security', 'api_key_secret', '');
        if (!$secret) {
            $request = $this->_handler->getRequest();
            return $request->getRouter()
                ->handleAuthorizationFailure(
                    $request,
                    'api.500.apiSecretKeyMissing'
                );
        }

        try {
            $apiToken = JWT::decode($jwt, $secret, ['HS256']);
            /**
             * Compatibility with old API keys
             *
             * @link https://github.com/pkp/pkp-lib/issues/6462
             */
            if (substr($apiToken, 0, 2) === '""') {
                $apiToken = json_decode($apiToken);
            }
            $this->_handler->setApiToken($apiToken);

            return true;
        } catch (Exception $e) {
            /**
             * If JWT decoding fails, it throws an 'UnexpectedValueException'.
             * If JSON decoding fails (of the JWT payload), it throws a 'DomainException'.
             * If token couldn't verified, it throws a 'SignatureInvalidException'.
             */
            if ($e instanceof SignatureInvalidException) {
                $request = $this->_handler->getRequest();
                return $request->getRouter()
                    ->handleAuthorizationFailure(
                        $request,
                        'api.400.invalidApiToken'
                    );
            }

            if ($e instanceof \UnexpectedValueException ||
                $e instanceof \DomainException
            ) {
                $request = $this->_handler->getRequest();
                return $request->getRouter()
                    ->handleAuthorizationFailure(
                        $request,
                        'api.400.tokenCouldNotBeDecoded',
                        [
                            'error' => $e->getMessage()
                        ]
                    );
            }

            throw $e;
        }
        // If we do not have a token, it's for the authentication logic
        // to decide if that's a problem.
        return true;
    }

    /**
     * Middleware invokable function
     *
     * @param SlimRequest $request request
     * @param SlimResponse $response response
     * @param callable $next Next middleware
     *
     * @return bool|string|unknown
     */
    public function __invoke($request, $response, $next)
    {
        $result = $this->_decode($request);
        if ($result !== true) {
            return $result;
        }

        $response = $next($request, $response);
        return $response;
    }

    protected function getJWT(SlimRequest $slimRequest)
    {
        $authHeader = $slimRequest->getHeader('Authorization');

        if (!count($authHeader) || empty($authHeader[0])) {
            // DEPRECATED as of 3.4.0. Use the Authorization header.
            return $slimRequest->getQueryParam('apiToken');
        }

        // Several authorization methods may be supplied with commas between them.
        // For example: Basic basic_auth_string_here, Bearer api_key_here
        // JWT uses the Bearer scheme with an API key. Ignore the others.
        $clauses = explode(',', $authHeader[0]);
        foreach ($clauses as $clause) {
            // Split the authorization scheme and parameters and look for the Bearer scheme.
            $parts = explode(' ', trim($clause));
            if (count($parts) == 2 && $parts[0] == 'Bearer') {
                // Found bearer authorization; return the token.
                return $parts[1];
            }
        }
        return null;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\ApiTokenDecodingMiddleware', '\ApiTokenDecodingMiddleware');
}
