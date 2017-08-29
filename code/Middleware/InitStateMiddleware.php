<?php

namespace SilverStripe\Subsites\Middleware;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

class InitStateMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate)
    {
        $state = SubsiteState::create();
        Injector::inst()->registerService($state);

        $state->setSubsiteId($this->detectSubsiteId($request));

        return $delegate($request);
    }

    /**
     * Use the given request to detect the current subsite ID
     *
     * @param  HTTPRequest $request
     * @return int
     */
    protected function detectSubsiteId(HTTPRequest $request)
    {
        $id = null;

        if ($request->getVar('SubsiteID')) {
            $id = (int) $request->getVar('SubsiteID');
        }

        if (Subsite::$use_session_subsiteid) {
            $id = $request->getSession()->get('SubsiteID');
        }

        if ($id === null) {
            $id = Subsite::getSubsiteIDForDomain();
        }

        return (int) $id;
    }
}
