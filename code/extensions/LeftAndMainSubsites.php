<?php

namespace SilverStripe\Subsites\Extensions;

use SilverStripe\Admin\CMSMenu;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Subsites\Controller\SubsiteXHRController;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;

/**
 * Decorator designed to add subsites support to LeftAndMain
 *
 * @package subsites
 */
class LeftAndMainSubsites extends Extension
{
    private static $allowed_actions = ['CopyToSubsite'];

    /**
     * Normally SubsiteID=0 on a DataObject means it is only accessible from the special "main site".
     * However in some situations SubsiteID=0 will be understood as a "globally accessible" object in which
     * case this property is set to true (i.e. in AssetAdmin).
     */
    private static $treats_subsite_0_as_global = false;

    public function init()
    {
        Requirements::css('subsites/css/LeftAndMain_Subsites.css');
        Requirements::javascript('subsites/javascript/LeftAndMain_Subsites.js');
        Requirements::javascript('subsites/javascript/VirtualPage_Subsites.js');
    }

    /**
     * Set the title of the CMS tree
     */
    public function getCMSTreeTitle()
    {
        $subsite = Subsite::currentSubsite();
        return $subsite ? Convert::raw2xml($subsite->Title) : _t('LeftAndMain.SITECONTENTLEFT');
    }

    public function updatePageOptions(&$fields)
    {
        $fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
    }

    /**
     * Find all subsites accessible for current user on this controller.
     *
     * @param bool $includeMainSite
     * @param string $mainSiteTitle
     * @param null $member
     * @return ArrayList of <a href='psi_element://Subsite'>Subsite</a> instances.
     * instances.
     */
    public function sectionSites($includeMainSite = true, $mainSiteTitle = 'Main site', $member = null)
    {
        if ($mainSiteTitle == 'Main site') {
            $mainSiteTitle = _t('Subsites.MainSiteTitle', 'Main site');
        }

        // Rationalise member arguments
        if (!$member) {
            $member = Member::currentUser();
        }
        if (!$member) {
            return new ArrayList();
        }
        if (!is_object($member)) {
            $member = DataObject::get_by_id(Member::class, $member);
        }

        // Collect permissions - honour the LeftAndMain::required_permission_codes, current model requires
        // us to check if the user satisfies ALL permissions. Code partly copied from LeftAndMain::canView.
        $codes = [];
        $extraCodes = Config::inst()->get($this->owner->class, 'required_permission_codes');
        if ($extraCodes !== false) {
            if ($extraCodes) {
                $codes = array_merge($codes, (array)$extraCodes);
            } else {
                $codes[] = "CMS_ACCESS_{$this->owner->class}";
            }
        } else {
            // Check overriden - all subsites accessible.
            return Subsite::all_sites();
        }

        // Find subsites satisfying all permissions for the Member.
        $codesPerSite = [];
        $sitesArray = [];
        foreach ($codes as $code) {
            $sites = Subsite::accessible_sites($code, $includeMainSite, $mainSiteTitle, $member);
            foreach ($sites as $site) {
                // Build the structure for checking how many codes match.
                $codesPerSite[$site->ID][$code] = true;

                // Retain Subsite objects for later.
                $sitesArray[$site->ID] = $site;
            }
        }

        // Find sites that satisfy all codes conjuncitvely.
        $accessibleSites = new ArrayList();
        foreach ($codesPerSite as $siteID => $siteCodes) {
            if (count($siteCodes) == count($codes)) {
                $accessibleSites->push($sitesArray[$siteID]);
            }
        }

        return $accessibleSites;
    }

    /*
     * Returns a list of the subsites accessible to the current user.
     * It's enough for any section to be accessible for the section to be included.
     */
    public function Subsites()
    {
        return Subsite::all_accessible_sites();
    }

    /*
     * Generates a list of subsites with the data needed to
     * produce a dropdown site switcher
     * @return ArrayList
     */

