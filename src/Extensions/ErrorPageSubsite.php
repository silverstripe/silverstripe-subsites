<?php

namespace SilverStripe\Subsites\Extensions;

use SilverStripe\Assets\FileNameFilter;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Subsites\Model\Subsite;

/**
 * @extends DataExtension<ErrorPage>
 */
class ErrorPageSubsite extends DataExtension
{
    /**
     * Alter file path to generated a static (static) error page file to handle error page template
     * on different sub-sites
     *
     * @see ErrorPage::get_error_filename()
     *
     * FIXME since {@link Subsite::currentSubsite()} partly relies on Session, viewing other sub-site (including
     * main site) between opening ErrorPage in the CMS and publish ErrorPage causes static error page to get
     * generated incorrectly.
     *
     * @param string $name
     * @param int $statusCode
     */
    public function updateErrorFilename(&$name, &$statusCode)
    {
        $static_filepath = Config::inst()->get($this->owner->ClassName, 'static_filepath');
        $subdomainPart = '';

        // Try to get current subsite from session
        $subsite = Subsite::currentSubsite();

        // since this function is called from Page class before the controller is created, we have
        // to get subsite from domain instead
        if (!$subsite) {
            $subsiteID = Subsite::getSubsiteIDForDomain();
            if ($subsiteID != 0) {
                $subsite = DataObject::get_by_id(Subsite::class, $subsiteID);
            } else {
                $subsite = null;
            }
        }

        if ($subsite) {
            $subdomain = $subsite->domain();
            $subdomainPart = "-{$subdomain}";
        }

        $fileName = FileNameFilter::create()->filter("error-{$statusCode}{$subdomainPart}.html");
        $name = implode('/', [$static_filepath, $fileName]);
    }
}
