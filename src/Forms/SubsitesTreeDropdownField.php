<?php

namespace SilverStripe\Subsites\Forms;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\View\Requirements;
use SilverStripe\Subsites\State\SubsiteState;

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

    /**
     * Extra HTML classes
     *
     * @skipUpgrade
     * @var string[]
     */
    protected $extraClasses = ['SubsitesTreeDropdownField'];

    public function Field($properties = [])
    {
        $html = parent::Field($properties);

        $module = ModuleLoader::getModule('silverstripe/subsites');
        Requirements::javascript($module->getRelativeResourcePath('javascript/SubsitesTreeDropdownField.js'));

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
        $results = SubsiteState::singleton()->withState(function () use ($request) {
            SubsiteState::singleton()->setSubsiteId($this->subsiteID);
            return parent::tree($request);
        });

        return $results;
    }
}
