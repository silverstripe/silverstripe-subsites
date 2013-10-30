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
		'Domain',
		'IsPrimary',
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
			new TextField('Domain', $this->fieldLabel('Domain'), null, 255),
			new CheckboxField('IsPrimary', $this->fieldLabel('IsPrimary'))
		);
	}

	public function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);
		$labels['Domain'] = _t('SubsiteDomain.DOMAIN', 'Domain');
		$labels['IsPrimary'] = _t('SubsiteDomain.IS_PRIMARY', 'Is Primary Domain');

		return $labels;
	}
}
