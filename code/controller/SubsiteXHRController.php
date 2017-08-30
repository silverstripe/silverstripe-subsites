<?php

namespace SilverStripe\Subsites\Controller;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Security\Permission;
use SilverStripe\Subsites\Model\Subsite;

/**
 * Section-agnostic PJAX controller.
 */
class SubsiteXHRController extends LeftAndMain
{
    private static $url_segment = 'subsite_xhr';

    private static $ignore_menuitem = true;

    /**
     * Relax the access permissions, so anyone who has access to any CMS subsite can access this controller.
     * @param null $member
     * @return bool
     */
    public function canView($member = null)
    {
        if (parent::canView($member)) {
            return true;
        }

        if (Subsite::all_accessible_sites()->count() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Allow access if user allowed into the CMS at all.
     */
    public function canAccess()
    {
        // Allow if any cms access is available
        return Permission::check([
            'CMS_ACCESS', // Supported by 3.1.14 and up
            'CMS_ACCESS_LeftAndMain'
        ]);
    }

    public function getResponseNegotiator()
    {
        $negotiator = parent::getResponseNegotiator();

        // Register a new callback
        $negotiator->setCallback('SubsiteList', function () {
            return $this->SubsiteList();
        });

        return $negotiator;
    }

    /**
     * Provide the list of available subsites as a cms-section-agnostic PJAX handler.
     */
    public function SubsiteList()
    {
        return $this->renderWith('Includes/SubsiteList');
    }
}
