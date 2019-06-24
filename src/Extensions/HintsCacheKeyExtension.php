<?php

namespace SilverStripe\Subsites\Extensions;

use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\Core\Extension;
use SilverStripe\Subsites\State\SubsiteState;

/**
 * This extension adds the current Subsite ID as an additional factor to the Hints Cßache Key, which is used to cache
 * the Site Tree Hints (which include allowed pagetypes).
 *
 * @package SilverStripe\Subsites\Extensions
 * @see CMSMain::generateHintsCacheKey()
 */
class HintsCacheKeyExtension extends Extension
{
    public function updateHintsCacheKey(&$baseKey)
    {
        $baseKey .= '_Subsite:' . SubsiteState::singleton()->getSubsiteId();
    }
}
