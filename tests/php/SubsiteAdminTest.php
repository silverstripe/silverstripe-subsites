<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Security\Member;
use SilverStripe\Control\Session;
use SilverStripe\Control\Director;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\Subsites\Model\Subsite;

class SubsiteAdminTest extends BaseSubsiteTest
{
    public static $fixture_file = 'subsites/tests/php/SubsiteTest.yml';

    public function adminLoggedInSession()
    {
        return new Session(array(
            'loggedInAs' => $this->idFromFixture(Member::class, 'admin')
        ));
    }

    /**
     * Test generation of the view
     */
    public function testBasicView()
    {
        Subsite::$write_hostmap = false;
        $subsite1ID = $this->objFromFixture(Subsite::class, 'domaintest1')->ID;

        // Open the admin area logged in as admin
        $response1 = Director::test('admin/subsites/', null, $this->adminLoggedInSession());

        // Confirm that this URL gets you the entire page, with the edit form loaded
        $response2 = Director::test("admin/subsites/SilverStripe-Subsites-Model-Subsite/EditForm/field/SilverStripe-Subsites-Model-Subsite/item/$subsite1ID/edit", null,
            $this->adminLoggedInSession());
        $this->assertTrue(strpos($response2->getBody(), 'id="Form_ItemEditForm_ID"') !== false,
            "Testing Form_ItemEditForm_ID exists");
        $this->assertTrue(strpos($response2->getBody(), '<head') !== false, "Testing <head> exists");
    }


    /**
     * Test that the main-site user with ADMIN permissions can access all subsites, regardless
     * of whether he is in a subsite-specific group or not.
     */
    public function testMainsiteAdminCanAccessAllSubsites()
    {
        $member = $this->objFromFixture(Member::class, 'admin');
        Session::set("loggedInAs", $member->ID);

        $cmsMain = new CMSMain();
        foreach ($cmsMain->Subsites() as $subsite) {
            $ids[$subsite->ID] = true;
        }

		$this->assertArrayHasKey(0, $ids, "Main site accessible");
		$this->assertArrayHasKey($this->idFromFixture(Subsite::class,'main'), $ids, "Site with no groups inaccesible");
		$this->assertArrayHasKey($this->idFromFixture(Subsite::class,'subsite1'), $ids, "Subsite1 Template inaccessible");
		$this->assertArrayHasKey($this->idFromFixture(Subsite::class,'subsite2'), $ids, "Subsite2 Template inaccessible");
	}


}

