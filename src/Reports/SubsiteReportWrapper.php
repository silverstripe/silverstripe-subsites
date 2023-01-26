<?php

namespace SilverStripe\Subsites\Reports;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Reports\ReportWrapper;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

/**
 * Creates a subsite-aware version of another report.
 * Pass another report (or its classname) into the constructor.
 */
class SubsiteReportWrapper extends ReportWrapper
{
    private const SUBSITE_ID_ALL = -1;

    /**
     * @return FieldList
     */
    public function parameterFields()
    {
        $subsites = Subsite::accessible_sites('CMS_ACCESS_CMSMain', true);
        $options = [self::SUBSITE_ID_ALL => _t(__CLASS__ . '.ReportDropdownAll', 'All')] + $subsites->map()->toArray();

        $subsiteField = DropdownField::create(
            'Subsite',
            _t(__CLASS__ . '.ReportDropdownSubsite', 'Subsite'),
            $options
        );

        // We don't need to make the field editable if only one subsite is available
        if (sizeof($options ?? []) <= 2) {
            $subsiteField = $subsiteField->performReadonlyTransformation();
        }

        $fields = parent::parameterFields();
        if ($fields) {
            $fields->insertBefore($fields->First()->getName(), $subsiteField);
        } else {
            $fields = FieldList::create($subsiteField);
        }
        return $fields;
    }

    /**
     * @return array
     */
    public function columns()
    {
        $columns = parent::columns();
        $columns['Subsite.Title'] = _t(__CLASS__ . '.ReportDropdownSubsite', 'Subsite');
        return $columns;
    }

    public function sourceQuery($params)
    {
        $subsiteID = (int) ($params['Subsite'] ?? self::SUBSITE_ID_ALL);
        if ($subsiteID === self::SUBSITE_ID_ALL) {
            return Subsite::withDisabledSubsiteFilter(function () use ($params) {
                return parent::sourceQuery($params);
            });
        }
        return SubsiteState::singleton()->withState(function (SubsiteState $newState) use ($subsiteID, $params) {
            $newState->setSubsiteId($subsiteID);
            return parent::sourceQuery($params);
        });
    }

    public function sourceRecords($params = [], $sort = null, $limit = null)
    {
        $subsiteID = (int) ($params['Subsite'] ?? self::SUBSITE_ID_ALL);
        if ($subsiteID === self::SUBSITE_ID_ALL) {
            return Subsite::withDisabledSubsiteFilter(function () use ($params, $sort, $limit) {
                return parent::sourceRecords($params, $sort, $limit);
            });
        }
        return SubsiteState::singleton()->withState(function (SubsiteState $newState) use (
            $subsiteID,
            $params,
            $sort,
            $limit
        ) {
            $newState->setSubsiteId($subsiteID);
            return parent::sourceRecords($params, $sort, $limit);
        });
    }
}
