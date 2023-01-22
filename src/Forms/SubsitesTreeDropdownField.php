<?php

namespace SilverStripe\Subsites\Forms;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\Subsites\Model\Subsite;
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

    /**
     * @var int
     */
    protected $subsiteId = 0;

    /**
     * Extra HTML classes
     *
     * @var string[]
     */
    protected $extraClasses = ['SubsitesTreeDropdownField'];

    public function Field($properties = [])
    {
        $html = parent::Field($properties);

        Requirements::javascript('silverstripe/subsites:client/javascript/SubsitesTreeDropdownField.js');

        return $html;
    }

    /**
     * Sets the subsite ID to use when generating the tree
     *
     * @param int $id
     * @return $this
     */
    public function setSubsiteId($id)
    {
        $this->subsiteId = $id;
        return $this;
    }

    /**
     * Get the subsite ID to use when generating the tree
     *
     * @return int
     */
    public function getSubsiteId()
    {
        return $this->subsiteId;
    }

    /**
     * Get the CMS tree with the provided subsite ID applied to the state
     *
     * {@inheritDoc}
     */
    public function tree(HTTPRequest $request)
    {
        // Detect subsite ID from the request
        if ($request->getVar($this->getName() . '_SubsiteID')) {
            $this->setSubsiteId($request->getVar($this->getName() . '_SubsiteID'));
        }

        $results = SubsiteState::singleton()->withState(function (SubsiteState $newState) use ($request) {
            $newState->setSubsiteId($this->getSubsiteId());
            return parent::tree($request);
        });

        return $results;
    }
}
