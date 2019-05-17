<?php
namespace SilverStripe\Subsites\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

class PageAddBlacklistExtension extends Extension
{
    public function updatePageOptions(FieldList $fields)
    {
        /** @var Subsite $subsite */
        $subsite = Subsite::currentSubsite();

        // Exit early if no subsite is active
        if (!$subsite || !$subsite->exists()) {
            return;
        }

        // Pull the blacklist of pages
        $blacklist = $subsite->parsePageTypeBlacklist();

        // Break early if there's no blacklist
        if (empty($blacklist)) {
            return;
        }

        // And the field for page types
        /** @var OptionsetField $pageTypeField */
        $pageTypeField = $fields->dataFieldByName('PageType');

        // Prune blacklisted items from the source
        $pageTypes = array_diff_key($pageTypeField->getSource(), array_flip($blacklist));
        $pageTypeField->setSource($pageTypes);
    }
}
