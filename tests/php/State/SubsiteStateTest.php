<?php

namespace SilverStripe\Subsites\Tests\State;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Subsites\State\SubsiteState;

class SubsiteStateTest extends SapphireTest
{
    public function testStateIsInjectable()
    {
        $this->assertInstanceOf(SubsiteState::class, Injector::inst()->get(SubsiteState::class));
    }

    public function testGetSubsiteIdWasChanged()
    {
        $state = new SubsiteState;

        // Initial set, doesn't count as being changed
        $state->setSubsiteId(123);
        $this->assertFalse($state->getSubsiteIdWasChanged());

        // Subsequent set with the same value, doesn't count as being changed
        $state->setSubsiteId(123);
        $this->assertFalse($state->getSubsiteIdWasChanged());

        // Subsequent set with new value, counts as changed
        $state->setSubsiteId(234);
        $this->assertTrue($state->getSubsiteIdWasChanged());
    }

    public function testWithState()
    {
        $state = new SubsiteState;
        $state->setSubsiteId(123);

        $state->withState(function ($newState) use ($state) {
            $this->assertInstanceOf(SubsiteState::class, $newState);

            $this->assertNotSame($newState, $state);

            $newState->setSubsiteId(234);
            $this->assertSame(234, $newState->getSubsiteId());
            $this->assertSame(123, $state->getSubsiteId());
        });

        $this->assertSame(123, $state->getSubsiteId());
    }
}
