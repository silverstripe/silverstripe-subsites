<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Subsites\Extensions\SiteConfigSubsites;
use SilverStripe\Subsites\Model\Subsite;

class SiteConfigSubsitesTest extends BaseSubsiteTest
{
    protected static $fixture_file = 'SubsiteTest.yml';

    public function testEachSubsiteHasAUniqueSiteConfig()
    {
        $subsite1 = $this->objFromFixture(Subsite::class, 'domaintest1');
        $subsite2 = $this->objFromFixture(Subsite::class, 'domaintest2');

        $this->assertTrue(is_array(singleton(SiteConfigSubsites::class)->extraStatics()));

        Subsite::changeSubsite(0);
        $sc = SiteConfig::current_site_config();
        $sc->Title = 'RootSite';
        $sc->write();

        Subsite::changeSubsite($subsite1->ID);
        $sc = SiteConfig::current_site_config();
        $sc->Title = 'Subsite1';
        $sc->write();

        Subsite::changeSubsite($subsite2->ID);
        $sc = SiteConfig::current_site_config();
        $sc->Title = 'Subsite2';
        $sc->write();

        Subsite::changeSubsite(0);
        $this->assertEquals('RootSite', SiteConfig::current_site_config()->Title);
        Subsite::changeSubsite($subsite1->ID);
        $this->assertEquals('Subsite1', SiteConfig::current_site_config()->Title);
        Subsite::changeSubsite($subsite2->ID);
        $this->assertEquals('Subsite2', SiteConfig::current_site_config()->Title);

        $keys = SiteConfig::current_site_config()->extend('cacheKeyComponent');
        static::assertContains('subsite-' . $subsite2->ID, $keys);
    }
}
