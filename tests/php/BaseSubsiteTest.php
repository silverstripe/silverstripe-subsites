<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

class BaseSubsiteTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();

        SubsiteState::singleton()->setUseSessions(true);
        Config::modify()->set(Subsite::class, 'write_hostmap', false);
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
