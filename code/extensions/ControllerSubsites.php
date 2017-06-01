<?php

namespace SilverStripe\Subsites\Extensions;
use SilverStripe\Core\Extension;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\View\SSViewer;

/**
 * @package subsites
 */
class ControllerSubsites extends Extension
{

    public function controllerAugmentInit()
    {
        if ($subsite = Subsite::currentSubsite()) {
            if ($theme = $subsite->Theme) {
                SSViewer::set_themes([$theme, SSViewer::DEFAULT_THEME]);
            }
        }
    }

    public function CurrentSubsite()
    {
        if ($subsite = Subsite::currentSubsite()) {
            return $subsite;
        }
    }

}
