<?php

class SubsiteDomain extends DataObject {
	static $db = array(
		"Domain" => "Varchar(255)",
		"IsPrimary" => "Boolean",
	);
	static $has_one = array(
 		"Subsite" => "Subsite",
	);
	
	public static $summary_fields=array(
		'Domain'=>'Domain',
		'IsPrimary'=>'Is Primary Domain'
	);
	
	/**
	 * Whenever a Subsite Domain is written, rewrite the hostmap
	 *
	 * @return void
	 */
	public function onAfterWrite() {
		Subsite::writeHostMap();
	}
	
	public function getCMSFields() {
		return new FieldList(
			new TextField('Domain', _t('SubsiteDomain.DOMAIN', '_Domain'), null, 255),
			new CheckboxField('IsPrimary', _t('SubsiteDomain.IS_PRIMARY', '_Is Primary Domain'))
		);
	}
}