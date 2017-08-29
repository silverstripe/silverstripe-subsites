<?php

namespace subsites\tests\php;

use Page;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\View\SSViewer;

class SubsiteFunctionalTest extends FunctionalTest
{
    public static $fixture_file = 'subsites/tests/php/SubsiteTest.yml';

    /**
     * @todo: remove test from SiteTreeSubsitesTest when this one works. Seems domain lookup is broken atm
     */
    public function testIfSubsiteThemeIsSetToThemeList()
    {
        $this->markTestSkipped('doesn\'t work somehow - refactor when domain lookup is working');
        $defaultThemes = ['default'];
        SSViewer::set_themes($defaultThemes);

        $subsitePage = $this->objFromFixture(Page::class, 'contact');
        $this->get($subsitePage->AbsoluteLink());
        $this->assertEquals($subsitePage->SubsiteID, Subsite::currentSubsiteID(), 'Subsite should be changed');
        $this->assertEquals(
            SSViewer::get_themes(),
            $defaultThemes,
            'Themes should not be modified when Subsite has no theme defined'
        );

        $pageWithTheme = $this->objFromFixture(Page::class, 'subsite1_contactus');
        $this->get($pageWithTheme->AbsoluteLink());
        $subsiteTheme = $pageWithTheme->Subsite()->Theme;
        $this->assertEquals($pageWithTheme->SubsiteID, Subsite::currentSubsiteID(), 'Subsite should be changed');
        $this->assertEquals(
            SSViewer::get_themes(),
            array_merge([$subsiteTheme], $defaultThemes),
            'Themes should be modified when Subsite has theme defined'
        );
    }
}
