<?php

namespace SilverStripe\Subsites\Extensions;

use Page;
use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Map;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\Service\ThemeResolver;
use SilverStripe\Subsites\State\SubsiteState;
use SilverStripe\View\SSViewer;

/**
 * Extension for the SiteTree object to add subsites support
 */
class SiteTreeSubsites extends DataExtension
{
    private static $has_one = [
        'Subsite' => Subsite::class, // The subsite that this page belongs to
    ];

    private static $many_many = [
        'CrossSubsiteLinkTracking' => SiteTree::class // Stored separately, as the logic for URL rewriting is different
    ];

    private static $many_many_extraFields = [
        'CrossSubsiteLinkTracking' => ['FieldName' => 'Varchar']
    ];

    public function isMainSite()
    {
        return $this->owner->SubsiteID == 0;
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
        // if(!$query->where
        // || (strpos($query->where[0], ".\"ID\" = ") === false
        // && strpos($query->where[0], ".`ID` = ") === false && strpos($query->where[0], ".ID = ") === false
        // && strpos($query->where[0], "ID = ") !== 0)) {
        if ($query->filtersOnID()) {
            return;
        }

        $subsiteID = null;
        $subsiteState = SubsiteState::singleton();
        $force_subsite = $subsiteState->withState(function (SubsiteState $newState) {
            return $newState->getSubsiteId();
        });

        if ($force_subsite) {
            $subsiteID = $force_subsite;
        } else {
            $subsiteID = $subsiteState->getSubsiteId();
        }

        if ($subsiteID === null) {
            return;
        }

        // The foreach is an ugly way of getting the first key :-)
        foreach ($query->getFrom() as $tableName => $info) {
            // The tableName should be SiteTree or SiteTree_Live...
            $siteTreeTableName = SiteTree::getSchema()->tableName(SiteTree::class);
            if (strpos($tableName ?? '', $siteTreeTableName ?? '') === false) {
                break;
            }
            $query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
            break;
        }
    }

    public function onBeforeWrite()
    {
        if (!$this->owner->ID && !$this->owner->SubsiteID) {
            $this->owner->SubsiteID = SubsiteState::singleton()->getSubsiteId();
        }

        parent::onBeforeWrite();
    }

    public function updateCMSFields(FieldList $fields)
    {
        $subsites = Subsite::accessible_sites('CMS_ACCESS_CMSMain');
        if ($subsites && $subsites->count()) {
            $subsitesToMap = $subsites->exclude('ID', $this->owner->SubsiteID);
            $subsitesMap = $subsitesToMap->map('ID', 'Title');
        } else {
            $subsitesMap = new Map(ArrayList::create());
        }

        // Master page edit field (only allowed from default subsite to avoid inconsistent relationships)
        $isDefaultSubsite = $this->owner->SubsiteID == 0 || $this->owner->Subsite()->DefaultSite;

        if ($isDefaultSubsite && $subsitesMap->count()) {
            $fields->addFieldToTab(
                'Root.Main',
                ToggleCompositeField::create(
                    'SubsiteOperations',
                    _t(__CLASS__ . '.SubsiteOperations', 'Subsite Operations'),
                    [
                        DropdownField::create('CopyToSubsiteID', _t(
                            __CLASS__ . '.CopyToSubsite',
                            'Copy page to subsite'
                        ), $subsitesMap),
                        CheckboxField::create(
                            'CopyToSubsiteWithChildren',
                            _t(__CLASS__ . '.CopyToSubsiteWithChildren', 'Include children pages?')
                        ),
                        $copyAction = FormAction::create(
                            'copytosubsite',
                            _t(__CLASS__ . '.CopyAction', 'Copy')
                        )
                    ]
                )->setHeadingLevel(4)
            );

            $copyAction->addExtraClass('btn btn-primary font-icon-save ml-3');

            // @todo check if this needs re-implementation
//            $copyAction->includeDefaultJS(false);
        }

        // replace readonly link prefix
        $subsite = $this->owner->Subsite();
        $nested_urls_enabled = Config::inst()->get(SiteTree::class, 'nested_urls');
        /** @var Subsite $subsite */
        if ($subsite && $subsite->exists()) {
            // Use baseurl from domain
            $baseLink = $subsite->absoluteBaseURL();

            // Add parent page if enabled
            if ($nested_urls_enabled && $this->owner->ParentID) {
                $baseLink = Controller::join_links(
                    $baseLink,
                    $this->owner->Parent()->RelativeLink(true)
                );
            }

            $urlsegment = $fields->dataFieldByName('URLSegment');
            if ($urlsegment && $urlsegment instanceof SiteTreeURLSegmentField) {
                $urlsegment->setURLPrefix($baseLink);
            }
        }
    }

