<?php

namespace SilverStripe\Subsites\Extensions;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Admin\CMSMenu;
use SilverStripe\Admin\LeftAndMainExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Subsites\Controller\SubsiteXHRController;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;

/**
 * Decorator designed to add subsites support to LeftAndMain
 *
 * @package subsites
 */
class LeftAndMainSubsites extends LeftAndMainExtension
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
        $module = ModuleLoader::getModule('silverstripe/subsites');

        Requirements::css($module->getRelativeResourcePath('css/LeftAndMain_Subsites.css'));
        Requirements::javascript($module->getRelativeResourcePath('javascript/LeftAndMain_Subsites.js'));
        Requirements::javascript($module->getRelativeResourcePath('javascript/VirtualPage_Subsites.js'));
    }

    /**
     * Set the title of the CMS tree
     */
    public function getCMSTreeTitle()
    {
        $subsite = Subsite::currentSubsite();
        return $subsite ? Convert::raw2xml($subsite->Title) : _t(__CLASS__.'.SITECONTENTLEFT', 'Site Content');
    }

    public function updatePageOptions(&$fields)
    {
        $fields->push(HiddenField::create('SubsiteID', 'SubsiteID', SubsiteState::singleton()->getSubsiteId()));
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
            $member = Security::getCurrentUser();
        }
        if (!$member) {
            return ArrayList::create();
        }
        if (!is_object($member)) {
            $member = DataObject::get_by_id(Member::class, $member);
        }

        // Collect permissions - honour the LeftAndMain::required_permission_codes, current model requires
        // us to check if the user satisfies ALL permissions. Code partly copied from LeftAndMain::canView.
        $codes = [];
        $extraCodes = Config::inst()->get(get_class($this->owner), 'required_permission_codes');
        if ($extraCodes !== false) {
            if ($extraCodes) {
                $codes = array_merge($codes, (array)$extraCodes);
            } else {
                $codes[] = sprintf('CMS_ACCESS_%s', get_class($this->owner));
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
        $currentSubsiteID = SubsiteState::singleton()->getSubsiteId();

        if ($list == null || $list->count() == 1 && $list->first()->DefaultSite == true) {
            return false;
        }

        $module = ModuleLoader::getModule('silverstripe/subsites');
        Requirements::javascript($module->getRelativeResourcePath('javascript/LeftAndMain_Subsites.js'));

        $output = ArrayList::create();

        foreach ($list as $subsite) {
            $currentState = $subsite->ID == $currentSubsiteID ? 'selected' : '';

            $output->push(ArrayData::create([
                'CurrentState' => $currentState,
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
        if (singleton($controllerName) instanceof SubsiteXHRController) {
            return false;
        }

        // Check subsite support.
        if (SubsiteState::singleton()->getSubsiteId() == 0) {
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
     * @param string $adminClass
     * @param int $recordSubsiteID
     * @param int $currentSubsiteID
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
        $member = Security::getCurrentUser();
        if ($member
            && (Permission::checkMember($member, 'ADMIN') // 'Full administrative rights'
                || Permission::checkMember($member, 'CMS_ACCESS_LeftAndMain') // 'Access to all CMS sections'
            )
        ) {
            return true;
        }

        // Check if we have access to current section on the current subsite.
        $accessibleSites = $this->owner->sectionSites(true, 'Main site', $member);
        return $accessibleSites->count() && $accessibleSites->find('ID', SubsiteState::singleton()->getSubsiteId());
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
        $request = Controller::curr()->getRequest();
        $session = $request->getSession();

        $state = SubsiteState::singleton();

        // FIRST, check if we need to change subsites due to the URL.

        // Catch forced subsite changes that need to cause CMS reloads.
        if ($request->getVar('SubsiteID') !== null) {
            // Clear current page when subsite changes (or is set for the first time)
            if ($state->getSubsiteIdWasChanged()) {
                // sessionNamespace() is protected - see for info
                $override = $this->owner->config()->get('session_namespace');
                $sessionNamespace = $override ? $override : get_class($this->owner);
                $session->clear($sessionNamespace . '.currentPage');
            }

            // Subsite ID has already been set to the state via InitStateMiddleware. If the user cannot view
            // the current page, or we're in the context of editing a specific page, redirect to the admin landing
            // section to prevent a loop of re-loading the original subsite for the current page.
            if (!$this->owner->canView() || Controller::curr() instanceof CMSPageEditController) {
                return $this->owner->redirect(AdminRootController::config()->get('url_base') . '/');
            }

            // Redirect to clear the current page, retaining the current URL parameters
            return $this->owner->redirect(
                Controller::join_links($this->owner->Link(), ...array_values($this->owner->getURLParams()))
            );
        }

        // Automatically redirect the session to appropriate subsite when requesting a record.
        // This is needed to properly initialise the session in situations where someone opens the CMS via a link.
        $record = $this->owner->currentPage();
        if ($record
            && isset($record->SubsiteID, $this->owner->urlParams['ID'])
            && is_numeric($record->SubsiteID)
            && $this->shouldChangeSubsite(
                get_class($this->owner),
                $record->SubsiteID,
                SubsiteState::singleton()->getSubsiteId()
            )
        ) {
            // Update current subsite
            $canViewElsewhere = SubsiteState::singleton()->withState(function ($newState) use ($record) {
                $newState->setSubsiteId($record->SubsiteID);

                if ($this->owner->canView(Security::getCurrentUser())) {
                    return true;
                }
                return false;
            });

            if ($canViewElsewhere) {
                // Redirect to clear the current page
                return $this->owner->redirect(
                    Controller::join_links($this->owner->Link('show'), $record->ID, '?SubsiteID=' . $record->SubsiteID)
                );
            }
            // Redirect to the default CMS section
            return $this->owner->redirect(AdminRootController::config()->get('url_base') . '/');
        }

        // SECOND, check if we need to change subsites due to lack of permissions.

        if (!$this->owner->canAccess()) {
            $member = Security::getCurrentUser();

            // Current section is not accessible, try at least to stick to the same subsite.
            $menu = CMSMenu::get_menu_items();
            foreach ($menu as $candidate) {
                if ($candidate->controller && $candidate->controller != get_class($this->owner)) {
                    $accessibleSites = singleton($candidate->controller)->sectionSites(true, 'Main site', $member);
                    if ($accessibleSites->count()
                        && $accessibleSites->find('ID', SubsiteState::singleton()->getSubsiteId())
                    ) {
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
        $request = Controller::curr()->getRequest();
        $item->SubsiteID = $request->postVar('SubsiteID') ?: SubsiteState::singleton()->getSubsiteId();
    }

    public function onAfterSave($record)
    {
        if ($record->hasMethod('NormalRelated') && ($record->NormalRelated() || $record->ReverseRelated())) {
            $this->owner->response->addHeader(
                'X-Status',
                rawurlencode(_t(__CLASS__ . '.Saved', 'Saved, please update related pages.'))
            );
        }
    }

    /**
     * @param array $data
     * @param Form $form
     */
    public function copytosubsite($data, $form)
    {
        $page = DataObject::get_by_id(SiteTree::class, $data['ID']);
        $subsite = DataObject::get_by_id(Subsite::class, $data['CopyToSubsiteID']);
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
