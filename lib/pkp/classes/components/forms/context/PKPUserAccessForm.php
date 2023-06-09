<?php
/**
 * @file classes/components/form/context/PKPUserAccessForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUserAccessForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring the user access settings on the Users
 *  and Roles page of a context.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;

define('FORM_USER_ACCESS', 'userAccess');

class PKPUserAccessForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_USER_ACCESS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param Context $context Journal or Press to change settings for
     */
    public function __construct($action, $context)
    {
        $this->action = $action;

        $this->addField(new FieldOptions('restrictSiteAccess', [
            'label' => __('manager.setup.siteAccess.view'),
            'value' => (bool) $context->getData('restrictSiteAccess'),
            'options' => [
                ['value' => true, 'label' => __('manager.setup.restrictSiteAccess')],
            ],
        ]))
            ->addField(new FieldOptions('disableUserReg', [
                'type' => 'radio',
                'label' => __('manager.setup.userRegistration'),
                'value' => (bool) $context->getData('disableUserReg'),
                'options' => [
                    ['value' => false, 'label' => __('manager.setup.enableUserRegistration')],
                    ['value' => true, 'label' => __('manager.setup.disableUserRegistration')],
                ],
            ]));
    }
}
