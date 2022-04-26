<?php

namespace SilverStripe\Subsites\Extensions;

use SilverStripe\Assets\File;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Permission;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

/**
 * Extension for the File object to add subsites support
 *
 * @package subsites
 */
class FileSubsites extends DataExtension
{
    /**
     * If this is set to true, all folders created will be default be considered 'global', unless set otherwise
     *
     * @config
     * @var bool
     */
    private static $default_root_folders_global = false;

    private static $has_one = [
        'Subsite' => Subsite::class,
    ];

    /**
     * Amends the CMS tree title for folders in the Files & Images section.
     * Prefixes a '* ' to the folders that are accessible from all subsites.
     */
    public function alternateTreeTitle()
    {
        if ($this->owner->SubsiteID == 0) {
            return ' * ' . $this->owner->Title;
        }

        return $this->owner->Title;
    }

    /**
     * Update any requests to limit the results to the current site
     * @param SQLSelect $query
     * @param DataQuery|null $dataQuery
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        if (Subsite::$disable_subsite_filter) {
            return;
        }
        if ($dataQuery && $dataQuery->getQueryParam('Subsite.filter') === false) {
            return;
        }

        // If you're querying by ID, ignore the sub-site - this is a bit ugly... (but it was WAYYYYYYYYY worse)
        // @TODO I don't think excluding if SiteTree_ImageTracking is a good idea however because of the SS 3.0 api and
        // ManyManyList::removeAll() changing the from table after this function is called there isn't much of a choice

        $from = $query->getFrom();
        if (isset($from['SiteTree_ImageTracking']) || $query->filtersOnID()) {
            return;
        }

        $subsiteID = SubsiteState::singleton()->getSubsiteId();
        if ($subsiteID === null) {
            return;
        }

        // The foreach is an ugly way of getting the first key :-)
        foreach ($query->getFrom() as $tableName => $info) {
            $where = "\"$tableName\".\"SubsiteID\" IN (0, $subsiteID)";
            $query->addWhere($where);
            break;
        }

        $sect = array_values($query->getSelect() ?? []);
        $isCounting = strpos($sect[0] ?? '', 'COUNT') !== false;

        // Ordering when deleting or counting doesn't apply
        if (!$isCounting) {
            $query->addOrderBy('"SubsiteID"');
        }
    }

    public function onBeforeWrite()
    {
        if (!$this->owner->ID && !$this->owner->SubsiteID) {
            if ($this->owner->config()->get('default_root_folders_global')) {
                $this->owner->SubsiteID = 0;
            } else {
                $this->owner->SubsiteID = SubsiteState::singleton()->getSubsiteId();
            }
        }
    }

    public function onAfterUpload()
    {
        // If we have a parent, use it's subsite as our subsite
        if ($this->owner->Parent()) {
            $this->owner->SubsiteID = $this->owner->Parent()->SubsiteID;
        } else {
            $this->owner->SubsiteID = SubsiteState::singleton()->getSubsiteId();
        }
        $this->owner->write();
    }

    public function canEdit($member = null)
    {
        // Opt out of making opinions if no subsite ID is set yet
        if (!$this->owner->SubsiteID) {
            return null;
        }

        // Check the CMS_ACCESS_SecurityAdmin privileges on the subsite that owns this group
        $subsiteID = SubsiteState::singleton()->getSubsiteId();
        if ($subsiteID && $subsiteID == $this->owner->SubsiteID) {
            return true;
        }

        return SubsiteState::singleton()->withState(function (SubsiteState $newState) use ($member) {
            $newState->setSubsiteId($this->owner->SubsiteID);

            return $this->owner->getPermissionChecker()->canEdit($this->owner->ID, $member);
        });
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific
     *
     * @return string
     */
    public function cacheKeyComponent()
    {
        return 'subsite-' . SubsiteState::singleton()->getSubsiteId();
    }
}
