<?php

namespace SilverStripe\Subsites\Tasks;

use SilverStripe\Dev\Tasks\MigrateFileTask;
use SilverStripe\Subsites\Model\Subsite;

class SubsiteMigrateFileTask extends MigrateFileTask
{
    public function run($request)
    {
        $origDisableSubsiteFilter = Subsite::$disable_subsite_filter;
        Subsite::disable_subsite_filter(true);

        parent::run($request);

        Subsite::disable_subsite_filter($origDisableSubsiteFilter);
    }
}
