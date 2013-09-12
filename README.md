# Subsites Module

[![Build Status](https://secure.travis-ci.org/silverstripe/silverstripe-subsites.png?branch=master)](http://travis-ci.org/silverstripe/silverstripe-subsites)

## Introduction

The subsites module allows multiple websites to run from a single installation of SilverStripe, and share users, content, and assets between them. A useful way to think of its use is where you have a business with a global headquarters and four branches in various countries. The subsites module allows the five offices to use a single SilverStripe installation, and have information from the headquarters flow down into the branches. The branches can have separate users/admins, and information that is individual. The website templates can also be different.

## Requirements

*  SilverStripe 3.1

## Installation

*  Create necessary tables by visiting `http://<yoursite>/dev/build` (you should see a `Subsite` table created, among other things). You don't need to run this command for every subsite.
*  Login to the CMS as an administrator.  You should now see a "Subsites" entry on the main menu, access that section now.
*  Hit the "Add Subsite" button to create a new subsite.
*  Once you've created a subsite, you'll see a "Create Subsite Domain" button, hit that button to enter a domain or subdomain for your subsite. This will determine the URL of your website.  For example, if your site is running on `http://localhost/mysite`, and you set the subdomain to "subsite", then your subsite will be accessible on `http://subsite.localhost/mysite`
*  Go to the "Pages" section of the CMS.  In the top-left above the menu, you'll see a dropdown listing the two subsites - "Main site" is the original site that you had before you installed the subsites module.  Select your new subsite, and the site content tree will be changed.  It should be empty at this stage.
*  Add a page - change its title to "Home", and its URL Segment will be changed to "home".  Save the page.
*  Update your DNS and, if necessary, your webserver configuration, so that your subdomain will point to the SilverStripe installation on your webserver.  Visit this new subdomain.  You should see the new subsite homepage.

## Usage

### Limit allowed domains

You can list available domains for your subsites (Example: subdomain.''domain''.tld). The subsites are generally identified only by their subdomain part (Example: ''subdomain''.domain.tld).

This example would let you create subsites such as ''wellington.mycompany.com'' or ''london.mycompany.org''

*mysite/_config.php*

	:::php
	Subsite::set_allowed_domains(array(
	   'mycompany.com',
	   'mycompany.org'
	));


If you would like to be able to choose any domain for your subsite, rather than subdomains off a common base, then list top-level domains in your `set_allowed_domains()` list.

In this example, your subsite name (e.g. ''silverstripe''), will be appended to a much shorter base domain (e.g.  ''co.nz'', or ''org'').  This would let you create subsites with domains such as ''silverstripe.org'' or ''example.co.nz''

*mysite/_config.php*

	:::php
	Subsite::set_allowed_domains(array(
	   'com',
	   'org',
	   'co.nz',
	   'org.nz',
	));


You can mix the two together, if you want to have some subsites hosted off subdomains of your mail site, and some subsites hosted off their own domains.  In this example, you could set up subsites at ''wellington.example.com'', ''othersite.co.nz'', or ''thirdsite.org''.

*mysite/_config.php*

	:::php
	Subsite::set_allowed_domains(array(
	   'example.com',
	   'com',
	   'org',
	   'co.nz',
	   'org.nz',
	));


Note that every site also has a ''www.''-prefixed version of the domain available.  For example, if your subsite is accessible from ''wellington.example.org'' then it will also be accessible from '''www.wellington.example.org''.

### Strict Subdomain Matching ###

The module tries to provide sensible defaults, in which it regards `example.com` and `www.example.com` as the same domains. In case you want to distinguish between these variations, set `Subsite::$strict_subdomain_matching` to TRUE. This won't affect wildcard/asterisk checks, but removes the ambiguity about default subdomains.

### Permissions ###

Groups can be associated with one or more subsites, in which case the granted permissions
only apply to this subsite. Even the `ADMIN` permission only grants super-user rights on certain subsites by default. If you want to create a super-user regardless of subsites association, please use the `Group.AccessAllSubsites` property ("Give this group access to all subsites"), together with the `ADMIN` permission.

### Access created domains

Once you have created some subsites/domains in your admin, you can check the overall functionality of subsites by

	http://your.primary-domain.com/subsite-metadata-url?SubsiteID=1

In some Browsers the SubsiteID is visible if you hover over the "Edit" link in the search results of Subsite admin.

### Subsite-specific themes

Download a second theme from http://www.silverstripe.com/themes/ and put it in your themes folder.  Open admin/subsites?flush=1 and select one of your subsites from the menu on the bottom-left.  You should see a Theme dropdown in the subsite details, and it should list both your original theme and the new theme.  Select the new theme in the dropdown.  Now, this subsite will use a different theme from the main site.

### Limit available themes for a subsite

Not all themes might be suitable or adapted for all subsites. You can optionally limit usage of themes:

*mysite/_config.php*

	:::php
	Subsite::set_allowed_themes(array('blackcandy','mytheme'));

### Enable Subsite support on DataObjects
To make your DataObject subsite aware, include a SubsiteID on your DataObject. eg:

*MyDataObject.php*

	:::php
	private static $has_one = array(
		'Subsite' => 'Subsite'
	);

Include the current SubsiteID as a hidden field on getCMSFields, or updateCMSFields. eg:

*MyDataObject.php*

	:::php
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		if(class_exists('Subsite')){
			$fields->push(new HiddenField('SubsiteID','SubsiteID', Subsite::currentSubsiteID()));
		}
		return $fields;
	}

To limit your admin gridfields to the current Subsite records, you can do something like this:

*MyAdmin.php*

	:::php
	public function getEditForm($id = null, $fields = null){
		$form = parent::getEditForm($id, $fields);
		
		$gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));
		if(class_exists('Subsite')){
			$list = $gridField->getList()->filter(array('SubsiteID'=>Subsite::currentSubsiteID()));
			$gridField->setList($list);
		}

		return $form;
	}

### Enable menu support for custom areas in subsites

Custom admin areas, by default, will not show in the menu of a subsite. Not all admins are adapted for or appropriate to show within a subsite. If your admin does have subsite support, or is intentionally global, you can enable the show in menu option either by applying:

*mysite/_config.php*

	:::php
	MyAdmin::add_extension('SubsiteMenuExtension');

or by defining the subsiteCMSShowInMenu function in your admin:

*MyAdmin.php*

	:::php
	public function subsiteCMSShowInMenu(){
		return true;
	}


### Public display of a subsite

By default, each subsite is available to the public (= not logged-in),
provided a correct host mapping is set up. A subsite can be marked as non-public
in its settings, in which case it only shows if a user with CMS permissions is logged in.
This is useful to create and check subsites on a live system before publishing them.

Please note that you need to filter for this manually in your own queries:

	$publicSubsites = DataObject::get(
		'Subsite',
		Subsite::$check_is_public ? '"IsPublic"=1' : '';
	);

To ensure the logged-in status of a member is carried across to subdomains,
you also need to configure PHP session cookies to be set
for all subdomains:

	// Example matching subsite1.example.org and www.example.org
	Session::set_cookie_domain('.example.org');

## Screenshots

![](docs/en/_images/subsites-module-adminscreenshot.png)
