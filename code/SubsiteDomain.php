<?php

class SubsiteDomain extends DataObject {
	static $db = array(
		"Domain" => "Varchar(255)",
		"IsPrimary" => "Boolean",
	);
	static $has_one = array(
 		"Subsite" => "Subsite",
	);
}