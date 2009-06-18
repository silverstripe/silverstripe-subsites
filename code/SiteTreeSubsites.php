<?php

/**
 * Extension for the SiteTree object to add subsites support
 */
class SiteTreeSubsites extends DataObjectDecorator {
	static $template_variables = array(
		'((Company Name))' => 'Title'
	);
	
	static $template_fields = array(
		"URLSegment",
		"Title",
		"MenuTitle",
		"Content",
		"MetaTitle",
		"MetaDescription",
		"MetaKeywords",
	);

	/**
	 * Set the fields that will be copied from the template.
	 * Note that ParentID and Sort are implied.
	 */
	static function set_template_fields($fieldList) {
		self::$template_fields = $fieldList;
	}

	
	function extraStatics() {
		// This is hard-coded to be applied to SiteTree, unfortunately
		if($this->owner->class == 'SiteTree') {
			return array(
				'has_one' => array(
					'Subsite' => 'Subsite', // The subsite that this page belongs to
					'MasterPage' => 'SiteTree', // Optional; the page that is the content master
				),
			);
		}
	}
	
	/**
	 * Update any requests to limit the results to the current site
	 */
	function augmentSQL(SQLQuery &$query) {
		if(Subsite::$disable_subsite_filter) return;
		
		// If you're querying by ID, ignore the sub-site - this is a bit ugly...
		if(!$query->where || (strpos($query->where[0], ".\"ID\" = ") === false && strpos($query->where[0], ".`ID` = ") === false && strpos($query->where[0], ".ID = ") === false && strpos($query->where[0], "ID = ") !== 0)) {

			if($context = DataObject::context_obj()) $subsiteID = (int)$context->SubsiteID;
			else $subsiteID = (int)Subsite::currentSubsiteID();
			
			// The foreach is an ugly way of getting the first key :-)
			foreach($query->from as $tableName => $info) {
				// The tableName should be SiteTree or SiteTree_Live...
				if(strpos($tableName,'SiteTree') === false) break;
				$query->where[] = "`$tableName`.SubsiteID IN ($subsiteID)";
				break;
			}
		}
	}
	
	/**
	 * Call this method before writing; the next write carried out by the system won't
	 * set the CustomContent value
	 */
	function nextWriteDoesntCustomise() {
		$this->nextWriteDoesntCustomise = true;
	}
	
	protected $nextWriteDoesntCustomise = false;
	
	function augmentBeforeWrite() {
		if(!is_numeric($this->owner->ID) && !$this->owner->SubsiteID) $this->owner->SubsiteID = Subsite::currentSubsiteID();

		// If the content has been changed, then the page should be marked as 'custom content'
		if(!$this->nextWriteDoesntCustomise && $this->owner->ID && $this->owner->MasterPageID && !$this->owner->CustomContent) {
			$changed = $this->owner->getChangedFields();

			foreach(self::$template_fields as $field) {
				if(isset($changed[$field]) && $changed[$field]) {
					$this->owner->CustomContent = true;
					FormResponse::add("if($('Form_EditForm_CustomContent')) $('Form_EditForm_CustomContent').checked = true;");
					break;
				}
			}
		}
		
		$this->nextWriteDoesntCustomise = false;
	}

	function updateCMSFields(&$fields) {
		if($this->owner->MasterPageID) {
			$fields->insertFirst(new HeaderField('This page\'s content is copied from a master page: ' . $this->owner->MasterPage()->Title, 2));
		}
		
		// replace readonly link prefix
		$subsite = $this->owner->Subsite();
		if($subsite && $subsite->ID) {
			$baseUrl = 'http://' . $subsite->domain() . '/';
			$fields->removeByName('BaseUrlLabel');
			$fields->addFieldToTab(
				'Root.Content.Metadata',
				new LabelField('BaseUrlLabel',$baseUrl),
				'URLSegment'
			);
		}
	}
	
	/**
	 * Only allow editing of a page if the member satisfies one of the following conditions:
	 * - Is in a group which has access to the subsite this page belongs to
	 * - Is in a group with edit permissions on the "main site"
	 * 
	 * @return boolean
	 */
	function canEdit($member = null) {
		if(!$member)  $member = Member::currentUser();
		
		// Check the CMS_ACCESS_CMSMain privileges on the subsite that owns this group
		$oldSubsiteID = Session::get('SubsiteID');

		Subsite::changeSubsite($this->owner->SubsiteID) ;
		$access = Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
		Subsite::changeSubsite($oldSubsiteID);
		
		if(!$access) $access = Permission::checkMember($member, 'SUBSITE_ACCESS_ALL');
		
		return $access;
	}
	
	/**
	 * @return boolean
	 */
	function canDelete($member = null) {
		if(!$member && $member !== FALSE) $member = Member::currentUser();
		
		return $this->canEdit($member);
	}
	
	/**
	 * @return boolean
	 */
	function canAddChildren($member = null) {
		if(!$member && $member !== FALSE) $member = Member::currentUser();
		
		return $this->canEdit($member);
	}
	
	/**
	 * @return boolean
	 */
	function canPublish($member = null) {
		if(!$member && $member !== FALSE) $member = Member::currentUser();

		return $this->canEdit($member);
	}

	/**
	 * Create a duplicate of this page and save it to another subsite
	 * @param $subsiteID int|Subsite The Subsite to copy to, or its ID
	 * @param $isTemplate boolean If this is true, then the current page will be treated as the template, and MasterPageID will be set
	 */
	public function duplicateToSubsite($subsiteID = null, $isTemplate = true) {
		if(is_object($subsiteID)) {
			$subsite = $subsiteID;
			$subsiteID = $subsite->ID;
		} else {
			$subsite = DataObject::get_by_id('Subsite', $subsiteID);
		}
		
		$page = $this->owner->duplicate(false);

		$page->CheckedPublicationDifferences = $page->AddedToStage = true;
		$subsiteID = ($subsiteID ? $subsiteID : Subsite::currentSubsiteID());
		$page->SubsiteID = $subsiteID;
		
		if($isTemplate) $page->MasterPageID = $this->owner->ID;
		
		$page->write();

		return $page;
	}

	/**
	 * Called by ContentController::init();
	 */
	static function contentcontrollerInit($controller) {
		// Need to set the SubsiteID to null incase we've been in the CMS
		Session::set('SubsiteID', null);
		$subsite = Subsite::currentSubsite();
		if($subsite && $subsite->Theme) SSViewer::set_theme(Subsite::currentSubsite()->Theme);
	}
	
	/**
	 * Called by ModelAsController::init();
	 */
	static function modelascontrollerInit($controller) {
		// Need to set the SubsiteID to null incase we've been in the CMS
		Session::set('SubsiteID', null);
	}
	
	function alternateAbsoluteLink() {
		// Generate the existing absolute URL and replace the domain with the subsite domain.
		// This helps deal with Link() returning an absolute URL.
		$url = Director::absoluteURL($this->owner->Link());
		return preg_replace('/\/\/[^\/]+\//', '//' .  $this->owner->Subsite()->domain() . '/', $url);
	}
}

?>
