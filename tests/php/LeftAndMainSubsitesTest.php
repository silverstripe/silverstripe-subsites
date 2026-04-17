<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\Subsites\Extensions\LeftAndMainSubsites;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;
use SilverStripe\Control\Director;

class LeftAndMainSubsitesTest extends FunctionalTest
{
    protected static $fixture_file = 'SubsiteTest.yml';

    /**
     * Avoid subsites filtering on fixture fetching.
     * @param string $className
     * @param string $identifier
     * @return \SilverStripe\ORM\DataObject
     */
    protected function objFromFixture($className, $identifier)
    {
        Subsite::disable_subsite_filter(true);
        $obj = parent::objFromFixture($className, $identifier);
        Subsite::disable_subsite_filter(false);

        return $obj;
    }

    public function testSectionSites()
    {
        $member = $this->objFromFixture(Member::class, 'subsite1member');

        $cmsmain = singleton(CMSMain::class);
        $subsites = $cmsmain->sectionSites(true, 'Main site', $member);
        $this->assertListEquals([
            ['Title' => 'Subsite1 Template']
        ], $subsites, 'Lists member-accessible sites for the accessible controller.');

        $assetadmin = singleton(AssetAdmin::class);
        $subsites = $assetadmin->sectionSites(true, 'Main site', $member);
        $this->assertListEquals([], $subsites, 'Does not list any sites for forbidden controller.');

        $member = $this->objFromFixture(Member::class, 'editor');

        $cmsmain = singleton(CMSMain::class);
        $subsites = $cmsmain->sectionSites(true, 'Main site', $member);
        $this->assertListContains([
            ['Title' => 'Main site']
        ], $subsites, 'Includes the main site for members who can access all sites.');
    }

    public function testAccessChecksDontChangeCurrentSubsite()
    {
        $this->logInAs('admin');
        $ids = [];

        $subsite1 = $this->objFromFixture(Subsite::class, 'domaintest1');
        $subsite2 = $this->objFromFixture(Subsite::class, 'domaintest2');
        $subsite3 = $this->objFromFixture(Subsite::class, 'domaintest3');
        $ids[] = $subsite1->ID;
        $ids[] = $subsite2->ID;
        $ids[] = $subsite3->ID;
        $ids[] = 0;

        // Enable session-based subsite tracking.
        SubsiteState::singleton()->setUseSessions(true);

        foreach ($ids as $id) {
            Subsite::changeSubsite($id);
            $this->assertEquals($id, SubsiteState::singleton()->getSubsiteId());

            $left = new LeftAndMain();
            $this->assertTrue($left->canView(), "Admin user can view subsites LeftAndMain with id = '$id'");
            $this->assertEquals(
                $id,
                SubsiteState::singleton()->getSubsiteId(),
                'The current subsite has not been changed in the process of checking permissions for admin user.'
            );
        }
    }

    public function testShouldChangeSubsite()
    {
        $l = new LeftAndMain();

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
    }

    public function testCanAccessWithPassedMember()
    {
        $memberID = $this->logInWithPermission('ADMIN');
        $member = Member::get()->byID($memberID);

        /** @var LeftAndMain&LeftAndMainSubsites $leftAndMain */
        $leftAndMain = new LeftAndMain();
        $this->assertTrue($leftAndMain->alternateAccessCheck($member));
    }

    public function testSubsiteSwitchListNullForSingleAccessUser()
    {
        $this->logInAs('subsite1member');
        SubsiteState::singleton()->setUseSessions(true);

        $result = LeftAndMainSubsites::SubsiteSwitchList();
        $this->assertNull($result, 'SubsiteSwitchList should return null when user has access to only one subsite');
    }

    public function testAutoSwitchToOnlyAccessibleSubsite()
    {
        SubsiteState::singleton()->setUseSessions(true);
        $this->logInAs('subsite1member');

        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');

        // Start on the main site (ID 0)
        Subsite::changeSubsite(0);
        $this->session()->set('SubsiteID', 0);

        $response = $this->get('admin/');

        // Should have been redirected (not a login redirect)
        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());

        // InitStateMiddleware writes SubsiteID to session in its finally block
        $this->assertEquals(
            $subsite1->ID,
            (int) $this->session()->get('SubsiteID'),
            'User with access to one subsite should be auto-switched to it'
        );
    }

    public function testAdminNotAutoSwitched()
    {
        SubsiteState::singleton()->setUseSessions(true);
        $this->logInAs('admin');

        // Start on the main site (ID 0)
        Subsite::changeSubsite(0);
        $this->session()->set('SubsiteID', 0);

        $this->get('admin/');

        $this->assertEquals(
            0,
            (int) $this->session()->get('SubsiteID'),
            'Admin user should not be auto-switched away from the main site'
        );
    }
}
