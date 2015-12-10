title: Working with multiple websites
summary: Setting up and editing multiple websites using SilverStripe

# Working with multiple sites

## In this section:

* Understand subsites
* Learn how to create and delete subsites
* Learn how to manage subsite permissions
* Enable/Disable public access to subsites
* Learn how to create and use subsite templates
* Learn how to edit existing subsites
* Sharing content between the main site and subsites

## Before we begin:

* Make sure you have the SilverStripe [Subsites](http://addons.silverstripe.org/add-ons/silverstripe/subsites) module installed.
* Make sure you are in the "Subsites" section on the Navigation Tabs. 
* Make sure you have full administrative rights on your site.

## Understanding subsites

Subsites is a module to allow you manage multiple related sites from a single CMS interface. Because all sites run on a single installation of SilverStripe, they can share users, content and assets. They can all use the same templates, or each use different ones.

When Subsites is installed your existing site is defined as the main site, you will be then be able to create related subsites under the main site. 

So for example you may have an international presence and you want to create a subsite for a country where you do business which is geared just for that market. You could create a subsite for this and have all information related to that country kept under this subsite, you can also set up a subdomain for this site.

One of the benefits of subsites is that it is easy to copy pages between the subsites and you have access to all of the assets across all of the subsites.

Subsites is not for running unrelated websites on a single SilverStripe instance so if two sites have different vhosts you will not be able to run them with Subsites on a single SilverStripe instance.

With Subsites you can set up users to have access to all subsites or just a selection of subsites.

## Common subsite uses

Subsites can be used for various different reasons here are some of the common ones:

* Setting up a subsite for a small campaign so for example a clothing company may set up a summer or winter subsite to market just that season of clothing.
* Locking down a particular subsite you may create a particular department like recruitment who would have access to create and edit pages for their particular subsite but they would not be able to modify the main website.
* Running sub-domains on a single SilverStripe instance, with subsites if a sub-domain is pointing to the same instance and has been setup correctly you can manage this via a single CMS instance.
* Subsites can not be used to run multiple websites on a single instance. Subsites does not allow you to run multiple domains/vhosts on a single instance.

## Documentation

* [Set up](set_up.md)
* [Working with subsites](working_with.md)