<?php

/**
 * Extension for the Group object to add subsites support
 */
class GroupSubsites extends DataObjectDecorator {
	
	function extraDBFields() {
		// This is hard-coded to be applied to SiteTree, unfortunately
		if($this->class == 'SiteTree') {
			return array(
				'has_one' => array(
					'Subsite' => 'Subsite',
				),
			);
		}
	}
	
	
	/**
	 * Update any requests to limit the results to the current site
	 */
	function augmentSQL(SQLQuery &$query) {
		return;
		// The foreach is an ugly way of getting the first key :-)
		foreach($query->from as $tableName => $info) {
			$query->where[] = "`$tableName`.SubsiteID = " . Subsite::currentSubsiteID();
			break;
		}
		
	}
	
	function augmentBeforeWrite() {
		if(!is_numeric($this->owner->ID)) $this->owner->SubsiteID = Subsite::currentSubsiteID();
	}
}

?>