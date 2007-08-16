<?php

/**
 * Decorator designed to add subsites support to LeftAndMain
 */
class LeftAndMainSubsites extends Extension {
	function augmentInit() {
		Requirements::css('subsites/css/LeftAndMain_Subsites.css');
		Requirements::javascript('subsites/javascript/LeftAndMain_Subsites.js');
	}
	
	/**
	 * Set the title of the CMS tree
	 */
	function getCMSTreeTitle() {
		$subsite = Subsite::currentSubSite();
		return $subsite ? $subsite->Title : 'Site Content';
	}


	public function changesubsite() {
		$id = $_REQUEST['ID'];
		
		Subsite::changeSubsite($id);
		
		if(Director::is_ajax()) {
			$tree = $this->owner->SiteTreeAsUL();
			$tree = ereg_replace('^[ \t\r\n]*<ul[^>]*>','', $tree);
			$tree = ereg_replace('</ul[^>]*>[ \t\r\n]*$','', $tree);
			return $tree;
		} else
			return array();
	}
	
	public function addsubsite() {
		$name = $_REQUEST['Name'];
		$newSubsite = Subsite::create($name);
	
		$subsites = $this->Subsites();
		
		if(Director::is_ajax()) {
			/*$output = "var option = null; for(var i = 0; i < \$('SubsitesSelect').size; i++) {\$('SubsitesSelect').remove(i);}\n";			

			if($subsites) {
				foreach($subsites as $subsite) {
					$output .= "option = document.createElement('option');\n option.title = '$subsite->Title';\n option.value = $subsite->ID;\$('SubsitesSelect').add(option);\n";
				}
			}
			
			return $output;*/
			
			return $this->SubsiteList();
		} else
			return array();
	}
	
	public function Subsites() {
		$subsites = Subsite::getSubsitesForMember(Member::currentUser(), array('CMS_ACCESS_CMSMain', 'ADMIN'));
		
		$siteList = new DataObjectSet();
		
		if(Subsite::hasMainSitePermission(Member::currentUser(), array('CMS_ACCESS_CMSMain', 'ADMIN')))
			$siteList->push(new ArrayData(array('Title' => 'Main site', 'ID' => 0)));
		
		if($subsites)
			$siteList->append($subsites);
			
		return $siteList;
	}
	
	public function SubsiteList() {
		$list = $this->Subsites();
		
		if($list->Count() > 1) {
			$output = '<select id="SubsitesSelect">';
		
			foreach($list as $subsite) {
				$selected = $subsite->ID == Session::get('SubsiteID') ? ' selected="selected"' : '';
		
				$output .= "\n<option value=\"{$subsite->ID}\"$selected>{$subsite->Title}</option>";
			}
		
			$output .= '</select>';
		
			return $output;
		} else {
			return $list->First()->Title;
		}
	}
	
	public function CanAddSubsites() {
		return Permission::check("ADMIN", "any", null, "all");
	}}

?>