<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\Subsites\Extensions\LeftAndMainSubsites;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

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

    /**
     * @dataProvider sectionSitesProvider
     *
     * @param string $identifier
     * @param string $className
     * @param array $expected
     * @param string $message
     * @param string $assertion
     */
    public function testSectionSites($identifier, $className, $expected, $message, $assertion = 'assertListEquals')
    {
        $member = $this->objFromFixture(Member::class, $identifier);

        /** @var CMSMain|LeftAndMainSubsites $cmsmain */
        $cmsMain = Injector::inst()->create($className);
        $subsites = $cmsMain->sectionSites(true, 'Main site', $member);
        $this->$assertion($expected, $subsites, $message);
    }

    /**
     * @return array[]
     */
    public function sectionSitesProvider()
    {
        return [
            [
                'subsite1member',
                CMSMain::class,
                [['Title' => 'Subsite1 Template']],
                'Lists member-accessible sites for the accessible controller.',
            ],
            [
                'subsite1member',
                AssetAdmin::class,
                [[]],
                'Does not list any sites for forbidden controller.',
            ],
            [
                'editor',
                CMSMain::class,
                [['Title' => 'Main site']],
                'Includes the main site for members who can access all sites.',
                'assertListContains',
            ],
        ];
    }

    /**
     * @dataProvider accessChecksProvider
     *
     * @param string $identifier
     */
    public function testAccessChecksDontChangeCurrentSubsite($identifier)
    {
        $this->logInAs('admin');

        /** @var Subsite $subsite */
        $subsite = $this->objFromFixture(Subsite::class, $identifier);
        $id = $subsite->ID;

        // Enable session-based subsite tracking.
        SubsiteState::singleton()->setUseSessions(true);

        Subsite::changeSubsite($id);
        $this->assertEquals($id, SubsiteState::singleton()->getSubsiteId(), 'Subsite ID is in the state');

        $left = new LeftAndMain();
        $this->assertTrue($left->canView(), "Admin user can view subsites LeftAndMain with id = '$id'");
        $this->assertEquals(
            $id,
            SubsiteState::singleton()->getSubsiteId(),
            'The current subsite has not been changed in the process of checking permissions for admin user.'
        );
    }

    /**
     * @return array[]
     */
    public function accessChecksProvider()
    {
        return [
            ['domaintest1'],
            ['domaintest3'],
            ['domaintest3'],
        ];
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
}
