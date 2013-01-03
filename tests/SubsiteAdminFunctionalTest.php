<?php

class SubsiteAdminFunctionalTest extends FunctionalTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';
	static $use_draft_site = true;

	protected $autoFollowRedirection = false;

	/**
	 * Admin should be able to access all subsites and the main site
	 */
	function testAdminCanAccessAllSubsites() {
		$member = $this->objFromFixture('Member', 'admin');
		Session::set("loggedInAs", $member->ID);
		
		$this->get('admin/pages?SubsiteID=0&ajax=1');
		$this->get('admin');
		$this->assertEquals(Subsite::currentSubsiteID(), '0', 'Can access main site');

		$mainSubsite = $this->objFromFixture('Subsite', 'main');
		$this->get("admin/pages?SubsiteID={$mainSubsite->ID}&ajax=1");
		$this->get('admin');
		$this->assertEquals(Subsite::currentSubsiteID(), $mainSubsite->ID, 'Can access the subsite');
	}

	/**
	 * User which has AccessAllSubsites set to 1 should be able to access all subsites and main site,
	 * even though he does not have the ADMIN permission.
	 */
	function testEditorCanAccessAllSubsites() {
		$member = $this->objFromFixture('Member', 'editor');
		Session::set("loggedInAs", $member->ID);
		
		$this->get('admin/pages?SubsiteID=0&ajax=1');
		$this->get('admin');
		$this->assertEquals(Subsite::currentSubsiteID(), '0', 'Can access main site');

		$mainSubsite = $this->objFromFixture('Subsite', 'main');
		$this->get("admin/pages?SubsiteID={$mainSubsite->ID}&ajax=1");
		$this->get('admin');
		$this->assertEquals(Subsite::currentSubsiteID(), $mainSubsite->ID, 'Can access the subsite');
	}
}
