<?php

use SilverStripe\Forms\HiddenField;
use SilverStripe\Core\Extension;
class CMSPageAddControllerExtension extends Extension {

	function updatePageOptions(&$fields) {
		$fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
	}

}
