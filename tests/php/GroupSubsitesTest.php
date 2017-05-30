<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Security\Group;
use SilverStripe\Forms\FieldList;
use SilverStripe\Subsites\Extensions\GroupSubsites;
use SilverStripe\Subsites\Model\Subsite;

class GroupSubsitesTest extends BaseSubsiteTest
{
    public static $fixture_file = 'subsites/tests/php/SubsiteTest.yml';

    protected $requireDefaultRecordsFrom = [GroupSubsites::class];
    public function testTrivialFeatures()
    {
        $this->assertTrue(is_array(singleton('GroupSubsites')->extraStatics()));
        $this->assertTrue(is_array(singleton('GroupSubsites')->providePermissions()));
        $this->assertTrue(singleton(Group::class)->getCMSFields() instanceof FieldList);
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
