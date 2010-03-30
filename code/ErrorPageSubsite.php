<?php
class ErrorPageSubsite extends DataObjectDecorator {
	
	/**
	 * Alter file path to generated a static (static) error page file to handle error page template on different sub-sites 
	 *
	 * @see Error::get_filepath_for_errorcode()
	 *
	 * FIXME since {@link Subsite::currentSubsite()} partly relies on Session, viewing other sub-site (including main site) between 
	 * opening ErrorPage in the CMS and publish ErrorPage causes static error page to get generated incorrectly. 
	 */
	function alternateFilepathForErrorcode($statusCode, $locale = null) {
		$static_filepath = Object::get_static($this->owner->ClassName, 'static_filepath');
		$subdomainPart = "";
		
		// when there's a controller get it subsite from session
		if (Controller::curr()) $subsite = Subsite::currentSubsite(false);
		// since this function is called from Page class before the controller is created, we have to get subsite from domain instead
		else {
			$subsiteID = Subsite::getSubsiteIDForDomain();
			if($subsiteID != 0) $subsite = DataObject::get_by_id("Subsite", $subsiteID);
		}
		
		if($subsite) {	
			$subdomain = $subsite->Domains()->First()->Domain;
			$subdomainPart = "-{$subdomain}";
		}
		
		if(singleton('SiteTree')->hasExtension('Translatable') && $locale && $locale != Translatable::default_locale()) {
			$filepath = $static_filepath . "/error-{$statusCode}-{$locale}{$subdomainPart}.html";
		} else {
			$filepath = $static_filepath . "/error-{$statusCode}{$subdomainPart}.html";
		}

		return $filepath;
	}
	
}