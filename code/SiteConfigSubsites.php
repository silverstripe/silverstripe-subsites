<?php

/**
 * Extension for the SiteConfig object to add subsites support
 */
class SiteConfigSubsites extends DataObjectDecorator {		
	function extraStatics() {
		return array(
			'has_one' => array(
				'Subsite' => 'Subsite', // The subsite that this page belongs to
			)
		);
	}
	
	/**
	 * Update any requests to limit the results to the current site
	 */
	function augmentSQL(SQLQuery &$query) {
		if(Subsite::$disable_subsite_filter) return;
		
		// If you're querying by ID, ignore the sub-site - this is a bit ugly...
		if (!$query->where || (!preg_match('/\.(\'|"|`|)ID(\'|"|`|)( ?)=/', $query->where[0]))) {

			if($context = DataObject::context_obj()) $subsiteID = (int)$context->SubsiteID;
			else $subsiteID = (int)Subsite::currentSubsiteID();
			
			$tableName = array_shift(array_keys($query->from));
			if($tableName != 'SiteConfig') return;
			$query->where[] = "`$tableName`.SubsiteID IN ($subsiteID)";
		}
	}

	function augmentBeforeWrite() {
		if((!is_numeric($this->owner->ID) || !$this->owner->ID) && !$this->owner->SubsiteID) {
			$this->owner->SubsiteID = Subsite::currentSubsiteID();
		}
	}
}
