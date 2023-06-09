<?php
/**
 * @file classes/components/form/context/ArchivingLockssForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArchivingLockssForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring the LOCKSS and CLOCKSS settings.
 */

namespace APP\components\forms\context;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;

define('FORM_ARCHIVING_LOCKSS', 'archivingLockss');

class ArchivingLockssForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_ARCHIVING_LOCKSS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Context $context Journal or Press to change settings for
     * @param string $lockssUrl URL to the publisher manifest page for LOCKSS
     * @param string $clockssUrl URL to the publisher manifest page for CLOCKSS
     */
    public function __construct($action, $locales, $context, $lockssUrl, $clockssUrl)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldOptions('enableLockss', [
            'label' => __('manager.setup.lockssTitle'),
            'description' => __('manager.setup.lockssLicenseDescription'),
            'options' => [
                [
                    'value' => true,
                    'label' => __('manager.setup.lockssEnable', ['lockssUrl' => $lockssUrl]),
                ],
            ],
            'value' => (bool) $context->getData('enableLockss'),
        ]))
            ->addField(new FieldOptions('enableClockss', [
                'label' => __('manager.setup.clockssTitle'),
                'description' => __('manager.setup.clockssLicenseDescription'),
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.setup.clockssEnable', ['clockssUrl' => $clockssUrl]),
                    ],
                ],
                'value' => (bool) $context->getData('enableClockss'),
            ]));
    }
}
