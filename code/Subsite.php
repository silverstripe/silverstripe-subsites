<?php
/**
 * A dynamically created subdomain. SiteTree objects can now belong to a subdomain
 */
class Subsite extends DataObject implements PermissionProvider {
	
	static $default_sort = 'Title';
	
	static $use_domain = false;
	
	static $db = array(
		'Subdomain' => 'Varchar',
		'Title' => 'Varchar(255)',
		'RedirectURL' => 'Varchar(255)',
		'DefaultSite' => 'Boolean',
		'Theme' => 'Varchar',
		'Domain' => 'Varchar',
		'IsPublic' => 'Boolean'
	);
	
	static $indexes = array(
		'Subdomain' => true,
		'Domain' => true
	);
	
	static $base_domain, $default_subdomain;
	
	static $cached_subsite = null;
	
	public static $allowed_domains = array(
	);
	
	/**
	 * Return the base domain for this set of subsites.
	 * You can set this by setting Subsite::$Base_domain, otherwise it defaults to HTTP_HOST
	 */
	static function base_domain() {
		if(self::$base_domain) return self::$base_domain;
		else return $_SERVER['HTTP_HOST'];		
	}
	
	/**
	 * Return the default domain of this set of subsites.  Generally this will be the base domain,
	 * but hyou can also set Subsite::$default_subdomain to add a default prefix to this
	 */
	static function default_domain() {
		if(self::$default_subdomain) return self::$default_subdomain . '.' . self::base_domain();
		else return self::base_domain();
	}
	
	/**
	 * Return the domain of this site
	 */
	function domain() {
		$base = $this->Domain ? $this->Domain : self::base_domain();
		$sub = $this->Subdomain ? $this->Subdomain : self::$default_subdomain;
		
		if($sub) return "$sub.$base";
		else return $base;
	}
	
	// Show the configuration fields for each subsite
	function getCMSFields() {
		$fields = new FieldSet(
			new TabSet('Root',
				new Tab('Configuration',
					new HeaderField($this->getClassName() . ' configuration', 2),
					new TextField('Title', 'Name of subsite:', $this->Title),
					new FieldGroup('URL',
						new TextField('Subdomain',"", $this->Subdomain),
						new DropdownField('Domain','.', $this->stat('allowed_domains'), $this->Domain)
					),
					// new TextField('RedirectURL', 'Redirect to URL', $this->RedirectURL),
					new CheckboxField('DefaultSite', 'Use this subsite as the default site', $this->DefaultSite),
					new CheckboxField('IsPublic', 'Can access this subsite publicly?', $this->IsPublic)
				)
			),
			new HiddenField('ID', '', $this->ID),
			new HiddenField('IsSubsite', '', 1)
		);

// This code needs to be updated to reference the new SS 2.0.3 theme system
/*		if($themes = SSViewer::getThemes(false))
			$fields->addFieldsToTab('Root.Configuration', new DropdownField('Theme', 'Theme:', $themes, $this->Theme));
*/
		return $fields;
	}
	
	function getClassName() {
		return $this->class;
	}
	
	function getCMSActions() {
		return new FieldSet(
            new FormAction('callPageMethod', "Create copy", null, 'adminDuplicate')
		);
	}
	
	function adminDuplicate() {
		$newItem = $this->duplicate();
		$JS_title = Convert::raw2js($this->Title);
		return <<<JS
			statusMessage('Created a copy of $JS_title', 'good');
			$('Form_EditForm').loadURLFromServer('admin/subsites/show/$newItem->ID');
JS;
	}
	
	static function currentSubsite() {
		if(!self::$cached_subsite) self::$cached_subsite = DataObject::get_by_id('Subsite', self::currentSubsiteID());
		return self::$cached_subsite;
	}
	
	/**
	 * This function gets the current subsite ID from the session. It used in the backend so Ajax requests
	 * use the correct subsite. The frontend handles subsites differently. It calls getSubsiteIDForDomain
	 * directly from ModelAsController::getNestedController.
	 */
	static function currentSubsiteID() {
		$id = Session::get('SubsiteID');
	
		if($id === null) Session::set('SubsiteID', $id = self::getSubsiteIDForDomain());
		
		return (int)$id;
	}
	