    public function ListSubsites()
    {
        $list = $this->Subsites();
        $currentSubsiteID = Subsite::currentSubsiteID();

        if ($list == null || $list->count() == 1 && $list->first()->DefaultSite == true) {
            return false;
        }

        Requirements::javascript('subsites/javascript/LeftAndMain_Subsites.js');

        $output = new ArrayList();

        foreach ($list as $subsite) {
            $CurrentState = $subsite->ID == $currentSubsiteID ? 'selected' : '';

            $output->push(new ArrayData([
                'CurrentState' => $CurrentState,
                'ID' => $subsite->ID,
                'Title' => Convert::raw2xml($subsite->Title)
            ]));
        }

        return $output;
    }

    public function alternateMenuDisplayCheck($controllerName)
    {
        if (!class_exists($controllerName)) {
            return false;
        }

        // Don't display SubsiteXHRController
        if ($controllerName == SubsiteXHRController::class) {
            return false;
        }

        // Check subsite support.
        if (Subsite::currentSubsiteID() == 0) {
            // Main site always supports everything.
            return true;
        }

        // It's not necessary to check access permissions here. Framework calls canView on the controller,
        // which in turn uses the Permission API which is augmented by our GroupSubsites.
        $controller = singleton($controllerName);
        return $controller->hasMethod('subsiteCMSShowInMenu') && $controller->subsiteCMSShowInMenu();
    }

    public function CanAddSubsites()
    {
        return Permission::check('ADMIN', 'any', null, 'all');
    }

    /**
     * Helper for testing if the subsite should be adjusted.
     * @param $adminClass
     * @param $recordSubsiteID
     * @param $currentSubsiteID
     * @return bool
     */
    public function shouldChangeSubsite($adminClass, $recordSubsiteID, $currentSubsiteID)
    {
        if (Config::inst()->get($adminClass, 'treats_subsite_0_as_global') && $recordSubsiteID == 0) {
            return false;
        }
        if ($recordSubsiteID != $currentSubsiteID) {
            return true;
        }
        return false;
    }

    /**
     * Check if the current controller is accessible for this user on this subsite.
     */
    public function canAccess()
    {
        // Admin can access everything, no point in checking.
        $member = Member::currentUser();
        if ($member &&
        (
            Permission::checkMember($member, 'ADMIN') || // 'Full administrative rights' in SecurityAdmin
            Permission::checkMember($member, 'CMS_ACCESS_LeftAndMain') // 'Access to all CMS sections' in SecurityAdmin
        )) {
            return true;
        }

        // Check if we have access to current section on the current subsite.
        $accessibleSites = $this->owner->sectionSites(true, 'Main site', $member);
        return $accessibleSites->count() && $accessibleSites->find('ID', Subsite::currentSubsiteID());
    }

    /**
     * Prevent accessing disallowed resources. This happens after onBeforeInit has executed,
     * so all redirections should've already taken place.
     */
    public function alternateAccessCheck()
    {
        return $this->owner->canAccess();
    }

