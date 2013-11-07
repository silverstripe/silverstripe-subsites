<?php
/**
 * Decorator designed to add subsites support to LeftAndMain
 *
 * @package subsites
 */
class LeftAndMainSubsites extends Extension {

	private static $allowed_actions = array('CopyToSubsite');

	/**
	 * Normally SubsiteID=0 on a DataObject means it is only accessible from the special "main site".
	 * However in some situations SubsiteID=0 will be understood as a "globally accessible" object in which
	 * case this property is set to true (i.e. in AssetAdmin).
	 */
	private static $treats_subsite_0_as_global = false;

	function init() {
		Requirements::css('subsites/css/LeftAndMain_Subsites.css');
		Requirements::javascript('subsites/javascript/LeftAndMain_Subsites.js');
		Requirements::javascript('subsites/javascript/VirtualPage_Subsites.js');
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

	/**
	 * Find all subsites accessible for current user on this controller.
	 *
	 * @return ArrayList of {@link Subsite} instances.
	 */
	function sectionSites($includeMainSite = true, $mainSiteTitle = "Main site", $member = null) {
		if($mainSiteTitle == 'Main site') {
			$mainSiteTitle = _t('Subsites.MainSiteTitle', 'Main site');
		}

		// Rationalise member arguments
		if(!$member) $member = Member::currentUser();
		if(!$member) return new ArrayList();
		if(!is_object($member)) $member = DataObject::get_by_id('Member', $member);

		// Collect permissions - honour the LeftAndMain::required_permission_codes, current model requires
		// us to check if the user satisfies ALL permissions. Code partly copied from LeftAndMain::canView.
		$codes = array();
		$extraCodes = Config::inst()->get($this->owner->class, 'required_permission_codes');
		if($extraCodes !== false) {
			if($extraCodes) $codes = array_merge($codes, (array)$extraCodes);
			else $codes[] = "CMS_ACCESS_{$this->owner->class}";
		} else {
			// Check overriden - all subsites accessible.
			return Subsite::all_sites();
		}

		// Find subsites satisfying all permissions for the Member.
		$codesPerSite = array();
		$sitesArray = array();
		foreach ($codes as $code) {
			$sites = Subsite::accessible_sites($code, $includeMainSite, $mainSiteTitle, $member);
			foreach ($sites as $site) {
				// Build the structure for checking how many codes match.
				$codesPerSite[$site->ID][$code] = true;

				// Retain Subsite objects for later.
				$sitesArray[$site->ID] = $site;
			}
		}

		// Find sites that satisfy all codes conjuncitvely.
		$accessibleSites = new ArrayList();
		foreach ($codesPerSite as $siteID => $siteCodes) {
			if (count($siteCodes)==count($codes)) {
				$accessibleSites->push($sitesArray[$siteID]);
			}
		}

		return $accessibleSites;
	}

	/*
	 * Returns a list of the subsites accessible to the current user.
	 * It's enough for any section to be accessible for the section to be included.
	 */
	public function Subsites() {
		return Subsite::all_accessible_sites();
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

	public function alternateMenuDisplayCheck($controllerName) {
		if(!class_exists($controllerName)){
			return false;
		}

		// Check subsite support.
		if(Subsite::currentSubsiteID() == 0){
			// Main site always supports everything.
			return true;
		} else {
			$controller = singleton($controllerName);
			if($controller->hasMethod('subsiteCMSShowInMenu') && $controller->subsiteCMSShowInMenu()){
				return true;
			}
		}

		// It's not necessary to check access permissions here. Framework calls canView on the controller,
		// which in turn uses the Permission API which is augmented by our GroupSubsites.

		return false;
	}

	public function CanAddSubsites() {
		return Permission::check("ADMIN", "any", null, "all");
	}

	/**
	 * Helper for testing if the subsite should be adjusted.
	 */
	public function shouldChangeSubsite($adminClass, $recordSubsiteID, $currentSubsiteID) {
		if (Config::inst()->get($adminClass, 'treats_subsite_0_as_global') && $recordSubsiteID==0) return false;
		if ($recordSubsiteID!=$currentSubsiteID) return true;
		return false;
	}

	/**
	 * Do some pre-flight checks if a subsite switch is needed.
	 * We redirect the user to something accessible if the current section/subsite is forbidden.
	 */
	public function onBeforeInit() {
		// We are accessing the CMS, so we need to let Subsites know we will be using the session.
		Subsite::$use_session_subsiteid = true;

		// Do not attempt to redirect for AJAX calls. The proper security checks are done on specific objects
		// (by Subsite augmentations of Member and Group). Also the only good time to change the subsite in the CMS
		// is upon initial load - otherwise we risk the internal state becoming mismatched with the interface.
		if ($this->owner->request->isAjax()) {
			return;
		}

		// FIRST, check if we need to change subsites due to the URL.

		// Automatically redirect the session to appropriate subsite when requesting a record.
		// This is needed to properly initialise the session in situations where someone opens the CMS via a link.
		$record = $this->owner->currentPage();
		if($record && isset($record->SubsiteID) && is_numeric($record->SubsiteID)) {

			if ($this->shouldChangeSubsite($this->owner->class, $record->SubsiteID, Subsite::currentSubsiteID())) {
				Subsite::changeSubsite($record->SubsiteID);
			}

		}

		// Catch forced subsite changes that need to cause CMS reloads.
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

		// SECOND, check if we need to change subsites due to lack of permissions.

		// If we can view current URL there is nothing to do.
		if ($this->owner->canView()) {
			return;
		}

		// Admin can access everything, no point in checking.
		$member = Member::currentUser();
		if($member && Permission::checkMember($member, 'ADMIN')) return;

		// Check if we have access to current section on the current subsite.
		$accessibleSites = $this->owner->sectionSites($member);
		if ($accessibleSites->count() && $accessibleSites->find('ID', Subsite::currentSubsiteID())) {
			// Current section can be accessed on the current site, all good.
			return;
		}

		// If the current section is not accessible, try at least to stick to the same subsite.
		$menu = CMSMenu::get_menu_items();
		foreach($menu as $candidate) {
			if($candidate->controller && $candidate->controller!=$this->owner->class) {

				$accessibleSites = singleton($candidate->controller)->sectionSites(true, 'Main site', $member);
				if ($accessibleSites->count() && $accessibleSites->find('ID', Subsite::currentSubsiteID())) {
					// Section is accessible, redirect there.
					$this->owner->redirect(singleton($candidate->controller)->Link());
					return;
				}
			}
		}

		// Finally, if no section is available, move to any other permitted subsite.
		foreach($menu as $candidate) {
			if($candidate->controller) {
				$accessibleSites = singleton($candidate->controller)->sectionSites(true, 'Main site', $member);
				if ($accessibleSites->count()) {
					Subsite::changeSubsite($accessibleSites->First()->ID);
					$this->owner->redirect(singleton($candidate->controller)->Link());
					return;
				}
			}
		}
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
