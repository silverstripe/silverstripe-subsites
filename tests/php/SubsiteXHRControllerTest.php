<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Dev\FunctionalTest;

class SubsiteXHRControllerTest extends FunctionalTest
{
    protected static $fixture_file = 'SubsiteTest.yml';

    public function testCanView()
    {
        // Test unauthenticated access
        $this->logOut();

        $result = $this->get('admin/subsite_xhr', null, [
            'X-Pjax' => 'SubsiteList',
            'X-Requested-With' => 'XMLHttpRequest'
        ]);
        $this->assertEquals(403, $result->getStatusCode());

        // Login with NO permissions
        $this->logInWithPermission('NOT_CMS_PERMISSION');
        $result = $this->get('admin/subsite_xhr', null, [
            'X-Pjax' => 'SubsiteList',
            'X-Requested-With' => 'XMLHttpRequest'
        ]);
        $this->assertEquals(403, $result->getStatusCode());

        // Test cms user
        $this->logInWithPermission('CMS_ACCESS_CMSMain');
        $result = $this->get('admin/subsite_xhr', null, [
            'X-Pjax' => 'SubsiteList',
            'X-Requested-With' => 'XMLHttpRequest'
        ]);

        $this->assertEquals(200, $result->getStatusCode());
        // SilverStripe 4.0-4.2: text/json. >=4.3: application/json
        $this->assertStringContainsString('json', $result->getHeader('Content-Type'));

        $body = $result->getBody();
        $this->assertStringContainsString('Main site', $body);
        $this->assertStringContainsString('Test 1', $body);
        $this->assertStringContainsString('Test 2', $body);
        $this->assertStringContainsString('Test 3', $body);
    }
}
