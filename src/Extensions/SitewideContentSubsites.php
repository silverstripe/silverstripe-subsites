<?php

namespace SilverStripe\SiteWideContentReport\Model;

use SilverStripe\Core\Extension;
use SilverStripe\SiteWideContentReport\Form\GridFieldBasicContentReport;
use SilverStripe\SiteWideContentReport\SitewideContentReport;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;

/**
 * Provides subsite integration for sitewide content report.
 *
 * @extends Extension<SitewideContentReport|GridFieldBasicContentReport>
 */
class SitewideContentSubsites extends Extension
{
    /**
     * Update columns to include subsite details.
     *
     * @param string $itemType (i.e 'Pages' or 'Files')
     * @param array  $columns  Columns
     */
    public function updateColumns($itemType, &$columns)
    {
        // Skip single subsite setups
        if (!Subsite::get()->count()) {
            return;
        }

        // Set title
        $mainSiteLabel = _t('SilverStripe\\SiteWideContentReport\\SitewideContentReport.MainSite', 'Main Site');
        if ($itemType !== 'Pages') {
            $mainSiteLabel .= ' '._t(
                'SilverStripe\\SiteWideContentReport\\SitewideContentReport.AccessFromAllSubsites',
                '(accessible by all subsites)'
            );
        }

        // Add subsite name
        $columns['SubsiteName'] = [
            'title' => _t('SilverStripe\\SiteWideContentReport\\SitewideContentReport.Subsite', 'Subsite'),
            'datasource' => function ($item) use ($mainSiteLabel) {
                $subsite = $item->Subsite();

                if ($subsite && $subsite->exists() && $subsite->Title) {
                    return $subsite->Title;
                } else {
                    return $mainSiteLabel;
                }
            },
        ];
    }

    /**
     * @param $total
     * @param $index
     * @param $record
     * @param $attributes
     */
    public function updateRowAttributes($total, $index, $record, &$attributes)
    {
        $attributes['data-subsite-id'] = $record->SubsiteID;
    }

    public function parameterFields()
    {
        $subsites = Subsite::all_sites()->map()->toArray();
        // Pad the 0 a little so doesn't get treated as the empty string and remove the original
        $mainSite = ['000' => $subsites[0]];
        unset($subsites[0]);
        $subsites = $mainSite + $subsites;
    
        $header = HeaderField::create('PagesTitle', _t(__CLASS__ . '.Pages', 'Pages'), 3);
        $dropdown = DropdownField::create('AllSubsites', _t(__CLASS__ . '.FilterBy', 'Filter by:'), $subsites);
        $dropdown->addExtraClass('subsite-filter no-change-track');
        $dropdown->setEmptyString(_t(__CLASS__ . '.ALL_SUBSITES', 'All Subsites'));
    
        return FieldList::create($header, $dropdown);
    }

    public function updateSourceRecords(array &$records, array &$params = []): void
    {
        if (Subsite::get()->count() === 0) {
            return;
        }
        Versioned::withVersionedMode(function () use (&$records, $params) {
            Versioned::set_reading_mode('Stage.Stage');
            $records = [
                'Pages' => Subsite::get_from_all_subsites(SiteTree::class),
                'Files' => Subsite::get_from_all_subsites(File::class),
            ];
            if (array_key_exists('AllSubsites', $params ?? [])) {
                $records['Pages'] = $records['Pages']->filter(['SubsiteID' => $params['AllSubsites']]);
                $records['Files'] = $records['Files']->filter(['SubsiteID' => [0, $params['AllSubsites']]]);
            }
        });
    }
}
