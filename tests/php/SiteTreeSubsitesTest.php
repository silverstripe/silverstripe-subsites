<?php

namespace SilverStripe\Subsites\Tests;

use Page;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Subsites\Extensions\SiteTreeSubsites;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\Pages\SubsitesVirtualPage;
use SilverStripe\Subsites\Tests\SiteTreeSubsitesTest\TestClassA;
use SilverStripe\Subsites\Tests\SiteTreeSubsitesTest\TestClassB;
use SilverStripe\Subsites\Tests\SiteTreeSubsitesTest\TestErrorPage;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\SSViewer;

class SiteTreeSubsitesTest extends BaseSubsiteTest
{
    protected static $fixture_file = 'SubsiteTest.yml';

    protected static $extra_dataobjects = [
        TestClassA::class,
        TestClassB::class,
        TestErrorPage::class
    ];

    protected static $illegal_extensions = [
        SiteTree::class => ['Translatable'] // @todo implement Translatable namespace
    ];

    protected function setUp()
    {
        // We have our own home page fixtures, prevent the default one being created in this test suite.
        Config::modify()->set(SiteTree::class, 'create_default_pages', false);

        parent::setUp();
    }

    public function testPagesInDifferentSubsitesCanShareURLSegment()
    {
        $subsiteMain = $this->objFromFixture(Subsite::class, 'main');
        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');

        $pageMain = new SiteTree();
        $pageMain->URLSegment = 'testpage';
        $pageMain->write();
        $pageMain->copyVersionToStage('Stage', 'Live');

        $pageMainOther = new SiteTree();
        $pageMainOther->URLSegment = 'testpage';
        $pageMainOther->write();
        $pageMainOther->copyVersionToStage('Stage', 'Live');

        $this->assertNotEquals(
            $pageMain->URLSegment,
            $pageMainOther->URLSegment,
            'Pages in same subsite cant share the same URL'
        );

        Subsite::changeSubsite($subsite1->ID);

        $pageSubsite1 = new SiteTree();
        $pageSubsite1->URLSegment = 'testpage';
        $pageSubsite1->write();
        $pageSubsite1->copyVersionToStage('Stage', 'Live');

        $this->assertEquals(
            $pageMain->URLSegment,
            $pageSubsite1->URLSegment,
            'Pages in different subsites can share the same URL'
        );
    }

    public function testBasicSanity()
    {
        $this->assertInstanceOf(SiteConfig::class, singleton(SiteTree::class)->getSiteConfig());
        // The following assert is breaking in Translatable.
        $this->assertInstanceOf(FieldList::class, singleton(SiteTree::class)->getCMSFields());
        $this->assertInstanceOf(FieldList::class, singleton(SubsitesVirtualPage::class)->getCMSFields());
        $this->assertTrue(is_array(singleton(SiteTreeSubsites::class)->extraStatics()));
    }

    public function errorPageLocationsProvider()
    {
        return [
            ['domaintest1', '/error-500-one.example.org.html'],
            ['domaintestVagrant', '/error-500-localhost8080.html']
        ];
    }

    /**
     * @dataProvider errorPageLocationsProvider
     */
    public function testErrorPageLocations($subsiteFixtureName, $expectedFilename)
    {
        $static_path = Config::inst()->get(ErrorPage::class, 'static_filepath');

        $subsite = $this->objFromFixture(Subsite::class, $subsiteFixtureName);
        $expected_path = $static_path . $expectedFilename;

        Subsite::changeSubsite($subsite->ID);
        $path = TestErrorPage::get_error_filename_spy(500);

        $this->assertEquals($expected_path, $path);
    }

