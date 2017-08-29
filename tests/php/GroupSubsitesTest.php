<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Group;
use SilverStripe\Subsites\Extensions\GroupSubsites;
use SilverStripe\Subsites\Model\Subsite;

class GroupSubsitesTest extends BaseSubsiteTest
{
    protected static $fixture_file = 'SubsiteTest.yml';

    protected $requireDefaultRecordsFrom = [GroupSubsites::class];

    public function testTrivialFeatures()
    {
        $this->assertTrue(is_array(singleton(GroupSubsites::class)->extraStatics()));
        $this->assertTrue(is_array(singleton(GroupSubsites::class)->providePermissions()));
        $this->assertInstanceOf(FieldList::class, singleton(Group::class)->getCMSFields());
    }

    public function testAlternateTreeTitle()
    {
        $group = new Group();
        $group->Title = 'The A Team';
        $group->AccessAllSubsites = true;
        $this->assertEquals($group->getTreeTitle(), 'The A Team <i>(global group)</i>');
        $group->AccessAllSubsites = false;
        $group->write();
        $group->Subsites()->add($this->objFromFixture(Subsite::class, 'domaintest1'));
        $group->Subsites()->add($this->objFromFixture(Subsite::class, 'domaintest2'));
        $this->assertEquals($group->getTreeTitle(), 'The A Team <i>(Test 1, Test 2)</i>');
    }
}
