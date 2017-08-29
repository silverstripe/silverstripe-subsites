<?php

namespace SilverStripe\Subsites\Forms;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\View\Requirements;

/**
 * Wraps around a TreedropdownField to add ability for temporary
 * switching of subsite sessions.
 *
 * @package subsites
 */
class SubsitesTreeDropdownField extends TreeDropdownField
{
    private static $allowed_actions = [
        'tree'
    ];

    protected $subsiteID = 0;

    protected $extraClasses = ['SubsitesTreeDropdownField'];

    public function Field($properties = [])
    {
        $html = parent::Field($properties);

        Requirements::javascript('subsites/javascript/SubsitesTreeDropdownField.js');

        return $html;
    }

    public function setSubsiteID($id)
    {
        $this->subsiteID = $id;
    }

    public function getSubsiteID()
    {
        return $this->subsiteID;
    }

    public function tree(HTTPRequest $request)
    {
        $session = Controller::curr()->getRequest()->getSession();

        $oldSubsiteID = $session->get('SubsiteID');
        $session->set('SubsiteID', $this->subsiteID);

        $results = parent::tree($request);

        $session->set('SubsiteID', $oldSubsiteID);

        return $results;
    }
}
