<?php
/**
 * Decorator designed to add subsites support to LeftAndMain
 * 
 * @package subsites
 */
class LeftAndMainSubsites extends Extension {

	static $allowed_actions = array('CopyToSubsite');

	function init() {
		Requirements::css('subsites/css/LeftAndMain_Subsites.css');
		Requirements::javascript('subsites/javascript/LeftAndMain_Subsites.js');
		Requirements::javascript('subsites/javascript/VirtualPage_Subsites.js');
		
		if(isset($_GET['SubsiteID'])) {
			// Clear current page when subsite changes (or is set for the first time)
			if(!Session::get('SubsiteID') || $_GET['SubsiteID'] != Session::get('SubsiteID')) {
				Session::clear("{$this->owner->class}.currentPage");
			}
			
			// Update current subsite in session
			Subsite::changeSubsite($_GET['SubsiteID']);
			
			//Redirect to clear the current page
			return $this->owner->redirect('admin/pages');
		}

		// Set subsite ID based on currently shown record
		$req = $this->owner->getRequest();
		$id = $req->param('ID');
		if($id && is_numeric($id)) {
			$record = DataObject::get_by_id($this->owner->stat('tree_class'), $id);
			if($record) Session::set('SubsiteID', $record->SubsiteID);
		}
	}

	/**
	 * Set the title of the CMS tree
	 */
	function getCMSTreeTitle() {
		$subsite = Subsite::currentSubSite();
		return $subsite ? Convert::raw2xml($subsite->Title) : _t('LeftAndMain.SITECONTENTLEFT');
	}
	
	function updatePageOptions(&$fields) {
		$fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
	}
	
	public function Subsites() {
		$accessPerm = 'CMS_ACCESS_'. $this->owner->class;
		switch($this->owner->class) {
			case "AssetAdmin":
				$subsites = Subsite::accessible_sites($accessPerm, true, "Shared files & images");
				break;
				
			case "SecurityAdmin":
				$subsites = Subsite::accessible_sites($accessPerm, true, "Groups accessing all sites");
				if($subsites->find('ID',0)) {
					$subsites->push(new ArrayData(array('Title' => 'All groups', 'ID' => -1)));
				}
				break;
				
			case "CMSMain":
				// If there's a default site then main site has no meaning
				$showMainSite = !DataObject::get_one('Subsite',"\"DefaultSite\"=1");
				$subsites = Subsite::accessible_sites($accessPerm, $showMainSite);
				break;
				
			case "SubsiteAdmin":
				$subsites = Subsite::accessible_sites('ADMIN', true);
				break;

			default: 
				$subsites = Subsite::accessible_sites($accessPerm);
				break;	
		}

		return $subsites;
	}
	
	public function SubsiteList() {
		$list = $this->Subsites();
		
		$currentSubsiteID = Subsite::currentSubsiteID();
		
		if($list->Count() > 1) {
			$output = '<select id="SubsitesSelect">';
		
			foreach($list as $subsite) {
				$selected = $subsite->ID == $currentSubsiteID ? ' selected="selected"' : '';
		
				$output .= "\n<option value=\"{$subsite->ID}\"$selected>". Convert::raw2xml($subsite->Title) . "</option>";
			}
		
			$output .= '</select>';
		
			Requirements::javascript('subsites/javascript/LeftAndMain_Subsites.js');
			return $output;
		} else if($list->Count() == 1) {
			if($list->First()->DefaultSite==false) {
				$output = '<select id="SubsitesSelect">';
				$output .= "\n<option value=\"0\">". _t('LeftAndMainSubsites.DEFAULT_SITE', '_Default Site') . "</option>";
				foreach($list as $subsite) {
					$selected = $subsite->ID == $currentSubsiteID ? ' selected="selected"' : '';
			
					$output .= "\n<option value=\"{$subsite->ID}\"$selected>". Convert::raw2xml($subsite->Title) . "</option>";
		}
			
				$output .= '</select>';
			
				Requirements::javascript('subsites/javascript/LeftAndMain_Subsites.js');
				return $output;
			}else {
				return '<span>'.$list->First()->Title.'</span>';
	}
		}
	}
	
	public function CanAddSubsites() {
		return Permission::check("ADMIN", "any", null, "all");
	}

	/**
	 * Alternative security checker for LeftAndMain.
	 * If security isn't found, then it will switch to a subsite where we do have access.
	 */
	public function alternateAccessCheck() {
		$className = $this->owner->class;

		// Switch to the subsite of the current page
		if ($this->owner->class == 'CMSMain' && $currentPage = $this->owner->currentPage()) {
			if (Subsite::currentSubsiteID() != $currentPage->SubsiteID) {
				Subsite::changeSubsite($currentPage->SubsiteID);
			}
		}
		
		// Switch to a subsite that this user can actually access.
		$member = Member::currentUser();
		if($member && Permission::checkMember($member, 'ADMIN')) return true; // admin can access all subsites
				
		$sites = Subsite::accessible_sites("CMS_ACCESS_{$this->owner->class}", true)->map('ID', 'Title');
		if(is_object($sites)) $sites = $sites->toArray();

		if($sites && !isset($sites[Subsite::currentSubsiteID()])) {
			$siteIDs = array_keys($sites);
			Subsite::changeSubsite($siteIDs[0]);
			return true;
		}
		
		// Switch to a different top-level menu item
		$menu = CMSMenu::get_menu_items();
		foreach($menu as $candidate) {
			if($candidate->controller != $this->owner->class) {
				$sites = Subsite::accessible_sites("CMS_ACCESS_{$candidate->controller}", true)->map('ID', 'Title');
				if(is_object($sites)) $sites = $sites->toArray();
					
				if($sites && !isset($sites[Subsite::currentSubsiteID()])) {
					$siteIDs = array_keys($sites);
					Subsite::changeSubsite($siteIDs[0]);
					$cClass = $candidate->controller;
					$cObj = new $cClass();
					$this->owner->redirect($cObj->Link());
					return null;
				}
			}
		}
		
		// If all of those fail, you really don't have access to the CMS		
		return null;
	}
	
	function augmentNewSiteTreeItem(&$item) {
		$item->SubsiteID = isset($_POST['SubsiteID']) ? $_POST['SubsiteID'] : Subsite::currentSubsiteID();	
	}
	
	function onAfterSave($record) {
		if($record->hasMethod('NormalRelated') && ($record->NormalRelated() || $record->ReverseRelated())) {
			$this->owner->response->addHeader('X-Status', rawurlencode(_t('LeftAndMainSubsites.Saved', 'Saved, please update related pages.')));
		}
	}

	function copytosubsite($data, $form) {
		$page = DataObject::get_by_id('SiteTree', $data['ID']);
		$subsite = DataObject::get_by_id('Subsite', $data['CopyToSubsiteID']);
		$newPage = $page->duplicateToSubsite($subsite->ID, true);
		$response = $this->owner->getResponse();
		$response->addHeader('X-Reload', true);
		return $this->owner->redirect(Controller::join_links($this->owner->Link('show'), $newPage->ID));
	}

}