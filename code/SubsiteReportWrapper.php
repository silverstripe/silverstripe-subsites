<?php

/**
 * Creates a subsite-aware version of another report.
 * Pass another report (or its classname) into the constructor.
 */
class SubsiteReportWrapper extends SS_ReportWrapper {
	///////////////////////////////////////////////////////////////////////////////////////////
	// Filtering
	
	function parameterFields() {
		$subsites = Subsite::accessible_sites('CMS_ACCESS_CMSMain', true);
		$options = $subsites->toDropdownMap('ID', 'Title');
		
		$subsiteField = new TreeMultiselectField('Subsites', 'Sites', $options);
		$subsiteField->setValue(array_keys($options));

		// We don't need to make the field editable if only one subsite is available
		if(sizeof($options) <= 1) {
			$subsiteField = $subsiteField->performReadonlyTransformation();
		}
		
		$fields = parent::parameterFields();
		if($fields) {
			$fields->insertBefore($subsiteField, $fields->First()->Name());
		} else {
			$fields = new FieldSet($subsiteField);
		}
		return $fields;
	}

	///////////////////////////////////////////////////////////////////////////////////////////
	// Columns
	
	function columns() {
		$columns = parent::columns();
		$columns['Subsite.Title'] = "Subsite";
		return $columns;
	}
	
	///////////////////////////////////////////////////////////////////////////////////////////
	// Querying
	
	function beforeQuery($params) {
		// The user has select a few specific sites
		if(!empty($params['Subsites'])) {
			Subsite::$force_subsite = $params['Subsites'];
			
		// Default: restrict to all accessible sites
		} else {
			$subsites = Subsite::accessible_sites('CMS_ACCESS_CMSMain');
			$options = $subsites->toDropdownMap('ID', 'Title');
			Subsite::$force_subsite = join(',', array_keys($options));
		}
	}
	function afterQuery() {
		// Manually manage the subsite filtering
		Subsite::$force_subsite = null;
	}
	
}