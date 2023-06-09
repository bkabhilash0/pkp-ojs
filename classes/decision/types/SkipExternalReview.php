<?php
/**
 * @file classes/decision/types/SkipExternalReview.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SkipExternalReview
 *
 * @brief Extend the skip review decision to handle APC payments.
 */

namespace APP\decision\types;

use APP\core\Application;
use APP\decision\Decision;
use APP\decision\types\traits\RequestPayment;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\Steps;
use PKP\decision\types\SkipExternalReview as PKPSkipExternalReview;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class SkipExternalReview extends PKPSkipExternalReview
{
    use RequestPayment;

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        if (!isset($props['actions'])) {
            return;
        }

        foreach ((array) $props['actions'] as $index => $action) {
            $actionErrorKey = 'actions.' . $index;
            switch ($action['id']) {
                case self::ACTION_PAYMENT:
                    $this->validatePaymentAction($action, $actionErrorKey, $validator, $context);
                    break;
            }
        }
    }

    public function runAdditionalActions(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        parent::runAdditionalActions($decision, $submission, $editor, $context, $actions);

        foreach ($actions as $action) {
            switch ($action['id']) {
                case self::ACTION_PAYMENT:
                    $this->requestPayment($submission, $editor, $context);
                    break;
            }
        }
    }

    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Steps
    {
        $steps = parent::getSteps($submission, $context, $editor, $reviewRound);

        // Request payment if configured
        $paymentManager = Application::getPaymentManager($context);
        if ($paymentManager->publicationEnabled()) {
            $steps->addStep($this->getPaymentForm($context), true);
        }

        return $steps;
    }
}
