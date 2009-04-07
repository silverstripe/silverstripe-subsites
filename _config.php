<?php

/**
 * The subsites module modifies the behaviour of the CMS - in the SiteTree and Group databases - to store information
 * about a number of sub-sites, rather than a single site.
 */

Object::add_extension('SiteTree', 'SiteTreeSubsites');
// Hack - this ensures that the SiteTree defineMethods gets called before any of its subclasses...
new SiteTree();
Object::add_extension('ContentController', 'ControllerSubsites');
Object::add_extension('LeftAndMain', 'LeftAndMainSubsites');
Object::add_extension('LeftAndMain', 'ControllerSubsites');

Object::add_extension('Group', 'GroupSubsites');
Object::add_extension('Member', 'MemberSubsites');
Object::add_extension('File', 'FileSubsites');

// Backwards compatibility with SilverStripe 2.2
if(!class_exists('CMSMenu')) {
	Director::addRules(100, array(
		'admin/subsites/$Action/$ID/$OtherID' => 'SubsiteAdmin',
	));
	Object::addStaticVars( 'LeftAndMain', array( 'extra_menu_items' => array(
		'Sub-sites' => array("intranets", "admin/subsites/", 'SubsiteAdmin')
	)));
}

if(!class_exists('GenericDataAdmin')) {
	user_error('Please install the module "genericdataadmin" to use subsites', E_USER_ERROR);
}
?>