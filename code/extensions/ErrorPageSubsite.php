<?php
class ErrorPageSubsite extends DataExtension {
	
	/**
	 * Alter file path to generated a static (static) error page file to handle error page template on different sub-sites 
	 *
	 * {@see Error::get_error_filename()}
	 *
	 * FIXME since {@link Subsite::currentSubsite()} partly relies on Session, viewing other sub-site (including main site) between 
	 * opening ErrorPage in the CMS and publish ErrorPage causes static error page to get generated incorrectly.
	 *
	 * @param string $name Filename to write to
	 * @param int $statusCode Integer error code
	 */
	public function updateErrorFilename(&$name, $statusCode) {
		
		// Try to get current subsite from session
		$subsite = Subsite::currentSubsite(false);
		
		// since this function is called from Page class before the controller is created, we have to get subsite from domain instead
		if(!$subsite) {
			$subsiteID = Subsite::getSubsiteIDForDomain();
			if($subsiteID != 0) {
				$subsite = DataObject::get_by_id("Subsite", $subsiteID);
			}
		}

		// Without subsite, don't rewrite
		if($subsite) {
			// Add subdomain to end of filename, just before .html
			// This should preserve translatable locale in the filename as well
			$subdomain = $subsite->domain();
			$name = substr($name, 0, -5) . "-{$subdomain}.html";
		}
	}
	
}