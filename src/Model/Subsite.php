<?php

namespace SilverStripe\Subsites\Model;

use SilverStripe\Admin\CMSMenu;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\i18n\Data\Intl\IntlLocales;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\SS_List;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Subsites\State\SubsiteState;
use SilverStripe\Versioned\Versioned;
use UnexpectedValueException;

/**
 * A dynamically created subsite. SiteTree objects can now belong to a subsite.
 * You can simulate subsite access without setting up virtual hosts by appending ?SubsiteID=<ID> to the request.
 *
 * @package subsites
 */
class Subsite extends DataObject
{

    private static $table_name = 'Subsite';

    /**
     * @var boolean $disable_subsite_filter If enabled, bypasses the query decoration
     * to limit DataObject::get*() calls to a specific subsite. Useful for debugging. Note that
     * for now this is left as a public static property to avoid having to nest and mutate the
     * configuration manifest.
     */
    public static $disable_subsite_filter = false;

    /**
     * Allows you to force a specific subsite ID, or comma separated list of IDs.
     * Only works for reading. An object cannot be written to more than 1 subsite.
     *
     * @deprecated 2.0.0..3.0.0 Use SubsiteState::singleton()->withState() instead.
     */
    public static $force_subsite = null;

    /**
     * Whether to write a host-map.php file
     *
     * @config
     * @var boolean
     */
    private static $write_hostmap = true;

    /**
     * Memory cache of accessible sites
     *
     * @array
     */
    protected static $cache_accessible_sites = [];

    /**
     * Memory cache of subsite id for domains
     *
     * @var array
     */
    protected static $cache_subsite_for_domain = [];

    /**
     * Numeric array of all themes which are allowed to be selected for all subsites.
     * Corresponds to subfolder names within the /themes folder. By default, all themes contained in this folder
     * are listed.
     *
     * @var array
     */
    protected static $allowed_themes = [];

    /**
     * If set to TRUE, don't assume 'www.example.com' and 'example.com' are the same.
     * Doesn't affect wildcard matching, so '*.example.com' will match 'www.example.com' (but not 'example.com')
     * in both TRUE or FALSE setting.
     *
     * @config
     * @var boolean
     */
    private static $strict_subdomain_matching = false;

    /**
     * Respects the IsPublic flag when retrieving subsites
     *
     * @config
     * @var boolean
     */
    private static $check_is_public = true;

    /*** @return array
     */
    private static $summary_fields = [
        'Title',
        'PrimaryDomain',
        'IsPublic'
    ];

    /**
     * @var array
     */
    private static $db = [
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
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'Domains' => SubsiteDomain::class,
    ];

    /**
     * @var array
     */
    private static $belongs_many_many = [
        'Groups' => Group::class,
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'IsPublic' => 1
    ];

    /**
     * @var array
     */
    private static $searchable_fields = [
        'Title',
        'Domains.Domain',
        'IsPublic',
    ];

    /**
     * @var string
     */
    private static $default_sort = '"Title" ASC';

    /**
     * Set allowed themes
     *
     * @param array $themes - Numeric array of all themes which are allowed to be selected for all subsites.
     */
    public static function set_allowed_themes($themes)
    {
        self::$allowed_themes = $themes;
    }

    /**
     * Gets the subsite currently set in the session.
     *
     * @uses ControllerSubsites->controllerAugmentInit()
     * @return DataObject The current Subsite
     */
    public static function currentSubsite()
    {
        return Subsite::get()->byID(SubsiteState::singleton()->getSubsiteId());
    }

    /**
     * This function gets the current subsite ID from the session. It used in the backend so Ajax requests
     * use the correct subsite. The frontend handles subsites differently. It calls getSubsiteIDForDomain
     * directly from ModelAsController::getNestedController. Only gets Subsite instances which have their
     * {@link IsPublic} flag set to TRUE.
     *
     * You can simulate subsite access without creating virtual hosts by appending ?SubsiteID=<ID> to the request.
     *
     * @return int ID of the current subsite instance
     *
     * @deprecated 2.0..3.0 Use SubsiteState::singleton()->getSubsiteId() instead
     */
    public static function currentSubsiteID()
    {
        Deprecation::notice('3.0', 'Use SubsiteState::singleton()->getSubsiteId() instead');
        return SubsiteState::singleton()->getSubsiteId();
    }

