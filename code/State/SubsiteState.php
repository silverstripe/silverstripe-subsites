<?php

namespace SilverStripe\Subsites\State;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

/**
 * SubsiteState provides static access to the current state for subsite related data during a request
 */
class SubsiteState
{
    use Injectable;

    /**
     * @var int|null
     */
    protected $subsiteId;

    /**
     * Get the current subsite ID
     *
     * @return int|null
     */
    public function getSubsiteId()
    {
        return $this->subsiteId;
    }

    /**
     * Set the current subsite ID
     *
     * @param int $id
     * @return $this
     */
    public function setSubsiteId($id)
    {
        $this->subsiteId = (int) $id;

        return $this;
    }

    /**
     * Perform a given action within the context of a new, isolated state. Modifications are temporary
     * and the existing state will be restored afterwards.
     *
     * @param callable $callback Callback to run. Will be passed the nested state as a parameter
     * @return mixed Result of callback
     */
    public function withState(callable $callback)
    {
        $newState = clone $this;
        try {
            Injector::inst()->registerService($newState);
            return $callback($newState);
        } finally {
            Injector::inst()->registerService($this);
        }
    }
}
