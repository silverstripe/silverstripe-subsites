<?php
class GridFieldSubsiteDetailForm extends GridFieldDetailForm {
	protected $itemRequestClass='GridFieldSubsiteDetailForm_ItemRequest';
}

class GridFieldSubsiteDetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest {
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

			$form->Fields()->addFieldToTab('Root.Configuration', new DropdownField('TemplateID', 'Copy structure from:', $templateArray, null, null, "(No template)"));
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
		
		try {
			$form->saveInto($this->record);
			$this->record->write();
			$this->gridField->getList()->add($this->record);
		} catch(ValidationException $e) {
			$form->sessionMessage($e->getResult()->message(), 'bad');
			return Controller::curr()->redirectBack();
		}

		// TODO Save this item into the given relationship

		$message = sprintf(
			_t('GridFieldDetailForm.Saved', 'Saved %s %s'),
			$this->record->singular_name(),
			'<a href="' . $this->Link('edit') . '">"' . htmlspecialchars($this->record->Title, ENT_QUOTES) . '"</a>'
		);
		
		$form->sessionMessage($message, 'good');

		return Controller::curr()->redirect($this->Link());
	}
}