<?php

class GroupSubsitesTest extends BaseSubsiteTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';
	
	protected $requireDefaultRecordsFrom = array('GroupSubsites');
	
	function testTrivialFeatures() {
		$this->assertTrue(is_array(singleton('GroupSubsites')->extraStatics()));
		$this->assertTrue(is_array(singleton('GroupSubsites')->providePermissions()));
		$this->assertTrue(singleton('Group')->getCMSFields() instanceof FieldList);
	}
	
	function testAlternateTreeTitle() {
		$group = new Group();
		$group->Title = 'The A Team';
		$group->AccessAllSubsites = true;
		$this->assertEquals($group->getTreeTitle(), 'The A Team <i>(global group)</i>');
		$group->AccessAllSubsites = false;
		$group->write();
		$group->Subsites()->add($this->objFromFixture('Subsite', 'domaintest1'));
		$group->Subsites()->add($this->objFromFixture('Subsite', 'domaintest2'));
		$this->assertEquals($group->getTreeTitle(), 'The A Team <i>(Test 1, Test 2)</i>');
	}
}