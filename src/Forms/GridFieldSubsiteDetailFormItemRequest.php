<?php

namespace SilverStripe\Subsites\Forms;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\Subsites\Model\Subsite;

class GridFieldSubsiteDetailFormItemRequest extends GridFieldDetailForm_ItemRequest
{

    private static $allowed_actions = [
        'ItemEditForm',
    ];

    /**
     * Builds an item edit form.  The arguments to getCMSFields() are the popupController and
     * popupFormName, however this is an experimental API and may change.
     *
     * @return Form
     * @see GridFieldDetailForm_ItemRequest::ItemEditForm()
     */
    public function ItemEditForm()
    {
        $form = parent::ItemEditForm();

        if ($this->record->ID == 0) {
            $templates = Subsite::get()->sort('Title');
            $templateArray = [];
            if ($templates) {
                $templateArray = $templates->map('ID', 'Title');
            }

            $templateDropdown = new DropdownField(
                'TemplateID',
                _t('Subsite.COPYSTRUCTURE', 'Copy structure from:'),
                $templateArray
            );
            $templateDropdown->setEmptyString('(' . _t('Subsite.NOTEMPLATE', 'No template') . ')');
            $form->Fields()->addFieldToTab('Root.Main', $templateDropdown);
        }

        return $form;
    }

    public function doSave($data, $form)
    {
        $new_record = $this->record->ID == 0;
        if ($new_record && isset($data['TemplateID']) && !empty($data['TemplateID'])) {
            $template = Subsite::get()->byID(intval($data['TemplateID']));
            if ($template) {
                $this->record = $template->duplicate();
            }
        }

        return parent::doSave($data, $form);
    }
}
