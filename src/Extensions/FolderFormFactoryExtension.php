<?php

namespace SilverStripe\Subsites\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Subsites\Model\Subsite;

class FolderFormFactoryExtension extends Extension
{
    /**
     * Add subsites-specific fields to the folder editor.
     * @param FieldList $fields
     */
    public function updateFormFields(FieldList $fields)
    {
        $sites = Subsite::accessible_sites('CMS_ACCESS_AssetAdmin');
        $values = [];
        $values[0] = _t(__CLASS__ . '.AllSitesDropdownOpt', 'All sites');
        foreach ($sites as $site) {
            $values[$site->ID] = $site->Title;
        }
        ksort($values);
        if ($sites) {
            // Dropdown needed to move folders between subsites
            $dropdown = DropdownField::create(
                'SubsiteID',
                _t(__CLASS__ . '.SubsiteFieldLabel', 'Subsite'),
                $values
            );
            $dropdown->addExtraClass('subsites-move-dropdown');
            $fields->push($dropdown);
            $fields->push(LiteralField::create(
                'Message',
                '<p class="alert alert-info">' .
                _t(
                    __CLASS__ . '.SUBSITENOTICE',
                    'Folders and files created in the main site are accessible by all subsites.'
                )
                . '</p>'
            ));
        }
    }
}
