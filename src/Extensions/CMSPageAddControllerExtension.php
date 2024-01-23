<?php

namespace SilverStripe\Subsites\Extensions;

use SilverStripe\CMS\Controllers\CMSPageAddController;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Subsites\State\SubsiteState;

/**
 * @extends Extension<CMSPageAddController>
 */
class CMSPageAddControllerExtension extends Extension
{
    public function updatePageOptions(FieldList $fields)
    {
        $fields->push(HiddenField::create('SubsiteID', 'SubsiteID', SubsiteState::singleton()->getSubsiteId()));
    }
}
