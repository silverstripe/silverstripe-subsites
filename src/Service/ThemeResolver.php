<?php

namespace SilverStripe\Subsites\Service;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\View\SSViewer;

class ThemeResolver
{
    use Injectable;
    use Configurable;

    /**
     * Cascading definitions for themes, keyed by the name they should appear under in the CMS. For example:
     *
     * [
     *   'theme-1' => [
     *     '$public',
     *     'starter',
     *     '$default',
     *   ],
     *   'theme-2' => [
     *     'custom',
     *     'watea',
     *     'starter',
     *     '$public',
     *     '$default',
     *   ]
     * ]
     *
     * @config
     * @var null|array[]
     */
    private static $theme_options;

    /**
     * Get the list of themes for the given sub site that can be given to SSViewer::set_themes
     *
     * @param Subsite $site
     * @return array
     */
    public function getThemeList(Subsite $site)
    {
        $themes = array_values(SSViewer::get_themes());
        $siteTheme = $site->Theme;

        if (!$siteTheme) {
            return $themes;
        }

        $customOptions = $this->config()->get('theme_options');
        if ($customOptions && isset($customOptions[$siteTheme])) {
            return $customOptions[$siteTheme];
        }

        // Ensure themes don't cascade "up" the list
        $index = array_search($siteTheme, $themes);

        if ($index > 0) {
            // 4.0 didn't have support for themes in the public webroot
            $constant = SSViewer::class . '::PUBLIC_THEME';
            $publicConstantDefined = defined($constant);

            // Check if the default is public themes
            $publicDefault = $publicConstantDefined && $themes[0] === SSViewer::PUBLIC_THEME;

            // Take only those that appear after theme chosen (non-inclusive)
            $themes = array_slice($themes, $index + 1);

            // Add back in public
            if ($publicDefault) {
                array_unshift($themes, SSViewer::PUBLIC_THEME);
            }
        }

        // Add our theme
        array_unshift($themes, $siteTheme);

        return $themes;
    }

    /**
     * Get a list of custom cascading theme definitions if available
     *
     * @return null|array
     */
    public function getCustomThemeOptions()
    {
        $config = $this->config()->get('theme_options');

        if (!$config) {
            return null;
        }

        return array_keys($config);
    }
}
