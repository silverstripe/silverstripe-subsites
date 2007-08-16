<?php

/**
 * The subsites module modifies the behaviour of the CMS - in the SiteTree and Group databases - to store information
 * about a number of sub-sites, rather than a single site.
 */

Object::add_extension('SiteTree', 'SiteTreeSubsites');
// Hack - this ensures that the SiteTree defineMethods gets called before any of its subclasses...
new SiteTree();

Object::add_extension('LeftAndMain', 'LeftAndMainSubsites');
Object::add_extension('Group', 'GroupSubsites');

Director::addRules(100, array(
	'admin/subsites/$Action/$ID/$OtherID' => 'SubsiteAdmin',
));
Object::addStaticVars( 'LeftAndMain', array( 'extra_menu_items' => array(
	'Sub-sites' => array("intranets", "admin/subsites/")
)));

?>
