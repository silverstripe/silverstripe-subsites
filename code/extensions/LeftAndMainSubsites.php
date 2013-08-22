<?php
/**
 * Decorator designed to add subsites support to LeftAndMain
 * 
 * @package subsites
 */
class LeftAndMainSubsites extends Extension {

	private static $allowed_actions = array('CopyToSubsite');

	function init() {

		//Use the session variable for current subsite in the CMS only
		Subsite::$use_session_subsiteid = true;

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
			return $this->owner->redirect('admin/');
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
	
	/*
	 * Returns a list of the subsites accessible to the current user
	 */
	public function Subsites() {
		// figure out what permission the controller needs
		// Subsite::accessible_sites() expects something, so if there's no permission
		// then fallback to using CMS_ACCESS_LeftAndMain.
		$permission = 'CMS_ACCESS_' . $this->owner->class;
		$available = Permission::get_codes(false);
		if(!isset($available[$permission])) {
			$permission = $this->owner->stat('required_permission_codes');
			if(!$permission) {
				$permission = 'CMS_ACCESS_LeftAndMain';
			}
		}

		return Subsite::accessible_sites($permission);
	}

	/*
	 * Generates a list of subsites with the data needed to 
	 * produce a dropdown site switcher
	 * @return ArrayList
	 */

	public function ListSubsites(){
		$list = $this->Subsites();
		$currentSubsiteID = Subsite::currentSubsiteID();

		if($list == null || $list->Count() == 1 && $list->First()->DefaultSite == true){
			return false;
		}

		Requirements::javascript('subsites/javascript/LeftAndMain_Subsites.js');

		$output = new ArrayList();

		foreach($list as $subsite) {
			$CurrentState = $subsite->ID == $currentSubsiteID ? 'selected' : '';
	
			$output->push(new ArrayData(array(
				'CurrentState' => $CurrentState,
				'ID' => $subsite->ID,
				'Title' => Convert::raw2xml($subsite->Title)
			)));
		}

		return $output;
	}

	/*
	 * Returns a subset of the main menu, filtered by admins that have 
	 * a subsiteCMSShowInMenu method returning true
	 *
	 * @return ArrayList
	 */
	public function SubsiteMainMenu(){
		if(Subsite::currentSubsiteID() == 0){
			return $this->owner->MainMenu();
		}
		// loop main menu items, add all items that have subsite support
		$mainMenu = $this->owner->MainMenu();
		$subsitesMenu = new ArrayList();

		foreach($mainMenu as $menuItem){

			$controllerName = $menuItem->MenuItem->controller;

			if(class_exists($controllerName)){
				$controller = singleton($controllerName);

				if($controller->hasMethod('subsiteCMSShowInMenu') && $controller->subsiteCMSShowInMenu()){
					$subsitesMenu->push($menuItem);
				}
			}

			if($menuItem->Code == 'Help'){
				$subsitesMenu->push($menuItem);
			}

		}
		return $subsitesMenu;
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
