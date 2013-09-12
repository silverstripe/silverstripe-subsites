<?php

class SubsitesVirtualPageTest extends BaseSubsiteTest {
	static $fixture_file = array(
		'subsites/tests/SubsiteTest.yml',
		'subsites/tests/SubsitesVirtualPageTest.yml',
	);
	
	function setUp() {
		parent::setUp();
		$this->logInWithPermission('ADMIN');
		
		$fh = fopen(Director::baseFolder() . '/assets/testscript-test-file.pdf', "w");
		fwrite($fh, str_repeat('x',1000000));
		fclose($fh);
	}

	function tearDown() {
		parent::tearDown();
		$testFiles = array(
			'/assets/testscript-test-file.pdf',
			'/assets/renamed-test-file.pdf',
			'/assets/renamed-test-file-second-time.pdf',
		);
		foreach($testFiles as $file) {
			if(file_exists(Director::baseFolder().$file)) unlink(Director::baseFolder().$file);
		}
	}
	
	// Attempt to bring main:linky to subsite2:linky
	function testVirtualPageFromAnotherSubsite() {
		Subsite::$write_hostmap = false;
		
		$subsite = $this->objFromFixture('Subsite', 'subsite2');
		
		Subsite::changeSubsite($subsite->ID);
		Subsite::$disable_subsite_filter = false;
		
		$linky = $this->objFromFixture('Page', 'linky');
		
		$svp = new SubsitesVirtualPage();
		$svp->CopyContentFromID = $linky->ID;
		$svp->SubsiteID = $subsite->ID;
		$svp->URLSegment = 'linky';
		
		$svp->write();
		
		$this->assertEquals($svp->SubsiteID, $subsite->ID);
		$this->assertEquals($svp->Title, $linky->Title);
	}

	function testFileLinkRewritingOnVirtualPages() {
		// File setup
		$this->logInWithPermission('ADMIN');
		touch(Director::baseFolder() . '/assets/testscript-test-file.pdf');

		// Publish the source page
		$page = $this->objFromFixture('SiteTree', 'page1');
		$this->assertTrue($page->doPublish());

		// Create a virtual page from it, and publish that
		$svp = new SubsitesVirtualPage();
		$svp->CopyContentFromID = $page->ID;
		$svp->write();
		$svp->doPublish();
		
		// Rename the file
		$file = $this->objFromFixture('File', 'file1');
		$file->Name = 'renamed-test-file.pdf';
		$file->write();
		
		// Verify that the draft and publish virtual pages both have the corrected link
		$this->assertContains('<img src="assets/renamed-test-file.pdf"',
			DB::query("SELECT \"Content\" FROM \"SiteTree\" WHERE \"ID\" = $svp->ID")->value());
		$this->assertContains('<img src="assets/renamed-test-file.pdf"',
			DB::query("SELECT \"Content\" FROM \"SiteTree_Live\" WHERE \"ID\" = $svp->ID")->value());

		// File teardown
		$testFiles = array(
			'/assets/testscript-test-file.pdf',
			'/assets/renamed-test-file.pdf',
		);
		foreach($testFiles as $file) {
			if(file_exists(Director::baseFolder().$file)) unlink(Director::baseFolder().$file);
		}
	}

	function testSubsiteVirtualPagesArentInappropriatelyPublished() {
		// Fixture
		$p = new Page();
		$p->Content = "test content";
		$p->write();
		$vp = new SubsitesVirtualPage();
		$vp->CopyContentFromID = $p->ID;
		$vp->write();

		// VP is oragne
		$this->assertTrue($vp->IsAddedToStage);

		// VP is still orange after we publish
		$p->doPublish();
		$this->fixVersionNumberCache($vp);
		$this->assertTrue($vp->IsAddedToStage);
		
		// A new VP created after P's initial construction
		$vp2 = new SubsitesVirtualPage();
		$vp2->CopyContentFromID = $p->ID;
		$vp2->write();
		$this->assertTrue($vp2->IsAddedToStage);
		
		// Also remains orange after a republish
		$p->Content = "new content";
		$p->write();
		$p->doPublish();
		$this->fixVersionNumberCache($vp2);
		$this->assertTrue($vp2->IsAddedToStage);
		
		// VP is now published
		$vp->doPublish();

		$this->fixVersionNumberCache($vp);
		$this->assertTrue($vp->ExistsOnLive);
		$this->assertFalse($vp->IsModifiedOnStage);
		
		// P edited, VP and P both go green
		$p->Content = "third content";
		$p->write();

		$this->fixVersionNumberCache($vp, $p);
		$this->assertTrue($p->IsModifiedOnStage);
		$this->assertTrue($vp->IsModifiedOnStage);

		// Publish, VP goes black
		$p->doPublish();
		$this->fixVersionNumberCache($vp);
		$this->assertTrue($vp->ExistsOnLive);
		$this->assertFalse($vp->IsModifiedOnStage);
	}
	
