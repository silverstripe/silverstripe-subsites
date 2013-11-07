<?php

class LeftAndMainSubsitesTest extends FunctionalTest {
	
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';

	/**
	 * Avoid subsites filtering on fixture fetching.
	 */
	function objFromFixture($class, $id) {
		Subsite::disable_subsite_filter(true);
		$obj = parent::objFromFixture($class, $id);
		Subsite::disable_subsite_filter(false);

		return $obj;
	}

	function testSectionSites() {
		$member = $this->objFromFixture('Member', 'subsite1member');

		$cmsmain = singleton('CMSMain');
		$subsites = $cmsmain->sectionSites(true, "Main site", $member);
		$this->assertDOSEquals(array(
			array('Title' =>'Subsite1 Template')
		), $subsites, 'Lists member-accessible sites for the accessible controller.');

		$assetadmin = singleton('AssetAdmin');
		$subsites = $assetadmin->sectionSites(true, "Main site", $member);
		$this->assertDOSEquals(array(), $subsites, 'Does not list any sites for forbidden controller.');

		$member = $this->objFromFixture('Member', 'editor');

		$cmsmain = singleton('CMSMain');
		$subsites = $cmsmain->sectionSites(true, "Main site", $member);
		$this->assertDOSContains(array(
			array('Title' =>'Main site')
		), $subsites, 'Includes the main site for members who can access all sites.');
	}

	function testAccessChecksDontChangeCurrentSubsite() {
		$admin = $this->objFromFixture("Member","admin");
		$this->loginAs($admin);
		$ids = array();
		
		$subsite1 = $this->objFromFixture('Subsite', 'domaintest1');
		$subsite2 = $this->objFromFixture('Subsite', 'domaintest2');
		$subsite3 = $this->objFromFixture('Subsite', 'domaintest3');
		
		$ids[] = $subsite1->ID;
		$ids[] = $subsite2->ID;
		$ids[] = $subsite3->ID;
		$ids[] = 0;
		
		// Enable session-based subsite tracking.
		Subsite::$use_session_subsiteid = true;

		foreach($ids as $id) {
			Subsite::changeSubsite($id);
			$this->assertEquals($id, Subsite::currentSubsiteID());

			$left = new LeftAndMain();
			$this->assertTrue($left->canView(), "Admin user can view subsites LeftAndMain with id = '$id'");
			$this->assertEquals($id, Subsite::currentSubsiteID(),
				"The current subsite has not been changed in the process of checking permissions for admin user.");
		}
		
	}

	function testShouldChangeSubsite() {
		$l = new LeftAndMain();
		Config::inst()->nest();

		Config::inst()->update('CMSPageEditController', 'treats_subsite_0_as_global', false);
		$this->assertTrue($l->shouldChangeSubsite('CMSPageEditController', 0, 5));
		$this->assertFalse($l->shouldChangeSubsite('CMSPageEditController', 0, 0));
		$this->assertTrue($l->shouldChangeSubsite('CMSPageEditController', 1, 5));
		$this->assertFalse($l->shouldChangeSubsite('CMSPageEditController', 1, 1));

		Config::inst()->update('CMSPageEditController', 'treats_subsite_0_as_global', true);
		$this->assertFalse($l->shouldChangeSubsite('CMSPageEditController', 0, 5));
		$this->assertFalse($l->shouldChangeSubsite('CMSPageEditController', 0, 0));
		$this->assertTrue($l->shouldChangeSubsite('CMSPageEditController', 1, 5));
		$this->assertFalse($l->shouldChangeSubsite('CMSPageEditController', 1, 1));

		Config::inst()->unnest();
	}

}
