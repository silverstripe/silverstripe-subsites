<?

class SubsiteAdminTest extends SapphireTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';

        /**
         * Test generation of the view
         */
        function testBasicView() {
            // Open the admin area logged in as admin
            $response1 = Director::test('admin/subsites/');
            
            
            // Confirm that this URL gets you the entire page, with the edit form loaded
            $response2 = Director::test('admin/subsites/show/1');
            $this->assertTrue(strpos($response2->getBody(), 'id="Root_Configuration"') !== false);
            $this->assertTrue(strpos($response2->getBody(), '<head') !== false);

            // Confirm that this URL gets you just the form content, with the edit form loaded
            $response3 = Director::test('admin/subsites/show/1', array('ajax' => 1));

            $this->assertTrue(strpos($response3->getBody(), 'id="Root_Configuration"') !== false);
            $this->assertTrue(strpos($response3->getBody(), '<form') === false);
            $this->assertTrue(strpos($response3->getBody(), '<head') === false);
        }
	
	/**
	 * Test that the template list is properly generated.
	 */
	function testTemplateList() {
		$cont = new SubsiteAdmin();
		$templates = $cont->getIntranetTemplates();
		
		$templateIDs = $this->allFixtureIDs('Subsite_Template');
		$this->assertTrue($templates->onlyContainsIDs($templateIDs));
	}
	
	/**
	 * Test searching for an intranet
	 */
	function testIntranetSearch() {
		$cont = new SubsiteAdmin();
		$cont->pushCurrent();
		
		$member = $this->objFromFixture('Member','admin');
		$member->logIn();
		
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
	
}

?>