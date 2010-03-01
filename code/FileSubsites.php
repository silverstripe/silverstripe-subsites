<?php
/**
 * Extension for the File object to add subsites support
 *
 * @package subsites
 */
class FileSubsites extends DataObjectDecorator {
	
	// If this is set to true, all folders created will be default be
	// considered 'global', unless set otherwise
	static $default_root_folders_global = false;
	
	function extraStatics() {
		if(!method_exists('DataObjectDecorator', 'load_extra_statics')) {
			if($this->owner->class != 'File') return null;
		}
		return array(
			'has_one' => array(
				'Subsite' => 'Subsite',
			),
		);
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
			$dropdownValues = ($sites) ? $sites->toDropdownMap() : array();
			$dropdownValues[0] = 'All sites';
			ksort($dropdownValues);
			if($sites)$fields->addFieldToTab('Root.Details', new DropdownField("SubsiteID", "Subsite", $dropdownValues));
		}
	}

	/**
	 * Update any requests to limit the results to the current site
	 */
	function augmentSQL(SQLQuery &$query) {
		if(defined('DB::USE_ANSI_SQL')) 
			$q="\"";
		else $q='`';

		// If you're querying by ID, ignore the sub-site - this is a bit ugly... (but it was WAYYYYYYYYY worse)
		if(!$query->where || !preg_match('/\.(\'|"|`|)ID(\'|"|`|)/', $query->where[0])) {
			if($context = DataObject::context_obj()) $subsiteID = (int) $context->SubsiteID;
			else $subsiteID = (int) Subsite::currentSubsiteID();

			// The foreach is an ugly way of getting the first key :-)
			foreach($query->from as $tableName => $info) {
				$where = "{$q}$tableName{$q}.{$q}SubsiteID{$q} IN (0, $subsiteID)";
				$query->where[] = $where;
				break;
			}
			
            $query->orderby = "\"SubsiteID\"" . ($query->orderby ? ', ' : '') . $query->orderby;
		}
	}

	function onBeforeWrite() {
		if (!$this->owner->ID && !$this->owner->SubsiteID) {
			if (self::$default_root_folders_global) {
				$this->owner->SubsiteID = 0;
			} else {
				$this->owner->SubsiteID = Subsite::currentSubsiteID();
			}
		}
	}

	function onAfterUpload() {
		// If we have a parent, use it's subsite as our subsite
		if ($this->owner->Parent()) {
			$this->owner->SubsiteID = $this->owner->Parent()->SubsiteID;
		} else {
			$this->owner->SubsiteID = Subsite::currentSubsiteID();
		}
		$this->owner->write();
	}

	function canEdit() {
		// Check the CMS_ACCESS_SecurityAdmin privileges on the subsite that owns this group
		$subsiteID = Session::get('SubsiteID');
		if($subsiteID&&$subsiteID == $this->owner->SubsiteID) {
			return true;
		} else {
			Session::set('SubsiteID', $this->owner->SubsiteID);
			$access = Permission::check('CMS_ACCESS_AssetAdmin');
			Session::set('SubsiteID', $subsiteID);

			return $access;
		}
	}
	
	/**
	 * Return a piece of text to keep DataObject cache keys appropriately specific
	 */
	function cacheKeyComponent() {
		return 'subsite-'.Subsite::currentSubsiteID();
	}
	
}


