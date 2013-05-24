<?php

/**
 * Extension for the SiteConfig object to add subsites support
 */
class SiteConfigSubsites extends DataExtension {

	private static $has_one = array(
		'Subsite' => 'Subsite', // The subsite that this page belongs to
	);
	
	/**
	 * Update any requests to limit the results to the current site
	 */
	function augmentSQL(SQLQuery &$query) {
		if(Subsite::$disable_subsite_filter) return;
		
		// If you're querying by ID, ignore the sub-site - this is a bit ugly...
		if (!$query->where || (!preg_match('/\.(\'|"|`|)ID(\'|"|`|)( ?)=/', $query->where[0]) && !preg_match('/\.?(\'|"|`|)SubsiteID(\'|"|`|)( ?)=/', $query->where[0]))) {
			/*if($context = DataObject::context_obj()) $subsiteID = (int)$context->SubsiteID;
			else */$subsiteID = (int)Subsite::currentSubsiteID();
			
			$froms=$query->getFrom();
			$froms=array_keys($froms);
			$tableName = array_shift($froms);
			if($tableName != 'SiteConfig') return;
			$query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
		}
	}

	function onBeforeWrite() {
		if((!is_numeric($this->owner->ID) || !$this->owner->ID) && !$this->owner->SubsiteID) $this->owner->SubsiteID = Subsite::currentSubsiteID();
	}

	/**
	 * Return a piece of text to keep DataObject cache keys appropriately specific
	 */
	function cacheKeyComponent() {
		return 'subsite-'.Subsite::currentSubsiteID();
	}

	function updateCMSFields(FieldList $fields) {
		$fields->push(new HiddenField('SubsiteID','SubsiteID', Subsite::currentSubsiteID()));
	}
}
