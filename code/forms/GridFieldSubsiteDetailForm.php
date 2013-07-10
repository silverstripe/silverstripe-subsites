<?php
class GridFieldSubsiteDetailForm extends GridFieldDetailForm {
	protected $itemRequestClass='GridFieldSubsiteDetailForm_ItemRequest';
}

class GridFieldSubsiteDetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest {

	private static $allowed_actions = array(
		'ItemEditForm',
	);

	/**
	 * Builds an item edit form.  The arguments to getCMSFields() are the popupController and
	 * popupFormName, however this is an experimental API and may change.
	 * 
	 * @todo In the future, we will probably need to come up with a tigher object representing a partially
	 * complete controller with gaps for extra functionality.  This, for example, would be a better way
	 * of letting Security/login put its log-in form inside a UI specified elsewhere.
	 * 
	 * @return Form
	 * @see GridFieldDetailForm_ItemRequest::ItemEditForm()
	 */
	function ItemEditForm() {
		$form=parent::ItemEditForm();
		
		if($this->record->ID == 0) {
			$templates = Subsite::get()->sort('Title');
			$templateArray = array();
			if($templates) {
				$templateArray = $templates->map('ID', 'Title');
			}

			$templateDropdown = new DropdownField('TemplateID', _t('Subsite.COPYSTRUCTURE', 'Copy structure from:'), $templateArray);
			$templateDropdown->setEmptyString('(' . _t('Subsite.NOTEMPLATE', 'No template') . ')');
			$form->Fields()->addFieldToTab('Root.Configuration', $templateDropdown);
		}
		
		return $form;
	}
	
	function doSave($data, $form) {
		$new_record = $this->record->ID == 0;
		if($new_record && isset($data['TemplateID']) && !empty($data['TemplateID'])) {
			$template = Subsite::get()->byID(intval($data['TemplateID']));
			if($template) {
				$this->record = $template->duplicate();
			}
		}

		return parent::doSave($data, $form);
	}
}
