<?php
/**
 * Admin interface to manage and create {@link Subsite} instances.
 * 
 * @package subsites
 */
class SubsiteAdmin extends ModelAdmin {
	
	private static $managed_models = array('Subsite');

	private static $url_segment = 'subsites';
	
	private static $menu_title = "Subsites";

	private static $menu_icon = "subsites/images/subsites.png";
	
	public $showImportForm=false;

	private static $tree_class = 'Subsite';

	public function getEditForm($id = null, $fields = null) {
		$form = parent::getEditForm($id, $fields);

		$grid=$form->Fields()->dataFieldByName('Subsite');
		if($grid) {
			$grid->getConfig()->removeComponentsByType('GridFieldDetailForm');
			$grid->getConfig()->addComponent(new GridFieldSubsiteDetailForm());
		}

		return $form;
	}

	public function getResponseNegotiator() {
		$negotiator = parent::getResponseNegotiator();
		$self = $this;
		// Register a new callback
		$negotiator->setCallback('SubsiteList', function() use(&$self) {
			return $self->SubsiteList();
		});
		return $negotiator;
	}

	public function SubsiteList() {
		return $this->renderWith('SubsiteList');
	}
}
