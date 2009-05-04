<?php
/**
 * Extension for the Group object to add subsites support
 *
 * @package subsites
 */
class MemberSubsites extends DataObjectDecorator {

	/* Only allow adding to groups we can edit */
	public function saveGroups( $groups ) {
		$groups = explode( ',', $groups ) ;
		$filtered = array() ;
		foreach( $groups as $groupID ) {
			$group = DataObject::get_by_id('Group', $groupID) ;
			if ( $group && $group->canEdit() ) $filtered[] = $groupID ;
		}
		$this->owner->Groups()->setByIDList( $filtered ) ;
	}

}