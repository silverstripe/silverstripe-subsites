<?php

namespace SilverStripe\Subsites\Tasks;

use SilverStripe\Dev\Tasks\MigrateFileTask;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Dev\Deprecation;

/**
 * @deprecated 2.8.0 Will be removed without equivalent functionality to replace it
 */
class SubsiteMigrateFileTask extends MigrateFileTask
{
    public function __construct()
    {
        Deprecation::withNoReplacement(function () {
            Deprecation::notice(
                '2.8.0',
                'Will be removed without equivalent functionality to replace it',
                Deprecation::SCOPE_CLASS
            );
        });
        parent::__construct();
    }

    public function run($request)
    {
        $origDisableSubsiteFilter = Subsite::$disable_subsite_filter;
        Subsite::disable_subsite_filter(true);

        parent::run($request);

        Subsite::disable_subsite_filter($origDisableSubsiteFilter);
    }
}
