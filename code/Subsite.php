<?php
/**
 * A dynamically created subdomain. SiteTree objects can now belong to a subdomain.
 * You can simulate subsite access without creating subdomains by appending ?SubsiteID=<ID> to the request.
 * 
 * @package subsites
 */
class Subsite extends DataObject implements PermissionProvider {

	/**
	 * @var boolean $disable_subsite_filter If enabled, bypasses the query decoration
	 * to limit DataObject::get*() calls to a specific subsite. Useful for debugging.
	 */
	static $disable_subsite_filter = false;
	
	static $default_sort = 'Title';
	
	/**
	 * @var boolean $use_domain Checks for valid domain in addition to subdomain
	 * when searching for a matching page with {@link getSubsiteIDForDomain()}.
	 * By default, only the subdomain has to match.
	 */
	static $use_domain = false;
	
	static $db = array(
		'Subdomain' => 'Varchar',
		'Title' => 'Varchar(255)',
		'RedirectURL' => 'Varchar(255)',
		'DefaultSite' => 'Boolean',
		'Theme' => 'Varchar',
		'Domain' => 'Varchar',
		// Used to hide unfinished/private subsites from public view.
		// If unset, will default to 
		'IsPublic' => 'Boolean'
	);
	
	static $has_one = array(
	);
	
	static $indexes = array(
		'Subdomain' => true,
		'Domain' => true
	);

	static $defaults = array(
		'IsPublic' => 1,
	);
	
	/**
	 * @var string $base_domain If {@link Domain} is not set for this subsite instance,
	 * default to this domain (without subdomain or protocol prefix).
	 */
	static $base_domain;
	
	/**
	 * @var string $default_subdomain If {@link Subdomain} is not set for this subsite instance,
	 * default to this domain (without domain or protocol prefix).
	 */
	static $default_subdomain;

	/**
	 * @var Subsite $cached_subsite Internal cache used by {@link currentSubsite()}.
	 */
	protected static $cached_subsite = null;
	
	/**
	 * @var array $allowed_domains Numeric array of all domains which are selectable for (without their subdomain-parts or http:// prefix)
	 */
	public static $allowed_domains = array();
	
	/**
	 * @var array $allowed_themes Numeric array of all themes which are allowed to be selected for all subsites.
	 * Corresponds to subfolder names within the /themes folder. By default, all themes contained in this folder
	 * are listed.
	 */
	protected static $allowed_themes = array();	
	
	static function set_allowed_domains($domain){
		if(is_array($domain)){
			foreach($domain as $do){
				self::set_allowed_domains($do);
			}
		}else{
			self::$allowed_domains[] = $domain;
		}
	}
	
	/**
	 * Returns all domains (without their subdomain parts)
	 * which are allowed to be combined to the full URL
	 * (subdomain.domain). If no custom domains are set through
	 * {@link set_allowed_domains()}, will fall back to the {@link base_domain()}. 
	 * 
	 * @return array
	 */
	static function allowed_domains() {
		if(self::$allowed_domains && count(self::$allowed_domains)) {
			return self::$allowed_domains;
		} else {
			return array(self::base_domain());
		}
	}
	
	static function set_allowed_themes($themes) {
		self::$allowed_themes = $themes;
	}
	
	/**
	 * Return the themes that can be used with this subsite, as an array of themecode => description
	 */
	function allowedThemes() {
		if($themes = $this->stat('allowed_themes')) {
			return ArrayLib::valuekey($themes);
		} else {
			$themes = array();
			if(is_dir('../themes/')) {
				foreach(scandir('../themes/') as $theme) {
					if($theme[0] == '.') continue;
					$theme = strtok($theme,'_');
					$themes[$theme] = $theme;
				}
				ksort($themes);
			}
			return $themes;
		}
	}
	
	/**
	 * Return the base domain for this set of subsites.
	 * You can set this by setting Subsite::$base_domain, otherwise it defaults to HTTP_HOST
	 * 
	 * @return string Domain name (without protocol prefix).
	 */
	static function base_domain() {
		if(self::$base_domain) return self::$base_domain;
		else return $_SERVER['HTTP_HOST'];		
	}
	
	/**
	 * Return the default domain of this set of subsites.  Generally this will be the base domain,
	 * but you can also set Subsite::$default_subdomain to add a default prefix to this.
	 * 
	 * @return string Domain name (without protocol prefix).
	 */
	static function default_domain() {
		if(self::$default_subdomain) return self::$default_subdomain . '.' . self::base_domain();
		else return self::base_domain();
	}
	
	/**
	 * Return the domain of this site
	 * 
	 * @return string Domain name including subdomain (without protocol prefix)
	 */
	function domain() {
		$base = $this->Domain ? $this->Domain : self::base_domain();
		$sub = $this->Subdomain ? $this->Subdomain : self::$default_subdomain;
		
		if($sub) return "$sub.$base";
		else return $base;
	}

	function absoluteBaseURL() {
		return "http://" . $this->domain() . Director::baseURL();
	}
	
