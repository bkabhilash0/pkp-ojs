<?php
/**
 * @file classes/components/form/announcement/PKPAnnouncementForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for creating a new announcement
 */

namespace PKP\components\forms\announcement;

use PKP\announcement\AnnouncementTypeDAO;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\db\DAORegistry;

define('FORM_ANNOUNCEMENT', 'announcement');

class PKPAnnouncementForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_ANNOUNCEMENT;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Context $announcementContext The context to get supported announcement types
     */
    public function __construct($action, $locales, $announcementContext)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldText('title', [
            'label' => __('common.title'),
            'size' => 'large',
            'isMultilingual' => true,
        ]))
            ->addField(new FieldRichTextarea('descriptionShort', [
                'label' => __('manager.announcements.form.descriptionShort'),
                'description' => __('manager.announcements.form.descriptionShortInstructions'),
                'isMultilingual' => true,
            ]))
            ->addField(new FieldRichTextarea('description', [
                'label' => __('manager.announcements.form.description'),
                'description' => __('manager.announcements.form.descriptionInstructions'),
                'isMultilingual' => true,
                'size' => 'large',
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => 'paste,link,lists',
            ]))
            ->addField(new FieldText('dateExpire', [
                'label' => __('manager.announcements.form.dateExpire'),
                'description' => __('manager.announcements.form.dateExpireInstructions'),
                'size' => 'small',
            ]));

        /** @var AnnouncementTypeDAO */
        $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO');
        $announcementTypes = $announcementTypeDao->getByContextId($announcementContext->getId());
        $announcementOptions = [];
        foreach ($announcementTypes as $announcementType) {
            $announcementOptions[] = [
                'value' => (int) $announcementType->getId(),
                'label' => $announcementType->getLocalizedTypeName(),
            ];
        }
        if (!empty($announcementOptions)) {
            $this->addField(new FieldOptions('typeId', [
                'label' => __('manager.announcementTypes.typeName'),
                'type' => 'radio',
                'options' => $announcementOptions,
            ]));
        }

        $this->addField(new FieldOptions('sendEmail', [
            'label' => __('common.sendEmail'),
            'options' => [
                [
                    'value' => true,
                    'label' => __('notification.sendNotificationConfirmation')
                ]
            ]
        ]));
    }
}
