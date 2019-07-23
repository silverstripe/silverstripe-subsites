<?php

/**
 * @mixin PHPUnit_Framework_TestCase
 */
class SubsiteXHRControllerTest extends FunctionalTest
{
	protected static $fixture_file = 'SubsiteTest.yml';

	public function testCanView() {
		// Test unauthenticated access
		Session::clear('MemberID');
		$result = $this->get('SubsiteXHRController', null, array(
			'X-Pjax' => 'SubsiteList',
			'X-Requested-With' => 'XMLHttpRequest'
		));
		$this->assertEquals(403, $result->getStatusCode());

		// Login with NO permissions
		$this->logInWithPermission('NOT_CMS_PERMISSION');
		$result = $this->get('SubsiteXHRController', null, array(
			'X-Pjax' => 'SubsiteList',
			'X-Requested-With' => 'XMLHttpRequest'
		));
		$this->assertEquals(403, $result->getStatusCode());

		// Test cms user
		$this->logInWithPermission('CMS_ACCESS_CMSMain');
		$result = $this->get('SubsiteXHRController', null, array(
			'X-Pjax' => 'SubsiteList',
			'X-Requested-With' => 'XMLHttpRequest'
		));
		$this->assertEquals(200, $result->getStatusCode());
		$this->assertEquals('application/json', $result->getHeader('Content-Type'));
		$body = $result->getBody();
		$this->assertContains('Main site', $body);
		$this->assertContains('Test 1', $body);
		$this->assertContains('Test 2', $body);
		$this->assertContains('Test 3', $body);
	}
}
