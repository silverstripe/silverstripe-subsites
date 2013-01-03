<?php 
class LeftAndMainSubsitesTest extends FunctionalTest {
	
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';

	/**
	 * Avoid subsites filtering on fixture fetching.
	 */
	function objFromFixture($class, $id) {
		Subsite::disable_subsite_filter(true);
		$obj = parent::objFromFixture($class, $id);
		Subsite::disable_subsite_filter(false);		

		return $obj;
	}
	
	function testAlternateAccessCheck() {
		$admin = $this->objFromFixture("Member","admin");
		$this->loginAs($admin);
		$ids = array();
		
		$subsite1 = $this->objFromFixture('Subsite', 'domaintest1');
		$subsite2 = $this->objFromFixture('Subsite', 'domaintest2');
		$subsite3 = $this->objFromFixture('Subsite', 'domaintest3');
		
		$ids[] = $subsite1->ID;
		$ids[] = $subsite2->ID;
		$ids[] = $subsite3->ID;
		$ids[] = 0;
		
		foreach($ids as $id) {
			Subsite::changeSubsite($id);	//switch to main site (subsite ID zero)
			$left = new LeftAndMain();
			$this->assertTrue($left->canView(), "Admin user can view subsites LeftAndMain with id = '$id'");
			$this->assertEquals($id, Subsite::currentSubsiteID(), "The current subsite has not been changed in the process of checking permissions for admin user.");
		}
		
	}

}