	/**
	 * Show the configuration fields for each subsite
	 */
	function getCMSFields() {
		$fields = new FieldSet(
			new TabSet('Root',
				new Tab('Configuration',
					new HeaderField($this->getClassName() . ' configuration', 2),
					new TextField('Title', 'Name of subsite:', $this->Title),
					new FieldGroup('URL',
						new TextField('Subdomain',"Subdomain <small>(without domain or protocol)</small>", $this->Subdomain),
						new DropdownField('Domain','.', ArrayLib::valuekey(self::allowed_domains()), $this->Domain)
					),
					// new TextField('RedirectURL', 'Redirect to URL', $this->RedirectURL),
					new CheckboxField('DefaultSite', 'Default site', $this->DefaultSite),
					new CheckboxField('IsPublic', 'Enable public access', $this->IsPublic),

					new DropdownField('Theme','Theme', $this->allowedThemes(), $this->Theme)
				)
			),
			new HiddenField('ID', '', $this->ID),
			new HiddenField('IsSubsite', '', 1)
		);

// This code needs to be updated to reference the new SS 2.0.3 theme system
/*		if($themes = SSViewer::getThemes(false))
			$fields->addFieldsToTab('Root.Configuration', new DropdownField('Theme', 'Theme:', $themes, $this->Theme));
*/

		$this->extend('updateCMSFields', $fields);
		return $fields;
	}
	
	/**
	 * @todo getClassName is redundant, already stored as a database field?
	 */
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
	
	/**
	 * Gets the subsite currently set in the session.
	 * 
	 * @uses ControllerSubsites->controllerAugmentInit()
	 * 
	 * @param boolean $cache
	 * @return Subsite
	 */
	static function currentSubsite($cache = true) {
		if(!self::$cached_subsite || !$cache) self::$cached_subsite = DataObject::get_by_id('Subsite', self::currentSubsiteID());
		return self::$cached_subsite;
	}
	
	/**
	 * This function gets the current subsite ID from the session. It used in the backend so Ajax requests
	 * use the correct subsite. The frontend handles subsites differently. It calls getSubsiteIDForDomain
	 * directly from ModelAsController::getNestedController. Only gets Subsite instances which have their
	 * {@link IsPublic} flag set to TRUE.
	 * 
	 * You can simulate subsite access without creating subdomains by appending ?SubsiteID=<ID> to the request.
	 * 
	 * @todo Pass $request object from controller so we don't have to rely on $_REQUEST
	 * 
	 * @param boolean $cache
	 * @return int ID of the current subsite instance
	 */
	static function currentSubsiteID($cache = true) {
		if(isset($_REQUEST['SubsiteID'])) {
			$id = (int)$_REQUEST['SubsiteID'];
		} else if(Session::get('SubsiteID')) {
			$id = Session::get('SubsiteID');
		}
	
		if(!isset($id) || $id === NULL) {
			$id = self::getSubsiteIDForDomain($cache);
			Session::set('SubsiteID', $id);
		}
		
		return (int)$id;
	}
	
	/**
	 * @todo Object::create() shoudln't be overloaded with different parameters.
	 */
	static function create($name) {
		$newSubsite = Object::create('Subsite');
		$newSubsite->Title = $name;
		$newSubsite->Subdomain = str_replace(' ', '-', preg_replace('/[^0-9A-Za-z\s]/', '', strtolower(trim($name))));
		$newSubsite->write();
		$newSubsite->createInitialRecords();
		return $newSubsite;
	}
	
	/**
	 * Switch to another subsite.
	 * 
	 * @param int|Subsite $subsite Either the ID of the subsite, or the subsite object itself
	 */
	static function changeSubsite($subsite) {
		if(is_object($subsite)) $subsiteID = $subsite->ID;
		else $subsiteID = $subsite;
	
		Session::set('SubsiteID', $subsiteID);
	}
	
	/**
	 * Make this subsite the current one
	 */
	public function activate() {
		Subsite::changeSubsite($this);
	}
	
	/**
	 * @todo Possible security issue, don't grant edit permissions to everybody.
	 */
	function canEdit() {
		return true;
	}
	
