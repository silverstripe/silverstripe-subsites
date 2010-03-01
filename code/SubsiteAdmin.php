<?php
/**
 * Admin interface to manage and create {@link Subsite} instances.
 * 
 * @package subsites
 */
class SubsiteAdmin extends ModelAdmin {
	
	static $managed_models = array('Subsite');
	static $url_segment = 'subsites';
	
	static $collection_controller_class = "SubsiteAdmin_CollectionController";
	
	function Link() {
		return 'admin/subsites/';
	}
}

class SubsiteAdmin_CollectionController extends ModelAdmin_CollectionController {
	function AddForm() {
		$form = parent::AddForm();

		$templates = DataObject::get('Subsite_Template', '', 'Title');
		$templateArray = array('' => "(No template)");
		if($templates) {
			$templateArray = $templateArray + $templates->map('ID', 'Title');
		}

		$form->Fields()->addFieldsToTab('Root.Configuration', array(
			new DropdownField('Type', 'Type', array(
				'subsite' => 'New site',
				'template' => 'New template',
			)),
			new DropdownField('TemplateID', 'Copy structure from:', $templateArray)
		));
		
		return $form;
	}
	
	function doCreate($data, $form, $request) {
		if(isset($data['TemplateID']) && $data['TemplateID']) {
			$template = DataObject::get_by_id('Subsite_Template', $data['TemplateID']);
		} else {
			$template = null;
		}

		// Create subsite from existing template
		switch($data['Type']) {
		case 'template':
			if($template) $subsite = $template->duplicate();
			else {
				$subsite = new Subsite_Template();
				$subsite->write();
			}
			break;

		case 'subsite':
		default:
			if($template) $subsite = $template->createInstance($data['Title']);
			else {
				$subsite = new Subsite();
				$subsite->Title = $data['Title'];
				$subsite->write();
			}
			break;
		}

		$form->dataFieldByName('Domains')->setExtraData(array(
			"SubsiteID" => $subsite->ID,
		));
		$form->saveInto($subsite);
		$subsite->write();
	
		Director::redirect(Controller::join_links($this->Link(), $subsite->ID , 'edit'));
	}
}

?>
