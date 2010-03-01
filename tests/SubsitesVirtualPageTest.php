<?php

class SubsitesVirtualPageTest extends SapphireTest {
	static $fixture_file = array(
		'subsites/tests/SubsiteTest.yml',
		'sapphire/tests/FileLinkTrackingTest.yml',
	);
	
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

	function testFileLinkRewritingOnVirtualPages() {
		// File setup
		$this->logInWithPermssion('ADMIN');
		touch(Director::baseFolder() . '/assets/testscript-test-file.pdf');

		// Publish the source page
		$page = $this->objFromFixture('Page', 'page1');
		$this->assertTrue($page->doPublish());

		$svp = new SubsitesVirtualPage();
		$svp->CopyContentFromID = $page->ID;
		$svp->write();
		$svp->doPublish();
			
		// Create a virtual page from it, and publish that
		
		// Rename the file
		$file = $this->objFromFixture('File', 'file1');
		$file->Name = 'renamed-test-file.pdf';
		
		// Verify that the draft and publish virtual pages both have the corrected link
		$this->assertContains('<img src="assets/renamed-test-file.pdf" />',
			DB::query("SELECT \"Content\" FROM \"SiteTree\" WHERE \"ID\" = $svp->ID")->value());
		$this->assertContains('<img src="assets/renamed-test-file.pdf" />',
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
	
	function testUnpublishingParentPageUnpublishesSubsiteVirtualPages() {
		StaticPublisher::$disable_realtime = true;
		
		// Go to main site, get parent page
		$subsite = $this->objFromFixture('Subsite_Template', 'main');
		Subsite::changeSubsite($subsite->ID);
		$page = $this->objFromFixture('SiteTree', 'importantpage');
		
		// Create two SVPs on other subsites
		$subsite = $this->objFromFixture('Subsite_Template', 'subsite1');
		Subsite::changeSubsite($subsite->ID);
		$vp1 = new SubsitesVirtualPage();
		$vp1->CopyContentFromID = $page->ID;
		$vp1->write();
		$vp1->doPublish();
		
		$subsite = $this->objFromFixture('Subsite_Template', 'subsite2');
		Subsite::changeSubsite($subsite->ID);
		$vp2 = new SubsitesVirtualPage();
		$vp2->CopyContentFromID = $page->ID;
		$vp2->write();
		$vp2->doPublish();
		
		// Switch back to main site, unpublish source
		$subsite = $this->objFromFixture('Subsite_Template', 'main');
		Subsite::changeSubsite($subsite->ID);
		$page = $this->objFromFixture('SiteTree', 'importantpage');
		$page->doUnpublish();
		
		Subsite::changeSubsite($vp1->SubsiteID);
		$onLive = Versioned::get_one_by_stage('SubsitesVirtualPage', 'Live', "SiteTree_Live.ID = ".$vp1->ID);
		$this->assertFalse($onLive, 'SVP has been removed from live');
		
		$subsite = $this->objFromFixture('Subsite_Template', 'subsite2');
		Subsite::changeSubsite($vp2->SubsiteID);
		$onLive = Versioned::get_one_by_stage('SubsitesVirtualPage', 'Live', "SiteTree_Live.ID = ".$vp2->ID);
		$this->assertFalse($onLive, 'SVP has been removed from live');
	}

	function fixVersionNumberCache($page) {
		$pages = func_get_args();
		foreach($pages as $p) {
			Versioned::prepopulate_versionnumber_cache('SiteTree', 'Stage', array($p->ID));
			Versioned::prepopulate_versionnumber_cache('SiteTree', 'Live', array($p->ID));
		}
	}

	/**
	 * Test custom metadata. Reloading Content should not
	 * obliterate our custom fields
	 */
	function testCustomMetadata() {
		Subsite::$write_hostmap = false;
		
		$subsite = $this->objFromFixture('Subsite_Template', 'main');
		
		Subsite::changeSubsite($subsite->ID);
		
		$orig = $this->objFromFixture('SiteTree', 'linky');
		
		$svp = new SubsitesVirtualPage();
		$svp->CopyContentFromID = $orig->ID;
		$svp->SubsiteID = $subsite->ID;
		$svp->URLSegment = 'linky-'.rand();
		$svp->write();
		
		$this->assertEquals($svp->MetaTitle, 'Linky');
		
		$svp->CustomMetaTitle = 'SVPTitle';
		$svp->write();
		$this->assertEquals($svp->MetaTitle, 'SVPTitle');
		
		$svp->copyFrom($svp->CopyContentFrom());
		$svp->write();
		
		$this->assertEquals($svp->MetaTitle, 'SVPTitle');
		
	}
}