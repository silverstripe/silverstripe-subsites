<?php
/**
 * A dynamically created subsite. SiteTree objects can now belong to a subsite.
 * You can simulate subsite access without setting up virtual hosts by appending ?SubsiteID=<ID> to the request.
 *
 * @package subsites
 */
class Subsite extends DataObject implements PermissionProvider {

	/**
	 * @var boolean $disable_subsite_filter If enabled, bypasses the query decoration
	 * to limit DataObject::get*() calls to a specific subsite. Useful for debugging.
	 */
	static $disable_subsite_filter = false;
	
	/**
	 * Allows you to force a specific subsite ID, or comma separated list of IDs.
	 * Only works for reading. An object cannot be written to more than 1 subsite.
	 */
	static $force_subsite = null;

	static $write_hostmap = true;
	static $default_sort = 'Title';

	static $db = array(
		'Title' => 'Varchar(255)',
		'RedirectURL' => 'Varchar(255)',
		'DefaultSite' => 'Boolean',
		'Theme' => 'Varchar',
		'Language' => 'Varchar(6)',

		// Used to hide unfinished/private subsites from public view.
		// If unset, will default to true
		'IsPublic' => 'Boolean'
	);
	
	static $has_one = array(
	);
	
	static $has_many = array(
		'Domains' => 'SubsiteDomain',
	);
	
	static $belongs_many_many = array(
		"Groups" => "Group",
	);

	static $defaults = array(
		'IsPublic' => 1,
	);

	static $searchable_fields = array(
		'Title' => array(
			'title' => 'Subsite Name'
		),
		'Domains.Domain' => array(
			'title' => 'Domain name'
		),
		'IsPublic' => array(
			'title' => 'Active subsite',
		),	
	);

	static $summary_fields = array(
		'Title' => 'Subsite Name',
		'PrimaryDomain' => 'Primary Domain',
		'IsPublic' => 'Active subsite',
	);

	/**
	 * @var array $allowed_themes Numeric array of all themes which are allowed to be selected for all subsites.
	 * Corresponds to subfolder names within the /themes folder. By default, all themes contained in this folder
	 * are listed.
	 */
	protected static $allowed_themes = array();

