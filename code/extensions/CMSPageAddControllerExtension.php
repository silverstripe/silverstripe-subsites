<?php

namespace SilverStripe\Subsites\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Subsites\Model\Subsite;

class CMSPageAddControllerExtension extends Extension
{

    public function updatePageOptions(&$fields)
    {
        $fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
    }
}
