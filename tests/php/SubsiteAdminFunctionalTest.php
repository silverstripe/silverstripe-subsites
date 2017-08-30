<?php

namespace SilverStripe\Subsites\Tests;

use Page;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

class SubsiteAdminFunctionalTest extends FunctionalTest
{
    protected static $fixture_file = 'SubsiteTest.yml';
    protected static $use_draft_site = true;

    protected $autoFollowRedirection = false;

    /**
     * Helper: FunctionalTest is only able to follow redirection once, we want to go all the way.
     * @param string $url
     * @return \SilverStripe\Control\HTTPResponse
     */
    public function getAndFollowAll($url)
    {
        $response = $this->get($url);
        while ($location = $response->getHeader('Location')) {
            $response = $this->mainSession->followRedirection();
        }
        echo $response->getHeader('Location');

        return $response;
    }

    /**
     * Anonymous user cannot access anything.
     */
    public function testAnonymousIsForbiddenAdminAccess()
    {
        $this->logOut();

        $response = $this->getAndFollowAll('admin/pages/?SubsiteID=0');
        $this->assertContains('Security/login', $this->mainSession->lastUrl(), 'Admin is disallowed');

        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $response = $this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
        $this->assertContains('Security/login', $this->mainSession->lastUrl(), 'Admin is disallowed');

        $response = $this->getAndFollowAll('admin/subsite_xhr');
        $this->assertContains('Security/login', $this->mainSession->lastUrl(), 'SubsiteXHRController is disallowed');
    }

    /**
     * Admin should be able to access all subsites and the main site
     */
    public function testAdminCanAccessAllSubsites()
    {
        $this->logInAs('admin');

        $this->getAndFollowAll('admin/pages/?SubsiteID=0');
        $this->assertEquals(0, $this->session()->get('SubsiteID'), 'Can access main site.');
        $this->assertContains('admin/pages', $this->mainSession->lastUrl(), 'Lands on the correct section');

        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");

        // Check the session manually, since the state is unique to the request, not this test
        $this->assertEquals($subsite1->ID, $this->session()->get('SubsiteID'), 'Can access other subsite.');
        $this->assertContains('admin/pages', $this->mainSession->lastUrl(), 'Lands on the correct section');

        $response = $this->getAndFollowAll('admin/subsite_xhr');
        $this->assertNotContains('Security/login', $this->mainSession->lastUrl(), 'SubsiteXHRController is reachable');
    }

    public function testAdminIsRedirectedToObjectsSubsite()
    {
        $this->logInAs('admin');

        $mainSubsitePage = $this->objFromFixture(Page::class, 'mainSubsitePage');
        $subsite1Home = $this->objFromFixture(Page::class, 'subsite1_home');

        Config::modify()->set(CMSPageEditController::class, 'treats_subsite_0_as_global', false);

        Subsite::changeSubsite(0);

        $this->getAndFollowAll("admin/pages/edit/show/$subsite1Home->ID");
        $this->assertEquals(
            $subsite1Home->SubsiteID,
            $this->session()->get('SubsiteID'),
            'Loading an object switches the subsite'
        );
        $this->assertContains('admin/pages', $this->mainSession->lastUrl(), 'Lands on the correct section');

        Config::modify()->set(CMSPageEditController::class, 'treats_subsite_0_as_global', true);
        Subsite::changeSubsite(0);

        $this->getAndFollowAll("admin/pages/edit/show/$subsite1Home->ID");
        $this->assertEquals(
            $subsite1Home->SubsiteID,
            $this->session()->get('SubsiteID'),
            'Loading a non-main-site object still switches the subsite if configured with treats_subsite_0_as_global'
        );
        $this->assertContains('admin/pages', $this->mainSession->lastUrl(), 'Lands on the correct section');

        $this->getAndFollowAll("admin/pages/edit/show/$mainSubsitePage->ID");
        $this->assertNotEquals(
            $mainSubsitePage->SubsiteID,
            $this->session()->get('SubsiteID'),
            'Loading a main-site object does not change the subsite if configured with treats_subsite_0_as_global'
        );
        $this->assertContains('admin/pages', $this->mainSession->lastUrl(), 'Lands on the correct section');
    }

    /**
     * User which has AccessAllSubsites set to 1 should be able to access all subsites and main site,
     * even though he does not have the ADMIN permission.
     */
    public function testEditorCanAccessAllSubsites()
    {
        $this->logInAs('editor');

        $this->get('admin/pages/?SubsiteID=0');
        $this->assertEquals(0, $this->session()->get('SubsiteID'), 'Can access main site.');
        $this->assertContains('admin/pages', $this->mainSession->lastUrl(), 'Lands on the correct section');

        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $this->get("admin/pages/?SubsiteID={$subsite1->ID}");
        $this->assertEquals($subsite1->ID, $this->session()->get('SubsiteID'), 'Can access other subsite.');
        $this->assertContains('admin/pages', $this->mainSession->lastUrl(), 'Lands on the correct section');

        $response = $this->get('admin/subsite_xhr');
        $this->assertNotContains('Security/login', $this->mainSession->lastUrl(), 'SubsiteXHRController is reachable');
    }

    /**
     * Test a member who only has access to one subsite (subsite1) and only some sections (pages and security).
     */
    public function testSubsiteAdmin()
    {
        $this->logInAs('subsite1member');

        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');

        // Check allowed URL.
        $this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
        $this->assertEquals($subsite1->ID, $this->session()->get('SubsiteID'), 'Can access own subsite.');
        $this->assertContains('admin/pages', $this->mainSession->lastUrl(), 'Can access permitted section.');

        // Check forbidden section in allowed subsite.
        $this->getAndFollowAll("admin/assets/?SubsiteID={$subsite1->ID}");
        $this->assertEquals($subsite1->ID, $this->session()->get('SubsiteID'), 'Is redirected within subsite.');
        $this->assertNotContains(
            'admin/assets',
            $this->mainSession->lastUrl(),
            'Is redirected away from forbidden section'
        );

        // Check forbidden site, on a section that's allowed on another subsite
        $this->getAndFollowAll('admin/pages/?SubsiteID=0');
        $this->assertEquals(
            $this->session()->get('SubsiteID'),
            $subsite1->ID,
            'Is redirected to permitted subsite.'
        );

        // Check forbidden site, on a section that's not allowed on any other subsite
        $this->getAndFollowAll('admin/assets/?SubsiteID=0');
        $this->assertEquals(
            $this->session()->get('SubsiteID'),
            $subsite1->ID,
            'Is redirected to first permitted subsite.'
        );
        $this->assertNotContains('Security/login', $this->mainSession->lastUrl(), 'Is not denied access');

        // Check the standalone XHR controller.
        $response = $this->getAndFollowAll('admin/subsite_xhr');
        $this->assertNotContains('Security/login', $this->mainSession->lastUrl(), 'SubsiteXHRController is reachable');
    }
}
