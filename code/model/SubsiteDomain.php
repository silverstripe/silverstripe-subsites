<?php

class SubsiteDomain extends DataObject {

	private static $db = array(
		"Domain" => "Varchar(255)",
		"IsPrimary" => "Boolean",
	);

	private static $has_one = array(
 		"Subsite" => "Subsite",
	);

	private static $summary_fields=array(
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
			new TextField('Domain', _t('SubsiteDomain.DOMAIN', 'Domain'), null, 255),
			new CheckboxField('IsPrimary', _t('SubsiteDomain.IS_PRIMARY', 'Is Primary Domain'))
		);
	}
}
