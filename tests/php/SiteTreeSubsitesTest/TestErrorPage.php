<?php

namespace SilverStripe\Subsites\Tests\SiteTreeSubsitesTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ErrorPage\ErrorPage;

class TestErrorPage extends ErrorPage implements TestOnly
{
    /**
     * Helper method to call protected members
     *
     * @param int $statusCode
     * @return string
     */
    public static function get_error_filename_spy($statusCode)
    {
        return self::get_error_filename($statusCode);
    }
}
