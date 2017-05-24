<?php

namespace SilverStripe\Subsites\Extensions;


use SilverStripe\View\SSViewer;
use SilverStripe\Core\Extension;
use SilverStripe\Subsites\Model\Subsite;

/**
 * @package subsites
 */
class ControllerSubsites extends Extension {
	function controllerAugmentInit(){
		if($subsite = Subsite::currentSubsite()){
			if($theme = $subsite->Theme)
			SSViewer::set_theme($theme);
		}
	}

	function CurrentSubsite(){
		if($subsite = Subsite::currentSubsite()){
			return $subsite;
		}
	}
}

?>