	static function create($name) {
		$newSubsite = Object::create('Subsite');
		$newSubsite->Title = $name;
		$newSubsite->Subdomain = str_replace(' ', '-', preg_replace('/[^0-9A-Za-z\s]/', '', strtolower(trim($name))));
		$newSubsite->write();
		$newSubsite->createInitialRecords();
		return $newSubsite;
	}
	
	/**
	 * Switch to another subsite
	 * @param $subsite Either the ID of the subsite, or the subsite object itself
	 */
	static function changeSubsite($subsite) {
		
		// Debug::backtrace();
		
		if(!$subsite) {
			Session::set('SubsiteID', 0);
			return;
		}	
	
		if(is_object($subsite))
			$subsite = $subsite->ID;
	
		Session::set('SubsiteID', $subsite);
	
		/*if(!is_object($subsite) && is_numeric($subsite))
			$subsite = DataObject::get_by_id('Subsite', $subsite);
	
		if($subsite)
			Session::set('SubsiteID', $subsite->ID);*/
	
	}
	
	function canEdit() {
		return true;
	}
	
	static function getSubsiteIDForDomain() {
		$domainNameParts = explode('.', $_SERVER['HTTP_HOST']);
		
		if($domainNameParts[0] == 'www') return 0;
			
		$SQL_subdomain = Convert::raw2sql(array_shift($domainNameParts));
		$SQL_domain = join('.', Convert::raw2sql($domainNameParts));
		// $_REQUEST['showqueries'] = 1;
		if(self::$use_domain) {
			$subsite = DataObject::get_one('Subsite',"`Subdomain` = '$SQL_subdomain' AND `Domain`='$SQL_domain' AND `IsPublic`=1");
		} else {
			$subsite = DataObject::get_one('Subsite',"`Subdomain` = '$SQL_subdomain' AND `IsPublic`=1");
		}
		
		if($subsite) {
			// This will need to be updated to use the current theme system
			// SSViewer::setCurrentTheme($subsite->Theme);
			return $subsite->ID;
		}
	}
	
	function getMembersByPermission($permissionCodes = array('ADMIN')){
		if(!is_array($permissionCodes))
			user_error('Permissions must be passed to Subsite::getMembersByPermission as an array', E_USER_ERROR);
		$SQL_permissionCodes = Convert::raw2sql($permissionCodes);
		
		$SQL_permissionCodes = join("','", $SQL_permissionCodes);
		
		$join = <<<SQL
LEFT JOIN `Group_Members` ON `Member`.`ID` = `Group_Members`.`MemberID`
LEFT JOIN `Group` ON `Group`.`ID` = `Group_Members`.`GroupID`
LEFT JOIN `Permission` ON `Permission`.`GroupID` = `Group`.`ID`
SQL;
		return DataObject::get('Member', "`Group`.`SubsiteID` = $this->ID AND `Permission`.`Code` IN ('$SQL_permissionCodes')", '', $join);
	}
	
	static function getSubsitesForMember( $member = null, $permissionCodes = array('ADMIN')) {
		if(!is_array($permissionCodes))
			user_error('Permissions must be passed to Subsite::getSubsitesForMember as an array', E_USER_ERROR);
		
		if(!$member)
			$member = Member::currentMember();		

		$memberID = (int)$member->ID;

		$SQLa_permissionCodes = Convert::raw2sql($permissionCodes);
		
		$SQLa_permissionCodes = join("','", $SQLa_permissionCodes);
		
		if(self::hasMainSitePermission($member, $permissionCodes))
			return DataObject::get('Subsite');
		else
			return DataObject::get('Subsite', "`MemberID` = {$memberID}" . ($permissionCodes ? " AND `Permission`.`Code` IN ('$SQLa_permissionCodes')" : ''), '', "LEFT JOIN `Group` ON `Subsite`.`ID` = `SubsiteID` LEFT JOIN `Permission` ON `Group`.`ID` = `Permission`.`GroupID` LEFT JOIN `Group_Members` ON `Group`.`ID` = `Group_Members`.`GroupID`");
	}
	
