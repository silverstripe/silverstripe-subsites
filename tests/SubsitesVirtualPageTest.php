<?php

class SubsitesVirtualPageTest extends SapphireTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';
	
	// Attempt to bring main:linky to subsite2:linky
	function testVirtualPageFromAnotherSubsite() {
		Subsite::$write_hostmap = false;
		
		$subsite = $this->objFromFixture('Subsite_Template', 'subsite2');
		
		Subsite::changeSubsite($subsite->ID);
		Subsite::$disable_subsite_filter = false;
		
		$linky = $this->objFromFixture('SiteTree', 'linky');
		
		$svp = new SubsitesVirtualPage();
		$svp->CopyContentFromID = $linky->ID;
		$svp->SubsiteID = $subsite->ID;
		$svp->URLSegment = 'linky';
		
		$svp->write();
		
		$this->assertEquals($svp->SubsiteID, $subsite->ID);
		$this->assertEquals($svp->Title, $linky->Title);
	}
}