	/**
	 * Get a matching subsite for the domain defined in HTTP_HOST.
	 * 
	 * @return int Subsite ID
	 */
	static function getSubsiteIDForDomain() {
		$domainNameParts = explode('.', $_SERVER['HTTP_HOST']);
		
		if($domainNameParts[0] == 'www') array_shift($domainNameParts);
			
		$SQL_subdomain = Convert::raw2sql(array_shift($domainNameParts));
		$SQL_domain = join('.', Convert::raw2sql($domainNameParts));

		$subsite = null;
		if(self::$use_domain) {
			$subsite = DataObject::get_one('Subsite',"`Subdomain` = '$SQL_subdomain' AND `Domain`='$SQL_domain' AND `IsPublic`=1");
		}
		if(!$subsite) {
			$subsite = DataObject::get_one('Subsite',"`Subdomain` = '$SQL_subdomain' AND `IsPublic`");
		}
		if(!$subsite) {
			$subsite = DataObject::get_one('Subsite',"`DefaultSite` AND `IsPublic`");
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
		
		return DataObject::get(
			'Member', 
			"`Group`.`SubsiteID` = $this->ID AND `Permission`.`Code` IN ('$SQL_permissionCodes')", 
			'', 
			"LEFT JOIN `Group_Members` ON `Member`.`ID` = `Group_Members`.`MemberID`
			LEFT JOIN `Group` ON `Group`.`ID` = `Group_Members`.`GroupID`
			LEFT JOIN `Permission` ON `Permission`.`GroupID` = `Group`.`ID`"
		);
	}
	
	/**
	 * Get all subsites.
	 * 
	 * @return DataObjectSet Subsite instances
	 */
	static function getSubsitesForMember( $member = null) {
		if(!$member && $member !== FALSE) $member = Member::currentMember();
		
		if(!$member) return false;

		if(self::hasMainSitePermission($member)) {
			return DataObject::get('Subsite');
		} else {
			return DataObject::get(
				'Subsite', 
				"`MemberID` = {$member->ID}", 
				'', 
				"LEFT JOIN `Group` ON `Subsite`.`ID` = `SubsiteID` 
				LEFT JOIN `Group_Members` ON `Group`.`ID` = `Group_Members`.`GroupID`"
			);
		}
	}
	
	static function hasMainSitePermission($member = null, $permissionCodes = array('ADMIN')) {
		if(!is_array($permissionCodes))
			user_error('Permissions must be passed to Subsite::hasMainSitePermission as an array', E_USER_ERROR);

		if(!$member && $member !== FALSE) $member = Member::currentMember();
		
		if(!$member) return false;
		
		if(Permission::checkMember($member->ID, "ADMIN")) return true;

		if(Permission::checkMember($member, "SUBSITE_ACCESS_ALL")) return true;

		$SQLa_perm = Convert::raw2sql($permissionCodes);
		$SQL_perms = join("','", $SQLa_perm);		
		$memberID = (int)$member->ID;
		
		$groupCount = DB::query("
			SELECT COUNT(`Permission`.`ID`) 
			FROM `Permission`   
			INNER JOIN `Group` ON `Group`.`ID` = `Permission`.`GroupID` AND `Group`.`SubsiteID` = 0  
			INNER JOIN `Group_Members` USING(`GroupID`)   
			WHERE 
			`Permission`.`Code` IN ('$SQL_perms') 
			AND `MemberID` = {$memberID}
		")->value();

		return ($groupCount > 0);
	
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
	
	
	/**
	 * Return the subsites that the current user can access.
	 * Look for one of the given permission codes on the site.
	 * 
	 * Sites will only be included if they have a Title and a Subdomain.
	 * Templates will only be included if they have a Title.
	 * 
	 * @param $permCode array|string Either a single permission code or an array of permission codes.
	 */
	function accessible_sites($permCode) {
		$member = Member::currentUser();
		
		if(is_array($permCode))	$SQL_codes = "'" . implode("', '", Convert::raw2sql($permCode)) . "'";
		else $SQL_codes = "'" . Convert::raw2sql($permCode) . "'";
		
		if(!$member) return new DataObjectSet();
		
		$templateClassList = "'" . implode("', '", ClassInfo::subclassesFor("Subsite_Template")) . "'";

		$subsites = DataObject::get(
			'Subsite',
			"`Group_Members`.`MemberID` = $member->ID 
			AND `Permission`.`Code` IN ($SQL_codes, 'ADMIN') 
			AND (Subdomain IS NOT NULL OR `Subsite`.ClassName IN ($templateClassList)) AND `Subsite`.Title != ''", 
			'',
			"LEFT JOIN `Group` ON (`SubsiteID`=`Subsite`.`ID` OR `SubsiteID` = 0) 
			LEFT JOIN `Group_Members` ON `Group_Members`.`GroupID`=`Group`.`ID`
			LEFT JOIN `Permission` ON `Group`.`ID`=`Permission`.`GroupID`"
		);
		
		return $subsites;
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
			'SUBSITE_ACCESS_ALL' => 'Access all subsites',
		);
	}

	static function get_from_all_subsites($className, $filter = "", $sort = "", $join = "", $limit = "") {
		self::$disable_subsite_filter = true;
		$result = DataObject::get($className, $filter, $sort, $join, $limit);
		self::$disable_subsite_filter = false;
		return $result;
	}
	
	/**
	 * Disable the sub-site filtering; queries will select from all subsites
	 */	
	static function disable_subsite_filter($disabled = true) {
		self::$disable_subsite_filter = $disabled;
	}
}

/**
 * An instance of subsite that can be duplicated to provide a quick way to create new subsites.
 * 
 * @package subsites
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
		 * Copy site content from this template to the given subsite. Does this using an iterative depth-first search.
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
		
		/**
		 * Copy groups from the template to the given subsites.  Each of the groups will be created and left
		 * empty.
		 */
		$groups = DataObject::get("Group", "SubsiteID = '$this->ID'");
		if($groups) foreach($groups as $group) {
			$group->duplicateToSubsite($intranet);
		}

		self::changeSubsite($oldSubsiteID);
		
		return $intranet;
	}
}
?>
