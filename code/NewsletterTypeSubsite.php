<?php

class NewsletterTypeSubsite extends DataObjectDecorator{
	/**
	 * Define extra database fields
	 *
	 * Return a map where the keys are db, has_one, etc, and the values are
	 * additional fields/relations to be defined.
	 *
	 * @return array Returns a map where the keys are db, has_one, etc, and
	 *               the values are additional fields/relations to be defined.
	 */
	function extraDBFields() {
		return array(
			'has_one'=>array(
				'Subsite' => "Subsite"
			),		
		);
	}
	function updateCMSFields(&$fields){
		$currentController = Controller::curr();
		$subsites = $currentController->Subsites();
		$subsite = new DropdownField("SubsiteID", _t('NewsletterAdmin.TEMPLETELINKAPPLY', 'Template link apply to:'), $subsites->map());
		$fields->fieldByName("Root")->fieldByName("NewsletterSettings")->push($subsite);
	}
	
	function updateAbsoluteBaseURL(&$url){
			$subsite = $this->owner->Subsite();
			$url = $subsite->absoluteBaseURL();
	}
}