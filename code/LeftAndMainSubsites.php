<?php
/**
 * Decorator designed to add subsites support to LeftAndMain
 * 
 * @package subsites
 */
class LeftAndMainSubsites extends Extension {
	static $allowed_actions = array(
		'changesubsite',
	);
	
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
		return $subsite ? $subsite->Title : null;
	}


	public function changesubsite() {
		$id = $_REQUEST['SubsiteID'];
		
		Subsite::changeSubsite($id);
		
		if ($id == '-1') Cookie::set('noSubsiteFilter', 'true', 1);
		else Cookie::set('noSubsiteFilter', 'false', 1);
		
		if(Director::is_ajax()) {
			$tree = $this->owner->SiteTreeAsUL();
			$tree = ereg_replace('^[ \t\r\n]*<ul[^>]*>','', $tree);
			$tree = ereg_replace('</ul[^>]*>[ \t\r\n]*$','', $tree);
			return $tree;
		} else
			return array();
	}
	
	public function Subsites() {
		$siteList = new DataObjectSet();
		$subsites = Subsite::accessible_sites('CMS_ACCESS_' . $this->owner->class);

		if(defined('DB::USE_ANSI_SQL')) 
			$q="\"";
		else $q='`';
		
		$mainSiteTitle = null;
		switch($this->owner->class) {
			case "AssetAdmin":
				$mainSiteTitle = "Shared files & images"; break;
			case "SecurityAdmin":
				$mainSiteTitle = "Groups accessing all sites";
				$siteList->push(new ArrayData(array('Title' => 'All groups', 'ID' => -1)));
				break;
			case "CMSMain":
				// If there's a default site then main site has no meaning
				if(!DataObject::get_one('Subsite', "{$q}DefaultSite{$q} = 1 AND {$q}IsPublic{$q} = 1")) {
					$mainSiteTitle = "Main site";
				}
				break;
			default: 
				$mainSiteTitle = "Main site";
				break;	
		}

		if($mainSiteTitle && Subsite::hasMainSitePermission(Member::currentUser(), array('CMS_ACCESS_' . $this->owner->class, 'ADMIN')))
			$siteList->push(new ArrayData(array('Title' => $mainSiteTitle, 'ID' => 0)));
		
		if($subsites)
			$siteList->merge($subsites);
			
		return $siteList;
	}
	
	public function SubsiteList() {
		$list = $this->Subsites();
		
		if(Controller::curr()->hasMethod('getRequest')) $requestSubsiteID = Controller::curr()->getRequest()->getVar('SubsiteID');
		else $requestSubsiteID = isset($_REQUEST['SubsiteID']) ? $_REQUEST['SubsiteID'] : null;
		
		$currentSubsiteID = ($requestSubsiteID) ? $requestSubsiteID : Session::get('SubsiteID');
		
		if($list->Count() > 1) {
			$output = '<select id="SubsitesSelect">';
		
			foreach($list as $subsite) {
				$selected = $subsite->ID == $currentSubsiteID ? ' selected="selected"' : '';
		
				$output .= "\n<option value=\"{$subsite->ID}\"$selected>{$subsite->Title}</option>";
			}
		
			$output .= '</select>';
		
			return $output;
		} else if($list->Count() == 1) {
			return $list->First()->Title;
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

		if($result = Permission::check("CMS_ACCESS_$className")) {
			return $result;
		} else {
			$otherSites = Subsite::accessible_sites("CMS_ACCESS_$className");
			if($otherSites && $otherSites->TotalItems() > 0) {
				$otherSites->First()->activate();
				return Permission::check("CMS_ACCESS_$className");
			}
		}
		
		return null;
	}
	
	function augmentNewSiteTreeItem(&$item) {
		$item->SubsiteID = Subsite::currentSubsiteID();	
	}
	
	function onAfterSave($record) {
		if($record->hasMethod('NormalRelated') && ($record->NormalRelated() || $record->ReverseRelated())) {
			FormResponse::status_message('Saved, please update related pages.', 'good');
		}
	}
}
	
	

?>
