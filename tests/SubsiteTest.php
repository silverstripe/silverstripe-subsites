<?php

class SubsiteTest extends SapphireTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';

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
		$subsite->activate();
	
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
		$this->assertEquals(
			$this->idFromFixture('Subsite','domaintest1'),
			Subsite::getSubsiteIDForDomain('one.example.org')
		);
		
		$this->assertEquals(
			$this->idFromFixture('Subsite','domaintest1'),
			Subsite::getSubsiteIDForDomain('one.localhost')
		);

		$this->assertEquals(
			$this->idFromFixture('Subsite','domaintest2'),
			Subsite::getSubsiteIDForDomain('two.mysite.com')
		);
		
		$this->assertEquals(
			$this->idFromFixture('Subsite','domaintest2'),
			Subsite::getSubsiteIDForDomain('other.mysite.com')
		);

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
			
			
		$this->assertEquals($_SERVER['HTTP_HOST'], singleton('Subsite')->PrimaryDomain);
		$this->assertEquals('http://'.$_SERVER['HTTP_HOST'].Director::baseURL(), singleton('Subsite')->absoluteBaseURL());


		$_SERVER['HTTP_HOST'] = $originalHTTPHost;

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
	
	function testhasMainSitePermission() {
		$admin = $this->objFromFixture('Member', 'admin');
		$subsite1member = $this->objFromFixture('Member', 'subsite1member');
		$subsite1admin = $this->objFromFixture('Member', 'subsite1admin');
		$allsubsitesauthor = $this->objFromFixture('Member', 'allsubsitesauthor');
		
		$this->assertTrue(
			Subsite::hasMainSitePermission($admin), 
			'Default permissions granted for super-admin'
		);
		$this->assertTrue(
			Subsite::hasMainSitePermission($admin, array("ADMIN")), 
			'ADMIN permissions granted for super-admin'
		);
		$this->assertFalse(
			Subsite::hasMainSitePermission($subsite1admin, array("ADMIN")), 
			'ADMIN permissions (on main site) denied for subsite1 admin'
		);
		$this->assertFalse(
			Subsite::hasMainSitePermission($subsite1admin, array("CMS_ACCESS_CMSMain")), 
			'CMS_ACCESS_CMSMain (on main site) denied for subsite1 admin'
		);
		$this->assertFalse(
			Subsite::hasMainSitePermission($allsubsitesauthor, array("ADMIN")), 
			'ADMIN permissions (on main site) denied for CMS author with edit rights on all subsites'
		);
		$this->assertTrue(
			Subsite::hasMainSitePermission($allsubsitesauthor, array("CMS_ACCESS_CMSMain")), 
			'CMS_ACCESS_CMSMain (on main site) granted for CMS author with edit rights on all subsites'
		);
		$this->assertFalse(
			Subsite::hasMainSitePermission($subsite1member, array("ADMIN")), 
			'ADMIN (on main site) denied for subsite1 subsite1 cms author'
		);
		$this->assertFalse(
			Subsite::hasMainSitePermission($subsite1member, array("CMS_ACCESS_CMSMain")), 
			'CMS_ACCESS_CMSMain (on main site) denied for subsite1 cms author'
		);
	}

	function testDuplicateSubsite() {
		// get subsite1 & create page
		$subsite1 = $this->objFromFixture('Subsite','domaintest1');
		$subsite1->activate();
		$page1 = new Page();
		$page1->Title = 'MyAwesomePage';
		$page1->write();
		$page1->doPublish();
		$this->assertEquals($page1->SubsiteID, $subsite1->ID);
		
		// duplicate
		$subsite2 = $subsite1->duplicate();
		$subsite2->activate();
		// change content on dupe
		$page2 = DataObject::get_one('Page', "\"Title\" = 'MyAwesomePage'");
		$page2->Title = 'MyNewAwesomePage';
		$page2->write();
		$page2->doPublish();
		
		// check change & check change has not affected subiste1
		$subsite1->activate();
		$this->assertEquals('MyAwesomePage', DataObject::get_by_id('Page', $page1->ID)->Title);
		$subsite2->activate();
		$this->assertEquals('MyNewAwesomePage', DataObject::get_by_id('Page', $page2->ID)->Title);
	}
}