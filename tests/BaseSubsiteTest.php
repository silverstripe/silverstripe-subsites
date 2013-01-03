<?php
class BaseSubsiteTest extends SapphireTest {
	/**
	 * Avoid subsites filtering on fixture fetching.
	 */
	function objFromFixture($class, $id) {
		Subsite::disable_subsite_filter(true);
		$obj = parent::objFromFixture($class, $id);
		Subsite::disable_subsite_filter(false);		

		return $obj;
	}
}