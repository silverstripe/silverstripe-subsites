<?php
/**
 * Extension for the File object to add subsites support
 *
 * @package subsites
 */
class FileSubsites extends DataObjectDecorator {
	function extraStatics() {
		// This is hard-coded to be applied to SiteTree, unfortunately
        if($this->owner->class == 'File') {
			return array(
				'has_one' => array(
					'Subsite' => 'Subsite',
				),
			);
		}
	}

	/**
	 * Amends the CMS tree title for folders in the Files & Images section.
	 * Prefixes a '* ' to the folders that are accessible from all subsites.
	 */
	function alternateTreeTitle() {
		if($this->owner->SubsiteID == 0) return " * " . $this->owner->Title;
		else return $this->owner->Title;
	}

	/**
	 * Add subsites-specific fields to the folder editor.
	 */
	function updateCMSFields(FieldSet &$fields) {
		if($this->owner instanceof Folder) {
			$sites = Subsite::accessible_sites('CMS_ACCESS_AssetAdmin');
			if($sites)$fields->addFieldToTab('Root.Details', new DropdownField("SubsiteID", "Subsite", $sites->toDropdownMap('ID', 'Title', "(Public)")));
		}
	}

	/**
	 * Update any requests to limit the results to the current site
	 */
	function augmentSQL(SQLQuery &$query) {
		// If you're querying by ID, ignore the sub-site - this is a bit ugly...
		if(strpos($query->where[0], ".`ID` = ") === false && strpos($query->where[0], ".ID = ") === false) {

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
		if(!$this->owner->ID && !$this->owner->SubsiteID) $this->owner->SubsiteID = Subsite::currentSubsiteID();
	}

	function onAfterUpload() {
		$this->owner->SubsiteID = Subsite::currentSubsiteID();
		$this->owner->write();
	}

	function canEdit() {
		// Check the CMS_ACCESS_SecurityAdmin privileges on the subsite that owns this group
		$subsiteID = Session::get('SubsiteID');

		if($subsiteID&&$subsiteID == $this->owner->SubsiteID) return true;
		else {
			Session::set('SubsiteID', $this->owner->SubsiteID);
			$access = Permission::check('CMS_ACCESS_AssetAdmin');
			Session::set('SubsiteID', $subsiteID);

			return $access;
		}
	}
}

