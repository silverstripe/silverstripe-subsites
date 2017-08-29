<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Subsites\Model\Subsite;

class BaseSubsiteTest extends SapphireTest
{
    protected function setUp()
    {
        parent::setUp();

        Subsite::$use_session_subsiteid = true;
        Subsite::$force_subsite = null;
    }

    /**
     * Avoid subsites filtering on fixture fetching.
     * @param string $className
     * @param string $identifier
     * @return \SilverStripe\ORM\DataObject
     */
    protected function objFromFixture($className, $identifier)
    {
        Subsite::disable_subsite_filter(true);
        $obj = parent::objFromFixture($className, $identifier);
        Subsite::disable_subsite_filter(false);

        return $obj;
    }

    /**
     * Tests the initial state of disable_subsite_filter
     */
    public function testDisableSubsiteFilter()
    {
        $this->assertFalse(Subsite::$disable_subsite_filter);
    }
}