    /**
     * Switch to another subsite through storing the subsite identifier in the current PHP session.
     * Only takes effect when {@link SubsiteState::singleton()->getUseSessions()} is set to TRUE.
     *
     * @param int|Subsite $subsite Either the ID of the subsite, or the subsite object itself
     */
    public static function changeSubsite($subsite)
    {
        // Session subsite change only meaningful if the session is active.
        // Otherwise we risk setting it to wrong value, e.g. if we rely on currentSubsiteID.
        if (!SubsiteState::singleton()->getUseSessions()) {
            return;
        }

        if (is_object($subsite)) {
            $subsiteID = $subsite->ID;
        } else {
            $subsiteID = $subsite;
        }

        SubsiteState::singleton()->setSubsiteId($subsiteID);

        // Set locale
        if (is_object($subsite) && $subsite->Language !== '') {
            $locale = (new IntlLocales())->localeFromLang($subsite->Language);
            if ($locale) {
                i18n::set_locale($locale);
            }
        }

        Permission::reset();
    }

    /**
     * Get a matching subsite for the given host, or for the current HTTP_HOST.
     * Supports "fuzzy" matching of domains by placing an asterisk at the start of end of the string,
     * for example matching all subdomains on *.example.com with one subsite,
     * and all subdomains on *.example.org on another.
     *
     * @param $host string The host to find the subsite for.  If not specified, $_SERVER['HTTP_HOST'] is used.
     * @param bool $checkPermissions
     * @return int Subsite ID
     */
    public static function getSubsiteIDForDomain($host = null, $checkPermissions = true)
    {
        if ($host == null && isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        }

        $matchingDomains = null;
        $cacheKey = null;
        if ($host) {
            if (!static::config()->get('strict_subdomain_matching')) {
                $host = preg_replace('/^www\./', '', $host);
            }

            $currentUserId = Security::getCurrentUser() ? Security::getCurrentUser()->ID : 0;
            $cacheKey = implode('_', [$host, $currentUserId, static::config()->get('check_is_public')]);
            if (isset(self::$cache_subsite_for_domain[$cacheKey])) {
                return self::$cache_subsite_for_domain[$cacheKey];
            }

            $SQL_host = Convert::raw2sql($host);

            $schema = DataObject::getSchema();

            /** @skipUpgrade */
            $domainTableName = $schema->tableName(SubsiteDomain::class);
            if (!in_array(strtolower($domainTableName), array_map("strtolower", DB::table_list())) ) { // case sensitive table names
                // Table hasn't been created yet. Might be a dev/build, skip.
                return 0;
            }

            $subsiteTableName = $schema->tableName(__CLASS__);
            /** @skipUpgrade */
            $matchingDomains = DataObject::get(
                SubsiteDomain::class,
                "'$SQL_host' LIKE replace(\"{$domainTableName}\".\"Domain\",'*','%')",
                '"IsPrimary" DESC'
            )->innerJoin(
                $subsiteTableName,
                '"' . $subsiteTableName . '"."ID" = "SubsiteDomain"."SubsiteID" AND "'
                    . $subsiteTableName . '"."IsPublic"=1'
            );
        }

        if ($matchingDomains && $matchingDomains->count()) {
            $subsiteIDs = array_unique($matchingDomains->column('SubsiteID'));
            $subsiteDomains = array_unique($matchingDomains->column('Domain'));
            if (sizeof($subsiteIDs) > 1) {
                throw new UnexpectedValueException(sprintf(
                    "Multiple subsites match on '%s': %s",
                    $host,
                    implode(',', $subsiteDomains)
                ));
            }

            $subsiteID = $subsiteIDs[0];
        } else {
            if ($default = DataObject::get_one(Subsite::class, '"DefaultSite" = 1')) {
                // Check for a 'default' subsite
                $subsiteID = $default->ID;
            } else {
                // Default subsite id = 0, the main site
                $subsiteID = 0;
            }
        }

        if ($cacheKey) {
            self::$cache_subsite_for_domain[$cacheKey] = $subsiteID;
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
    public static function get_from_all_subsites($className, $filter = '', $sort = '', $join = '', $limit = '')
    {
        $result = DataObject::get($className, $filter, $sort, $join, $limit);
        $result = $result->setDataQueryParam('Subsite.filter', false);
        return $result;
    }

    /**
     * Disable the sub-site filtering; queries will select from all subsites
     * @param bool $disabled
     */
    public static function disable_subsite_filter($disabled = true)
    {
        self::$disable_subsite_filter = $disabled;
    }

    /**
     * Flush caches on database reset
     */
    public static function on_db_reset()
    {
        self::$cache_accessible_sites = [];
        self::$cache_subsite_for_domain = [];
    }

    /**
     * Return all subsites, regardless of permissions (augmented with main site).
     *
     * @param bool $includeMainSite
     * @param string $mainSiteTitle
     * @return SS_List List of <a href='psi_element://Subsite'>Subsite</a> objects (DataList or ArrayList).
     * objects (DataList or ArrayList).
     */
    public static function all_sites($includeMainSite = true, $mainSiteTitle = 'Main site')
    {
        $subsites = Subsite::get();

        if ($includeMainSite) {
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
    public static function all_accessible_sites($includeMainSite = true, $mainSiteTitle = 'Main site', $member = null)
    {
        // Rationalise member arguments
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        if (!$member) {
            return ArrayList::create();
        }
        if (!is_object($member)) {
            $member = DataObject::get_by_id(Member::class, $member);
        }

        $subsites = ArrayList::create();

        // Collect subsites for all sections.
        $menu = CMSMenu::get_viewable_menu_items();
        foreach ($menu as $candidate) {
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
     * @param $includeMainSite bool If true, the main site will be included if appropriate.
     * @param $mainSiteTitle string The label to give to the main site
     * @param $member int|Member The member attempting to access the sites
     * @return DataList|ArrayList of {@link Subsite} instances
     */
    public static function accessible_sites(
        $permCode,
        $includeMainSite = true,
        $mainSiteTitle = 'Main site',
        $member = null
    ) {

        // Rationalise member arguments
        if (!$member) {
            $member = Member::currentUser();
        }
        if (!$member) {
            return new ArrayList();
        }
        if (!is_object($member)) {
            $member = DataObject::get_by_id(Member::class, $member);
        }

        // Rationalise permCode argument
        if (is_array($permCode)) {
            $SQL_codes = "'" . implode("', '", Convert::raw2sql($permCode)) . "'";
        } else {
            $SQL_codes = "'" . Convert::raw2sql($permCode) . "'";
        }

        // Cache handling
        $cacheKey = $SQL_codes . '-' . $member->ID . '-' . $includeMainSite . '-' . $mainSiteTitle;
        if (isset(self::$cache_accessible_sites[$cacheKey])) {
            return self::$cache_accessible_sites[$cacheKey];
        }

        /** @skipUpgrade */
        $subsites = DataList::create(Subsite::class)
            ->where("\"Subsite\".\"Title\" != ''")
            ->leftJoin('Group_Subsites', '"Group_Subsites"."SubsiteID" = "Subsite"."ID"')
            ->innerJoin(
                'Group',
                '"Group"."ID" = "Group_Subsites"."GroupID" OR "Group"."AccessAllSubsites" = 1'
            )
            ->innerJoin(
                'Group_Members',
                "\"Group_Members\".\"GroupID\"=\"Group\".\"ID\" AND \"Group_Members\".\"MemberID\" = $member->ID"
            )
            ->innerJoin(
                'Permission',
                "\"Group\".\"ID\"=\"Permission\".\"GroupID\" 
                AND \"Permission\".\"Code\" 
                IN ($SQL_codes, 'CMS_ACCESS_LeftAndMain', 'ADMIN')"
            );

        if (!$subsites) {
            $subsites = new ArrayList();
        }

        /** @var DataList $rolesSubsites */
        /** @skipUpgrade */
        $rolesSubsites = DataList::create(Subsite::class)
            ->where("\"Subsite\".\"Title\" != ''")
            ->leftJoin('Group_Subsites', '"Group_Subsites"."SubsiteID" = "Subsite"."ID"')
            ->innerJoin(
                'Group',
                '"Group"."ID" = "Group_Subsites"."GroupID" OR "Group"."AccessAllSubsites" = 1'
            )
            ->innerJoin(
                'Group_Members',
                "\"Group_Members\".\"GroupID\"=\"Group\".\"ID\" AND \"Group_Members\".\"MemberID\" = $member->ID"
            )
            ->innerJoin('Group_Roles', '"Group_Roles"."GroupID"="Group"."ID"')
            ->innerJoin('PermissionRole', '"Group_Roles"."PermissionRoleID"="PermissionRole"."ID"')
            ->innerJoin(
                'PermissionRoleCode',
                "\"PermissionRole\".\"ID\"=\"PermissionRoleCode\".\"RoleID\" 
                AND \"PermissionRoleCode\".\"Code\" 
                IN ($SQL_codes, 'CMS_ACCESS_LeftAndMain', 'ADMIN')"
            );

        if (!$subsites && $rolesSubsites) {
            return $rolesSubsites;
        }

        $subsites = new ArrayList($subsites->toArray());

        if ($rolesSubsites) {
            foreach ($rolesSubsites as $subsite) {
                if (!$subsites->find('ID', $subsite->ID)) {
                    $subsites->push($subsite);
                }
            }
        }

        if ($includeMainSite) {
            if (!is_array($permCode)) {
                $permCode = [$permCode];
            }
            if (self::hasMainSitePermission($member, $permCode)) {
                $subsites = $subsites->toArray();

                $mainSite = new Subsite();
                $mainSite->Title = $mainSiteTitle;
                array_unshift($subsites, $mainSite);
                $subsites = ArrayList::create($subsites);
            }
        }

        self::$cache_accessible_sites[$cacheKey] = $subsites;

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
    public static function writeHostMap($file = null)
    {
        if (!static::config()->get('write_hostmap')) {
            return;
        }

        if (!$file) {
            $subsitesPath = ModuleLoader::getModule('silverstripe/subsites')->getRelativePath();
            $file = Director::baseFolder() . $subsitesPath . '/host-map.php';
        }
        $hostmap = [];

        $subsites = DataObject::get(Subsite::class);

        if ($subsites) {
            foreach ($subsites as $subsite) {
                $domains = $subsite->Domains();
                if ($domains) {
                    foreach ($domains as $domain) {
                        $domainStr = $domain->Domain;
                        if (!static::config()->get('strict_subdomain_matching')) {
                            $domainStr = preg_replace('/^www\./', '', $domainStr);
                        }
                        $hostmap[$domainStr] = $subsite->domain();
                    }
                }
                if ($subsite->DefaultSite) {
                    $hostmap['default'] = $subsite->domain();
                }
            }
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
     * @param array $permissionCodes
     * @return bool
     */
    public static function hasMainSitePermission($member = null, $permissionCodes = ['ADMIN'])
    {
        if (!is_array($permissionCodes)) {
            user_error('Permissions must be passed to Subsite::hasMainSitePermission as an array', E_USER_ERROR);
        }

        if (!$member && $member !== false) {
            $member = Security::getCurrentUser();
        }

        if (!$member) {
            return false;
        }

        if (!in_array('ADMIN', $permissionCodes)) {
            $permissionCodes[] = 'ADMIN';
        }

        $SQLa_perm = Convert::raw2sql($permissionCodes);
        $SQL_perms = join("','", $SQLa_perm);
        $memberID = (int)$member->ID;

        // Count this user's groups which can access the main site
        $groupCount = DB::query("
            SELECT COUNT(\"Permission\".\"ID\")
            FROM \"Permission\"
            INNER JOIN \"Group\"
            ON \"Group\".\"ID\" = \"Permission\".\"GroupID\" AND \"Group\".\"AccessAllSubsites\" = 1
            INNER JOIN \"Group_Members\"
            ON \"Group_Members\".\"GroupID\" = \"Permission\".\"GroupID\"
            WHERE \"Permission\".\"Code\"
            IN ('$SQL_perms') AND \"Group_Members\".\"MemberID\" = {$memberID}
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
            AND \"Group_Members\".\"MemberID\" = {$memberID}
        ")->value();

        // There has to be at least one that allows access.
        return ($groupCount + $roleCount > 0);
    }

    /**
     * @todo Possible security issue, don't grant edit permissions to everybody.
     * @param bool $member
     * @return bool
     */
    public function canEdit($member = false)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return true;
    }

    /**
     * Show the configuration fields for each subsite
     *
     * @return FieldList
     */
    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            if ($this->exists()) {
                // Add a GridField for domains to a new tab if the subsite has already been created
                $fields->addFieldsToTab('Root.Domains', [
                    GridField::create(
                        'Domains',
                        '',
                        $this->Domains(),
                        GridFieldConfig_RecordEditor::create(10)
                    )
                ]);
            }

            // Remove the default scaffolded blacklist field, we replace it with a checkbox set field
            // in a wrapper further down. The RedirectURL field is currently not in use.
            $fields->removeByName(['PageTypeBlacklist', 'RedirectURL']);

            $fields->addFieldToTab('Root.Main', DropdownField::create(
                'Language',
                $this->fieldLabel('Language'),
                Injector::inst()->get(IntlLocales::class)->getLocales()
            ), 'DefaultSite');

            $fields->addFieldsToTab('Root.Main', [
                ToggleCompositeField::create(
                    'PageTypeBlacklistToggle',
                    _t(__CLASS__ . '.PageTypeBlacklistField', 'Disallow page types?'),
                    [
                        CheckboxSetField::create('PageTypeBlacklist', '', $this->getPageTypeMap())
                    ]
                )->setHeadingLevel(4),
                HiddenField::create('ID', '', $this->ID),
                HiddenField::create('IsSubsite', '', 1)
            ]);

            // If there are any themes available, add the dropdown
            $themes = $this->allowedThemes();
            if (!empty($themes)) {
                $fields->addFieldToTab(
                    'Root.Main',
                    DropdownField::create('Theme', $this->fieldLabel('Theme'), $this->allowedThemes(), $this->Theme)
                        ->setEmptyString(_t(__CLASS__ . '.ThemeFieldEmptyString', '-')),
                    'PageTypeBlacklistToggle'
                );
            }

            // Targetted by the XHR PJAX JavaScript to reload the subsite list in the CMS
            $fields->fieldByName('Root.Main')->addExtraClass('subsite-model');

            // We don't need the Groups many many tab
            $fields->removeByName('Groups');

            // Rename the main tab to configuration
            $fields->fieldByName('Root.Main')->setTitle(_t(__CLASS__ . '.ConfigurationTab', 'Configuration'));
        });

        return parent::getCMSFields();
    }

    /**
     * Return a list of the different page types available to the CMS
     *
     * @return array
     */
    public function getPageTypeMap()
    {
        $pageTypeMap = [];

        $pageTypes = SiteTree::page_type_classes();
        foreach ($pageTypes as $pageType) {
            $pageTypeMap[$pageType] = singleton($pageType)->i18n_singular_name();
        }

        asort($pageTypeMap);

        return $pageTypeMap;
    }

    /**
     *
     * @param boolean $includerelations
     * @return array
     */
    public function fieldLabels($includerelations = true)
    {
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
     * Return the themes that can be used with this subsite, as an array of themecode => description
     *
     * @return array
     */
    public function allowedThemes()
    {
        if ($themes = self::$allowed_themes) {
            return ArrayLib::valuekey($themes);
        }

        $themes = [];
        if (is_dir(THEMES_PATH)) {
            foreach (scandir(THEMES_PATH) as $theme) {
                if ($theme[0] == '.') {
                    continue;
                }
                $theme = strtok($theme, '_');
                $themes[$theme] = $theme;
            }
            ksort($themes);
        }
        return $themes;
    }

    /**
     * @return string Current locale of the subsite
     */
    public function getLanguage()
    {
        if ($this->getField('Language')) {
            return $this->getField('Language');
        }

        return i18n::get_locale();
    }

    /**
     *
     * @return \SilverStripe\ORM\ValidationResult
     */
    public function validate()
    {
        $result = parent::validate();
        if (!$this->Title) {
            $result->addError(_t(__CLASS__ . '.ValidateTitle', 'Please add a "Title"'));
        }
        return $result;
    }

    /**
     * Whenever a Subsite is written, rewrite the hostmap and create some default pages
     *
     * @return void
     */
    public function onAfterWrite()
    {
        Subsite::writeHostMap();
        if ($this->isChanged('ID')) {
            $this->createDefaultPages();
        }
        parent::onAfterWrite();
    }

    /**
     * Automatically create default pages for new subsites
     */
    protected function createDefaultPages()
    {
        SubsiteState::singleton()->withState(function (SubsiteState $newState) {
            $newState->setSubsiteId($this->ID);

            // Silence DB schema output
            DB::quiet();
            $siteTree = new SiteTree();
            $siteTree->requireDefaultRecords();
        });
    }

    /**
     * Return the primary domain of this site. Tries to "normalize" the domain name,
     * by replacing potential wildcards.
     *
     * @return string The full domain name of this subsite (without protocol prefix)
     */
    public function domain()
    {
        // Get best SubsiteDomain object
        $domainObject = $this->getPrimarySubsiteDomain();
        if ($domainObject) {
            return $domainObject->SubstitutedDomain;
        }

        // If there are no objects, default to the current hostname
        return $_SERVER['HTTP_HOST'];
    }

    /**
     * Finds the primary {@see SubsiteDomain} object for this subsite
     *
     * @return SubsiteDomain
     */
    public function getPrimarySubsiteDomain()
    {
        return $this
            ->Domains()
            ->sort('"IsPrimary" DESC')
            ->first();
    }

    /**
     *
     * @return string - The full domain name of this subsite (without protocol prefix)
     */
    public function getPrimaryDomain()
    {
        return $this->domain();
    }

    /**
     * Get the absolute URL for this subsite
     * @return string
     */
    public function absoluteBaseURL()
    {
        // Get best SubsiteDomain object
        $domainObject = $this->getPrimarySubsiteDomain();
        if ($domainObject) {
            return $domainObject->absoluteBaseURL();
        }

        // Fall back to the current base url
        return Director::absoluteBaseURL();
    }

    /**
     * Javascript admin action to duplicate this subsite
     *
     * @return string - javascript
     */
    public function adminDuplicate()
    {
        $newItem = $this->duplicate();
        $message = _t(
            __CLASS__ . '.CopyMessage',
            'Created a copy of {title}',
            ['title' => Convert::raw2js($this->Title)]
        );

        return <<<JS
            statusMessage($message, 'good');
            $('Form_EditForm').loadURLFromServer('admin/subsites/show/$newItem->ID');
JS;
    }

    /**
     * Make this subsite the current one
     */
    public function activate()
    {
        Subsite::changeSubsite($this);
    }

    /**
     *
     * @param array $permissionCodes
     * @return DataList
     */
    public function getMembersByPermission($permissionCodes = ['ADMIN'])
    {
        if (!is_array($permissionCodes)) {
            user_error('Permissions must be passed to Subsite::getMembersByPermission as an array', E_USER_ERROR);
        }
        $SQL_permissionCodes = Convert::raw2sql($permissionCodes);

        $SQL_permissionCodes = join("','", $SQL_permissionCodes);

        return DataObject::get(
            Member::class,
            "\"Group\".\"SubsiteID\" = $this->ID AND \"Permission\".\"Code\" IN ('$SQL_permissionCodes')",
            '',
            'LEFT JOIN "Group_Members" ON "Member"."ID" = "Group_Members"."MemberID"
            LEFT JOIN "Group" ON "Group"."ID" = "Group_Members"."GroupID"
            LEFT JOIN "Permission" ON "Permission"."GroupID" = "Group"."ID"'
        );
    }

    /**
     * Duplicate this subsite
     * @param bool $doWrite
     * @param string $manyMany
     * @return DataObject
     */
    public function duplicate($doWrite = true, $manyMany = 'many_many')
    {
        $duplicate = parent::duplicate($doWrite);

        $oldSubsiteID = SubsiteState::singleton()->getSubsiteId();
        self::changeSubsite($this->ID);

        /*
         * Copy data from this object to the given subsite. Does this using an iterative depth-first search.
         * This will make sure that the new parents on the new subsite are correct, and there are no funny
         * issues with having to check whether or not the new parents have been added to the site tree
         * when a page, etc, is duplicated
         */
        $stack = [[0, 0]];
        while (count($stack) > 0) {
            list($sourceParentID, $destParentID) = array_pop($stack);
            $children = Versioned::get_by_stage('Page', 'Live', "\"ParentID\" = $sourceParentID", '');

            if ($children) {
                foreach ($children as $child) {
                    self::changeSubsite($duplicate->ID); //Change to destination subsite

                    $childClone = $child->duplicateToSubsite($duplicate, false);
                    $childClone->ParentID = $destParentID;
                    $childClone->writeToStage('Stage');
                    $childClone->copyVersionToStage('Stage', 'Live');

                    self::changeSubsite($this->ID); //Change Back to this subsite

                    array_push($stack, [$child->ID, $childClone->ID]);
                }
            }
        }

        self::changeSubsite($oldSubsiteID);

        return $duplicate;
    }
}