	/**
	 * This test ensures published Subsites Virtual Pages immediately reflect updates
	 * to their published target pages. Note - this has to happen when the virtual page
	 * is in a different subsite to the page you are editing and republishing,
	 * otherwise the test will pass falsely due to current subsite ID being the same.
	 */
	function testPublishedSubsiteVirtualPagesUpdateIfTargetPageUpdates()
	{
		// create page
		$p = new Page();
		$p->Content = 'Content';
		$p->Title = 'Title';
		$p->writeToStage('Stage');
		$p->publish('Stage', 'Live');
		$this->assertTrue($p->ExistsOnLive);
		
		// change to subsite
		$subsite = $this->objFromFixture('Subsite', 'subsite2');
		Subsite::changeSubsite($subsite->ID);
		Subsite::$disable_subsite_filter = false;
		
		// create svp in subsite
		$svp = new SubsitesVirtualPage();
		$svp->CopyContentFromID = $p->ID;
		$svp->writeToStage('Stage');
		$svp->publish('Stage', 'Live');
		$this->assertEquals($svp->SubsiteID, $subsite->ID);
		$this->assertTrue($svp->ExistsOnLive);
		
		// change back to original subsite ("Main site")
		Subsite::changeSubsite(0);
		
		// update original page
		$p->Title = 'New Title';
		// "save & publish"
		$p->writeToStage('Stage');
		$p->publish('Stage', 'Live');
		$this->assertNotEquals($p->SubsiteID, $subsite->ID);
		
		// reload SVP from database
		// can't use DO::get by id because caches.
		$svpdb = $svp->get()->byID($svp->ID);
		
		// ensure title changed
		$this->assertEquals($svpdb->Title, $p->Title); 
	}
	
	function testUnpublishingParentPageUnpublishesSubsiteVirtualPages() {
		Config::inst()->update('StaticPublisher', 'disable_realtime', true);
		
		// Go to main site, get parent page
		$subsite = $this->objFromFixture('Subsite', 'main');
		Subsite::changeSubsite($subsite->ID);
		$page = $this->objFromFixture('Page', 'importantpage');
		
		// Create two SVPs on other subsites
		$subsite = $this->objFromFixture('Subsite', 'subsite1');
		Subsite::changeSubsite($subsite->ID);
		$vp1 = new SubsitesVirtualPage();
		$vp1->CopyContentFromID = $page->ID;
		$vp1->write();
		$vp1->doPublish();
		
		$subsite = $this->objFromFixture('Subsite', 'subsite2');
		Subsite::changeSubsite($subsite->ID);
		$vp2 = new SubsitesVirtualPage();
		$vp2->CopyContentFromID = $page->ID;
		$vp2->write();
		$vp2->doPublish();
		
		// Switch back to main site, unpublish source
		$subsite = $this->objFromFixture('Subsite', 'main');
		Subsite::changeSubsite($subsite->ID);
		$page = $this->objFromFixture('Page', 'importantpage');
		$page->doUnpublish();
		
		Subsite::changeSubsite($vp1->SubsiteID);
		$onLive = Versioned::get_one_by_stage('SubsitesVirtualPage', 'Live', "\"SiteTree_Live\".\"ID\" = ".$vp1->ID);
		$this->assertNull($onLive, 'SVP has been removed from live');
		
		$subsite = $this->objFromFixture('Subsite', 'subsite2');
		Subsite::changeSubsite($vp2->SubsiteID);
		$onLive = Versioned::get_one_by_stage('SubsitesVirtualPage', 'Live', "\"SiteTree_Live\".\"ID\" = ".$vp2->ID);
		$this->assertNull($onLive, 'SVP has been removed from live');
	}
	
	/**
	 * Similar to {@link SiteTreeSubsitesTest->testTwoPagesWithSameURLOnDifferentSubsites()}
	 * and {@link SiteTreeSubsitesTest->testPagesInDifferentSubsitesCanShareURLSegment()}.
	 */
	function testSubsiteVirtualPageCanHaveSameUrlsegmentAsOtherSubsite() {
		Subsite::$write_hostmap = false;
		$subsite1 = $this->objFromFixture('Subsite', 'subsite1');
		$subsite2 = $this->objFromFixture('Subsite', 'subsite2');
		Subsite::changeSubsite($subsite1->ID);
		
		$subsite1Page = $this->objFromFixture('Page', 'subsite1_staff');
		$subsite1Page->URLSegment = 'staff';
		$subsite1Page->write();
		
		// saving on subsite1, and linking to subsite1
		$subsite1Vp = new SubsitesVirtualPage();
		$subsite1Vp->CopyContentFromID = $subsite1Page->ID;
		$subsite1Vp->SubsiteID = $subsite1->ID;
		$subsite1Vp->write();
		$this->assertNotEquals(
			$subsite1Vp->URLSegment, 
			$subsite1Page->URLSegment,
			"Doesn't allow explicit URLSegment overrides when already existing in same subsite"
		);
		
		//Change to subsite 2
		Subsite::changeSubsite($subsite2->ID);

		// saving in subsite2 (which already has a page with URLSegment 'contact-us'), 
		// but linking to a page in subsite1
		$subsite2Vp = new SubsitesVirtualPage();
		$subsite2Vp->CopyContentFromID = $subsite1Page->ID;
		$subsite2Vp->SubsiteID = $subsite2->ID; 
		$subsite2Vp->write();
		$this->assertEquals(
			$subsite2Vp->URLSegment, 
			$subsite1Page->URLSegment,
			"Does allow explicit URLSegment overrides when only existing in a different subsite"
		);
	}

	function fixVersionNumberCache($page) {
		$pages = func_get_args();
		foreach($pages as $p) {
			Versioned::prepopulate_versionnumber_cache('SiteTree', 'Stage', array($p->ID));
			Versioned::prepopulate_versionnumber_cache('SiteTree', 'Live', array($p->ID));
		}
	}

}
