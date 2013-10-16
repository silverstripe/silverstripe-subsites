<?php

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

		$subsite1 = $this->objFromFixture('Subsite', 'subsite1');
		$response = $this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
		$this->assertRegExp('#^Security/login.*#', $this->mainSession->lastUrl(), 'Admin is disallowed');

		$response = $this->getAndFollowAll('SubsiteXHRController');
		$this->assertRegExp('#^Security/login.*#', $this->mainSession->lastUrl(),
			'SubsiteXHRController is disallowed');
	}

	/**
	 * Admin should be able to access all subsites and the main site
	 */
	function testAdminCanAccessAllSubsites() {
		$member = $this->objFromFixture('Member', 'admin');
		Session::set("loggedInAs", $member->ID);
		
		$this->getAndFollowAll('admin/pages/?SubsiteID=0');
		$this->assertEquals(Subsite::currentSubsiteID(), '0', 'Can access main site.');
		$this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Lands on the correct section');

		$subsite1 = $this->objFromFixture('Subsite', 'subsite1');
		$this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Can access other subsite.');
		$this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Lands on the correct section');

		$response = $this->getAndFollowAll('SubsiteXHRController');
		$this->assertNotRegExp('#^Security/login.*#', $this->mainSession->lastUrl(),
			'SubsiteXHRController is reachable');
	}

	/**
	 * User which has AccessAllSubsites set to 1 should be able to access all subsites and main site,
	 * even though he does not have the ADMIN permission.
	 */
	function testEditorCanAccessAllSubsites() {
		$member = $this->objFromFixture('Member', 'editor');
		Session::set("loggedInAs", $member->ID);

		$this->getAndFollowAll('admin/pages/?SubsiteID=0');
		$this->assertEquals(Subsite::currentSubsiteID(), '0', 'Can access main site.');
		$this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Lands on the correct section');

		$subsite1 = $this->objFromFixture('Subsite', 'subsite1');
		$this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Can access other subsite.');
		$this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Lands on the correct section');

		$response = $this->getAndFollowAll('SubsiteXHRController');
		$this->assertNotRegExp('#^Security/login.*#', $this->mainSession->lastUrl(),
			'SubsiteXHRController is reachable');
	}

	/**
	 * Test a member who only has access to one subsite (subsite1) and only some sections (pages and security).
	 */
	function testSubsiteAdmin() {
		$member = $this->objFromFixture('Member', 'subsite1member');
		Session::set("loggedInAs", $member->ID);

		$subsite1 = $this->objFromFixture('Subsite', 'subsite1');

		// Check allowed URL.
		$this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Can access own subsite.');
		$this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Can access permitted section.');

		// Check forbidden section in allowed subsite.
		$this->getAndFollowAll("admin/assets/?SubsiteID={$subsite1->ID}");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Is redirected within subsite.');
		$this->assertNotRegExp('#^admin/assets/.*#', $this->mainSession->lastUrl(),
			'Is redirected away from forbidden section');

		// Check forbidden site.
		$this->getAndFollowAll("admin/pages/?SubsiteID=0");
		$this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Is redirected to permitted subsite.');

		// Check the standalone XHR controller.
		$response = $this->getAndFollowAll('SubsiteXHRController');
		$this->assertNotRegExp('#^Security/login.*#', $this->mainSession->lastUrl(),
			'SubsiteXHRController is reachable');
	}
}
