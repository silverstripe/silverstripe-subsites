<?php

namespace SilverStripe\Subsites\Extensions;


use SilverStripe\Forms\HiddenField;
use SilverStripe\Core\Extension;
use SilverStripe\Subsites\Model\Subsite;

class CMSPageAddControllerExtension extends Extension {

	function updatePageOptions(&$fields) {
		$fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
	}

}
