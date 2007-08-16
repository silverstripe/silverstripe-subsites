<?php

class SubsiteTest extends SapphireTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';
	
	/**
	 * Create a new subsite from the template and verify that all the template's pages are copied
	 */
	function testSubsiteCreation() {
		// Create the instance
		$template = $this->objFromFixture('Subsite_Template', 'main');

		// Test that changeSubsite is working
		Subsite::changeSubsite($template->ID);

		$tmplHome = DataObject::get_one('SiteTree', "URLSegment = 'home'");

		// Publish all the pages in the template, testing that DataObject::get only returns pages from the chosen subsite
		$pages = DataObject::get("SiteTree");
		$totalPages = $pages->TotalItems();
		foreach($pages as $page) {
			$this->assertEquals($template->ID, $page->SubsiteID);
			$page->publish('Stage', 'Live');
		}
		
		// Create a new site
		$subsite = $template->createInstance('My Site', 'something');

		// Check title
		$this->assertEquals($subsite->Title, 'My Site');
		
		// Check that domain generation is working
		$this->assertEquals($subsite->domain(), 'something.test.com');

		// Another test that changeSubsite is working
		Subsite::changeSubsite($subsite->ID);
		$pages = DataObject::get("SiteTree");
		
		$siteHome = DataObject::get_one('SiteTree', "URLSegment = 'home'");
		$this->assertEquals($subsite->ID, $siteHome->SubsiteID);
		
		// Check master page value
		$this->assertEquals($siteHome->MasterPageID, $tmplHome->ID);
		
		// Check linking of child pages
		$tmplStaff = $this->objFromFixture('Page','staff');
		$siteStaff = DataObject::get_one('SiteTree', "URLSegment = '" . Convert::raw2sql($tmplStaff->URLSegment) . "'");
		$this->assertEquals($siteStaff->MasterPageID, $tmplStaff->ID);
		
	}
	
	/**
	 * Only the published content from the template should publish.
	 */
	function testUnpublishedPagesDontCopy() {
		
	}

	/**
	 * Publish a change on a master page of a newly created sub-site, and verify that the change has been propagated.
	 * Verify that if CustomContent is set, then the changes aren't propagated.
	 */
	
	/**
	 * Reorganise a couple of pages on the master site and verify that the changes are propagated, whether or not CustomContent
	 * is set.
	 */
	
	/**
	 * Edit a subsite's content and verify that CustomContent is set on the page.
	 * Edit a page without actually making any changes and verify that CustomContent isn't set.
	 */
	

}