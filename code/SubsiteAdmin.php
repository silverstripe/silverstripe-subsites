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
	
	public $showImportForm=false;

	static $tree_class = 'Subsite';

	public function getEditForm($id = null, $fields = null) {
		$form = parent::getEditForm($id, $fields);

		$grid=$form->Fields()->dataFieldByName('Subsite');
		if($grid) {
			$grid->getConfig()->removeComponentsByType('GridFieldDetailForm');
			$grid->getConfig()->addComponent(new GridFieldSubsiteDetailForm());
		}

		return $form;
	}
}
