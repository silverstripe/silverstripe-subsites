<?php

class SiteConfigSubsitesTest extends BaseSubsiteTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';
	
	function testEachSubsiteHasAUniqueSiteConfig() {
		$subsite1 = $this->objFromFixture('Subsite', 'domaintest1');
		$subsite2 = $this->objFromFixture('Subsite', 'domaintest2');

		$this->assertTrue(is_array(singleton('SiteConfigSubsites')->extraStatics()));
		
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
		$this->assertEquals(SiteConfig::current_site_config()->Title, 'RootSite');
		Subsite::changeSubsite($subsite1->ID);
		$this->assertEquals(SiteConfig::current_site_config()->Title, 'Subsite1');
		Subsite::changeSubsite($subsite2->ID);
		$this->assertEquals(SiteConfig::current_site_config()->Title, 'Subsite2');

		$keys = SiteConfig::current_site_config()->extend('cacheKeyComponent');
		$this->assertContains('subsite-' . $subsite2->ID, $keys);
	}

}
