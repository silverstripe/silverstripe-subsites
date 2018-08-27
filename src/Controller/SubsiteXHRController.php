<?php

namespace SilverStripe\Subsites\Controller;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Security\Member;
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
     * @param Member|null $member
     * @return bool
     */
    public function canView($member = null)
    {
        if (parent::canView($member)) {
            return true;
        }

        if (Subsite::all_accessible_sites(true, 'Main site', $member)->count() > 0) {
            return true;
        }

        return false;
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
        return $this->renderWith(['type' => 'Includes', self::class . '_subsitelist']);
    }
}
