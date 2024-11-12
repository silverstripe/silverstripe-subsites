<?php

namespace SilverStripe\Subsites\Controller;

use SilverStripe\Admin\AdminController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\PjaxResponseNegotiator;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Member;
use SilverStripe\Subsites\Model\Subsite;

/**
 * Section-agnostic PJAX controller that renders the subsites swapper dropdown
 */
class SubsiteXHRController extends AdminController
{
    private static $url_segment = 'subsite_xhr';

    private static string $required_permission_codes = 'CMS_ACCESS';

    public function index(HTTPRequest $request): HTTPResponse
    {
        return $this->getResponseNegotiator()->respond($request);
    }

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

        if (Subsite::all_accessible_sites()->count() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Get a Pjax response negotiator for the subsite list
     */
    public function getResponseNegotiator(): PjaxResponseNegotiator
    {
        return new PjaxResponseNegotiator([
            'SubsiteList' => function () {
                return $this->SubsiteList();
            },
        ]);
    }

    /**
     * Provide the list of available subsites as a cms-section-agnostic PJAX handler.
     */
    public function SubsiteList(): DBHTMLText
    {
        return $this->renderWith(['type' => 'Includes', SubsiteXHRController::class . '_subsitelist']);
    }
}
