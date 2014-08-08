## Architecture


Subsites works by creating a Subsites model which stores all the details like Theme and Language for a 
subsite that you create.
It also adds a column to SiteTree called SubsiteID which defaults to 0 for the main site but can be used to link a
page to a particular subsite.


The subsite module adds functionality to the admin section of the site to allow you to create new subsites and copy 
pages between the main site and any subsites.
Subsites makes use of a DataExtension called SiteTreeSubsites to add support for subsites to the SiteTree,
which extends various methods to add Subsite functionality some of the methods are listed below

### augmentSQL
This methods modifies the SiteTree results returned for a Subsite it does this by using the Subsite ID and filtering the
SiteTree via the SubsiteID column on the SiteTree

### onBeforeWrite
This method is used to update the SubsiteID on a SiteTree object when a page is saved and the the current SubsiteID is null.

### updateCMSFields
This method is used to add Subsite related fields to the CMS form for adding and editing SiteTree pages.

### duplicateToSubsite
This method is called when a pages are being copied between the main site or another subsite.

### alternateAbsoluteLink
This method modifies the absolute link to contain the valid subsite domain

### alternatePreviewLink
This method modifies the preview link for the CMS.
