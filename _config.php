<?php

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Controllers\CMSPageAddController;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Security\Group;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\ErrorPage;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\SiteConfig\SiteConfigLeftAndMain;
use SilverStripe\Reports\Report;
use SilverStripe\Admin\SecurityAdmin;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Controllers\CMSPagesController;
use SilverStripe\CMS\Controllers\CMSPageSettingsController;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;

/**
 * The subsites module modifies the behaviour of the CMS - in the SiteTree and Group databases - to store information
 * about a number of sub-sites, rather than a single site.
 */

SiteTree::add_extension('SiteTreeSubsites');
ContentController::add_extension('ControllerSubsites');
CMSPageAddController::add_extension('CMSPageAddControllerExtension');
LeftAndMain::add_extension('LeftAndMainSubsites');
LeftAndMain::add_extension('ControllerSubsites');

Group::add_extension('GroupSubsites');
File::add_extension('FileSubsites');
ErrorPage::add_extension('ErrorPageSubsite');
SiteConfig::add_extension('SiteConfigSubsites');

Report::add_excluded_reports('SubsiteReportWrapper');

//Display in cms menu
AssetAdmin::add_extension('SubsiteMenuExtension');
SecurityAdmin::add_extension('SubsiteMenuExtension');
CMSMain::add_extension('SubsiteMenuExtension');
CMSPagesController::add_extension('SubsiteMenuExtension');
SubsiteAdmin::add_extension('SubsiteMenuExtension');
SiteConfigLeftAndMain::add_extension('SubsiteMenuExtension');

