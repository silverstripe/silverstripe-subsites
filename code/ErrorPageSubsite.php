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

		if($subsite = Subsite::currentSubsite(false)) {	
			$subdomain = $subsite->Subdomain;
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