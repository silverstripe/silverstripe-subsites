<?php

class SubsiteAdminTest extends BaseSubsiteTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';

    function adminLoggedInSession() {
        return new Session(array(
            'loggedInAs' => $this->idFromFixture('Member', 'admin')
        ));
    }

    /**
     * Test generation of the view
     */
    function testBasicView() {
		Subsite::$write_hostmap = false;
		$subsite1ID = $this->objFromFixture('Subsite','domaintest1')->ID;

        // Open the admin area logged in as admin
        $response1 = Director::test('admin/subsites/', null, $this->adminLoggedInSession());
        
        // Confirm that this URL gets you the entire page, with the edit form loaded
		$response2 = Director::test("admin/subsites/Subsite/EditForm/field/Subsite/item/$subsite1ID/edit", null, $this->adminLoggedInSession());
		$this->assertTrue(strpos($response2->getBody(), 'id="Form_ItemEditForm_ID"') !== false, "Testing Form_ItemEditForm_ID exists");
        $this->assertTrue(strpos($response2->getBody(), '<head') !== false, "Testing <head> exists");
    }
	
	/**
	 * Test searching for an intranet
	 */
	function XXtestIntranetSearch() {
		$cont = new SubsiteAdmin();
		$cont->pushCurrent();
        $cont->setSession($this->adminLoggedInSession());
		
		// Check that the logged-in member has the correct permissions
		$this->assertTrue(Permission::check('ADMIN') ? true : false);

		$form = $cont->SearchForm();
		
		$searches = array(
			array('Name' => 'Other'),
		);
		
		foreach($searches as $search) {
			$response = $form->testAjaxSubmission('getResults', $search);
            $links = $response->getLinks();
            foreach($links as $link) {
                $this->assertTrue(preg_match('/^admin\/subsites\/show\/[0-9]+$/', $link['href']) == 1, "Search result links bad.");
            }
		}
		
		$cont->popCurrent();
	}
    
    /**
     * Test the intranet creation form.
     */
    function XXtestIntranetCreation() {
  		$cont = new SubsiteAdmin();
        $cont->pushCurrent();
        $cont->setSession($this->adminLoggedInSession());
        
        $form = $cont->AddSubsiteForm();
        $source = $form->dataFieldByName('TemplateID')->getSource();
        
        $templateIDs = $this->allFixtureIDs('Subsite');
        foreach($templateIDs as $templateID) {
            $this->assertArrayHasKey($templateID, $source);
        }
        
        $templateObj = $this->objFromFixture('Subsite','main');
        $this->assertEquals($templateObj->Title, $source[$templateObj->ID], "Template dropdown isn't listing Title values");

        $response = $form->testSubmission('addintranet', array(
            'Name' => 'Test Intranet',
            'Domain' => 'test.example.com',
            'TemplateID' => 1,
            'AdminEmail' => '',
            'AdminName' => '',
        ));

        $this->assertTrue(true == preg_match('/admin\/subsites\/show\/([0-9]+)/i', $response->getHeader('Location'), $matches), "Intranet creation dowsn't redirect to new view");
        
        $newIntranet = DataObject::get_by_id("Subsite", $matches[1]);
        $this->assertEquals('Test Intranet', $newIntranet->Title, "New intranet not created properly.");
        
        $cont->popCurrent();
  }

	
	/**
	 * Test that the main-site user with ADMIN permissions can access all subsites, regardless
	 * of whether he is in a subsite-specific group or not.
	 */
	function testMainsiteAdminCanAccessAllSubsites() {
		$member = $this->objFromFixture('Member', 'admin');
		Session::set("loggedInAs", $member->ID);
		
		$cmsMain = new CMSMain();
		foreach($cmsMain->Subsites() as $subsite) {
			$ids[$subsite->ID] = true;
		}	

		$this->assertArrayHasKey(0, $ids, "Main site accessible");
		$this->assertArrayHasKey($this->idFromFixture('Subsite','main'), $ids, "Site with no groups inaccesible");
		$this->assertArrayHasKey($this->idFromFixture('Subsite','subsite1'), $ids, "Subsite1 Template inaccessible");
		$this->assertArrayHasKey($this->idFromFixture('Subsite','subsite2'), $ids, "Subsite2 Template inaccessible");
	}

	
}

