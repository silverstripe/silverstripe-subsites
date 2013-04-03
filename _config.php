<?php

/**
 * The subsites module modifies the behaviour of the CMS - in the SiteTree and Group databases - to store information
 * about a number of sub-sites, rather than a single site.
 */

Object::add_extension('SiteTree', 'SiteTreeSubsites');
Object::add_extension('ContentController', 'ControllerSubsites');
Object::add_extension('LeftAndMain', 'LeftAndMainSubsites');
Object::add_extension('LeftAndMain', 'ControllerSubsites');

Object::add_extension('Group', 'GroupSubsites');
Object::add_extension('File', 'FileSubsites');
Object::add_extension('ErrorPage', 'ErrorPageSubsite');
Object::add_extension('SiteConfig', 'SiteConfigSubsites');


SS_Report::add_excluded_reports('SubsiteReportWrapper');
