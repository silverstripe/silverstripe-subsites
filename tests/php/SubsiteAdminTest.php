<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Subsites\Model\Subsite;

class SubsiteAdminTest extends FunctionalTest
{
    protected static $fixture_file = 'SubsiteTest.yml';

    protected function setUp(): void
    {
        parent::setUp();

        Config::modify()->set(Subsite::class, 'write_hostmap', false);
    }

    /**
     * Test generation of the view
     */
    public function testBasicView()
    {
        $subsite1ID = $this->objFromFixture(Subsite::class, 'domaintest1')->ID;

        $this->logInAs('admin');

        // Confirm that this URL gets you the entire page, with the edit form loaded
        $response = $this->get(
            "admin/subsites/SilverStripe-Subsites-Model-Subsite/EditForm/field/"
            ."SilverStripe-Subsites-Model-Subsite/item/$subsite1ID/edit"
        );
        $this->assertTrue(
            strpos($response->getBody() ?? '', 'id="Form_ItemEditForm_ID"') !== false,
            'Testing Form_ItemEditForm_ID exists'
        );
        $this->assertTrue(strpos($response->getBody() ?? '', '<head') !== false, 'Testing <head> exists');
    }

    /**
     * Test that the main-site user with ADMIN permissions can access all subsites, regardless
     * of whether he is in a subsite-specific group or not.
     */
    public function testMainsiteAdminCanAccessAllSubsites()
    {
        $this->logInAs('admin');

        $cmsMain = new CMSMain();
        foreach ($cmsMain->Subsites() as $subsite) {
            $ids[$subsite->ID] = true;
        }

        $this->assertArrayHasKey(0, $ids, 'Main site accessible');
        $this->assertArrayHasKey($this->idFromFixture(Subsite::class, 'main'), $ids, 'Site with no groups inaccesible');
        $this->assertArrayHasKey(
            $this->idFromFixture(Subsite::class, 'subsite1'),
            $ids,
            'Subsite1 Template inaccessible'
        );
        $this->assertArrayHasKey(
            $this->idFromFixture(Subsite::class, 'subsite2'),
            $ids,
            'Subsite2 Template inaccessible'
        );
    }
}
