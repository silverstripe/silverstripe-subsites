<?php

/**
 * Section-agnostic PJAX controller.
 */
class SubsiteXHRController extends LeftAndMain {

	/**
	 * Relax the access permissions, so anyone who has access to any CMS subsite can access this controller.
	 */
	public function canView($member = null) {
		if (parent::canView()) return true;

		if (Subsite::all_accessible_sites()->count()>0) return true;

		return false;
	}

	/**
	 * Similar as above, but for the LeftAndMainSubsites - allow access if user allowed into the CMS at all.
	 */
	public function canAccess() {
		if (Subsite::all_accessible_sites()->count()>0) return true;
	}

	public function getResponseNegotiator() {
		$negotiator = parent::getResponseNegotiator();
		$self = $this;

		// Register a new callback
		$negotiator->setCallback('SubsiteList', function() use(&$self) {
			return $self->SubsiteList();
		});

		return $negotiator;
	}

	/**
	 * Provide the list of available subsites as a cms-section-agnostic PJAX handler.
	 */
	public function SubsiteList() {
		return $this->renderWith('SubsiteList');
	}

}
