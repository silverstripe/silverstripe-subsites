## Introduction

Subsites is a module to allow you manage multiple related sites from a single CMS interface.

When Subsites is installed your existing site is defined as the main site, you will be then be able to create related 
subsites under the main site. 


So for example you may have an international presence and you want to create a subsite for a country where you do 
business which is geared just for that market.
You could create a subsite for this and have all information related to that country kept under this subsite, you can 
also set up a subdomain for this site.


One of the benefits of subsites is that it is easy to copy pages between the subsites and you have access to all of 
the assets across all of the subsites.

Subsites is not for running unrelated websites on a single SilverStripe instance so if 2 sites have different vhosts
you will not be able to run them with Subsites on a single SilverStripe instance.

With Subsites you can set up users to have access to all subsites or just a selection of subsites.


## Common subsite uses
Subsites can be used for various different reasons here are some of the common ones

- Setting up a subsite for a small campaign so for example a clothing company may set up a summer or winter subsite to
market just that season of clothing.

- Locking down a particular subsite you may create a particular department like recruitment who would have access to
create and edit pages for their particular subsite but they would not be able to modify the main website.

- Running sub-domains on a single SilverStripe instance, with subsites if a sub-domain is pointing to the same instance
and has been setup correctly you can manage this via a single CMS instance.

- Subsites can not be used to run multiple websites on a single instance.
Subsites does not allow you to run multiple domains/vhosts on a single instance.


## Access


Access to certain subsites can be limited to administrators based on the groups they are in.
So for example if you had a couple of subsites you could create a group for each subsite and then specify that the 
group had access to all subsites or just a specific subsites.
To access this functionality go to


Security -> Groups

![alt text](_images/subsite-admin-security-group.png "Groups")

Select the group you want to modify and then go to the Subsites tab

You can also limit the page types that are available for a subsite (but not the main site).
This can be done via accessing the particular subsite you want to amend via the Subsite admin section, underneath the
Subsite theme will be a link called 'Disallow page types?' clicking on that link will display a list of checkboxes for
all of the page types which can be selected to disable that page type for the subsite you are editing.
This is useful when you create a content editor and you do not want them to be able to add certain page types.

## Theme
A theme is group of templates, images and CSS for the look of a website.
When you are using Subsites you may have different themes installed for your site so you could apply different
themes for each subsite.

## Page types
Page types refer to the type of pages that can be set up on a site.
A page type will have certain features and functionality some examples on SilverStripe would be 'Page', 'HomePage'
and 'ErrorPage' these all differ to each other in what they would be used for so you would use Page for any pages
underneath the HomePage.


You would only have one HomePage for your site and you may have some logic to only allow you to create one of these
pages, ErrorPage would only be used for error pages and would be designed to be very minimal to work in situations
where the site is experiencing difficulties like no DB access.


You can set up a Subsite to only work with certain page types so you may have a page type with a contact form for a 
particular department so you may set up a new subsite and not allow that page type to be used on a particular subsite.
You will not be able to filter Page Types for the main site.

## Assets
Assets are files that have been uploaded via the CMS.
It is suggested to use a naming convention for files designated to be used on a particular subsite or to create folders
for each subsite to help organise them.

## FAQ


### How can I restrict a content author to a particular subsite?


To lock down a subsite to certain content authors you will need to create a group in security add the content authors 
to this group and then specify that this group only has access to a the relevant subsite.


### Why is the first site I have developed is appearing as the main site?


When you install SilverStripe with Subsites no subsites will exists and you will just have the main site. 
If you install Subsites to an existing sites then the current site will be classed at the main site.


### What is the difference between the main site and the sub sites?


The main site will be the your primary focus so if SilverStripe was using Subsites we will have silverstripe.com 
as the main site and a subsite would be something like doc.silverstripe.com


### Will I need to make any changes to my domain name?


Potentially if you intend to have something like subsite.mydomain.com as well as www.mydomain.com both running on 
your SilverStripe install on the same server then you will need to check that that subsite.mydomain.com points to 
the same web server as www.mydomain.com You may also need to speak to your domain registrar and hosting provider 
regarding this to confirm if this will work. 
You can confirm if your sub domain is set up by pinging it and confirming if the IP address returned is the same as 
your main website. However this does not guarantee this will work as your hosting provider may need to set up a 
virtual host.


### Will I need to set up a virtual host to use subsites?


Yes if you are running on a Apache server a virtual host will need to be set up to support a subsite, you will need 
to speak to your website administrator or hosting provider to facilitate this.


### How can I test a subsite works on my local machine or on a machine without a virtual host being set up?


You can simulate subsite access without setting up virtual hosts by appending ?SubsiteID=<ID> to the request.
