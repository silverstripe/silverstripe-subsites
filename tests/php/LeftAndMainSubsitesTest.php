<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\Security\Member;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Config\Config;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Subsites\Model\Subsite;

class LeftAndMainSubsitesTest extends FunctionalTest
{
    public static $fixture_file = 'subsites/tests/php/SubsiteTest.yml';

    /**
     * Avoid subsites filtering on fixture fetching.
     * @param string $class
     * @param string $id
     * @return \SilverStripe\ORM\DataObject
     */
    public function objFromFixture($class, $id)
    {
        Subsite::disable_subsite_filter(true);
        $obj = parent::objFromFixture($class, $id);
        Subsite::disable_subsite_filter(false);

        return $obj;
    }

    public function testSectionSites()
    {
        $member = $this->objFromFixture(Member::class, 'subsite1member');

        $cmsmain = singleton(CMSMain::class);
        $subsites = $cmsmain->sectionSites(true, 'Main site', $member);
        $this->assertDOSEquals(array(
            array('Title' =>'Subsite1 Template')
        ), $subsites, 'Lists member-accessible sites for the accessible controller.');

        $assetadmin = singleton(AssetAdmin::class);
        $subsites = $assetadmin->sectionSites(true, 'Main site', $member);
        $this->assertDOSEquals([], $subsites, 'Does not list any sites for forbidden controller.');

        $member = $this->objFromFixture(Member::class, 'editor');

        $cmsmain = singleton(CMSMain::class);
        $subsites = $cmsmain->sectionSites(true, 'Main site', $member);
        $this->assertDOSContains(array(
            array('Title' =>'Main site')
        ), $subsites, 'Includes the main site for members who can access all sites.');
    }

    public function testAccessChecksDontChangeCurrentSubsite()
    {
        $admin = $this->objFromFixture(Member::class, 'admin');
        $this->logInAs($admin);
        $ids = array();

        $subsite1 = $this->objFromFixture(Subsite::class, 'domaintest1');
        $subsite2 = $this->objFromFixture(Subsite::class, 'domaintest2');
        $subsite3 = $this->objFromFixture(Subsite::class, 'domaintest3');
        $ids[] = $subsite1->ID;
        $ids[] = $subsite2->ID;
        $ids[] = $subsite3->ID;
        $ids[] = 0;

        // Enable session-based subsite tracking.
        Subsite::$use_session_subsiteid = true;

        foreach ($ids as $id) {
            Subsite::changeSubsite($id);
            $this->assertEquals($id, Subsite::currentSubsiteID());

			$left = new LeftAndMain();
			$this->assertTrue($left->canView(), "Admin user can view subsites LeftAndMain with id = '$id'");
			$this->assertEquals($id, Subsite::currentSubsiteID(),
                'The current subsite has not been changed in the process of checking permissions for admin user.');
		}

	}

    public function testShouldChangeSubsite()
    {
        $l = new LeftAndMain();
        Config::nest();

        Config::modify()->set(CMSPageEditController::class, 'treats_subsite_0_as_global', false);
        $this->assertTrue($l->shouldChangeSubsite(CMSPageEditController::class, 0, 5));
        $this->assertFalse($l->shouldChangeSubsite(CMSPageEditController::class, 0, 0));
        $this->assertTrue($l->shouldChangeSubsite(CMSPageEditController::class, 1, 5));
        $this->assertFalse($l->shouldChangeSubsite(CMSPageEditController::class, 1, 1));

        Config::modify()->set(CMSPageEditController::class, 'treats_subsite_0_as_global', true);
        $this->assertFalse($l->shouldChangeSubsite(CMSPageEditController::class, 0, 5));
        $this->assertFalse($l->shouldChangeSubsite(CMSPageEditController::class, 0, 0));
        $this->assertTrue($l->shouldChangeSubsite(CMSPageEditController::class, 1, 5));
        $this->assertFalse($l->shouldChangeSubsite(CMSPageEditController::class, 1, 1));

        Config::unnest();
    }
}
