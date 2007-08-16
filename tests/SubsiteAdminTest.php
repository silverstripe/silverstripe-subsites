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
	
}

?>