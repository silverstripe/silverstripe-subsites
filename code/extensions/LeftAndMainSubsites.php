<?php
/**
 * Decorator designed to add subsites support to LeftAndMain
 * 
 * @package subsites
 */
class LeftAndMainSubsites extends Extension {

	function init() {
		Requirements::css('subsites/css/LeftAndMain_Subsites.css');
		Requirements::javascript('subsites/javascript/LeftAndMain_Subsites.js');
		Requirements::javascript('subsites/javascript/VirtualPage_Subsites.js');
		
		if(isset($_REQUEST['SubsiteID'])) {
			// Clear current page when subsite changes (or is set for the first time)
			if(!Session::get('SubsiteID') || $_REQUEST['SubsiteID'] != Session::get('SubsiteID')) {
				Session::clear("{$this->owner->class}.currentPage");
			}
			
			// Update current subsite in session
			Subsite::changeSubsite($_REQUEST['SubsiteID']);
			
			//Redirect to clear the current page
			$this->owner->redirect('admin/pages');
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
		return Subsite::accessible_sites('ADMIN');
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
		if ($member && Permission::check('ADMIN')) {
			return true;	//admin can access all subsites
		}
		
		$sites = Subsite::accessible_sites("CMS_ACCESS_{$this->owner->class}")->map('ID', 'Title')->toArray();
		if($sites && !isset($sites[Subsite::currentSubsiteID()])) {
			$siteIDs = array_keys($sites);
			Subsite::changeSubsite($siteIDs[0]);
			return true;
		}
		
		// Switch to a different top-level menu item
		$menu = CMSMenu::get_menu_items();
		foreach($menu as $candidate) {
			if($candidate->controller != $this->owner->class) {
					
				$sites = Subsite::accessible_sites("CMS_ACCESS_{$candidate->controller}")->map('ID', 'Title')->toArray();
				if($sites && !isset($sites[Subsite::currentSubsiteID()])) {
					$siteIDs = array_keys($sites);
					Subsite::changeSubsite($siteIDs[0]);
					$cClass = $candidate->controller;
					$cObj = new $cClass();
					Director::redirect($cObj->Link());
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
			FormResponse::status_message('Saved, please update related pages.', 'good');
		}
	}
}
	
	

?>
