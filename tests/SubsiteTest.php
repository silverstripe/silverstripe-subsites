<?php

class SubsiteTest extends SapphireTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';

	function testPagesInDifferentSubsitesCanShareURLSegment() {
		$subsiteMain = $this->objFromFixture('Subsite_Template', 'main');
		$subsite1 = $this->objFromFixture('Subsite_Template', 'subsite1');
		
		$pageMain = new SiteTree();
		$pageMain->URLSegment = 'testpage';
		$pageMain->write();
		$pageMain->publish('Stage', 'Live');
		
		$pageMainOther = new SiteTree();
		$pageMainOther->URLSegment = 'testpage';
		$pageMainOther->write();
		$pageMainOther->publish('Stage', 'Live');
		
		$this->assertNotEquals($pageMain->URLSegment, $pageMainOther->URLSegment,
			'Pages in same subsite cant share the same URL'
		);
	
		Subsite::changeSubsite($subsite1->ID);
	
		$pageSubsite1 = new SiteTree();
		$pageSubsite1->URLSegment = 'testpage';
		$pageSubsite1->write();
		$pageSubsite1->publish('Stage', 'Live');
		
		$this->assertEquals($pageMain->URLSegment, $pageSubsite1->URLSegment,
			'Pages in different subsites can share the same URL'
		);
	}
	
	/**
	 * Create a new subsite from the template and verify that all the template's pages are copied
	 */
	function testSubsiteCreation() {
		Subsite::$write_hostmap = false;
		
		// Create the instance
		$template = $this->objFromFixture('Subsite_Template', 'main');
	
		// Test that changeSubsite is working
		Subsite::changeSubsite($template->ID);
	
		if(defined('DB::USE_ANSI_SQL')) 
			$q="\"";
		else $q='`';
		
		$tmplHome = DataObject::get_one('SiteTree', "{$q}URLSegment{$q} = 'home'");
	
		// Publish all the pages in the template, testing that DataObject::get only returns pages from the chosen subsite
		$pages = DataObject::get("SiteTree");
		$totalPages = $pages->TotalItems();
		foreach($pages as $page) {
			$this->assertEquals($template->ID, $page->SubsiteID);
			$page->publish('Stage', 'Live');
		}
		
		// Create a new site
		$subsite = $template->createInstance('My Site', 'something.test.com');
	
		// Check title
		$this->assertEquals($subsite->Title, 'My Site');
		
		// Check that domain generation is working
		$this->assertEquals('something.test.com', $subsite->domain());
	
		// Another test that changeSubsite is working
		Subsite::changeSubsite($subsite->ID);
	
		$siteHome = DataObject::get_one('SiteTree', "{$q}URLSegment{$q} = 'home'");
		$this->assertNotNull($siteHome);
		$this->assertEquals($subsite->ID, $siteHome->SubsiteID,
			'createInstance() copies existing pages retaining the same URLSegment'
		);
		$this->assertEquals($siteHome->MasterPageID, $tmplHome->ID, 'Check master page value');
		
		// Check linking of child pages
		$tmplStaff = $this->objFromFixture('SiteTree','staff');
		$siteStaff = DataObject::get_one('SiteTree', "{$q}URLSegment{$q} = '" . Convert::raw2sql($tmplStaff->URLSegment) . "'");
		$this->assertEquals($siteStaff->MasterPageID, $tmplStaff->ID);
		
		Subsite::changeSubsite(0);
		
	}
	
	/**
	 * Confirm that domain lookup is working
	 */
	function testDomainLookup() {
		$this->assertEquals($this->idFromFixture('Subsite','domaintest1'),
			Subsite::getSubsiteIDForDomain('one.example.org'));
		$this->assertEquals($this->idFromFixture('Subsite','domaintest1'),
			Subsite::getSubsiteIDForDomain('one.localhost'));

		$this->assertEquals($this->idFromFixture('Subsite','domaintest2'),
			Subsite::getSubsiteIDForDomain('two.mysite.com'));
		$this->assertEquals($this->idFromFixture('Subsite','domaintest2'),
			Subsite::getSubsiteIDForDomain('other.mysite.com'));

		$this->assertNull(Subsite::getSubsiteIDForDomain('other.example.com'));
		$this->assertNull(Subsite::getSubsiteIDForDomain('two.example.com'));
	}

	/**
	 * Test the Subsite->domain() method
	 */
	function testDefaultDomain() {
		$this->assertEquals('one.example.org', 
			$this->objFromFixture('Subsite','domaintest1')->domain());

		$this->assertEquals('two.mysite.com', 
			$this->objFromFixture('Subsite','domaintest2')->domain());
			
		$originalHTTPHost = $_SERVER['HTTP_HOST'];
		
		$_SERVER['HTTP_HOST'] = "www.example.org";
		$this->assertEquals('three.example.org', 
			$this->objFromFixture('Subsite','domaintest3')->domain());

		$_SERVER['HTTP_HOST'] = "mysite.example.org";
		$this->assertEquals('three.mysite.example.org', 
			$this->objFromFixture('Subsite','domaintest3')->domain());


		$_SERVER['HTTP_HOST'] = $originalHTTPHost;

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
	
	function testCanEditSiteTree() {
		$admin = $this->objFromFixture('Member', 'admin');
		$subsite1member = $this->objFromFixture('Member', 'subsite1member');
		$subsite2member = $this->objFromFixture('Member', 'subsite2member');
		$mainpage = $this->objFromFixture('SiteTree', 'home');
		$subsite1page = $this->objFromFixture('SiteTree', 'subsite1_home');
		$subsite2page = $this->objFromFixture('SiteTree', 'subsite2_home');
		$subsite1 = $this->objFromFixture('Subsite_Template', 'subsite1');
		$subsite2 = $this->objFromFixture('Subsite_Template', 'subsite2');
	
		// Cant pass member as arguments to canEdit() because of GroupSubsites
		Session::set("loggedInAs", $admin->ID);
		$this->assertTrue(
			(bool)$subsite1page->canEdit(),
			'Administrators can edit all subsites'
		);
	
		// @todo: Workaround because GroupSubsites->augmentSQL() is relying on session state
		Subsite::changeSubsite($subsite1);
		
		Session::set("loggedInAs", $subsite1member->ID);
		$this->assertTrue(
			(bool)$subsite1page->canEdit(),
			'Members can edit pages on a subsite if they are in a group belonging to this subsite'
		);
	
		Session::set("loggedInAs", $subsite2member->ID);
		$this->assertFalse(
			(bool)$subsite1page->canEdit(),
			'Members cant edit pages on a subsite if they are not in a group belonging to this subsite'
		);
		
		// @todo: Workaround because GroupSubsites->augmentSQL() is relying on session state
		Subsite::changeSubsite(0);
		$this->assertFalse(
			$mainpage->canEdit(),
			'Members cant edit pages on the main site if they are not in a group allowing this'
		);
	}	

}