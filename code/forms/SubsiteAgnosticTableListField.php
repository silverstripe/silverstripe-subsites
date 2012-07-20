<?php

class SubsiteAgnosticTableListField extends TableListField {
	function getQuery() {
		$oldState = Subsite::$disable_subsite_filter;
		Subsite::$disable_subsite_filter = true;
		$return = parent::getQuery();
		Subsite::$disable_subsite_filter = $oldState;
		return $return;
	}
}
