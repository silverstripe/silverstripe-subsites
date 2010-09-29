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
	
	function gettree(SS_HTTPRequest $request) {
		$oldSubsiteID = Session::get('SubsiteID');
		Session::set('SubsiteID', $this->subsiteID);
		
		$results = parent::tree($request);
		
		Session::set('SubsiteID', $oldSubsiteID);
		
		return $results;
	}
	
	public function getsubtree(SS_HTTPRequest $request) {
		$oldSubsiteID = Session::get('SubsiteID');
		Session::set('SubsiteID', $this->subsiteID);

		$obj = $this->objectForKey($_REQUEST['SubtreeRootID']);

		if(!$obj) user_error("Can't find database record $this->sourceObject with $this->keyField = $_REQUEST[SubtreeRootID]", E_USER_ERROR);

		if($this->filterFunc) $obj->setMarkingFilterFunction($this->filterFunc);
		else if($this->sourceObject == 'Folder') $obj->setMarkingFilter('ClassName', 'Folder');
		$obj->markPartialTree();

		$eval = '"<li id=\"selector-' . $this->name . '-$child->' . $this->keyField .  '\" class=\"$child->class" . $child->markingClasses() . "\"><a>" . $child->' . $this->labelField . ' . "</a>"';
		$tree = $obj->getChildrenAsUL("", $eval, null, true);
		echo substr(trim($tree), 4,-5);

		Session::set('SubsiteID', $oldSubsiteID);
	}

}