    /**
     * Does the basic duplication, but doesn't write anything this means we can subclass this easier and do more
     * complex relation duplication.
     *
     * Note that when duplicating including children, everything is written.
     *
     * @param Subsite|int $subsiteID
     * @param bool $includeChildren
     * @return SiteTree
     */
    public function duplicateToSubsitePrep($subsiteID, $includeChildren)
    {
        if (is_object($subsiteID)) {
            $subsiteID = $subsiteID->ID;
        }

        return SubsiteState::singleton()
            ->withState(function (SubsiteState $newState) use ($subsiteID, $includeChildren) {
                $newState->setSubsiteId($subsiteID);

                /** @var SiteTree $page */
                $page = $this->owner;

                try {
                    // We have no idea what the ParentID should be, but it shouldn't be the same as it was since
                    // we're now in a different subsite. As a workaround use the url-segment and subsite ID.
                    if ($page->Parent()) {
                        $parentSeg = $page->Parent()->URLSegment;
                        $newParentPage = Page::get()->filter('URLSegment', $parentSeg)->first();
                        $originalParentID = $page->ParentID;
                        if ($newParentPage) {
                            $page->ParentID = (int) $newParentPage->ID;
                        } else {
                            // reset it to the top level, so the user can decide where to put it
                            $page->ParentID = 0;
                        }
                    }

                    // Disable query filtering by subsite during actual duplication
                    $originalFilter = Subsite::$disable_subsite_filter;
                    Subsite::disable_subsite_filter(true);

                    return $includeChildren ? $page->duplicateWithChildren() : $page->duplicate(false);
                } finally {
                    Subsite::disable_subsite_filter($originalFilter);

                    // Re-set the original parent ID for the current page
                    $page->ParentID = $originalParentID;
                }
            });
    }

    /**
     * When duplicating a page, assign the current subsite ID from the state
     */
    public function onBeforeDuplicate()
    {
        $subsiteId = SubsiteState::singleton()->getSubsiteId();
        if ($subsiteId !== null) {
            $this->owner->SubsiteID = $subsiteId;
        }
    }

    /**
     * Create a duplicate of this page and save it to another subsite
     *
     * @param Subsite|int $subsiteID   The Subsite to copy to, or its ID
     * @param boolean $includeChildren Whether to duplicate child pages too
     * @return SiteTree                The duplicated page
     */
    public function duplicateToSubsite($subsiteID = null, $includeChildren = false)
    {
        /** @var SiteTree|SiteTreeSubsites */
        $clone = $this->owner->duplicateToSubsitePrep($subsiteID, $includeChildren);
        $clone->invokeWithExtensions('onBeforeDuplicateToSubsite', $this->owner);

        if (!$includeChildren) {
            // Write the new page if "include children" is false, because it is written by default when it's true.
            $clone->write();
        }
        // Deprecated: manually duplicate any configured relationships
        $clone->duplicateSubsiteRelations($this->owner);

        $clone->invokeWithExtensions('onAfterDuplicateToSubsite', $this->owner);

        return $clone;
    }

    /**
     * Duplicate relations using a static property to define
     * which ones we want to duplicate
     *
     * It may be that some relations are not diostinct to sub site so can stay
     * whereas others may need to be duplicated
     *
     * This was originally deprecated - Use the "cascade_duplicates" config API instead
     * Ideally this would be re-deprecated
     *
     * @param SiteTree $originalPage
     */
    public function duplicateSubsiteRelations($originalPage)
    {
        $thisClass = $originalPage->ClassName;
        $relations = Config::inst()->get($thisClass, 'duplicate_to_subsite_relations');

        if ($relations && !empty($relations)) {
            foreach ($relations as $relation) {
                $items = $originalPage->$relation();
                foreach ($items as $item) {
                    $duplicateItem = $item->duplicate(false);
                    $duplicateItem->{$thisClass.'ID'} = $this->owner->ID;
                    $duplicateItem->write();
                }
            }
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
            $sc->Title = _t('SilverStripe\\Subsites\\Model\\Subsite.SiteConfigTitle', 'Your Site Name');
            $sc->Tagline = _t('SilverStripe\\Subsites\\Model\\Subsite.SiteConfigSubtitle', 'Your tagline here');
            $sc->write();
        }
        return $sc;
    }

    /**
     * Only allow editing of a page if the member satisfies one of the following conditions:
     * - Is in a group which has access to the subsite this page belongs to
     * - Is in a group with edit permissions on the "main site"
     *
     * If there are no subsites configured yet, this logic is skipped.
     *
     * @param Member|null $member
     * @return bool|null
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        // Do not provide any input if there are no subsites configured
        if (!Subsite::get()->exists()) {
            return null;
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
            $subsiteID = SubsiteState::singleton()->getSubsiteId();
        }

        // Return true if they have access to this object's site
        if (!(in_array(0, $goodSites ?? []) || in_array($subsiteID, $goodSites ?? []))) {
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
            $member = Security::getCurrentUser();
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
            $member = Security::getCurrentUser();
        }

        return $this->canEdit($member);
    }

    /**
     * @param Member|null $member
     * @return bool|null
     */
    public function canPublish($member = null)
    {
        if (!$member && $member !== false) {
            $member = Security::getCurrentUser();
        }

        return $this->canEdit($member);
    }

