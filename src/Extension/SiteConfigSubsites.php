<?php

namespace SilverStripe\Subsites\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

/**
 * Extension for the SiteConfig object to add subsites support
 */
class SiteConfigSubsites extends DataExtension
{
    private static $has_one = [
        'Subsite' => Subsite::class, // The subsite that this page belongs to
    ];

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

        // If you're querying by ID, ignore the sub-site - this is a bit ugly...
        if ($query->filtersOnID()) {
            return;
        }
        $regexp = '/^(.*\.)?("|`)?SubsiteID("|`)?\s?=/';
        foreach ($query->getWhereParameterised($parameters) as $predicate) {
            if (preg_match($regexp, $predicate)) {
                return;
            }
        }

        $subsiteID = SubsiteState::singleton()->getSubsiteId();
        if ($subsiteID === null) {
            return;
        }

        $froms = $query->getFrom();
        $froms = array_keys($froms);
        $tableName = array_shift($froms);
        if ($tableName !== SiteConfig::getSchema()->tableName(SiteConfig::class)) {
            return;
        }
        $query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
    }

    public function onBeforeWrite()
    {
        if ((!is_numeric($this->owner->ID) || !$this->owner->ID) && !$this->owner->SubsiteID) {
            $this->owner->SubsiteID = SubsiteState::singleton()->getSubsiteId();
        }
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific
     */
    public function cacheKeyComponent()
    {
        return 'subsite-' . SubsiteState::singleton()->getSubsiteId();
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->push(HiddenField::create('SubsiteID', 'SubsiteID', SubsiteState::singleton()->getSubsiteId()));
    }
}
