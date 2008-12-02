<?php
/**
 * Wraps around a TreedropdownField to add ability for temporary
 * switching of subsite sessions.
 * 
 * @package subsites
 */
class SubsitesTreeDropdownField extends TreeDropdownField {
	
	protected $subsiteID = 0;
	
	protected $extraClasses = array('SubsitesTreeDropdownField');
	
	function Field() {
		$html = parent::Field();
		
		Requirements::javascript('subsites/javascript/SubsitesTreeDropdownField.js');
		
		return $html;
	}
	
	function setSubsiteID($id) {
		$this->subsiteID = $id;
	}
	
	function getSubsiteID() {
		return $this->subsiteID;
	}
	
	function gettree() {
		$oldSubsiteID = Session::get('SubsiteID');
		Session::set('SubsiteID', $this->subsiteID);
		
		$results = parent::gettree();
		
		Session::set('SubsiteID', $oldSubsiteID);
		
		return $results;
	}
	
	function getsubtree() {
		$oldSubsiteID = Session::get('SubsiteID');
		Session::set('SubsiteID', $this->subsiteID);
		
		$results = parent::getsubtree();
		
		Session::set('SubsiteID', $oldSubsiteID);
		
		return $results;
	}
}
?>