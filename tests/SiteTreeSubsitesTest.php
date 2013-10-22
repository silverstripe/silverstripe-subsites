<?php

class SiteTreeSubsitesTest extends BaseSubsiteTest {

	static $fixture_file = 'subsites/tests/SubsiteTest.yml';
	
	protected $extraDataObjects = array(
		'SiteTreeSubsitesTest_ClassA',
		'SiteTreeSubsitesTest_ClassB'
	);
	
	function testPagesInDifferentSubsitesCanShareURLSegment() {
		$subsiteMain = $this->objFromFixture('Subsite', 'main');
		$subsite1 = $this->objFromFixture('Subsite', 'subsite1');
		
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
	
	function testBasicSanity() {
		$this->assertTrue(singleton('SiteTree')->getSiteConfig() instanceof SiteConfig);
		// The following assert is breaking in Translatable.
		$this->assertTrue(singleton('SiteTree')->getCMSFields() instanceof FieldList);
		$this->assertTrue(singleton('SubsitesVirtualPage')->getCMSFields() instanceof FieldList);
		$this->assertTrue(is_array(singleton('SiteTreeSubsites')->extraStatics()));
	}
	
	function testErrorPageLocations() {
		$subsite1 = $this->objFromFixture('Subsite', 'domaintest1');
		
		Subsite::changeSubsite($subsite1->ID);
		$path = ErrorPage::get_filepath_for_errorcode(500);
		
		$static_path = Config::inst()->get('ErrorPage', 'static_filepath');
		$expected_path = $static_path . '/error-500-'.$subsite1->domain().'.html';
		$this->assertEquals($expected_path, $path);
	}
	
	function testCanEditSiteTree() {
		$admin = $this->objFromFixture('Member', 'admin');
		$subsite1member = $this->objFromFixture('Member', 'subsite1member');
		$subsite2member = $this->objFromFixture('Member', 'subsite2member');
		$mainpage = $this->objFromFixture('Page', 'home');
		$subsite1page = $this->objFromFixture('Page', 'subsite1_home');
		$subsite2page = $this->objFromFixture('Page', 'subsite2_home');
		$subsite1 = $this->objFromFixture('Subsite', 'subsite1');
		$subsite2 = $this->objFromFixture('Subsite', 'subsite2');
	
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
	
	/**
	 * Similar to {@link SubsitesVirtualPageTest->testSubsiteVirtualPageCanHaveSameUrlsegmentAsOtherSubsite()}.
	 */
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
	
	function testPageTypesBlacklistInClassDropdown() {
		Session::set("loggedInAs", null);
		
		$s1 = $this->objFromFixture('Subsite','domaintest1');
		$s2 = $this->objFromFixture('Subsite','domaintest2');
		$page = singleton('SiteTree');
		
		$s1->PageTypeBlacklist = 'SiteTreeSubsitesTest_ClassA,ErrorPage';
		$s1->write();
		
		Subsite::changeSubsite($s1);
		$settingsFields = $page->getSettingsFields()->dataFieldByName('ClassName')->getSource();
		$this->assertArrayNotHasKey('ErrorPage', 
			$settingsFields
		);
		$this->assertArrayNotHasKey('SiteTreeSubsitesTest_ClassA', 
			$settingsFields
		);
		$this->assertArrayHasKey('SiteTreeSubsitesTest_ClassB', 
			$settingsFields
		);

		Subsite::changeSubsite($s2);
		$settingsFields = $page->getSettingsFields()->dataFieldByName('ClassName')->getSource();
		$this->assertArrayHasKey('ErrorPage', 
			$settingsFields
		);
		$this->assertArrayHasKey('SiteTreeSubsitesTest_ClassA', 
			$settingsFields
		);
		$this->assertArrayHasKey('SiteTreeSubsitesTest_ClassB', 
			$settingsFields
		);
	}
	
	function testPageTypesBlacklistInCMSMain() {
		Session::set("loggedInAs", null);
		
		$cmsmain = new CMSMain();
		
		$s1 = $this->objFromFixture('Subsite','domaintest1');
		$s2 = $this->objFromFixture('Subsite','domaintest2');
		
		$s1->PageTypeBlacklist = 'SiteTreeSubsitesTest_ClassA,ErrorPage';
		$s1->write();

		Subsite::changeSubsite($s1);
		$classes = $cmsmain->PageTypes()->column('ClassName');
		$this->assertNotContains('ErrorPage', $classes);
		$this->assertNotContains('SiteTreeSubsitesTest_ClassA', $classes);
		$this->assertContains('SiteTreeSubsitesTest_ClassB', $classes);

		Subsite::changeSubsite($s2);
		$classes = $cmsmain->PageTypes()->column("ClassName");
		$this->assertContains('ErrorPage', $classes);
		$this->assertContains('SiteTreeSubsitesTest_ClassA', $classes);
		$this->assertContains('SiteTreeSubsitesTest_ClassB', $classes);
	}
	
}

class SiteTreeSubsitesTest_ClassA extends SiteTree implements TestOnly {}

class SiteTreeSubsitesTest_ClassB extends SiteTree implements TestOnly {}
