<?php
/**
 * Extension for the File object to add subsites support
 *
 * @package subsites
 */
class FileSubsites extends DataExtension {
	
	// If this is set to true, all folders created will be default be
	// considered 'global', unless set otherwise
	static $default_root_folders_global = false;
	
	private static $has_one=array(
		'Subsite' => 'Subsite',
	);

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
	function updateCMSFields(FieldList $fields) {
		if($this->owner instanceof Folder) {
			$sites = Subsite::accessible_sites('CMS_ACCESS_AssetAdmin');
			$values = array();
			$values[0] = _t('FileSubsites.AllSitesDropdownOpt','All sites');
			foreach ($sites as $site) {
				$values[$site->ID] = $site->Title;
			}
			ksort($values);
			if($sites){
				//Dropdown needed to move folders between subsites
				$dropdown = new DropdownField(
					'SubsiteID', 
					_t('FileSubsites.SubsiteFieldLabel','Subsite'), 
					$values
				);
				$dropdown->addExtraClass('subsites-move-dropdown');
				$fields->push($dropdown);
				$fields->push(new LiteralField(
					'Message', 
					'<p class="message notice">'.
					_t('ASSETADMIN.SUBSITENOTICE', 'Folders and files created in the main site are accessible by all subsites.')
					.'</p>'
				));
			}
		}
	}

	/**
	 * Update any requests to limit the results to the current site
	 */
	function augmentSQL(SQLQuery &$query) {
		if(Subsite::$disable_subsite_filter) return;

		// If you're querying by ID, ignore the sub-site - this is a bit ugly... (but it was WAYYYYYYYYY worse)
		//@TODO I don't think excluding if SiteTree_ImageTracking is a good idea however because of the SS 3.0 api and ManyManyList::removeAll() changing the from table after this function is called there isn't much of a choice

		$from = $query->getFrom();
		$where = $query->getWhere();

		if(!isset($from['SiteTree_ImageTracking']) && !($where && preg_match('/\.(\'|"|`|)ID(\'|"|`|)/', $where[0]))) {
			$subsiteID = (int) Subsite::currentSubsiteID();

			// The foreach is an ugly way of getting the first key :-)
			foreach($query->getFrom() as $tableName => $info) {
				$where = "\"$tableName\".\"SubsiteID\" IN (0, $subsiteID)";
				$query->addWhere($where);
				break;
			}
			
			$sect=array_values($query->getSelect());
			$isCounting = strpos($sect[0], 'COUNT') !== false;

			// Ordering when deleting or counting doesn't apply
			if(!$query->getDelete() && !$isCounting) {
				$query->addOrderBy("\"SubsiteID\"");
			}
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

	function canEdit($member = null) {
		// Check the CMS_ACCESS_SecurityAdmin privileges on the subsite that owns this group
		$subsiteID = Session::get('SubsiteID');
		if($subsiteID&&$subsiteID == $this->owner->SubsiteID) {
			return true;
		} else {
			Session::set('SubsiteID', $this->owner->SubsiteID);
			$access = Permission::check(array('CMS_ACCESS_AssetAdmin', 'CMS_ACCESS_LeftAndMain'));
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

