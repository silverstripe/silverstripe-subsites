<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Control\Session;
use SilverStripe\Dev\FunctionalTest;

/**
 * Created by PhpStorm.
 * User: dmooyman
 * Date: 27/05/16
 * Time: 3:10 PM
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
		$this->assertEquals('text/json', $result->getHeader('Content-Type'));
		$body = $result->getBody();
		static::assertContains('Main site', $body);
		static::assertContains('Test 1', $body);
		static::assertContains('Test 2', $body);
		static::assertContains('Test 3', $body);
	}
}
