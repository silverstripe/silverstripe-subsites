<?php

/**
 * This DataObject only exists to provide a link between related pages.
 * Unfortunately, there is no way to provide a decent GUI otherwise.
 */
class RelatedPageLink extends DataObject {
	static $db = array(
	);
	
	static $has_one = array(
		'RelatedPage' => 'SiteTree',
		// Note: The *last* matching has_one relation to SiteTree is used as the link field for the
		// has_many (RelatedPages) on SiteTree.  This isn't obvious and the framework could be
		// extended in a future version to allow for explicit selection of a has_one relation to
		// bind a has_many to.
		'MasterPage' => 'SiteTree',
	);
	
	function getCMSFields() {
		$subsites = Subsite::accessible_sites("CMS_ACCESS_CMSMain");
		if(!$subsites) $subsites = new DataObjectSet();

		if(Subsite::hasMainSitePermission(null, array("CMS_ACCESS_CMSMain"))) {
			$subsites->push(new ArrayData(array('Title' => 'Main site', "\"ID\"" => 0)));
		}
	
		if($subsites->Count()) {
			$subsiteSelectionField = new DropdownField(
				"CopyContentFromID_SubsiteID", 
				"Subsite", 
				$subsites->toDropdownMap('ID', 'Title'),
				($this->CopyContentFromID) ? $this->CopyContentFrom()->SubsiteID : Session::get('SubsiteID')
			);
		}
		
		// Setup the linking to the original page.
		$pageSelectionField = new SubsitesTreeDropdownField(
			"RelatedPageID", 
			_t('VirtualPage.CHOOSE', "Choose a page to link to"), 
			"SiteTree",
			"ID",
			"MenuTitle"
		);
		
		if (isset($_GET['RelatedPageID_SubsiteID'])) $pageSelectionField->setSubsiteID($_GET['RelatedPageID_SubsiteID']);
				
		$pageSelectionField->setFilterFunction(create_function('$item', 'return $item->ClassName != "VirtualPage";'));
		
		if($subsites->Count()) $fields = new FieldSet($subsiteSelectionField, $pageSelectionField);
		else $fields = new FieldSet($pageSelectionField);
		
		return $fields;
	}
	
	function RelatedPageAdminLink($master = false) {
		$page = $master ? Dataobject::get_by_id("SiteTree", $this->MasterPageID) : Dataobject::get_by_id("SiteTree", $this->RelatedPageID);
		$otherPage = $master ? Dataobject::get_by_id("SiteTree", $this->RelatedPageID) : Dataobject::get_by_id("SiteTree", $this->MasterPageID);
		if(!$page || !$otherPage) return;
		
		// Use cmsEditlink only when moving between different pages in the same subsite.
		$classClause = ($page->SubsiteID == $otherPage->SubsiteID) ? ' class="cmsEditlink"' : '';
		return '<a href="admin/show/' . $page->ID . "\"$classClause>" . Convert::raw2xml($page->Title) . '</a>';
	}

	function AbsoluteLink($master = false) {
		$page = $master ? Dataobject::get_by_id("SiteTree", $this->MasterPageID) : Dataobject::get_by_id("SiteTree", $this->RelatedPageID);
		if(!$page) return;
		

		$url = $page->AbsoluteLink();
	}

	function canView($member = null) {
		return $this->MasterPage()->canView($member);
	}
	function canEdit($member = null) {
		return $this->MasterPage()->canView($member);
	}
	function canDelete($member = null) {
		return $this->MasterPage()->canDelete($member);
	}
}

?>
