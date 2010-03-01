<?php

/**
 * Creates a subsite-aware version of another report.
 * Pass another report (or its classname) into the constructor.
 */
class SubsiteReportWrapper extends SSReport {
	protected $baseReport;
	
	function __construct($baseReport) {
		$this->baseReport = is_string($baseReport) ? new $baseReport : $baseReport;
		$this->dataClass = $this->baseReport->dataClass();
		parent::__construct();
	}
	
	function ID() {
		return get_class($this->baseReport) . '_subsite';
	}

	///////////////////////////////////////////////////////////////////////////////////////////
	// Filtering
	
	function parameterFields() {
		$subsites = Subsite::accessible_sites('CMS_ACCESS_CMSMain');
		$options = $subsites->toDropdownMap('ID', 'Title');
		$subsiteField = new TreeMultiselectField('Subsites', 'Sites', $options);

		$fields = $this->baseReport->parameterFields();
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
		$columns = $this->baseReport->columns();
		$columns['Subsite.Title'] = "Subsite";
		return $columns;
	}
	
	///////////////////////////////////////////////////////////////////////////////////////////
	// Querying
	
	function beforeQuery($params) {
		if(!empty($params['Subsites'])) {
			// 'any' wasn't selected
			$subsiteIds = array();
			foreach(explode(',', $params['Subsites']) as $subsite) {
				if(is_numeric(trim($subsite))) $subsiteIds[] = trim($subsite);
			}
			Subsite::$force_subsite = join(',', $subsiteIds);
		}
	}
	function afterQuery() {
		// Manually manage the subsite filtering
		Subsite::$force_subsite = null;
	}
	
	function sourceQuery($params) {
		if($this->baseReport->hasMethod('sourceRecords')) {
			// The default implementation will create a fake query from our sourceRecords() method
			return parent::sourceQuery($params);

		} else if($this->baseReport->hasMethod('sourceQuery')) {
			$this->beforeQuery($params);
			$query = $this->baseReport->sourceQuery($params);
			$this->afterQuery();
			return $query;
			
		} else {
			user_error("Please override sourceQuery()/sourceRecords() and columns() in your base report", E_USER_ERROR);
		}

	}
	
	function sourceRecords($params, $sort, $limit) {
		$this->beforeQuery($params);
		$records = $this->baseReport->sourceRecords($params, $sort, $limit);
		$this->afterQuery();
		return $records;
	}


	///////////////////////////////////////////////////////////////////////////////////////////
	// Pass-through
		
	function title() {
		return $this->baseReport->title();
	}

	function description() {
		return $this->baseReport->title();
	}
	
	function canView() {
		return $this->baseReport->canView();
	}
	
}