    /**
     * Redirect the user to something accessible if the current section/subsite is forbidden.
     *
     * This is done via onBeforeInit as it needs to be done before the LeftAndMain::init has a
     * chance to forbids access via alternateAccessCheck.
     *
     * If we need to change the subsite we force the redirection to /admin/ so the frontend is
     * fully re-synchronised with the internal session. This is better than risking some panels
     * showing data from another subsite.
     */
    public function onBeforeInit()
    {
        // We are accessing the CMS, so we need to let Subsites know we will be using the session.
        Subsite::$use_session_subsiteid = true;

        $session = Controller::curr()->getRequest()->getSession();

        // FIRST, check if we need to change subsites due to the URL.

        // Catch forced subsite changes that need to cause CMS reloads.
        if (isset($_GET['SubsiteID'])) {
            // Clear current page when subsite changes (or is set for the first time)
            if (!$session->get('SubsiteID') || $_GET['SubsiteID'] != $session->get('SubsiteID')) {
                $session->clear("{$this->owner->class}.currentPage");
            }

            // Update current subsite in session
            Subsite::changeSubsite($_GET['SubsiteID']);

            //Redirect to clear the current page
            if ($this->owner->canView(Member::currentUser())) {
                //Redirect to clear the current page
                return $this->owner->redirect($this->owner->Link());
            }
            //Redirect to the default CMS section
            return $this->owner->redirect('admin/');
        }

        // Automatically redirect the session to appropriate subsite when requesting a record.
        // This is needed to properly initialise the session in situations where someone opens the CMS via a link.
        $record = $this->owner->currentPage();
        if ($record
            && isset($record->SubsiteID, $this->owner->urlParams['ID'])
            && is_numeric($record->SubsiteID)
            && $this->shouldChangeSubsite($this->owner->class, $record->SubsiteID, Subsite::currentSubsiteID())
        ) {
            // Update current subsite in session
            Subsite::changeSubsite($record->SubsiteID);

            if ($this->owner->canView(Member::currentUser())) {
                //Redirect to clear the current page
                return $this->owner->redirect($this->owner->Link());
            }
            //Redirect to the default CMS section
            return $this->owner->redirect('admin/');
        }

        // SECOND, check if we need to change subsites due to lack of permissions.

        if (!$this->owner->canAccess()) {
            $member = Member::currentUser();

            // Current section is not accessible, try at least to stick to the same subsite.
            $menu = CMSMenu::get_menu_items();
            foreach ($menu as $candidate) {
                if ($candidate->controller && $candidate->controller != $this->owner->class) {
                    $accessibleSites = singleton($candidate->controller)->sectionSites(true, 'Main site', $member);
                    if ($accessibleSites->count() && $accessibleSites->find('ID', Subsite::currentSubsiteID())) {
                        // Section is accessible, redirect there.
                        return $this->owner->redirect(singleton($candidate->controller)->Link());
                    }
                }
            }

            // If no section is available, look for other accessible subsites.
            foreach ($menu as $candidate) {
                if ($candidate->controller) {
                    $accessibleSites = singleton($candidate->controller)->sectionSites(true, 'Main site', $member);
                    if ($accessibleSites->count()) {
                        Subsite::changeSubsite($accessibleSites->First()->ID);
                        return $this->owner->redirect(singleton($candidate->controller)->Link());
                    }
                }
            }

            // We have not found any accessible section or subsite. User should be denied access.
            return Security::permissionFailure($this->owner);
        }

        // Current site is accessible. Allow through.
        return;
    }

    public function augmentNewSiteTreeItem(&$item)
    {
        $item->SubsiteID = isset($_POST['SubsiteID']) ? $_POST['SubsiteID'] : Subsite::currentSubsiteID();
    }

    public function onAfterSave($record)
    {
        if ($record->hasMethod('NormalRelated') && ($record->NormalRelated() || $record->ReverseRelated())) {
            $this->owner->response->addHeader(
                'X-Status',
                rawurlencode(_t('LeftAndMainSubsites.Saved', 'Saved, please update related pages.'))
            );
        }
    }

    /**
     * @param array $data
     * @param Form $form
     */
    public function copytosubsite($data, $form)
    {
        $page = DataObject::get_by_id('SiteTree', $data['ID']);
        $subsite = DataObject::get_by_id('Subsite', $data['CopyToSubsiteID']);
        $includeChildren = (isset($data['CopyToSubsiteWithChildren'])) ? $data['CopyToSubsiteWithChildren'] : false;

        $newPage = $page->duplicateToSubsite($subsite->ID, $includeChildren);
        $response = $this->owner->getResponse();
        $response->addHeader('X-Reload', true);

        return $this->owner->redirect(Controller::join_links(
            $this->owner->Link('show'),
            $newPage->ID
        ));
    }
}