    /**
     * Called by ContentController::init();
     * @param $controller
     */
    public static function contentcontrollerInit($controller)
    {
        /** @var Subsite $subsite */
        $subsite = Subsite::currentSubsite();

        if ($subsite && $subsite->Theme) {
            SSViewer::set_themes(ThemeResolver::singleton()->getThemeList($subsite));
        }

        $ignore_subsite_locale = Config::inst()->get(self::class, 'ignore_subsite_locale');

        if (!$ignore_subsite_locale
            && $subsite
            && $subsite->Language
            && i18n::getData()->validate($subsite->Language)
        ) {
            i18n::set_locale($subsite->Language);
        }
    }

    /**
     * @param null $action
     * @return string
     */
    public function alternateAbsoluteLink($action = null)
    {
        // Generate the existing absolute URL and replace the domain with the subsite domain.
        // This helps deal with Link() returning an absolute URL.
        $url = Director::absoluteURL($this->owner->Link($action));
        if ($this->owner->SubsiteID) {
            $pattern = '/\/\/[^\/]*(?<slash>\/)?/';
            preg_match($pattern, $url ?? '', $matches, PREG_UNMATCHED_AS_NULL);
            $delim = $matches['slash'] ?? '';

            $url = preg_replace($pattern, '//' . $this->owner->Subsite()->domain() . $delim, $url ?? '');
        }
        return $url;
    }

    /**
     * Use the CMS domain for iframed CMS previews to prevent single-origin violations
     * and SSL cert problems. Always set SubsiteID to avoid errors because a page doesn't
     * exist on the CMS domain.
     *
     * @param string &$link
     * @param string|null $action
     * @return string
     */
    public function updatePreviewLink(&$link, $action = null)
    {
        $url = Director::absoluteURL($this->owner->Link($action));
        $link = HTTP::setGetVar('SubsiteID', $this->owner->SubsiteID, $url);
        return $link;
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
        $linkedPages = [];

        if ($links) {
            foreach ($links as $link) {
                if (substr($link ?? '', 0, strlen('http://')) == 'http://') {
                    $withoutHttp = substr($link ?? '', strlen('http://'));
                    if (strpos($withoutHttp ?? '', '/') &&
                        strpos($withoutHttp ?? '', '/') < strlen($withoutHttp ?? '')
                    ) {
                        $domain = substr($withoutHttp ?? '', 0, strpos($withoutHttp ?? '', '/'));
                        $rest = substr($withoutHttp ?? '', strpos($withoutHttp ?? '', '/') + 1);

                        $subsiteID = Subsite::getSubsiteIDForDomain($domain);
                        if ($subsiteID == 0) {
                            continue;
                        } // We have no idea what the domain for the main site is, so cant track links to it

                        $origDisableSubsiteFilter = Subsite::$disable_subsite_filter;
                        Subsite::disable_subsite_filter(true);
                        $candidatePage = SiteTree::get()->filter([
                                                                     'URLSegment' => urldecode($rest),
                                                                     'SubsiteID'  => $subsiteID,
                                                                 ])->first();
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
        return SubsiteState::singleton()->withState(function (SubsiteState $newState) {
            
            // If this page is being filtered in the current subsite, then no custom validation query is required.
            $subsite = $newState->getSubsiteId() ?: SubsiteState::singleton()->getSubsiteId();
            if (empty($this->owner->SubsiteID) || $subsite == $this->owner->SubsiteID) {
                return null;
            }
            
            // Backup forced subsite
            $prevForceSubsite = $newState->getSubsiteId();
            $newState->setSubsiteId($this->owner->SubsiteID);
            
            // Repeat validation in the correct subsite
            $isValid = $this->owner->validURLSegment();
            
            // Restore
            $newState->setSubsiteId($prevForceSubsite);
            
            return (bool)$isValid;
        });
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific
     */
    public function cacheKeyComponent()
    {
        return 'subsite-' . SubsiteState::singleton()->getSubsiteId();
    }

    /**
     * @param Member $member
     * @return boolean|null
     */
    public function canCreate($member = null)
    {
        // Typically called on a singleton, so we're not using the Subsite() relation
        $subsite = Subsite::currentSubsite();
        if ($subsite && $subsite->exists() && $subsite->PageTypeBlacklist) {
            // SS 4.1: JSON encoded. SS 4.0, comma delimited
            $blacklist = json_decode($subsite->PageTypeBlacklist ?? '', true);
            if ($blacklist === false) {
                $blacklist = explode(',', $subsite->PageTypeBlacklist ?? '');
            }

            if (in_array(get_class($this->owner), (array) $blacklist)) {
                return false;
            }
        }
    }
}
