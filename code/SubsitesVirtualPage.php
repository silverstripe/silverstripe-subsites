<?php
class SubsitesVirtualPage extends VirtualPage {
	static $db = array(
		'CustomMetaTitle' => 'Varchar(255)',
		'CustomMetaKeywords' => 'Varchar(255)',
		'CustomMetaDescription' => 'Text',
		'CustomExtraMeta' => 'HTMLText'
	);
	
	function getCMSFields() {
		$fields = parent::getCMSFields();
		
		$subsites = DataObject::get('Subsite');
		if(!$subsites) $subsites = new DataObjectSet();
		$subsites->push(new ArrayData(array('Title' => 'Main site', 'ID' => 0)));

		$subsiteSelectionField = new DropdownField(
			"CopyContentFromID_SubsiteID", 
			"Subsite", 
			$subsites->toDropdownMap('ID', 'Title'),
			($this->CopyContentFromID) ? $this->CopyContentFrom()->SubsiteID : Session::get('SubsiteID')
		);
		$fields->addFieldToTab(
			'Root.Content.Main',
			$subsiteSelectionField,
			'CopyContentFromID'
		);
		
		// Setup the linking to the original page.
		$pageSelectionField = new SubsitesTreeDropdownField(
			"CopyContentFromID", 
			_t('VirtualPage.CHOOSE', "Choose a page to link to"), 
			"SiteTree",
			"ID",
			"MenuTitle"
		);
		
		if(Controller::has_curr() && Controller::curr()->getRequest()) {
			$subsiteID = Controller::curr()->getRequest()->getVar('CopyContentFromID_SubsiteID');
			$pageSelectionField->setSubsiteID($subsiteID);
		}
		$fields->replaceField('CopyContentFromID', $pageSelectionField);
		
		// Create links back to the original object in the CMS
		if($this->CopyContentFromID) {
			$editLink = "admin/show/$this->CopyContentFromID/?SubsiteID=" . $this->CopyContentFrom()->SubsiteID;
			$linkToContent = "
				<a class=\"cmsEditlink\" href=\"$editLink\">" . 
				_t('VirtualPage.EDITCONTENT', 'Click here to edit the content') . 
				"</a>";
			$fields->removeByName("VirtualPageContentLinkLabel");
			$fields->addFieldToTab(
				"Root.Content.Main", 
				$linkToContentLabelField = new LabelField('VirtualPageContentLinkLabel', $linkToContent),
				'Title'
			);
			$linkToContentLabelField->setAllowHTML(true);
		}
		
		$fields->addFieldToTab('Root.Content.Metadata', new TextField('CustomMetaTitle', 'Title (overrides inherited value from the source)'), 'MetaTitle');
		$fields->addFieldToTab('Root.Content.Metadata', new TextareaField('CustomMetaKeywords', 'Keywords (overrides inherited value from the source)'), 'MetaKeywords');
		$fields->addFieldToTab('Root.Content.Metadata', new TextareaField('CustomMetaDescription', 'Description (overrides inherited value from the source)'), 'MetaDescription');
		$fields->addFieldToTab('Root.Content.Metadata', new TextField('CustomExtraMeta', 'Custom Meta Tags (overrides inherited value from the source)'), 'ExtraMeta');
		
		return $fields;
	}
	
	function getVirtualFields() {
		$fields = parent::getVirtualFields();
		foreach($fields as $k => $v) {
			if($v == 'SubsiteID') unset($fields[$k]);
		}
		
		foreach(self::$db as $field => $type) if (in_array($field, $fields)) unset($fields[array_search($field, $fields)]);

		return $fields;
	}
	
	function syncLinkTracking() {
		$oldState = Subsite::$disable_subsite_filter;
		Subsite::$disable_subsite_filter = true;
		if ($this->CopyContentFromID) $this->HasBrokenLink = DataObject::get_by_id('SiteTree', $this->CopyContentFromID) ? false : true;
		Subsite::$disable_subsite_filter = $oldState;
	}

	function onBeforeWrite() {
		parent::onBeforeWrite();
	
		if($this->CustomMetaTitle) $this->MetaTitle = $this->CustomMetaTitle;
		else {
			$this->MetaTitle = $this->ContentSource()->MetaTitle ? $this->ContentSource()->MetaTitle : $this->MetaTitle; 
		}
		if($this->CustomMetaKeywords) $this->MetaKeywords = $this->CustomMetaKeywords;
		else {
			$this->MetaKeywords = $this->ContentSource()->MetaKeywords ? $this->ContentSource()->MetaKeywords : $this->MetaKeywords; 
		}
		if($this->CustomMetaDescription) $this->MetaDescription = $this->CustomMetaDescription;
		else {
			$this->MetaDescription = $this->ContentSource()->MetaDescription ? $this->ContentSource()->MetaDescription : $this->MetaDescription; 
		}
		if($this->CustomExtraMeta) $this->ExtraMeta = $this->CustomExtraMeta;
		else {
			$this->ExtraMeta = $this->ContentSource()->ExtraMeta ? $this->ContentSource()->ExtraMeta : $this->ExtraMeta; 
		}
	}
	
	function validURLSegment() {
		$isValid = parent::validURLSegment();
		
		// Veto the validation rules if its false. In this case, some logic
		// needs to be duplicated from parent to find out the exact reason the validation failed.
		if(!$isValid) {
			$IDFilter     = ($this->ID) ? "AND \"SiteTree\".\"ID\" <> $this->ID" :  null;
			$parentFilter = null;

			if(self::nested_urls()) {
				if($this->ParentID) {
					$parentFilter = " AND \"SiteTree\".\"ParentID\" = $this->ParentID";
				} else {
					$parentFilter = ' AND "SiteTree"."ParentID" = 0';
				}
			}
			
			$origDisableSubsiteFilter = Subsite::$disable_subsite_filter;
			Subsite::$disable_subsite_filter = true;
			$existingPage = DataObject::get_one(
				'SiteTree', 
				"\"URLSegment\" = '$this->URLSegment' $IDFilter $parentFilter",
				false // disable cache, it doesn't include subsite status in the key
			);
			Subsite::$disable_subsite_filter = $origDisableSubsiteFilter;
			$existingPageInSubsite = DataObject::get_one(
				'SiteTree', 
				"\"URLSegment\" = '$this->URLSegment' $IDFilter $parentFilter",
				false // disable cache, it doesn't include subsite status in the key
			);

			// If URL has been vetoed because of an existing page,
			// be more specific and allow same URLSegments in different subsites
			$isValid = !($existingPage && $existingPageInSubsite);
		}
		
		return $isValid;
	}
}

class SubsitesVirtualPage_Controller extends VirtualPage_Controller {
	
	function reloadContent() {
		$this->failover->copyFrom($this->failover->CopyContentFrom());
		$this->failover->write();
		return;
	}
	
	function init(){
		$origDisableSubsiteFilter = Subsite::$disable_subsite_filter;
		Subsite::$disable_subsite_filter = true;
		
		parent::init();
		
		Subsite::$disable_subsite_filter = $origDisableSubsiteFilter;
	}
}
?>
