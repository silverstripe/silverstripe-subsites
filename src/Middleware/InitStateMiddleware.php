<?php

namespace SilverStripe\Subsites\Middleware;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\Connect\DatabaseException;
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
        try {
            // Initialise and register the State
            $state = SubsiteState::create();
            Injector::inst()->registerService($state);

            // Detect whether the request was made in the CMS area (or other admin-only areas)
            $isAdmin = $this->getIsAdmin($request);
            $state->setUseSessions($isAdmin);

            // Detect the subsite ID
            $subsiteId = $this->detectSubsiteId($request);
            $state->setSubsiteId($subsiteId);

            return $delegate($request);
        } catch (DatabaseException $ex) {
            // Database is not ready
            return $delegate($request);
        } finally {
            // Persist to the session if using the CMS
            if ($state->getUseSessions()) {
                $request->getSession()->set('SubsiteID', $state->getSubsiteId());
            }
        }
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
        if ($request->getVar('SubsiteID') !== null) {
            return (int) $request->getVar('SubsiteID');
        }

        if (SubsiteState::singleton()->getUseSessions() && $request->getSession()->get('SubsiteID') !== null) {
            return (int) $request->getSession()->get('SubsiteID');
        }

        $subsiteIdFromDomain = Subsite::getSubsiteIDForDomain();
        if ($subsiteIdFromDomain !== null) {
            return (int) $subsiteIdFromDomain;
        }

        // Default fallback
        return 0;
    }
}