    public function testCanEditSiteTree()
    {
        $admin = $this->objFromFixture(Member::class, 'admin');
        $subsite1member = $this->objFromFixture(Member::class, 'subsite1member');
        $subsite2member = $this->objFromFixture(Member::class, 'subsite2member');
        $mainpage = $this->objFromFixture('Page', 'home');
        $subsite1page = $this->objFromFixture('Page', 'subsite1_home');
        $subsite2page = $this->objFromFixture('Page', 'subsite2_home');
        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $subsite2 = $this->objFromFixture(Subsite::class, 'subsite2');

        // Cant pass member as arguments to canEdit() because of GroupSubsites
        $this->logInAs($admin);

        $this->assertTrue(
            (bool)$subsite1page->canEdit(),
            'Administrators can edit all subsites'
        );

        // @todo: Workaround because GroupSubsites->augmentSQL() is relying on session state
        Subsite::changeSubsite($subsite1);

        $this->logInAs($subsite1member->ID);
        $this->assertTrue(
            (bool)$subsite1page->canEdit(),
            'Members can edit pages on a subsite if they are in a group belonging to this subsite'
        );

        $this->logInAs($subsite2member->ID);
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
    public function testTwoPagesWithSameURLOnDifferentSubsites()
    {
        // Set up a couple of pages with the same URL on different subsites
        $s1 = $this->objFromFixture(Subsite::class, 'domaintest1');
        $s2 = $this->objFromFixture(Subsite::class, 'domaintest2');

        $p1 = new SiteTree();
        $p1->Title = $p1->URLSegment = 'test-page';
        $p1->SubsiteID = $s1->ID;
        $p1->write();

        $p2 = new SiteTree();
        $p2->Title = $p1->URLSegment = 'test-page';
        $p2->SubsiteID = $s2->ID;
        $p2->write();

        // Check that the URLs weren't modified in our set-up
        $this->assertEquals('test-page', $p1->URLSegment);
        $this->assertEquals('test-page', $p2->URLSegment);

        // Check that if we switch between the different subsites, we receive the correct pages
        Subsite::changeSubsite($s1);
        $this->assertEquals($p1->ID, SiteTree::get_by_link('test-page')->ID);

        Subsite::changeSubsite($s2);
        $this->assertEquals($p2->ID, SiteTree::get_by_link('test-page')->ID);
    }

    public function testPageTypesBlacklistInClassDropdown()
    {
        $this->logInAs('editor');

        $s1 = $this->objFromFixture(Subsite::class, 'domaintest1');
        $s2 = $this->objFromFixture(Subsite::class, 'domaintest2');
        $page = singleton(SiteTree::class);

        $s1->PageTypeBlacklist = json_encode([TestClassA::class, ErrorPage::class]);
        $s1->write();

        Subsite::changeSubsite($s1);
        $settingsFields = $page->getSettingsFields()->dataFieldByName('ClassName')->getSource();

        $this->assertArrayNotHasKey(
            ErrorPage::class,
            $settingsFields
        );
        $this->assertArrayNotHasKey(
            TestClassA::class,
            $settingsFields
        );
        $this->assertArrayHasKey(
            TestClassB::class,
            $settingsFields
        );

        Subsite::changeSubsite($s2);
        $settingsFields = $page->getSettingsFields()->dataFieldByName('ClassName')->getSource();
        $this->assertArrayHasKey(
            ErrorPage::class,
            $settingsFields
        );
        $this->assertArrayHasKey(
            TestClassA::class,
            $settingsFields
        );
        $this->assertArrayHasKey(
            TestClassB::class,
            $settingsFields
        );
    }

    public function testCopyToSubsite()
    {
        // Remove baseurl if testing in subdir
        Config::modify()->set(Director::class, 'alternate_base_url', '/');

        /** @var Subsite $otherSubsite */
        $otherSubsite = $this->objFromFixture(Subsite::class, 'subsite1');
        $staffPage = $this->objFromFixture(Page::class, 'staff'); // nested page
        $contactPage = $this->objFromFixture(Page::class, 'contact'); // top level page

        $staffPage2 = $staffPage->duplicateToSubsite($otherSubsite->ID);
        $contactPage2 = $contactPage->duplicateToSubsite($otherSubsite->ID);

        $this->assertNotEquals($staffPage->ID, $staffPage2->ID);
        $this->assertNotEquals($staffPage->SubsiteID, $staffPage2->SubsiteID);
        $this->assertNotEquals($contactPage->ID, $contactPage2->ID);
        $this->assertNotEquals($contactPage->SubsiteID, $contactPage2->SubsiteID);
        $this->assertEmpty($staffPage2->ParentID);
        $this->assertEmpty($contactPage2->ParentID);
        $this->assertNotEmpty($staffPage->ParentID);
        $this->assertEmpty($contactPage->ParentID);

        // Staff is shifted to top level and given a unique url segment
        $domain = $otherSubsite->domain();
        $this->assertEquals('http://' . $domain . '/staff-2/', $staffPage2->AbsoluteLink());
        $this->assertEquals('http://' . $domain . '/contact-us-2/', $contactPage2->AbsoluteLink());
    }

    public function testPageTypesBlacklistInCMSMain()
    {
        $this->logInAs('editor');

        $s1 = $this->objFromFixture(Subsite::class, 'domaintest1');
        $s2 = $this->objFromFixture(Subsite::class, 'domaintest2');

        $s1->PageTypeBlacklist = json_encode([TestClassA::class, ErrorPage::class]);
        $s1->write();

        Subsite::changeSubsite($s1);
        $cmsmain = CMSMain::create();
        $hints = Convert::json2array($cmsmain->SiteTreeHints());
        $classes = $hints['Root']['disallowedChildren'];
        $this->assertContains(ErrorPage::class, $classes);
        $this->assertContains(TestClassA::class, $classes);
        $this->assertNotContains(TestClassB::class, $classes);

        Subsite::changeSubsite($s2);
        // SS 4.1 and above
        if ($cmsmain->hasMethod('getHintsCache')) {
            $cmsmain->getHintsCache()->clear();
        }
        $hints = Convert::json2array($cmsmain->SiteTreeHints());

        $classes = $hints['Root']['disallowedChildren'];
        $this->assertNotContains(ErrorPage::class, $classes);
        $this->assertNotContains(TestClassA::class, $classes);
        $this->assertNotContains(TestClassB::class, $classes);
    }

    /**
     * Tests that url segments between subsites don't conflict, but do conflict within them
     */
    public function testValidateURLSegment()
    {
        $this->logInWithPermission('ADMIN');
        // Saving existing page in the same subsite doesn't change urls
        $mainHome = $this->objFromFixture(Page::class, 'home');
        $mainSubsiteID = $this->idFromFixture(Subsite::class, 'main');
        Subsite::changeSubsite($mainSubsiteID);
        $mainHome->Content = '<p>Some new content</p>';
        $mainHome->write();
        $this->assertEquals('home', $mainHome->URLSegment);
        $mainHome->publishRecursive();
        $mainHomeLive = Versioned::get_one_by_stage('Page', 'Live', sprintf('"SiteTree"."ID" = \'%d\'', $mainHome->ID));
        $this->assertEquals('home', $mainHomeLive->URLSegment);

        // Saving existing page in another subsite doesn't change urls
        Subsite::changeSubsite($mainSubsiteID);
        $subsite1Home = $this->objFromFixture('Page', 'subsite1_home');
        $subsite1Home->Content = '<p>In subsite 1</p>';
        $subsite1Home->write();
        $this->assertEquals('home', $subsite1Home->URLSegment);
        $subsite1Home->publishRecursive();
        $subsite1HomeLive = Versioned::get_one_by_stage(
            'Page',
            'Live',
            sprintf('"SiteTree"."ID" = \'%d\'', $subsite1Home->ID)
        );
        $this->assertEquals('home', $subsite1HomeLive->URLSegment);

        // Creating a new page in a subsite doesn't conflict with urls in other subsites
        $subsite1ID = $this->idFromFixture(Subsite::class, 'subsite1');
        Subsite::changeSubsite($subsite1ID);
        $subsite1NewPage = new Page();
        $subsite1NewPage->SubsiteID = $subsite1ID;
        $subsite1NewPage->Title = 'Important Page (Subsite 1)';
        $subsite1NewPage->URLSegment = 'important-page'; // Also exists in main subsite
        $subsite1NewPage->write();
        $this->assertEquals('important-page', $subsite1NewPage->URLSegment);
        $subsite1NewPage->publishRecursive();
        $subsite1NewPageLive = Versioned::get_one_by_stage(
            'Page',
            'Live',
            sprintf('"SiteTree"."ID" = \'%d\'', $subsite1NewPage->ID)
        );
        $this->assertEquals('important-page', $subsite1NewPageLive->URLSegment);

        // Creating a new page in a subsite DOES conflict with urls in the same subsite
        $subsite1NewPage2 = new Page();
        $subsite1NewPage2->SubsiteID = $subsite1ID;
        $subsite1NewPage2->Title = 'Important Page (Subsite 1)';
        $subsite1NewPage2->URLSegment = 'important-page'; // Also exists in main subsite
        $subsite1NewPage2->write();
        $this->assertEquals('important-page-2', $subsite1NewPage2->URLSegment);
        $subsite1NewPage2->publishRecursive();
        $subsite1NewPage2Live = Versioned::get_one_by_stage(
            'Page',
            'Live',
            sprintf('"SiteTree"."ID" = \'%d\'', $subsite1NewPage2->ID)
        );
        $this->assertEquals('important-page-2', $subsite1NewPage2Live->URLSegment);

        // Original page is left un-modified
        $mainSubsiteImportantPageID = $this->idFromFixture('Page', 'importantpage');
        $mainSubsiteImportantPage = Page::get()->byID($mainSubsiteImportantPageID);
        $this->assertEquals('important-page', $mainSubsiteImportantPage->URLSegment);
        $mainSubsiteImportantPage->Content = '<p>New Important Page Content</p>';
        $mainSubsiteImportantPage->write();
        $this->assertEquals('important-page', $mainSubsiteImportantPage->URLSegment);
    }

    /**
     * @param bool $withChildren
     * @param int $expectedChildren
     * @dataProvider duplicateToSubsiteProvider
     */
    public function testDuplicateToSubsite($withChildren, $expectedChildren)
    {
        /** @var SiteTree $page */
        $page = $this->objFromFixture(Page::class, 'about');
        /** @var Subsite $newSubsite */
        $newSubsite = $this->objFromFixture(Subsite::class, 'subsite1');

        /** @var SiteTree $duplicatedPage */
        $duplicatedPage = $page->duplicateToSubsite($newSubsite->ID, $withChildren);
        $this->assertInstanceOf(SiteTree::class, $duplicatedPage, 'A new page is returned');

        $this->assertEquals($newSubsite->ID, $duplicatedPage->SubsiteID, 'Ensure returned records are on new subsite');

        $this->assertCount(1, $page->AllChildren());
        $this->assertCount(
            $expectedChildren,
            $duplicatedPage->AllChildren(),
            'Duplicated page also duplicates children'
        );
    }

    /**
     * @return array[]
     */
    public function duplicateToSubsiteProvider()
    {
        return [
            [true, 1],
            [false, 0],
        ];
    }

    public function testIfSubsiteThemeIsSetToThemeList()
    {
        $defaultThemes = ['default'];
        SSViewer::set_themes($defaultThemes);

        $subsitePage = $this->objFromFixture(Page::class, 'home');
        Subsite::changeSubsite($subsitePage->SubsiteID);
        $controller = ModelAsController::controller_for($subsitePage);
        SiteTree::singleton()->extend('contentcontrollerInit', $controller);

        $this->assertEquals(
            SSViewer::get_themes(),
            $defaultThemes,
            'Themes should not be modified when Subsite has no theme defined'
        );

        $pageWithTheme = $this->objFromFixture(Page::class, 'subsite1_home');
        Subsite::changeSubsite($pageWithTheme->SubsiteID);
        $controller = ModelAsController::controller_for($pageWithTheme);
        SiteTree::singleton()->extend('contentcontrollerInit', $controller);
        $subsiteTheme = $pageWithTheme->Subsite()->Theme;

        $allThemes = SSViewer::get_themes();

        $this->assertContains(
            $subsiteTheme,
            $allThemes,
            'Themes should be modified when Subsite has theme defined'
        );

        $this->assertEquals(
            $subsiteTheme,
            array_shift($allThemes),
            'Subsite theme should be prepeded to theme list'
        );
    }

    public function provideAlternateAbsoluteLink()
    {
        return [
            ['home', null, 'http://localhost/'],
            ['home', 'myaction', 'http://localhost/home/myaction'],
            ['contact', null, 'http://localhost/contact-us/'],
            ['contact', 'myaction', 'http://localhost/contact-us/myaction'],
            ['subsite1_home', null, 'http://subsite1.localhost/'],
            ['subsite1_home', 'myaction', 'http://subsite1.localhost/home/myaction'],
            ['subsite1_contactus', null, 'http://subsite1.localhost/contact-us/'],
            ['subsite1_contactus', 'myaction', 'http://subsite1.localhost/contact-us/myaction']
        ];
    }

    /**
     * @dataProvider provideAlternateAbsoluteLink
     * @param name $pageFixtureName
     * @param string|null $action
     * @param string $expectedAbsoluteLink
     */
    public function testAlternateAbsoluteLink($pageFixtureName, $action, $expectedAbsoluteLink)
    {
        // Setting a control value, in case base url is set for the installation under test
        Config::modify()->set(Director::class, 'alternate_base_url', 'http://localhost/');

        /** @var Page $page */
        $page = $this->objFromFixture(Page::class, $pageFixtureName);

        $result = $page->AbsoluteLink($action);

        $this->assertEquals($expectedAbsoluteLink, $result);
    }
}
