<?php

/**
 * @file api/v1/_submissions/BackendSubmissionsHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BackendSubmissionsHandler
 *
 * @ingroup api_v1_backend
 *
 * @brief Handle API requests for backend operations.
 *
 */

namespace APP\API\v1\_submissions;

use APP\core\Application;
use APP\payment\ojs\OJSPaymentManager;
use APP\submission\Collector;
use PKP\db\DAORegistry;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use Slim\Http\Request;

class BackendSubmissionsHandler extends \PKP\API\v1\_submissions\PKPBackendSubmissionsHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_endpoints = array_merge_recursive($this->_endpoints, [
            'PUT' => [
                [
                    'pattern' => '/{contextPath}/api/{version}/_submissions/{submissionId:\d+}/payment',
                    'handler' => [$this, 'payment'],
                    'roles' => [
                        Role::ROLE_ID_SUB_EDITOR,
                        Role::ROLE_ID_MANAGER,
                        Role::ROLE_ID_SITE_ADMIN,
                        Role::ROLE_ID_ASSISTANT,
                    ],
                ],
            ],
        ]);

        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $routeName = $this->getSlimRequest()->getAttribute('route')->getName();

        if ($routeName === 'payment') {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Change the status of submission payments.
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function payment($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission || !$context || $context->getId() != $submission->getContextId()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $paymentManager = Application::getPaymentManager($context);
        $publicationFeeEnabled = $paymentManager->publicationEnabled();
        if (!$publicationFeeEnabled) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $params = $slimRequest->getParsedBody();

        if (empty($params['publicationFeeStatus'])) {
            return $response->withJson([
                'publicationFeeStatus' => [__('validator.required')],
            ], 400);
        }

        $completedPaymentDao = DAORegistry::getDAO('OJSCompletedPaymentDAO'); /** @var OJSCompletedPaymentDAO $completedPaymentDao */
        $publicationFeePayment = $completedPaymentDao->getByAssoc(null, OJSPaymentManager::PAYMENT_TYPE_PUBLICATION, $submission->getId());

        switch ($params['publicationFeeStatus']) {
            case 'waived':
                // Check if a waiver already exists; if so, don't do anything.
                if ($publicationFeePayment && !$publicationFeePayment->getAmount()) {
                    break;
                }

                // If a fulfillment (nonzero amount) already exists, remove it.
                if ($publicationFeePayment) {
                    $completedPaymentDao->deleteById($publicationFeePayment->getId());
                }

                // Record a waived payment.
                $queuedPayment = $paymentManager->createQueuedPayment(
                    $request,
                    OJSPaymentManager::PAYMENT_TYPE_PUBLICATION,
                    $request->getUser()->getId(),
                    $submission->getId(),
                    0,
                    '' // Zero amount, no currency
                );
                $paymentManager->queuePayment($queuedPayment);
                $paymentManager->fulfillQueuedPayment($request, $queuedPayment, 'ManualPayment');
                break;
            case 'paid':
                // Check if a fulfilled payment already exists; if so, don't do anything.
                if ($publicationFeePayment && $publicationFeePayment->getAmount()) {
                    break;
                }

                // If a waiver (0 amount) already exists, remove it.
                if ($publicationFeePayment) {
                    $completedPaymentDao->deleteById($publicationFeePayment->getId());
                }

                // Record a fulfilled payment.
                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                $submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleIds($submission->getId(), [Role::ROLE_ID_AUTHOR]);
                $submitterAssignment = $submitterAssignments->next();
                $queuedPayment = $paymentManager->createQueuedPayment(
                    $request,
                    OJSPaymentManager::PAYMENT_TYPE_PUBLICATION,
                    $submitterAssignment->getUserId(),
                    $submission->getId(),
                    $context->getSetting('publicationFee'),
                    $context->getSetting('currency')
                );
                $paymentManager->queuePayment($queuedPayment);
                $paymentManager->fulfillQueuedPayment($request, $queuedPayment, 'Waiver');
                break;
            case 'unpaid':
                if ($publicationFeePayment) {
                    $completedPaymentDao->deleteById($publicationFeePayment->getId());
                }
                break;
            default:
                return $response->withJson([
                    'publicationFeeStatus' => [__('validator.required')],
                ], 400);
        }

        return $response->withJson(true);
    }

    /** @copydoc PKPSubmissionHandler::getSubmissionCollector() */
    protected function getSubmissionCollector(array $queryParams): Collector
    {
        $collector = parent::getSubmissionCollector($queryParams);

        if (isset($queryParams['issueIds'])) {
            $collector->filterByIssueIds(
                array_map('intval', $this->paramToArray($queryParams['issueIds']))
            );
        }

        if (isset($queryParams['sectionIds'])) {
            $collector->filterBySectionIds(
                array_map('intval', $this->paramToArray($queryParams['sectionIds']))
            );
        }

        return $collector;
    }
}
