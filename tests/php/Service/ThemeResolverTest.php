<?php

namespace SilverStripe\Subsites\Tests\Service;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\Service\ThemeResolver;
use SilverStripe\View\SSViewer;

class ThemeResolverTest extends SapphireTest
{
    protected $themeList = [
        '$public',
        'custom',
        'main',
        'backup',
        SSViewer::DEFAULT_THEME,
    ];

    protected function setUp()
    {
        parent::setUp();

        // Setup known theme config
        Config::modify()->set(SSViewer::class, 'themes', $this->themeList);
    }

    public function testSubsiteWithoutThemeReturnsDefaultThemeList()
    {
        $subsite = new Subsite();
        $resolver = new ThemeResolver();

        $this->assertSame($this->themeList, $resolver->getThemeList($subsite));
    }

    public function testSubsiteWithCustomThemePrependsToList()
    {
        $subsite = new Subsite();
        $subsite->Theme = 'subsite';

        $resolver = new ThemeResolver();

        $expected = array_merge(['subsite'], $this->themeList);

        $this->assertSame($expected, $resolver->getThemeList($subsite));
    }

    public function testSubsiteWithCustomThemeDoesNotCascadeUpTheList()
    {
        $subsite = new Subsite();
        $subsite->Theme = 'main';

        $resolver = new ThemeResolver();

        $expected = [
            'main', // 'main' is moved to the top
            '$public', // $public is preserved
            // Anything above 'main' is removed
            'backup',
            SSViewer::DEFAULT_THEME,
        ];

        $this->assertSame($expected, $resolver->getThemeList($subsite));
    }

    /**
     * @dataProvider customThemeDefinitionsAreRespectedProvider
     */
    public function testCustomThemeDefinitionsAreRespected($themeOptions, $siteTheme, $expected)
    {
        Config::modify()->set(ThemeResolver::class, 'theme_options', $themeOptions);

        $subsite = new Subsite();
        $subsite->Theme = $siteTheme;

        $resolver = new ThemeResolver();

        $this->assertSame($expected, $resolver->getThemeList($subsite));
    }

    public function customThemeDefinitionsAreRespectedProvider()
    {
        return [
            // Simple
            [
                ['test' => $expected = [
                    'subsite',
                    'backup',
                    '$public',
                    SSViewer::DEFAULT_THEME,
                ]],
                'test',
                $expected
            ],
            // Many options
            [
                [
                    'aye' => [
                        'aye',
                        'thing',
                        SSViewer::DEFAULT_THEME,
                    ],
                    'bee' => $expected = [
                        'subsite',
                        'backup',
                        '$public',
                        SSViewer::DEFAULT_THEME,
                    ],
                    'sea' => [
                        'mer',
                        'ocean',
                        SSViewer::DEFAULT_THEME,
                    ],
                ],
                'bee',
                $expected
            ],
            // Conflicting with root definitions
            [
                ['main' => $expected = [
                    'subsite',
                    'backup',
                    '$public',
                    SSViewer::DEFAULT_THEME,
                ]],
                'main',
                $expected
            ],
            // Declaring a theme specifically should still work
            [
                ['test' => [
                    'subsite',
                    'backup',
                    '$public',
                    SSViewer::DEFAULT_THEME,
                ]],
                'other',
                array_merge(['other'], $this->themeList)
            ],
        ];
    }
}
