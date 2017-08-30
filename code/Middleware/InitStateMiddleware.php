<?php

namespace SilverStripe\Subsites\Middleware;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

class InitStateMiddleware implements HTTPMiddleware
{
    use Configurable;

    /**
     * URL paths that should be considered as admin only, i.e. not frontend
     *
     * @config
     * @var array
     */
    private static $admin_url_paths = [
        'dev/',
        'graphql/',
    ];

    public function process(HTTPRequest $request, callable $delegate)
    {
        $state = SubsiteState::create();
        Injector::inst()->registerService($state);

        // If the request is from the CMS, we should enable session storage
        $state->setUseSessions($this->getIsAdmin($request));

        $state->setSubsiteId($this->detectSubsiteId($request));

        return $delegate($request);
    }

    /**
     * Determine whether the website is being viewed from an admin protected area or not
     *
     * @param  HTTPRequest $request
     * @return bool
     */
    public function getIsAdmin(HTTPRequest $request)
    {
        $adminPaths = static::config()->get('admin_url_paths');
        $adminPaths[] = AdminRootController::config()->get('url_base') . '/';
        $currentPath = rtrim($request->getURL(), '/') . '/';
        foreach ($adminPaths as $adminPath) {
            if (substr($currentPath, 0, strlen($adminPath)) === $adminPath) {
                return true;
            }
        }
        return false;
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

        if (SubsiteState::singleton()->getUseSessions()) {
            $id = $request->getSession()->get('SubsiteID');
        }

        if ($id === null) {
            $id = Subsite::getSubsiteIDForDomain();
        }

        return (int) $id;
    }
}
