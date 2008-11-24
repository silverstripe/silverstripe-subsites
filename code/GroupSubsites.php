<?php
/**
 * Extension for the Group object to add subsites support
 * 
 * @package subsites
 */
class GroupSubsites extends DataObjectDecorator {
	
	function extraDBFields() {
		// This is hard-coded to be applied to SiteTree, unfortunately
        if($this->owner->class == 'Group') {
			return array(
				'has_one' => array(
					'Subsite' => 'Subsite',
				),
			);
		}
	}
	
	function updateCMSFields(&$fields) {
		$subsites = DataObject::get('Subsite');
		$tab = $fields->findOrMakeTab(
			'Root.Subsites',
			_t('GroupSubsites.SECURITYTABTITLE', 'Subsites')
		);
		$tab->push(new DropdownField(
			'SubsiteID', 
			_t('GroupSubsites.SECURITYACCESS', 'Limit CMS access to subsites', PR_MEDIUM, 'Dropdown listing existing subsites which this group has access to'),
			($subsites) ? $subsites->toDropDownMap() : null,
			null,
			null,
			'(' . _t('GroupSubsites.SECURITYACCESS_ALL', 'all', PR_MEDIUM, 'Default for dropdown selection: Group has access to all existingsubsites') . ')'
		));

	}
	
    function alternateTreeTitle() {
        if($this->owner->SubsiteID == 0) {
			return $this->owner->Title;
		} else {
			return $this->owner->Title . ' <i>(' . $this->owner->Subsite()->Title . ')</i>';
		}
    }
    
	/**
	 * Update any requests to limit the results to the current site
	 */
	function augmentSQL(SQLQuery &$query) {
		if(Subsite::$disable_subsite_filter) return;

		// If you're querying by ID, ignore the sub-site - this is a bit ugly...
		if(!$query->where || (strpos($query->where[0], ".`ID` = ") === false && strpos($query->where[0], ".ID = ") === false)) {

			if($context = DataObject::context_obj()) $subsiteID = (int)$context->SubsiteID;
			else $subsiteID = (int)Subsite::currentSubsiteID();
			
			// The foreach is an ugly way of getting the first key :-)
			foreach($query->from as $tableName => $info) {
				$query->where[] = "`$tableName`.SubsiteID IN (0, $subsiteID)";
				break;
			}

            $query->orderby = 'SubsiteID' . ($query->orderby ? ', ' : '') . $query->orderby;
		}
	}
	
	function augmentBeforeWrite() {
		if(!is_numeric($this->owner->ID) && !$this->owner->SubsiteID) $this->owner->SubsiteID = Subsite::currentSubsiteID();
	}
	
	function alternateCanEdit() {
		// Check the CMS_ACCESS_SecurityAdmin privileges on the subsite that owns this group
		$oldSubsiteID = Session::get('SubsiteID');

		Session::set('SubsiteID', $this->owner->SubsiteID);
		$access = Permission::check('CMS_ACCESS_SecurityAdmin');
		Session::set('SubsiteID', $oldSubsiteID);
		
		return $access;
	}
}

?>