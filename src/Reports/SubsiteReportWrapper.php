<?php

namespace SilverStripe\Subsites\Reports;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\Reports\ReportWrapper;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

/**
 * Creates a subsite-aware version of another report.
 * Pass another report (or its classname) into the constructor.
 */
class SubsiteReportWrapper extends ReportWrapper
{

    /**
     * @return FieldList
     */
    public function parameterFields()
    {
        $subsites = Subsite::accessible_sites('CMS_ACCESS_CMSMain', true);
        $options = $subsites->toDropdownMap('ID', 'Title');

        $subsiteField = TreeMultiselectField::create(
            'Subsites',
            _t(__CLASS__ . '.ReportDropdown', 'Sites'),
            $options
        );
        $subsiteField->setValue(array_keys($options ?? []));

        // We don't need to make the field editable if only one subsite is available
        if (sizeof($options ?? []) <= 1) {
            $subsiteField = $subsiteField->performReadonlyTransformation();
        }

        $fields = parent::parameterFields();
        if ($fields) {
            $fields->insertBefore($subsiteField, $fields->First()->Name());
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
        $columns['Subsite.Title'] = Subsite::class;
        return $columns;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////
    // Querying

    /**
     * @param arary $params
     * @return void
     */
    public function beforeQuery($params)
    {
        // The user has select a few specific sites
        if (!empty($params['Subsites'])) {
            SubsiteState::singleton()->withState(function (SubsiteState $newState) use ($params) {
                $newState->setSubsiteId($params['Subsites']);
            });

            // Default: restrict to all accessible sites
        } else {
            $subsites = Subsite::accessible_sites('CMS_ACCESS_CMSMain');
            $options = $subsites->toDropdownMap('ID', 'Title');
            SubsiteState::singleton()->withState(function (SubsiteState $newState) use ($options) {
                $newState->setSubsiteId(join(',', array_keys($options ?? [])));
            });
        }
    }

    /**
     * @return void
     */
    public function afterQuery()
    {
        // Manually manage the subsite filtering
        SubsiteState::singleton()->withState(function (SubsiteState $newState) {
            $newState->setSubsiteId(null);
        });
    }
}