	static function set_allowed_domains($domain){
		user_error('Subsite::set_allowed_domains() is deprecated; it is no longer necessary '
			. 'because users can now enter any domain name', E_USER_NOTICE);
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
	 * Whenever a Subsite is written, rewrite the hostmap
	 *
	 * @return void
	 */
	public function onAfterWrite() {
		Subsite::writeHostMap();
	}
	
	/**
	 * Return the domain of this site
	 *
	 * @return string The full domain name of this subsite (without protocol prefix)
	 */
	function domain() {
		if($this->ID) {
			$domains = DataObject::get("SubsiteDomain", "\"SubsiteID\" = $this->ID", "\"IsPrimary\" DESC",
				"", 1);
			if($domains) {
				$domain = $domains->First()->Domain;
				// If there are wildcards in the primary domain (not recommended), make some
				// educated guesses about what to replace them with
				$domain = preg_replace("/\\.\\*\$/",".$_SERVER[HTTP_HOST]", $domain);
				$domain = preg_replace("/^\\*\\./","subsite.", $domain);
				$domain = str_replace('.www.','.', $domain);
				return $domain;
			}
			
		// SubsiteID = 0 is often used to refer to the main site, just return $_SERVER['HTTP_HOST']
		} else {
			return $_SERVER['HTTP_HOST'];
		}
	}
	
	function getPrimaryDomain() {
		return $this->domain();
	}

	function absoluteBaseURL() {
		return "http://" . $this->domain() . Director::baseURL();
	}

	/**
	 * Show the configuration fields for each subsite
	 */
	function getCMSFields() {
		$domainTable = new TableField("Domains", "SubsiteDomain", 
			array("Domain" => "Domain (use * as a wildcard)", "IsPrimary" => "Primary domain?"), 
			array("Domain" => "TextField", "IsPrimary" => "CheckboxField"), 
			"SubsiteID", $this->ID);
			
		$languageSelector = new DropdownField('Language', 'Language', i18n::get_common_locales());

		$fields = new FieldSet(
			new TabSet('Root',
				new Tab('Configuration',
					new HeaderField($this->getClassName() . ' configuration', 2),
					new TextField('Title', 'Name of subsite:', $this->Title),
					
					new HeaderField("Domains for this subsite"),
					$domainTable,
					$languageSelector,
					// new TextField('RedirectURL', 'Redirect to URL', $this->RedirectURL),
					new CheckboxField('DefaultSite', 'Default site', $this->DefaultSite),
					new CheckboxField('IsPublic', 'Enable public access', $this->IsPublic),

					new DropdownField('Theme','Theme', $this->allowedThemes(), $this->Theme)
				)
			),
			new HiddenField('ID', '', $this->ID),
			new HiddenField('IsSubsite', '', 1)
		);

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
	 * @return Subsite
	 */
	static function currentSubsite() {
		// get_by_id handles caching so we don't have to
		return DataObject::get_by_id('Subsite', self::currentSubsiteID());
	}

	/**
	 * This function gets the current subsite ID from the session. It used in the backend so Ajax requests
	 * use the correct subsite. The frontend handles subsites differently. It calls getSubsiteIDForDomain
	 * directly from ModelAsController::getNestedController. Only gets Subsite instances which have their
	 * {@link IsPublic} flag set to TRUE.
	 *
	 * You can simulate subsite access without creating virtual hosts by appending ?SubsiteID=<ID> to the request.
	 *
	 * @todo Pass $request object from controller so we don't have to rely on $_REQUEST
	 *
	 * @param boolean $cache
	 * @return int ID of the current subsite instance
	 */
	static function currentSubsiteID() {
		if(isset($_REQUEST['SubsiteID'])) $id = (int)$_REQUEST['SubsiteID'];
		else $id = Session::get('SubsiteID');

		if($id === NULL) {
			$id = self::getSubsiteIDForDomain();
			Session::set('SubsiteID', $id);
		}

		return (int)$id;
	}
	
	/**
	 * Switch to another subsite.
	 *
	 * @param int|Subsite $subsite Either the ID of the subsite, or the subsite object itself
	 */
	static function changeSubsite($subsite) {
		if(is_object($subsite)) $subsiteID = $subsite->ID;
		else $subsiteID = $subsite;
		
		Session::set('SubsiteID', (int)$subsiteID);
		
		// Set locale
		if (is_object($subsite) && $subsite->Language != '') {
			if (isset(i18n::$likely_subtags[$subsite->Language])) {
				i18n::set_locale(i18n::$likely_subtags[$subsite->Language]);
			}
		}
		
		// Only bother flushing caches if we've actually changed
		if($subsiteID != self::currentSubsiteID()) Permission::flush_permission_cache();
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
	 * Get a matching subsite for the given host, or for the current HTTP_HOST.
	 * 
	 * @param $host The host to find the subsite for.  If not specified, $_SERVER['HTTP_HOST']
	 * is used.
	 *
	 * @return int Subsite ID
	 */
	static function getSubsiteIDForDomain($host = null, $returnMainIfNotFound = true) {
		if($host == null) $host = $_SERVER['HTTP_HOST'];
		
		$host = str_replace('www.','',$host);
		$SQL_host = Convert::raw2sql($host);

		$matchingDomains = DataObject::get("SubsiteDomain", "'$SQL_host' LIKE replace(\"SubsiteDomain\".\"Domain\",'*','%')",
			"\"IsPrimary\" DESC", "INNER JOIN \"Subsite\" ON \"Subsite\".\"ID\" = \"SubsiteDomain\".\"SubsiteID\" AND
			\"Subsite\".\"IsPublic\"=1");
		
		if($matchingDomains) {
			$subsiteIDs = array_unique($matchingDomains->column('SubsiteID'));
			if(sizeof($subsiteIDs) > 1) user_error("Multiple subsites match '$host'", E_USER_WARNING);
			return $subsiteIDs[0];
		}
		
		// Check for a 'default' subsite
		if ($default = DataObject::get_one('Subsite', "\"DefaultSite\" = 1")) {
			return $default->ID;
		}
		
		// Default subsite id = 0, the main site
		return 0;
	}

	function getMembersByPermission($permissionCodes = array('ADMIN')){
		if(!is_array($permissionCodes))
			user_error('Permissions must be passed to Subsite::getMembersByPermission as an array', E_USER_ERROR);
		$SQL_permissionCodes = Convert::raw2sql($permissionCodes);

		$SQL_permissionCodes = join("','", $SQL_permissionCodes);

		return DataObject::get(
			'Member',
			"\"Group\".\"SubsiteID\" = $this->ID AND \"Permission\".\"Code\" IN ('$SQL_permissionCodes')",
			'',
			"LEFT JOIN \"Group_Members\" ON \"Member\".\"ID\" = \"Group_Members\".\"MemberID\"
			LEFT JOIN \"Group\" ON \"Group\".\"ID\" = \"Group_Members\".\"GroupID\"
			LEFT JOIN \"Permission\" ON \"Permission\".\"GroupID\" = \"Group\".\"ID\""
		);
	
	}

	static function hasMainSitePermission($member = null, $permissionCodes = array('ADMIN')) {
		if(!is_array($permissionCodes))
			user_error('Permissions must be passed to Subsite::hasMainSitePermission as an array', E_USER_ERROR);

		if(!$member && $member !== FALSE) $member = Member::currentMember();

		if(!$member) return false;
		
		if(!in_array("ADMIN", $permissionCodes)) $permissionCodes[] = "ADMIN";

		$SQLa_perm = Convert::raw2sql($permissionCodes);
		$SQL_perms = join("','", $SQLa_perm);
		$memberID = (int)$member->ID;
		
		$groupCount = DB::query("
			SELECT COUNT(\"Permission\".\"ID\")
			FROM \"Permission\"
			INNER JOIN \"Group\" ON \"Group\".\"ID\" = \"Permission\".\"GroupID\" AND \"Group\".\"AccessAllSubsites\" = 1
			INNER JOIN \"Group_Members\" ON \"Group_Members\".\"GroupID\" = \"Permission\".\"GroupID\"
			WHERE \"Permission\".\"Code\" IN ('$SQL_perms')
			AND \"MemberID\" = {$memberID}
		")->value();

		return ($groupCount > 0);

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

			$children = Versioned::get_by_stage('Page', 'Live', "\"ParentID\" = $sourceParentID", '');

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
	 * Sites and Templates will only be included if they have a Title
	 *
	 * @param $permCode array|string Either a single permission code or an array of permission codes.
	 * @param $includeMainSite If true, the main site will be included if appropriate.
	 * @param $mainSiteTitle The label to give to the main site
	 */
	function accessible_sites($permCode, $includeMainSite = false, $mainSiteTitle = "Main site", $member = null) {
		// Rationalise member arguments
		if(!$member) $member = Member::currentUser();
		if(!$member) return new DataObjectSet();
		if(!is_object($member)) $member = DataObject::get_by_id('Member', $member);

		if(is_array($permCode))	$SQL_codes = "'" . implode("', '", Convert::raw2sql($permCode)) . "'";
		else $SQL_codes = "'" . Convert::raw2sql($permCode) . "'";

		$templateClassList = "'" . implode("', '", ClassInfo::subclassesFor("Subsite_Template")) . "'";

		$subsites = DataObject::get(
			'Subsite',
			"\"Subsite\".\"Title\" != ''",
			'',
			"LEFT JOIN \"Group_Subsites\" 
				ON \"Group_Subsites\".\"SubsiteID\" = \"Subsite\".\"ID\"
			INNER JOIN \"Group\" ON \"Group\".\"ID\" = \"Group_Subsites\".\"GroupID\"
				OR \"Group\".\"AccessAllSubsites\" = 1
			INNER JOIN \"Group_Members\" 
				ON \"Group_Members\".\"GroupID\"=\"Group\".\"ID\"
				AND \"Group_Members\".\"MemberID\" = $member->ID
			INNER JOIN \"Permission\" 
				ON \"Group\".\"ID\"=\"Permission\".\"GroupID\"
				AND \"Permission\".\"Code\" IN ($SQL_codes, 'ADMIN')"
		);

		$rolesSubsites = DataObject::get(
			'Subsite',
			"\"Subsite\".\"Title\" != ''",
			'',
			"LEFT JOIN \"Group_Subsites\" 
				ON \"Group_Subsites\".\"SubsiteID\" = \"Subsite\".\"ID\"
			INNER JOIN \"Group\" ON \"Group\".\"ID\" = \"Group_Subsites\".\"GroupID\"
				OR \"Group\".\"AccessAllSubsites\" = 1
			INNER JOIN \"Group_Members\" 
				ON \"Group_Members\".\"GroupID\"=\"Group\".\"ID\"
				AND \"Group_Members\".\"MemberID\" = $member->ID
			INNER JOIN \"Group_Roles\"
				ON \"Group_Roles\".\"GroupID\"=\"Group\".\"ID\"
			INNER JOIN \"PermissionRole\"
				ON \"Group_Roles\".\"PermissionRoleID\"=\"PermissionRole\".\"ID\"
			INNER JOIN \"PermissionRoleCode\"
				ON \"PermissionRole\".\"ID\"=\"PermissionRoleCode\".\"RoleID\"
				AND \"PermissionRoleCode\".\"Code\" IN ($SQL_codes, 'ADMIN')"
		);

		if(!$subsites && $rolesSubsites) return $rolesSubsites;

		if($rolesSubsites) foreach($rolesSubsites as $subsite) {
			if(!$subsites->containsIDs(array($subsite->ID))) {
				$subsites->push($subsite);
			}
		}

		// Include the main site
		if(!$subsites) $subsites = new DataObjectSet();
		if($includeMainSite) {
			if(!is_array($permCode)) $permCode = array($permCode);
			if(self::hasMainSitePermission($member, $permCode)) {
				$mainSite = new Subsite();
				$mainSite->Title = $mainSiteTitle;
				$subsites->insertFirst($mainSite);
			}
		}

		return $subsites;
	}
	
	/**
	 * Write a host->domain map to subsites/host-map.php
	 *
	 * This is used primarily when using subsites in conjunction with StaticPublisher
	 *
	 * @return void
	 */
	static function writeHostMap($file = null) {
		if (!self::$write_hostmap) return;
		
		if (!$file) $file = Director::baseFolder().'/subsites/host-map.php';
		$hostmap = array();
		
		$subsites = DataObject::get('Subsite');
		
		if ($subsites) foreach($subsites as $subsite) {
			$domains = $subsite->Domains();
			if ($domains) foreach($domains as $domain) {
				$hostmap[str_replace('www.', '', $domain->Domain)] = $subsite->domain(); 
			}
			if ($subsite->DefaultSite) $hostmap['default'] = $subsite->domain();
		}
		
		$data = "<?php \n";
		$data .= "// Generated by Subsite::writeHostMap() on " . date('d/M/y') . "\n";
		$data .= '$subsiteHostmap = ' . var_export($hostmap, true) . ';';

		if (is_writable(dirname($file)) || is_writable($file)) {
			file_put_contents($file, $data);
		}
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
			'SUBSITE_ASSETS_CREATE_SUBSITE' => array(
				'name' => _t('Subsite.MANAGE_ASSETS', 'Manage assets for subsites'),
				'category' => _t('Permissions.PERMISSIONS_CATEGORY', 'Roles and access permissions'),
				'help' => _t('Subsite.MANAGE_ASSETS_HELP', 'Ability to select the subsite to which an asset folder belongs. Requires "Access to Files & Images."'),
				'sort' => 300
			)
		);
	}

	static function get_from_all_subsites($className, $filter = "", $sort = "", $join = "", $limit = "") {
		$oldState = self::$disable_subsite_filter;
		self::$disable_subsite_filter = true;
		$result = DataObject::get($className, $filter, $sort, $join, $limit);
		self::$disable_subsite_filter = $oldState;
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
	 * Create an instance of this template, with the given title & domain
	 */
	function createInstance($title, $domain = null) {
		$intranet = Object::create('Subsite');
		$intranet->Title = $title;
		$intranet->TemplateID = $this->ID;
		$intranet->write();
		
		if($domain) {
			$intranetDomain = Object::create('SubsiteDomain');
			$intranetDomain->SubsiteID = $intranet->ID;
			$intranetDomain->Domain = $domain;
			$intranetDomain->write();
		}

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

			$children = Versioned::get_by_stage('SiteTree', 'Live', "\"ParentID\" = $sourceParentID", '');

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
