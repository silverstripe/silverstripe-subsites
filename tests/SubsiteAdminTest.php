<?

class SubsiteAdminTest extends SapphireTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';
	
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
			array('Name' => ''),
		);
		
		foreach($searches as $search) {
			$response = $form->testAjaxSubmission('getResults', $search);

			echo $response->getBody();
		}
		
		
		/*
		$this->assertHasLink($response->getBody(), 'admin/intranets/show/' . $this->idFromFixture('Subsite', 'other'));
		$this->assertHasntLink($response->getBody(), 'admin/intranets/show/' . $this->idFromFixture('Subsite', 'other'));
		*/
		$cont->popCurrent();
	}
	
}

?>