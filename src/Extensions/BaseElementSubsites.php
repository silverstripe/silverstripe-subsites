<?php

namespace SilverStripe\Subsites\Extensions;

use SilverStripe\Control\HTTP;
use SilverStripe\ORM\DataExtension;

/**
 * Extension for the BaseElement object to add subsites support for CMS previews
 */
class BaseElementSubsites extends DataExtension
{
    /**
     * Set SubsiteID to avoid errors when a page doesn't exist on the CMS domain.
     *
     * @param string &$link
     * @param string|null $action
     * @return string
     */
    public function updatePreviewLink(&$link)
    {
        // Get subsite ID from the element or from its page. Defaults to 0 automatically.
        $subsiteID = $this->owner->SubsiteID;
        if (is_null($subsiteID)) {
            $page = $this->owner->getPage();
            if ($page) {
                $subsiteID = $page->SubsiteID;
            }
        }

        $link = HTTP::setGetVar('SubsiteID', intval($subsiteID), $link);
    }
}
