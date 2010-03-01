<?php

class SubsiteDomain extends DataObject {
	static $db = array(
		"Domain" => "Varchar(255)",
		"IsPrimary" => "Boolean",
	);
	static $has_one = array(
 		"Subsite" => "Subsite",
	);
	
	/**
	 * Whenever a Subsite Domain is written, rewrite the hostmap
	 *
	 * @return void
	 */
	public function onAfterWrite() {
		Subsite::writeHostMap();
	}
}