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

}