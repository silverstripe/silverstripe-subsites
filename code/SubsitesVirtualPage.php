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
		
		$subsites = Subsite::getSubsitesForMember();
		if(!$subsites) $subsites = new DataObjectSet();
		if(Subsite::hasMainSitePermission()) {
			$subsites->push(new ArrayData(array('Title' => 'Main site', 'ID' => 0)));
		}

		if($subsites->Count()) {
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
		}
		
		// Setup the linking to the original page.
		$pageSelectionField = new SubsitesTreeDropdownField(
			"CopyContentFromID", 
			_t('VirtualPage.CHOOSE', "Choose a page to link to"), 
			"SiteTree",
			"ID",
			"MenuTitle"
		);
				
		$pageSelectionField->setFilterFunction(create_function('$item', 'return $item->ClassName != "VirtualPage";'));
		
		if(Controller::curr()->getRequest()) {
			$subsiteID = Controller::curr()->getRequest()->getVar('TreeDropdownField_Form_EditForm_CopyContentFromID_SubsiteID');
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

		return $fields;
	}
	
	function onBeforeWrite() {
		parent::onBeforeWrite();
	
		if($this->CustomMetaTitle) $this->MetaTitle = $this->CustomMetaTitle;
		if($this->CustomMetaKeywords) $this->MetaKeywords = $this->CustomMetaKeywords;
		if($this->CustomMetaDescription) $this->MetaDescription = $this->CustomMetaDescription;
		if($this->CustomExtraMeta) $this->ExtraMeta = $this->CustomExtraMeta;
	}
}

class SubsitesVirtualPage_Controller extends VirtualPage_Controller {
	
	function reloadContent() {
		$this->failover->nextWriteDoesntCustomise();
		$this->failover->copyFrom($this->failover->CopyContentFrom());
		$this->failover->nextWriteDoesntCustomise();
		$this->failover->write();
		return;
	}
	
	function init(){
		Subsite::$disable_subsite_filter = true;
		
		parent::init();
		
		Subsite::$disable_subsite_filter = false;
	}
}
?>
