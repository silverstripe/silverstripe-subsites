<?php

use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\Controller\SubsiteXHRController;


class SubsiteAdminFunctionalTest extends FunctionalTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';
	static $use_draft_site = true;

	protected $autoFollowRedirection = false;

	/**
	 * Helper: FunctionalTest is only able to follow redirection once, we want to go all the way.
	 */
	function getAndFollowAll($url) {
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
	function testAnonymousIsForbiddenAdminAccess() {
		$response = $this->getAndFollowAll('admin/pages/?SubsiteID=0');
		$this->assertRegExp('#^Security/login.*#', $this->mainSession->lastUrl(), 'Admin is disallowed');

		$subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
		$response = $this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
		$this->assertRegExp('#^Security/login.*#', $this->mainSession->lastUrl(), 'Admin is disallowed');

		$response = $this->getAndFollowAll(SubsiteXHRController::class);
		$this->assertRegExp('#^Security/login.*#', $this->mainSession->lastUrl(),
			'SubsiteXHRController is disallowed');
	}

	/**
	 * Admin should be able to access all subsites and the main site
	 */
	function testAdminCanAccessAllSubsites() {
		$member = $this->objFromFixture('SilverStripe\\Security\\Member', 'admin');
		Session::set("loggedInAs", $member->ID);

		$this->getAndFollowAll('admin/pages/?SubsiteID=0');
		$this->assertEquals(Subsite::currentSubsiteID(), '0', 'Can access main site.');
		$this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Lands on the correct section');

		$subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
		$this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Can access other subsite.');
		$this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Lands on the correct section');

		$response = $this->getAndFollowAll(SubsiteXHRController::class);
		$this->assertNotRegExp('#^Security/login.*#', $this->mainSession->lastUrl(),
			'SubsiteXHRController is reachable');
	}

	function testAdminIsRedirectedToObjectsSubsite() {
		$member = $this->objFromFixture('SilverStripe\\Security\\Member', 'admin');
		Session::set("loggedInAs", $member->ID);

		$mainSubsitePage = $this->objFromFixture('Page', 'mainSubsitePage');
		$subsite1Home = $this->objFromFixture('Page', 'subsite1_home');

		Config::inst()->nest();

		Config::modify()->set('SilverStripe\\CMS\\Controllers\\CMSPageEditController', 'treats_subsite_0_as_global', false);
		Subsite::changeSubsite(0);
		$this->getAndFollowAll("admin/pages/edit/show/$subsite1Home->ID");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1Home->SubsiteID, 'Loading an object switches the subsite');
		$this->assertRegExp("#^admin/pages.*#", $this->mainSession->lastUrl(), 'Lands on the correct section');

		Config::modify()->set('SilverStripe\\CMS\\Controllers\\CMSPageEditController', 'treats_subsite_0_as_global', true);
		Subsite::changeSubsite(0);
		$this->getAndFollowAll("admin/pages/edit/show/$subsite1Home->ID");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1Home->SubsiteID, 'Loading a non-main-site object still switches the subsite if configured with treats_subsite_0_as_global');
		$this->assertRegExp("#^admin/pages.*#", $this->mainSession->lastUrl(), 'Lands on the correct section');

		$this->getAndFollowAll("admin/pages/edit/show/$mainSubsitePage->ID");
		$this->assertNotEquals(Subsite::currentSubsiteID(), $mainSubsitePage->SubsiteID, 'Loading a main-site object does not change the subsite if configured with treats_subsite_0_as_global');
		$this->assertRegExp("#^admin/pages.*#", $this->mainSession->lastUrl(), 'Lands on the correct section');

		Config::inst()->unnest();
	}

	/**
	 * User which has AccessAllSubsites set to 1 should be able to access all subsites and main site,
	 * even though he does not have the ADMIN permission.
	 */
	function testEditorCanAccessAllSubsites() {
		$member = $this->objFromFixture('SilverStripe\\Security\\Member', 'editor');
		Session::set("loggedInAs", $member->ID);

		$this->getAndFollowAll('admin/pages/?SubsiteID=0');
		$this->assertEquals(Subsite::currentSubsiteID(), '0', 'Can access main site.');
		$this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Lands on the correct section');

		$subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
		$this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Can access other subsite.');
		$this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Lands on the correct section');

		$response = $this->getAndFollowAll(SubsiteXHRController::class);
		$this->assertNotRegExp('#^Security/login.*#', $this->mainSession->lastUrl(),
			'SubsiteXHRController is reachable');
	}

	/**
	 * Test a member who only has access to one subsite (subsite1) and only some sections (pages and security).
	 */
	function testSubsiteAdmin() {
		$member = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite1member');
		Session::set("loggedInAs", $member->ID);

		$subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');

		// Check allowed URL.
		$this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Can access own subsite.');
		$this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Can access permitted section.');

		// Check forbidden section in allowed subsite.
		$this->getAndFollowAll("admin/assets/?SubsiteID={$subsite1->ID}");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Is redirected within subsite.');
		$this->assertNotRegExp('#^admin/assets/.*#', $this->mainSession->lastUrl(),
			'Is redirected away from forbidden section');

		// Check forbidden site, on a section that's allowed on another subsite
		$this->getAndFollowAll("admin/pages/?SubsiteID=0");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Is redirected to permitted subsite.');

		// Check forbidden site, on a section that's not allowed on any other subsite
		$this->getAndFollowAll("admin/assets/?SubsiteID=0");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Is redirected to first permitted subsite.');
		$this->assertNotRegExp('#^Security/login.*#', $this->mainSession->lastUrl(), 'Is not denied access');

		// Check the standalone XHR controller.
		$response = $this->getAndFollowAll(SubsiteXHRController::class);
		$this->assertNotRegExp('#^Security/login.*#', $this->mainSession->lastUrl(),
			'SubsiteXHRController is reachable');
	}
}
