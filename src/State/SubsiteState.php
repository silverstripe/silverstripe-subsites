<?php

namespace SilverStripe\Subsites\State;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Resettable;

/**
 * SubsiteState provides static access to the current state for subsite related data during a request
 */
class SubsiteState implements Resettable
{
    use Injectable;

    protected ?int $subsiteId = null;

    protected ?int $originalSubsiteId = null;

    protected ?bool $useSessions = null;

    /**
     * Get the current subsite ID
     */
    public function getSubsiteId(): ?int
    {
        return $this->subsiteId;
    }

    /**
     * Set the current subsite ID, and track the first subsite ID set as the "original". This is used to check
     * whether the ID has been changed through a request.
     */
    public function setSubsiteId(?int $id): static
    {
        if (is_null($this->originalSubsiteId)) {
            $this->originalSubsiteId = (int) $id;
        }

        $this->subsiteId = (int) $id;

        return $this;
    }

    /**
     * Get whether to use sessions for storing the subsite ID
     */
    public function getUseSessions(): ?bool
    {
        return $this->useSessions;
    }

    /**
     * Set whether to use sessions for storing the subsite ID
     */
    public function setUseSessions(?bool $useSessions): static
    {
        $this->useSessions = $useSessions;

        return $this;
    }

    /**
     * Get whether the subsite ID has been changed during a request, based on the original and current IDs
     */
    public function getSubsiteIdWasChanged(): bool
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
    public function withState(callable $callback): mixed
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
    public static function reset(): void
    {
        SubsiteState::singleton()->resetState();
    }

    /**
     * Reset the local cache of this object
     */
    public function resetState(): void
    {
        $this->originalSubsiteId = null;
        $this->subsiteId = null;
        $this->useSessions = null;
    }
}
