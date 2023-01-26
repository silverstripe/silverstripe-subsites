<?php

namespace SilverStripe\Subsites\State;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Resettable;
use SilverStripe\Dev\Deprecation;

/**
 * SubsiteState provides static access to the current state for subsite related data during a request
 */
class SubsiteState implements Resettable
{
    use Injectable;

    /**
     * @var int|null
     */
    protected $subsiteId;


    /**
     * @var int|null
     */
    protected $originalSubsiteId;

    /**
     * @var bool
     */
    protected $useSessions;

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
     * Set the current subsite ID, and track the first subsite ID set as the "original". This is used to check
     * whether the ID has been changed through a request.
     *
     * @param int $id
     * @return $this
     */
    public function setSubsiteId($id)
    {
        if (!ctype_digit((string) $id) && !is_null($id)) {
            Deprecation::notice('2.8.0', 'Passing multiple IDs is deprecated, only pass a single ID instead.');
        }
        if (is_null($this->originalSubsiteId)) {
            $this->originalSubsiteId = (int) $id;
        }

        $this->subsiteId = (int) $id;

        return $this;
    }

    /**
     * Get whether to use sessions for storing the subsite ID
     *
     * @return bool
     */
    public function getUseSessions()
    {
        return $this->useSessions;
    }

    /**
     * Set whether to use sessions for storing the subsite ID
     *
     * @param bool $useSessions
     * @return $this
     */
    public function setUseSessions($useSessions)
    {
        $this->useSessions = $useSessions;

        return $this;
    }

    /**
     * Get whether the subsite ID has been changed during a request, based on the original and current IDs
     *
     * @return bool
     */
    public function getSubsiteIdWasChanged()
    {
        return $this->originalSubsiteId !== $this->getSubsiteId();
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

    /**
     * Reset the local cache of the singleton
     */
    public static function reset()
    {
        SubsiteState::singleton()->resetState();
    }

    /**
     * Reset the local cache of this object
     */
    public function resetState()
    {
        $this->originalSubsiteId = null;
        $this->subsiteId = null;
        $this->useSessions = null;
    }
}
