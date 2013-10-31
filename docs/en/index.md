# Subsites

The SilverStripe subsites module allows you to manage multiple related sites through a single CMS interface. Because all sites run on a single installation of SilverStripe, they can share users, content and assets. 
They can all use the same templates, or each use different ones.

A useful way to think of its use is where you have a business with a global headquarters and four branches in various countries. The subsites module allows the five offices to use a single SilverStripe installation, and have information from the headquarters flow down into the branches. The branches can have separate users/admins, and information that is individual. The website theme (the look and feel of the website) can also be completely different.

## Features:
 * Each subsite appears as a standalone website from a users prospective
 * No need to duplicate existing code as all subsites use the same codebase as the main site
 * You can set individual permissions on each subsite domain name
 * Ability to copy a page and its content from the main site into a subsite
 * Create translations of subsite pages
 * Schedule the publishing of subsite pages
 * The database is shared between subsites (meaning duplicating content is easy)

## Limitations:
 * Each subsite domain name has to be set up on the server first, and DNS records need to be updated as appropriate.
 * A subsite cannot use a different codebase as the main site, they are intrinsically tied
   * However, you can remove page types from a subsite when creating the subsite - [see the setup documentation for further details](set_up.md)
 * The only code a developer can edit between subsites is the theme
 * All subsites run in the same process space and data set. Therefore if an outage affects one subsite it will affect all subsites, and if bad code or hardware corrupts one subsite's data, it's very likely that it has corrupted all subsite data. It is not currently possible to backup or restore the data from a single subsite. On the other hand, when recovering from a disaster it's much easier to bring up a new copy of a single environment with 100 subsites than it is to bring up 100 environments. This principle applies to application error, security vulnerabilities, or high levels of traffic - this will be experienced across all subsites.
 * It is awkward (but not impossible) to have separate teams of developers working on different subsites - primarily because of the level of collaboration needed. It is more suited to the same group of developers being responsible for all of the subsites.

If more isolation of code, security, or performance is needed, then consider running multiple separate installations (e.g. on separate servers).

*This document assumes that you have full admin rights for your site.*

## Further Documentation
 1. [Setting up subsites](set_up.md)
 2. [Working with subsites](working_with.md)