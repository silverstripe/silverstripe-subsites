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
	
		$tmplHome = DataObject::get_one('SiteTree', "\"URLSegment\" = 'home'");
	
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
	
		$siteHome = DataObject::get_one('SiteTree', "\"URLSegment\" = 'home'");
		$this->assertNotNull($siteHome);
		$this->assertEquals($subsite->ID, $siteHome->SubsiteID,
			'createInstance() copies existing pages retaining the same URLSegment'
		);
		$this->assertEquals($siteHome->MasterPageID, $tmplHome->ID, 'Check master page value');
		
		// Check linking of child pages
		$tmplStaff = $this->objFromFixture('SiteTree','staff');
		$siteStaff = DataObject::get_one('SiteTree', "\"URLSegment\" = '" . Convert::raw2sql($tmplStaff->URLSegment) . "'");
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

		$this->assertEquals(0, Subsite::getSubsiteIDForDomain('other.example.com'));
		$this->assertEquals(0, Subsite::getSubsiteIDForDomain('two.example.com'));
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
	 * Test Subsite::accessible_sites()
	 */
	function testAccessibleSites() {
		$member1Sites = Subsite::accessible_sites("CMS_ACCESS_CMSMain", false, null, 
			$this->objFromFixture('Member', 'subsite1member'));
		$member1SiteTitles = $member1Sites->column("Title");
		sort($member1SiteTitles);
		$this->assertEquals(array('Subsite1 Template'), $member1SiteTitles);

		$adminSites = Subsite::accessible_sites("CMS_ACCESS_CMSMain", false, null, 
			$this->objFromFixture('Member', 'admin'));
		$adminSiteTitles = $adminSites->column("Title");
		sort($adminSiteTitles);
		$this->assertEquals(array(
			'Subsite1 Template',
			'Subsite2 Template',
			'Template',
			'Test 1',
			'Test 2',
			'Test 3',
		), $adminSiteTitles);
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
	
	function testTwoPagesWithSameURLOnDifferentSubsites() {
		// Set up a couple of pages with the same URL on different subsites
		$s1 = $this->objFromFixture('Subsite','domaintest1');
		$s2 = $this->objFromFixture('Subsite','domaintest2');
		
		$p1 = new SiteTree();
		$p1->Title = $p1->URLSegment = "test-page";
		$p1->SubsiteID = $s1->ID;
		$p1->write();

		$p2 = new SiteTree();
		$p2->Title = $p1->URLSegment = "test-page";
		$p2->SubsiteID = $s2->ID;
		$p2->write();

		// Check that the URLs weren't modified in our set-up
		$this->assertEquals($p1->URLSegment, 'test-page');
		$this->assertEquals($p2->URLSegment, 'test-page');
		
		// Check that if we switch between the different subsites, we receive the correct pages
		Subsite::changeSubsite($s1);
		$this->assertEquals($p1->ID, SiteTree::get_by_link('test-page')->ID);

		Subsite::changeSubsite($s2);
		$this->assertEquals($p2->ID, SiteTree::get_by_link('test-page')->ID);
	}

}