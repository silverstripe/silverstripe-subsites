<?php
/**
 * A dynamically created subsite. SiteTree objects can now belong to a subsite.
 * You can simulate subsite access without setting up virtual hosts by appending ?SubsiteID=<ID> to the request.
 *
 * @package subsites
 */
class Subsite extends DataObject implements PermissionProvider {

	/**
	 * @var $use_session_subsiteid Boolean Set to TRUE when using the CMS and FALSE
	 * when browsing the frontend of a website. 
	 * 
	 * @todo Remove flag once the Subsite CMS works without session state,
	 * similarly to the Translatable module.
	 */
	public static $use_session_subsiteid = false;

	/**
	 * @var boolean $disable_subsite_filter If enabled, bypasses the query decoration
	 * to limit DataObject::get*() calls to a specific subsite. Useful for debugging.
	 */
	public static $disable_subsite_filter = false;
	
	/**
	 * Allows you to force a specific subsite ID, or comma separated list of IDs.
	 * Only works for reading. An object cannot be written to more than 1 subsite.
	 */
	public static $force_subsite = null;

	/**
	 *
	 * @var boolean
	 */
	public static $write_hostmap = true;
	
	/**
	 * Memory cache of accessible sites
	 * 
	 * @array
	 */
	private static $_cache_accessible_sites = array();

	/**
	 * Memory cache of subsite id for domains
	 *
	 * @var array
	 */
	private static $_cache_subsite_for_domain = array();

	/**
	 * @var array $allowed_themes Numeric array of all themes which are allowed to be selected for all subsites.
	 * Corresponds to subfolder names within the /themes folder. By default, all themes contained in this folder
	 * are listed.
	 */
	private static $allowed_themes = array();
	
	/**
	 * @var Boolean If set to TRUE, don't assume 'www.example.com' and 'example.com' are the same.
	 * Doesn't affect wildcard matching, so '*.example.com' will match 'www.example.com' (but not 'example.com')
	 * in both TRUE or FALSE setting.
	 */
	public static $strict_subdomain_matching = false;

	/**
	 * @var boolean Respects the IsPublic flag when retrieving subsites
	 */
	public static $check_is_public = true;

	/**
	 * Set allowed themes
	 * 
	 * @param array $themes - Numeric array of all themes which are allowed to be selected for all subsites.
	 */
	public static function set_allowed_themes($themes) {
		self::$allowed_themes = $themes;
	}
	