	static function hasMainSitePermission($member = null, $permissionCodes = array('ADMIN')) {

		if(!is_array($permissionCodes))
			user_error('Permissions must be passed to Subsite::hasMainSitePermission as an array', E_USER_ERROR);

		if(!$member)
			$member = Member::currentMember();		

		$SQLa_perm = Convert::raw2sql($permissionCodes);
		$SQL_perms = join("','", $SQLa_perm);		
		$memberID = (int)$member->ID;
    // `SubsiteID` = 0 AND 
		return DB::query("SELECT COUNT(`Permission`.`ID`) FROM `Permission` LEFT JOIN `Group` ON `Group`.`ID` = `Permission`.`GroupID` LEFT JOIN `Group_Members` USING(`GroupID`) WHERE `Permission`.`Code` IN ('$SQL_perms') AND `MemberID` = {$memberID}")->value();
	}
	
	function createInitialRecords() {
		
	}

	/**
	 * Duplicate this subsite
	 */
	function duplicate() {
		$newTemplate = parent::duplicate();
		
		$oldSubsiteID = Session::get('SubsiteID');
		self::changeSubsite($this->ID);
		
		/*
		 * Copy data from this template to the given subsite. Does this using an iterative depth-first search.
		 * This will make sure that the new parents on the new subsite are correct, and there are no funny
		 * issues with having to check whether or not the new parents have been added to the site tree
		 * when a page, etc, is duplicated
		 */
		$stack = array(array(0,0));
		while(count($stack) > 0) {		
			list($sourceParentID, $destParentID) = array_pop($stack);
			
			$children = Versioned::get_by_stage('Page', 'Live', "`ParentID`=$sourceParentID", '');
			
			if($children) {
				foreach($children as $child) {
					$childClone = $child->duplicateToSubsite($newTemplate, false);
					$childClone->ParentID = $destParentID;
					$childClone->writeToStage('Stage');
					$childClone->publish('Stage', 'Live');
					array_push($stack, array($child->ID, $childClone->ID));
				}
			}
		}

		self::changeSubsite($oldSubsiteID);
		
		return $newTemplate;
	}	
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// CMS ADMINISTRATION HELPERS
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * Return the FieldSet that will build the search form in the CMS
	 */
	function adminSearchFields() {
		return new FieldSet(
			new TextField('Name', 'Sub-site name')
		);	
	}
	
	function providePermissions() {
		return array(
			'SUBSITE_EDIT' => 'Edit Sub-site Details',
		);
	}
}

/**
 * An instance of subsite that can be duplicated to provide a quick way to create new subsites.
 */
class Subsite_Template extends Subsite {
	/**
	 * Create an instance of this template, with the given title & subdomain
	 */
	function createInstance($title, $subdomain) {
		$intranet = Object::create('Subsite');
		$intranet->Title = $title;
		$intranet->Domain = $this->Domain;
		$intranet->Subdomain = $subdomain;
		$intranet->TemplateID = $this->ID;
		$intranet->write();
		
		
		$oldSubsiteID = Session::get('SubsiteID');
		self::changeSubsite($this->ID);
		
		/*
		 * Copy data from this template to the given subsite. Does this using an iterative depth-first search.
		 * This will make sure that the new parents on the new subsite are correct, and there are no funny
		 * issues with having to check whether or not the new parents have been added to the site tree
		 * when a page, etc, is duplicated
		 */
		$stack = array(array(0,0));
		while(count($stack) > 0) {		
			list($sourceParentID, $destParentID) = array_pop($stack);
			
			$children = Versioned::get_by_stage('Page', 'Live', "`ParentID`=$sourceParentID", '');
			
			if($children) {
				foreach($children as $child) {
					$childClone = $child->duplicateToSubsite($intranet);
					$childClone->ParentID = $destParentID;
					$childClone->writeToStage('Stage');
					$childClone->publish('Stage', 'Live');
					array_push($stack, array($child->ID, $childClone->ID));
				}
			}
		}

		self::changeSubsite($oldSubsiteID);
		
		return $intranet;
	}
}
?>
