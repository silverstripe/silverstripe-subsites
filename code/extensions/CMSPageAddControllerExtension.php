<?php
class CMSPageAddControllerExtension extends Extension {

	function updatePageOptions(&$fields) {
		$fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
	}

}