	/**
	 * Gets the subsite currently set in the session.
	 *
	 * @uses ControllerSubsites->controllerAugmentInit()
	 * @return Subsite
	 */
	public static function currentSubsite() {
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
	 * @todo Pass $request object from controller so we don't have to rely on $_GET
	 *
	 * @param boolean $cache
	 * @return int ID of the current subsite instance
	 */
	public static function currentSubsiteID() {
		$id = NULL;

		if(isset($_GET['SubsiteID'])) {
			$id = (int)$_GET['SubsiteID'];
		} else if (Subsite::$use_session_subsiteid) {
			$id = Session::get('SubsiteID');
		}

		if($id === NULL) {
			$id = self::getSubsiteIDForDomain();
		}

		return (int)$id;
	}
	
	/**
	 * Switch to another subsite through storing the subsite identifier in the current PHP session.
	 * Only takes effect when {@link Subsite::$use_session_subsiteid} is set to TRUE.
	 *
	 * @param int|Subsite $subsite Either the ID of the subsite, or the subsite object itself
	 */
	public static function changeSubsite($subsite) {
		// Session subsite change only meaningful if the session is active.
		// Otherwise we risk setting it to wrong value, e.g. if we rely on currentSubsiteID.
		if (!Subsite::$use_session_subsiteid) return;

		if(is_object($subsite)) $subsiteID = $subsite->ID;
		else $subsiteID = $subsite;

		Session::set('SubsiteID', (int)$subsiteID);

		// Set locale
		if (is_object($subsite) && $subsite->Language != '') {
			$locale = i18n::get_locale_from_lang($subsite->Language);
			if($locale) {
				i18n::set_locale($locale);
			}
		}

		Permission::flush_permission_cache();
	}
	
	/**
	 * Get a matching subsite for the given host, or for the current HTTP_HOST.
	 * Supports "fuzzy" matching of domains by placing an asterisk at the start of end of the string,
	 * for example matching all subdomains on *.example.com with one subsite,
	 * and all subdomains on *.example.org on another.
	 * 
	 * @param $host The host to find the subsite for.  If not specified, $_SERVER['HTTP_HOST'] is used.
	 * @return int Subsite ID
	 */
	public static function getSubsiteIDForDomain($host = null, $checkPermissions = true) {
		if($host == null && isset($_SERVER['HTTP_HOST'])) {
			$host = $_SERVER['HTTP_HOST'];
		}

		$matchingDomains = null;
		$cacheKey = null;
		if ($host) {
			if(!self::$strict_subdomain_matching) $host = preg_replace('/^www\./', '', $host);

			$cacheKey = implode('_', array($host, Member::currentUserID(), self::$check_is_public));
			if(isset(self::$_cache_subsite_for_domain[$cacheKey])) return self::$_cache_subsite_for_domain[$cacheKey];

			$SQL_host = Convert::raw2sql($host);
			$matchingDomains = DataObject::get(
				"SubsiteDomain", 
				"'$SQL_host' LIKE replace(\"SubsiteDomain\".\"Domain\",'*','%')",
				"\"IsPrimary\" DESC"
			)->innerJoin('Subsite', "\"Subsite\".\"ID\" = \"SubsiteDomain\".\"SubsiteID\" AND \"Subsite\".\"IsPublic\"=1");
		}

		if($matchingDomains && $matchingDomains->Count()) {
			$subsiteIDs = array_unique($matchingDomains->column('SubsiteID'));
			$subsiteDomains = array_unique($matchingDomains->column('Domain'));
			if(sizeof($subsiteIDs) > 1) {
				throw new UnexpectedValueException(sprintf(
					"Multiple subsites match on '%s': %s",
					$host,
					implode(',', $subsiteDomains)
				));
			}

			$subsiteID = $subsiteIDs[0];
		} else if($default = DataObject::get_one('Subsite', "\"DefaultSite\" = 1")) {
			// Check for a 'default' subsite
			$subsiteID = $default->ID;
		} else {
			// Default subsite id = 0, the main site
			$subsiteID = 0;
		}

		if ($cacheKey) {
			self::$_cache_subsite_for_domain[$cacheKey] = $subsiteID;
		}

		return $subsiteID;
	}

	/**
	 * 
	 * @param string $className
	 * @param string $filter
	 * @param string $sort
	 * @param string $join
	 * @param string $limit
	 * @return DataList
	 */
	public static function get_from_all_subsites($className, $filter = "", $sort = "", $join = "", $limit = "") {
		$result = DataObject::get($className, $filter, $sort, $join, $limit);
		$result = $result->setDataQueryParam('Subsite.filter', false);
		return $result;
	}

	/**
	 * Disable the sub-site filtering; queries will select from all subsites
	 */
	public static function disable_subsite_filter($disabled = true) {
		self::$disable_subsite_filter = $disabled;
	}
	
	/**
	 * Flush caches on database reset
	 */
	public static function on_db_reset() {
		self::$_cache_accessible_sites = array();
		self::$_cache_subsite_for_domain = array();
	}
	
	/**
	 * Return all subsites, regardless of permissions (augmented with main site).
	 *
	 * @return SS_List List of {@link Subsite} objects (DataList or ArrayList).
	 */
	public static function all_sites($includeMainSite = true, $mainSiteTitle = "Main site") {
		$subsites = Subsite::get();

		if($includeMainSite) {
			$subsites = $subsites->toArray();

			$mainSite = new Subsite();
			$mainSite->Title = $mainSiteTitle;
			array_unshift($subsites, $mainSite);

			$subsites = ArrayList::create($subsites);
		}

		return $subsites;
	}

	/*
	 * Returns an ArrayList of the subsites accessible to the current user.
	 * It's enough for any section to be accessible for the site to be included.
	 *
	 * @return ArrayList of {@link Subsite} instances.
	 */
	public static function all_accessible_sites($includeMainSite = true, $mainSiteTitle = "Main site", $member = null) {
		// Rationalise member arguments
		if(!$member) $member = Member::currentUser();
		if(!$member) return new ArrayList();
		if(!is_object($member)) $member = DataObject::get_by_id('Member', $member);

		$subsites = new ArrayList();

		// Collect subsites for all sections.
		$menu = CMSMenu::get_viewable_menu_items();
		foreach($menu as $candidate) {
			if ($candidate->controller) {
				$accessibleSites = singleton($candidate->controller)->sectionSites(
					$includeMainSite,
					$mainSiteTitle,
					$member
				);

				// Replace existing keys so no one site appears twice.
				$subsites->merge($accessibleSites);
			}
		}

		$subsites->removeDuplicates();

		return $subsites;
	}

	/**
	 * Return the subsites that the current user can access by given permission.
	 * Sites will only be included if they have a Title.
	 *
	 * @param $permCode array|string Either a single permission code or an array of permission codes.
	 * @param $includeMainSite If true, the main site will be included if appropriate.
	 * @param $mainSiteTitle The label to give to the main site
	 * @param $member
	 * @return DataList of {@link Subsite} instances
	 */
	public static function accessible_sites($permCode, $includeMainSite = true, $mainSiteTitle = "Main site", $member = null) {
		// Rationalise member arguments
		if(!$member) $member = Member::currentUser();
		if(!$member) return new ArrayList();
		if(!is_object($member)) $member = DataObject::get_by_id('Member', $member);

		// Rationalise permCode argument 
		if(is_array($permCode))	$SQL_codes = "'" . implode("', '", Convert::raw2sql($permCode)) . "'";
		else $SQL_codes = "'" . Convert::raw2sql($permCode) . "'";
		
		// Cache handling
		$cacheKey = $SQL_codes . '-' . $member->ID . '-' . $includeMainSite . '-' . $mainSiteTitle;
		if(isset(self::$_cache_accessible_sites[$cacheKey])) {
			return self::$_cache_accessible_sites[$cacheKey];
		}

		$subsites = DataList::create('Subsite')
			->where("\"Subsite\".\"Title\" != ''")
			->leftJoin('Group_Subsites', "\"Group_Subsites\".\"SubsiteID\" = \"Subsite\".\"ID\"")
			->innerJoin('Group', "\"Group\".\"ID\" = \"Group_Subsites\".\"GroupID\" OR \"Group\".\"AccessAllSubsites\" = 1")
			->innerJoin('Group_Members', "\"Group_Members\".\"GroupID\"=\"Group\".\"ID\" AND \"Group_Members\".\"MemberID\" = $member->ID")
			->innerJoin('Permission', "\"Group\".\"ID\"=\"Permission\".\"GroupID\" AND \"Permission\".\"Code\" IN ($SQL_codes, 'CMS_ACCESS_LeftAndMain', 'ADMIN')");

		if(!$subsites) $subsites = new ArrayList();

		$rolesSubsites = DataList::create('Subsite')
			->where("\"Subsite\".\"Title\" != ''")
			->leftJoin('Group_Subsites', "\"Group_Subsites\".\"SubsiteID\" = \"Subsite\".\"ID\"")
			->innerJoin('Group', "\"Group\".\"ID\" = \"Group_Subsites\".\"GroupID\" OR \"Group\".\"AccessAllSubsites\" = 1")
			->innerJoin('Group_Members', "\"Group_Members\".\"GroupID\"=\"Group\".\"ID\" AND \"Group_Members\".\"MemberID\" = $member->ID")
			->innerJoin('Group_Roles', "\"Group_Roles\".\"GroupID\"=\"Group\".\"ID\"")
			->innerJoin('PermissionRole', "\"Group_Roles\".\"PermissionRoleID\"=\"PermissionRole\".\"ID\"")
			->innerJoin('PermissionRoleCode', "\"PermissionRole\".\"ID\"=\"PermissionRoleCode\".\"RoleID\" AND \"PermissionRoleCode\".\"Code\" IN ($SQL_codes, 'CMS_ACCESS_LeftAndMain', 'ADMIN')");

		if(!$subsites && $rolesSubsites) return $rolesSubsites;

		$subsites = new ArrayList($subsites->toArray());

		if($rolesSubsites) foreach($rolesSubsites as $subsite) {
			if(!$subsites->find('ID', $subsite->ID)) {
				$subsites->push($subsite);
			}
		}

		if($includeMainSite) {
			if(!is_array($permCode)) $permCode = array($permCode);
			if(self::hasMainSitePermission($member, $permCode)) {
				$subsites=$subsites->toArray();
				
				$mainSite = new Subsite();
				$mainSite->Title = $mainSiteTitle;
				array_unshift($subsites, $mainSite);
				$subsites=ArrayList::create($subsites);
			}
		}
		
		self::$_cache_accessible_sites[$cacheKey] = $subsites;

		return $subsites;
	}
	
	/**
	 * Write a host->domain map to subsites/host-map.php
	 *
	 * This is used primarily when using subsites in conjunction with StaticPublisher
	 *
	 * @param string $file - filepath of the host map to be written
	 * @return void
	 */
	public static function writeHostMap($file = null) {
		if (!self::$write_hostmap) return;
		
		if (!$file) $file = Director::baseFolder().'/subsites/host-map.php';
		$hostmap = array();
		
		$subsites = DataObject::get('Subsite');
		
		if ($subsites) foreach($subsites as $subsite) {
			$domains = $subsite->Domains();
			if ($domains) foreach($domains as $domain) {
				$domainStr = $domain->Domain;
				if(!self::$strict_subdomain_matching) $domainStr = preg_replace('/^www\./', '', $domainStr);
				$hostmap[$domainStr] = $subsite->domain(); 
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
	
	/**
	 * Checks if a member can be granted certain permissions, regardless of the subsite context.
	 * Similar logic to {@link Permission::checkMember()}, but only returns TRUE
	 * if the member is part of a group with the "AccessAllSubsites" flag set.
	 * If more than one permission is passed to the method, at least one of them must
	 * be granted for if to return TRUE.
	 * 
	 * @todo Allow permission inheritance through group hierarchy.
	 * 
	 * @param Member Member to check against. Defaults to currently logged in member
	 * @param Array Permission code strings. Defaults to "ADMIN".
	 * @return boolean
	 */
	public static function hasMainSitePermission($member = null, $permissionCodes = array('ADMIN')) {
		if(!is_array($permissionCodes))
			user_error('Permissions must be passed to Subsite::hasMainSitePermission as an array', E_USER_ERROR);

		if(!$member && $member !== FALSE) $member = Member::currentUser();

		if(!$member) return false;
		
		if(!in_array("ADMIN", $permissionCodes)) $permissionCodes[] = "ADMIN";

		$SQLa_perm = Convert::raw2sql($permissionCodes);
		$SQL_perms = join("','", $SQLa_perm);
		$memberID = (int)$member->ID;
		
		// Count this user's groups which can access the main site
		$groupCount = DB::query("
			SELECT COUNT(\"Permission\".\"ID\")
			FROM \"Permission\"
			INNER JOIN \"Group\" ON \"Group\".\"ID\" = \"Permission\".\"GroupID\" AND \"Group\".\"AccessAllSubsites\" = 1
			INNER JOIN \"Group_Members\" ON \"Group_Members\".\"GroupID\" = \"Permission\".\"GroupID\"
			WHERE \"Permission\".\"Code\" IN ('$SQL_perms')
			AND \"MemberID\" = {$memberID}
		")->value();

		// Count this user's groups which have a role that can access the main site
		$roleCount = DB::query("
			SELECT COUNT(\"PermissionRoleCode\".\"ID\")
			FROM \"Group\"
			INNER JOIN \"Group_Members\" ON \"Group_Members\".\"GroupID\" = \"Group\".\"ID\"
			INNER JOIN \"Group_Roles\" ON \"Group_Roles\".\"GroupID\"=\"Group\".\"ID\"
			INNER JOIN \"PermissionRole\" ON \"Group_Roles\".\"PermissionRoleID\"=\"PermissionRole\".\"ID\"
			INNER JOIN \"PermissionRoleCode\" ON \"PermissionRole\".\"ID\"=\"PermissionRoleCode\".\"RoleID\"
			WHERE \"PermissionRoleCode\".\"Code\" IN ('$SQL_perms')
			AND \"Group\".\"AccessAllSubsites\" = 1
			AND \"MemberID\" = {$memberID}
		")->value();

		// There has to be at least one that allows access.
		return ($groupCount + $roleCount > 0);
	}
	
	/**
	 *
	 * @var array
	 */
	private static $db = array(
		'Title' => 'Varchar(255)',
		'RedirectURL' => 'Varchar(255)',
		'DefaultSite' => 'Boolean',
		'Theme' => 'Varchar',
		'Language' => 'Varchar(6)',

		// Used to hide unfinished/private subsites from public view.
		// If unset, will default to true
		'IsPublic' => 'Boolean',
		
		// Comma-separated list of disallowed page types
		'PageTypeBlacklist' => 'Text',
	);

	/**
	 *
	 * @var array
	 */
	private static $has_many = array(
		'Domains' => 'SubsiteDomain',
	);
	
	/**
	 *
	 * @var array
	 */
	private static $belongs_many_many = array(
		"Groups" => "Group",
	);

	/**
	 *
	 * @var array
	 */
	private static $defaults = array(
		'IsPublic' => 1
	);

	/**
	 *
	 * @var array
	 */
	private static $searchable_fields = array(
		'Title',
		'Domains.Domain',
		'IsPublic',	
	);
	
	/**
	 *
	 * @var string
	 */
	private static $default_sort = "\"Title\" ASC";
	
	/**
	 * @todo Possible security issue, don't grant edit permissions to everybody.
	 * @return boolean
	 */
	public function canEdit($member = false) {
		return true;
	}
	
	/**
	 * 
	 * @return array
	 */
	public function providePermissions() {
		return array(
			'SUBSITE_ASSETS_CREATE_SUBSITE' => array(
				'name' => _t('Subsite.MANAGE_ASSETS', 'Manage assets for subsites'),
				'category' => _t('Permissions.PERMISSIONS_CATEGORY', 'Roles and access permissions'),
				'help' => _t('Subsite.MANAGE_ASSETS_HELP', 'Ability to select the subsite to which an asset folder belongs. Requires "Access to Files & Images."'),
				'sort' => 300
			)
		);
	}
	
	/**
	 * Show the configuration fields for each subsite
	 * 
	 * @return FieldList
	 */
	public function getCMSFields() {
		if($this->ID!=0) {
			$domainTable = new GridField(
				"Domains", 
				_t('Subsite.DomainsListTitle',"Domains"), 
				$this->Domains(), 
				GridFieldConfig_RecordEditor::create(10)
			);
		}else {
			$domainTable = new LiteralField(
				'Domains', 
				'<p>'._t('Subsite.DOMAINSAVEFIRST', 'You can only add domains after saving for the first time').'</p>'
			);
		}
			
		$languageSelector = new DropdownField(
			'Language', 
			$this->fieldLabel('Language'),
			i18n::get_common_locales()
		);
		
		$pageTypeMap = array();
		$pageTypes = SiteTree::page_type_classes();
		foreach($pageTypes as $pageType) {
			$pageTypeMap[$pageType] = singleton($pageType)->i18n_singular_name();
		}
		asort($pageTypeMap);

		$fields = new FieldList(
			$subsiteTabs = new TabSet('Root',
				new Tab(
					'Configuration',
					_t('Subsite.TabTitleConfig', 'Configuration'),
					new HeaderField($this->getClassName() . ' configuration', 2),
					new TextField('Title', $this->fieldLabel('Title'), $this->Title),
					
					new HeaderField(
						_t('Subsite.DomainsHeadline',"Domains for this subsite")
					),
					$domainTable,
					$languageSelector,
					// new TextField('RedirectURL', 'Redirect to URL', $this->RedirectURL),
					new CheckboxField('DefaultSite', $this->fieldLabel('DefaultSite'), $this->DefaultSite),
					new CheckboxField('IsPublic', $this->fieldLabel('IsPublic'), $this->IsPublic),

					new DropdownField('Theme',$this->fieldLabel('Theme'), $this->allowedThemes(), $this->Theme),
					
					
					new LiteralField(
						'PageTypeBlacklistToggle',
						sprintf(
							'<div class="field"><a href="#" id="PageTypeBlacklistToggle">%s</a></div>',
							_t('Subsite.PageTypeBlacklistField', 'Disallow page types?')
						)
					),
					new CheckboxSetField(
						'PageTypeBlacklist', 
						false,
						$pageTypeMap
					)
				)
			),
			new HiddenField('ID', '', $this->ID),
			new HiddenField('IsSubsite', '', 1)
		);

		$subsiteTabs->addExtraClass('subsite-model');

		$this->extend('updateCMSFields', $fields);
		return $fields;
	}
	
	/**
	 * 
	 * @param boolean $includerelations
	 * @return array
	 */
	public function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);
		$labels['Title'] = _t('Subsites.TitleFieldLabel', 'Subsite Name');
		$labels['RedirectURL'] = _t('Subsites.RedirectURLFieldLabel', 'Redirect URL');
		$labels['DefaultSite'] = _t('Subsites.DefaultSiteFieldLabel', 'Default site');
		$labels['Theme'] = _t('Subsites.ThemeFieldLabel', 'Theme');
		$labels['Language'] = _t('Subsites.LanguageFieldLabel', 'Language');
		$labels['IsPublic'] = _t('Subsites.IsPublicFieldLabel', 'Enable public access');
		$labels['PageTypeBlacklist'] = _t('Subsites.PageTypeBlacklistFieldLabel', 'Page Type Blacklist');
		$labels['Domains.Domain'] = _t('Subsites.DomainFieldLabel', 'Domain');
		$labels['PrimaryDomain'] = _t('Subsites.PrimaryDomainFieldLabel', 'Primary Domain');

		return $labels;
	}

	/**
	 * 
	 * @return array
	 */
	public function summaryFields() {
		return array(
			'Title' => $this->fieldLabel('Title'),
			'PrimaryDomain' => $this->fieldLabel('PrimaryDomain'),
			'IsPublic' => _t('Subsite.IsPublicHeaderField','Active subsite'),
		);
	}
	
	/**
	 * Return the themes that can be used with this subsite, as an array of themecode => description
	 * 
	 * @return array
	 */
	public function allowedThemes() {
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
	 * @return string Current locale of the subsite
	 */
	public function getLanguage() {
		if($this->getField('Language')) {
			return $this->getField('Language');
		} else {
			return i18n::get_locale();
		}
	}

	/**
	 * 
	 * @return ValidationResult
	 */
	public function validate() {
		$result = parent::validate();
		if(!$this->Title) {
			$result->error(_t('Subsite.ValidateTitle', 'Please add a "Title"'));
		}
		return $result;
	}

	/**
	 * Whenever a Subsite is written, rewrite the hostmap
	 *
	 * @return void
	 */
	public function onAfterWrite() {
		Subsite::writeHostMap();
		parent::onAfterWrite();
	}
	
	/**
	 * Return the primary domain of this site. Tries to "normalize" the domain name,
	 * by replacing potential wildcards.
	 * 
	 * @return string The full domain name of this subsite (without protocol prefix)
	 */
	public function domain() {
		if($this->ID) {
			$domains = DataObject::get("SubsiteDomain", "\"SubsiteID\" = $this->ID", "\"IsPrimary\" DESC","", 1);
			if($domains && $domains->Count()>0) {
				$domain = $domains->First()->Domain;
				// If there are wildcards in the primary domain (not recommended), make some
				// educated guesses about what to replace them with:
				$domain = preg_replace('/\.\*$/',".$_SERVER[HTTP_HOST]", $domain);
				// Default to "subsite." prefix for first wildcard
				// TODO Whats the significance of "subsite" in this context?!
				$domain = preg_replace('/^\*\./',"subsite.", $domain);
				// *Only* removes "intermediate" subdomains, so 'subdomain.www.domain.com' becomes 'subdomain.domain.com'
				$domain = str_replace('.www.','.', $domain);
				
				return $domain;
			}
			
		// SubsiteID = 0 is often used to refer to the main site, just return $_SERVER['HTTP_HOST']
		} else {
			return $_SERVER['HTTP_HOST'];
		}
	}
	
	/**
	 * 
	 * @return string - The full domain name of this subsite (without protocol prefix)
	 */
	public function getPrimaryDomain() {
		return $this->domain();
	}

	/**
	 * 
	 * @return string 
	 */
	public function absoluteBaseURL() {
		return "http://" . $this->domain() . Director::baseURL();
	}

	/**
	 * @todo getClassName is redundant, already stored as a database field?
	 */
	public function getClassName() {
		return $this->class;
	}

	/**
	 * Javascript admin action to duplicate this subsite
	 * 
	 * @return string - javascript 
	 */
	public function adminDuplicate() {
		$newItem = $this->duplicate();
		$message = _t(
			'Subsite.CopyMessage',
			'Created a copy of {title}',
			array('title' => Convert::raw2js($this->Title))
		);
		
		return <<<JS
			statusMessage($message, 'good');
			$('Form_EditForm').loadURLFromServer('admin/subsites/show/$newItem->ID');
JS;
	}

	/**
	 * Make this subsite the current one
	 */
	public function activate() {
		Subsite::changeSubsite($this);
	}

	/**
	 * 
	 * @param array $permissionCodes
	 * @return DataList
	 */
	public function getMembersByPermission($permissionCodes = array('ADMIN')){
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

	/**
	 * Duplicate this subsite
	 */
	public function duplicate($doWrite = true) {
		$duplicate = parent::duplicate($doWrite);

		$oldSubsiteID = Session::get('SubsiteID');
		self::changeSubsite($this->ID);

		/*
		 * Copy data from this object to the given subsite. Does this using an iterative depth-first search.
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
					self::changeSubsite($duplicate->ID); //Change to destination subsite
					
					$childClone = $child->duplicateToSubsite($duplicate, false);
					$childClone->ParentID = $destParentID;
					$childClone->writeToStage('Stage');
					$childClone->publish('Stage', 'Live');

					self::changeSubsite($this->ID); //Change Back to this subsite

					array_push($stack, array($child->ID, $childClone->ID));
				}
			}
		}

		self::changeSubsite($oldSubsiteID);

		return $duplicate;
	}
}
