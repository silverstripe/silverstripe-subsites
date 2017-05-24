<?php

namespace SilverStripe\Subsites\Model;


use SilverStripe\Forms\TextField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;



/**
 * @property text Domain domain name of this subsite. Do not include the URL scheme here
 * @property bool IsPrimary Is this the primary subdomain?
 */
class SubsiteDomain extends DataObject {

    private static $table_name = 'SubsiteDomain';

    /**
	 *
	 * @var string
	 */
	private static $default_sort = "\"IsPrimary\" DESC";

	/**
	 *
	 * @var array
	 */
	private static $db = array(
		"Domain" => "Varchar(255)",
		"IsPrimary" => "Boolean",
	);

	/**
	 *
	 * @var array
	 */
	private static $has_one = array(
 		"Subsite" => Subsite::class,
	);

	/**
	 *
	 * @var array
	 */
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

	/**
	 *
	 * @return \FieldList
	 */
	public function getCMSFields() {
		$fields = new FieldList(
			new TextField('Domain', $this->fieldLabel('Domain'), null, 255),
			new CheckboxField('IsPrimary', $this->fieldLabel('IsPrimary'))
		);

		$this->extend('updateCMSFields', $fields);
		return $fields;
	}

	/**
	 *
	 * @param bool $includerelations
	 * @return array
	 */
	public function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);
		$labels['Domain'] = _t('SubsiteDomain.DOMAIN', 'Domain');
		$labels['IsPrimary'] = _t('SubsiteDomain.IS_PRIMARY', 'Is Primary Domain');

		return $labels;
	}

	/**
	 * Before writing the Subsite Domain, strip out any HTML the user has entered.
	 * @return void
	 */
	public function onBeforeWrite() {
		parent::onBeforeWrite();

		//strip out any HTML to avoid XSS attacks
		$this->Domain = Convert::html2raw($this->Domain);
	}
}
