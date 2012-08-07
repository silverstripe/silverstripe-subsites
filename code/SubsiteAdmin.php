<?php
/**
 * Admin interface to manage and create {@link Subsite} instances.
 * 
 * @package subsites
 */
class SubsiteAdmin extends ModelAdmin {
	
	static $managed_models = array('Subsite');
	static $url_segment = 'subsites';
	
	static $menu_title = "Subsites";
	
	static $collection_controller_class = "SubsiteAdmin_CollectionController";
}

class SubsiteAdmin_CollectionController extends ModelAdmin_CollectionController {
	function AddForm() {
		$form = parent::AddForm();

		$subsites = DataObject::get('Subsite', '', 'Title');
		$subsiteMap = array('' => "(No template)", -1 => '(Main Site)');
		if($subsites) {
			$subsiteMap = $subsiteMap + $subsites->map('ID', 'Title');
		}

		$form->Fields()->addFieldsToTab('Root.Configuration', array(
			new DropdownField('TemplateID', 'Copy structure from:', $subsiteMap)
		));
		
		return $form;
	}
	
	function doCreate($data, $form, $request) {
		if(isset($data['TemplateID']) && $data['TemplateID'] == -1) {
			// Copy from main site. Hacky as it relies on ID=0
			// in order to query the main site (which is technically not contained in a Subsite record)
			$mainsite = new Subsite();
			$subsite = $mainsite->duplicate();
		} elseif(isset($data['TemplateID']) && $data['TemplateID']) {
			$template = DataObject::get_by_id('Subsite', $data['TemplateID']);
			$subsite = $template->duplicate();
		} else {
			$subsite = new Subsite();
		}

		$form->dataFieldByName('Domains')->setExtraData(array(
			"SubsiteID" => $subsite->ID,
		));
		$form->saveInto($subsite);
		$subsite->write();
		
		if(Director::is_ajax()) {
			$recordController = new ModelAdmin_RecordController($this, $request, $subsite->ID);
			return new SS_HTTPResponse(
				$recordController->EditForm()->forAjaxTemplate(), 
				200, 
				sprintf(
					_t('ModelAdmin.LOADEDFOREDITING', "Loaded '%s' for editing."),
					$subsite->Title
				)
			);
		} else {
			Director::redirect(Controller::join_links($this->Link(), $subsitess->ID , 'edit'));
		}
	}
}

?>
