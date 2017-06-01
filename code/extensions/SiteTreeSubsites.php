<?php

namespace SilverStripe\Subsites\Extensions;


use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\InlineFormAction;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\View\SSViewer;


/**
 * Extension for the SiteTree object to add subsites support
 */
class SiteTreeSubsites extends DataExtension
{

    private static $has_one = [
        'Subsite' => Subsite::class, // The subsite that this page belongs to
    ];

    private static $many_many = array(
        'CrossSubsiteLinkTracking' => SiteTree::class // Stored separately, as the logic for URL rewriting is different
    );

    private static $many_many_extraFields = array(
        'CrossSubsiteLinkTracking' => array('FieldName' => 'Varchar')
    );

    public function isMainSite()
    {
        if ($this->owner->SubsiteID == 0) {
            return true;
        }
        return false;
    }

    /**
     * Update any requests to limit the results to the current site
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        if (Subsite::$disable_subsite_filter) {
            return;
        }
        if ($dataQuery && $dataQuery->getQueryParam('Subsite.filter') === false) {
            return;
        }

        // If you're querying by ID, ignore the sub-site - this is a bit ugly...
        // if(!$query->where || (strpos($query->where[0], ".\"ID\" = ") === false && strpos($query->where[0], ".`ID` = ") === false && strpos($query->where[0], ".ID = ") === false && strpos($query->where[0], "ID = ") !== 0)) {
        if ($query->filtersOnID()) {
            return;
        }

        if (Subsite::$force_subsite) {
            $subsiteID = Subsite::$force_subsite;
        } else {
            /*if($context = DataObject::context_obj()) $subsiteID = (int)$context->SubsiteID;
            else */$subsiteID = (int)Subsite::currentSubsiteID();
        }

        // The foreach is an ugly way of getting the first key :-)
        foreach ($query->getFrom() as $tableName => $info) {
            // The tableName should be SiteTree or SiteTree_Live...
            $siteTreeTableName = SiteTree::getSchema()->tableName(SiteTree::class);
            if (strpos($tableName, $siteTreeTableName) === false) {
                break;
            }
            $query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
            break;
        }
    }

    public function onBeforeWrite()
    {
        if (!$this->owner->ID && !$this->owner->SubsiteID) {
            $this->owner->SubsiteID = Subsite::currentSubsiteID();
        }

        parent::onBeforeWrite();
    }

    public function updateCMSFields(FieldList $fields)
    {
        $subsites = Subsite::accessible_sites('CMS_ACCESS_CMSMain');
        $subsitesMap = array();
        if ($subsites && $subsites->count()) {
            $subsitesToMap = $subsites->exclude('ID', $this->owner->SubsiteID);
            $subsitesMap = $subsitesToMap->map('ID', 'Title');
        }

        // Master page edit field (only allowed from default subsite to avoid inconsistent relationships)
        $isDefaultSubsite = $this->owner->SubsiteID == 0 || $this->owner->Subsite()->DefaultSite;

        if ($isDefaultSubsite && $subsitesMap) {
            $fields->addFieldsToTab(
                'Root.Main',
                ToggleCompositeField::create('SubsiteOperations',
                    _t('SiteTreeSubsites.SubsiteOperations', 'Subsite Operations'),
                    array(
                        new DropdownField('CopyToSubsiteID', _t('SiteTreeSubsites.CopyToSubsite',
                            'Copy page to subsite'), $subsitesMap),
                        new CheckboxField('CopyToSubsiteWithChildren', _t('SiteTreeSubsites.CopyToSubsiteWithChildren', 'Include children pages?')),
                        $copyAction = new InlineFormAction(
                            'copytosubsite',
                            _t('SiteTreeSubsites.CopyAction', 'Copy')
                        )
                    )
                )->setHeadingLevel(4)
            );


//            $copyAction->includeDefaultJS(false);
        }

        // replace readonly link prefix
        $subsite = $this->owner->Subsite();
        $nested_urls_enabled = Config::inst()->get(SiteTree::class, 'nested_urls');
        if ($subsite && $subsite->exists()) {
            // Use baseurl from domain
            $baseLink = $subsite->absoluteBaseURL();

            // Add parent page if enabled
            if($nested_urls_enabled && $this->owner->ParentID) {
                $baseLink = Controller::join_links(
                    $baseLink,
                    $this->owner->Parent()->RelativeLink(true)
                );
            }

            $urlsegment = $fields->dataFieldByName('URLSegment');
            $urlsegment->setURLPrefix($baseLink);
        }
    }

    /**
     * @return SiteConfig
     */
    public function alternateSiteConfig()
    {
        if (!$this->owner->SubsiteID) {
            return false;
        }
        $sc = DataObject::get_one(SiteConfig::class, '"SubsiteID" = ' . $this->owner->SubsiteID);
        if (!$sc) {
            $sc = new SiteConfig();
            $sc->SubsiteID = $this->owner->SubsiteID;
            $sc->Title = _t('Subsite.SiteConfigTitle', 'Your Site Name');
            $sc->Tagline = _t('Subsite.SiteConfigSubtitle', 'Your tagline here');
            $sc->write();
        }
        return $sc;
    }

    /**
     * Only allow editing of a page if the member satisfies one of the following conditions:
     * - Is in a group which has access to the subsite this page belongs to
     * - Is in a group with edit permissions on the "main site"
     *
     * @param null $member
     * @return bool
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }

        // Find the sites that this user has access to
        $goodSites = Subsite::accessible_sites('CMS_ACCESS_CMSMain', true, 'all', $member)->column('ID');

        if (!is_null($this->owner->SubsiteID)) {
            $subsiteID = $this->owner->SubsiteID;
        } else {
            // The relationships might not be available during the record creation when using a GridField.
            // In this case the related objects will have empty fields, and SubsiteID will not be available.
            //
            // We do the second best: fetch the likely SubsiteID from the session. The drawback is this might
            // make it possible to force relations to point to other (forbidden) subsites.
            $subsiteID = Subsite::currentSubsiteID();
        }

        // Return true if they have access to this object's site
        if (!(in_array(0, $goodSites) || in_array($subsiteID, $goodSites))) {
            return false;
        }
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canDelete($member = null)
    {
        if (!$member && $member !== false) {
            $member = Member::currentUser();
        }

        return $this->canEdit($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canAddChildren($member = null)
    {
        if (!$member && $member !== false) {
            $member = Member::currentUser();
        }

        return $this->canEdit($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canPublish($member = null)
    {
        if (!$member && $member !== false) {
            $member = Member::currentUser();
        }

        return $this->canEdit($member);
    }

    /**
     * Create a duplicate of this page and save it to another subsite
     *
     * @param int|Subsite $subsiteID The Subsite to copy to, or its ID
     * @param bool $includeChildren Recursively copy child Pages.
     * @param int $parentID Where to place the Page in the SiteTree's structure.
     *
     * @return SiteTree duplicated page
     */
    public function duplicateToSubsite($subsiteID = null, $includeChildren = false, $parentID = 0)
    {
        if ($subsiteID instanceof Subsite) {
            $subsiteID = $subsiteID->ID;
        }

        $oldSubsite = Subsite::currentSubsiteID();

        if ($subsiteID) {
            Subsite::changeSubsite($subsiteID);
        } else {
            $subsiteID = $oldSubsite;
        }

        $page = $this->owner->duplicate(false);

        $page->CheckedPublicationDifferences = $page->AddedToStage = true;
        $subsiteID = ($subsiteID ? $subsiteID : $oldSubsite);
        $page->SubsiteID = $subsiteID;

        $page->ParentID = $parentID;

        // MasterPageID is here for legacy purposes, to satisfy the subsites_relatedpages module
        $page->MasterPageID = $this->owner->ID;
        $page->write();

        Subsite::changeSubsite($oldSubsite);

        if($includeChildren) {
            foreach($this->owner->AllChildren() as $child) {
                $child->duplicateToSubsite($subsiteID, $includeChildren, $page->ID);
            }
        }

        return $page;
    }

    /**
     * Called by ContentController::init();
     * @param $controller
     */
    public static function contentcontrollerInit($controller)
    {
        $subsite = Subsite::currentSubsite();

        if ($subsite && $subsite->Theme) {
            Config::modify()->set(SSViewer::class, 'theme', Subsite::currentSubsite()->Theme);
        }
    }

    public function alternateAbsoluteLink()
    {
        // Generate the existing absolute URL and replace the domain with the subsite domain.
        // This helps deal with Link() returning an absolute URL.
        $url = Director::absoluteURL($this->owner->Link());
        if ($this->owner->SubsiteID) {
            $url = preg_replace('/\/\/[^\/]+\//', '//' .  $this->owner->Subsite()->domain() . '/', $url);
        }
        return $url;
    }

    /**
     * Use the CMS domain for iframed CMS previews to prevent single-origin violations
     * and SSL cert problems.
     * @param null $action
     * @return string
     */
    public function alternatePreviewLink($action = null)
    {
        $url = Director::absoluteURL($this->owner->Link());
        if ($this->owner->SubsiteID) {
            $url = HTTP::setGetVar('SubsiteID', $this->owner->SubsiteID, $url);
        }
        return $url;
    }

    /**
     * Inject the subsite ID into the content so it can be used by frontend scripts.
     * @param $tags
     * @return string
     */
    public function MetaTags(&$tags)
    {
        if ($this->owner->SubsiteID) {
            $tags .= '<meta name="x-subsite-id" content="' . $this->owner->SubsiteID . "\" />\n";
        }

        return $tags;
    }

    public function augmentSyncLinkTracking()
    {
        // Set LinkTracking appropriately
        $links = HTTP::getLinksIn($this->owner->Content);
        $linkedPages = array();

        if ($links) {
            foreach ($links as $link) {
                if (substr($link, 0, strlen('http://')) == 'http://') {
                    $withoutHttp = substr($link, strlen('http://'));
                    if (strpos($withoutHttp, '/') && strpos($withoutHttp, '/') < strlen($withoutHttp)) {
                        $domain = substr($withoutHttp, 0, strpos($withoutHttp, '/'));
                        $rest = substr($withoutHttp, strpos($withoutHttp, '/') + 1);

                        $subsiteID = Subsite::getSubsiteIDForDomain($domain);
                        if ($subsiteID == 0) {
                            continue;
                        } // We have no idea what the domain for the main site is, so cant track links to it

                    $origDisableSubsiteFilter = Subsite::$disable_subsite_filter;
                        Subsite::disable_subsite_filter(true);
                        $candidatePage = DataObject::get_one(SiteTree::class, "\"URLSegment\" = '" . Convert::raw2sql(urldecode($rest)) . "' AND \"SubsiteID\" = " . $subsiteID, false);
                        Subsite::disable_subsite_filter($origDisableSubsiteFilter);

                        if ($candidatePage) {
                            $linkedPages[] = $candidatePage->ID;
                        } else {
                            $this->owner->HasBrokenLink = true;
                        }
                    }
                }
            }
        }

        $this->owner->CrossSubsiteLinkTracking()->setByIDList($linkedPages);
    }

    /**
     * Ensure that valid url segments are checked within the correct subsite of the owner object,
     * even if the current subsiteID is set to some other subsite.
     *
     * @return null|bool Either true or false, or null to not influence result
     */
    public function augmentValidURLSegment()
    {
        // If this page is being filtered in the current subsite, then no custom validation query is required.
        $subsite = Subsite::$force_subsite ?: Subsite::currentSubsiteID();
        if (empty($this->owner->SubsiteID) || $subsite == $this->owner->SubsiteID) {
            return null;
        }

        // Backup forced subsite
        $prevForceSubsite = Subsite::$force_subsite;
        Subsite::$force_subsite = $this->owner->SubsiteID;

        // Repeat validation in the correct subsite
        $isValid = $this->owner->validURLSegment();

        // Restore
        Subsite::$force_subsite = $prevForceSubsite;

        return (bool)$isValid;
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific
     */
    public function cacheKeyComponent()
    {
        return 'subsite-'.Subsite::currentSubsiteID();
    }

    /**
     * @param Member
     * @return boolean|null
     */
    public function canCreate($member = null)
    {
        // Typically called on a singleton, so we're not using the Subsite() relation
        $subsite = Subsite::currentSubsite();
        if ($subsite && $subsite->exists() && $subsite->PageTypeBlacklist) {
            $blacklisted = explode(',', $subsite->PageTypeBlacklist);
            // All subclasses need to be listed explicitly
            if (in_array($this->owner->class, $blacklisted)) {
                return false;
            }
        }
    }

}
