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
        $subsite = $this->CurrentSubsite();
        if ($subsite && $theme = $subsite->Theme) {
            SSViewer::set_themes([$theme, SSViewer::DEFAULT_THEME]);
        }
    }

    /**
     * @return Subsite
     */
    public function CurrentSubsite()
    {
        return Subsite::currentSubsite();
    }

}
