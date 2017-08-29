<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Control\Session;
use SilverStripe\Dev\FunctionalTest;

class SubsiteXHRControllerTest extends FunctionalTest
{
    protected static $fixture_file = 'SubsiteTest.yml';

    public function testCanView()
    {
        // Test unauthenticated access
        $this->logOut();

        $result = $this->get('SubsiteXHRController', null, [
            'X-Pjax' => 'SubsiteList',
            'X-Requested-With' => 'XMLHttpRequest'
        ]);
        $this->assertEquals(403, $result->getStatusCode());

        // Login with NO permissions
        $this->logInWithPermission('NOT_CMS_PERMISSION');
        $result = $this->get('SubsiteXHRController', null, [
            'X-Pjax' => 'SubsiteList',
            'X-Requested-With' => 'XMLHttpRequest'
        ]);
        $this->assertEquals(403, $result->getStatusCode());

        // Test cms user
        $this->logInWithPermission('CMS_ACCESS_CMSMain');
        $result = $this->get('SubsiteXHRController', null, [
            'X-Pjax' => 'SubsiteList',
            'X-Requested-With' => 'XMLHttpRequest'
        ]);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('text/json', $result->getHeader('Content-Type'));
        $body = $result->getBody();
        static::assertContains('Main site', $body);
        static::assertContains('Test 1', $body);
        static::assertContains('Test 2', $body);
        static::assertContains('Test 3', $body);
    }
}
