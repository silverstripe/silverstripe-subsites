# Setting up

## Creating subsites

To view, edit and create subsites, go to the *Subsite* section of the CMS. Click *Search* to view a list of all
existing subsites.

![View subsites](_images/view-subsites.jpg)

Click on a subsite name to edit its details. Click the red X to delete a subsite (you will be asked for confirmation.)
To create a new subsite, click *Add Subsite*. This opens the *Subsite configuration* subsection.

![Subsite configuration](_images/subsite-configuration.jpg)

You can configure the following details for a subsite:

 * *Name of subsite:* This is the display name for the site in the CMS (not public-facing)
 * *Domains for this subsite:* Lets you add one or more domains for this subsite, e.g., subsite.co.nz, subsite.org.nz,
subsite.com
 * *Language:* Sets the language for the subsite. This affects the spellchecker (not the CMS interface language)
 * *Default site:* If your site is accessed by a domain not listed in any subsites, this subsite is shown as default.
 * *Enable public access:* Enables/disables the subsite. Corresponds to the *Active Subsite* column.
 * *Theme:* Shows the list of available themes that exist in the themes directory. The subsite will use the templates
and styles from the selected theme.
 * *Disallow page types:* Lets you mark some page types to prevent being used within this subsite.
 * *Copy structure from:* Gives you the option to select an existing subsite from which to copy pages and files (see
 "Copying subsites" for more information about this)

## Roles

When creating roles, you can assign the following subsite-specific permissions:

 * *Access to 'Subsites' section:* Shows the *Subsite* section in the CMS, allowing you to manage subsites for your
site (ie, create, edit, view.)
 * *Manage subsites for groups:* Ability to limit the permissions for a group to one or more subsites.
 * *Manage assets for subsites:* Ability to select the subsite to which an asset folder belongs (also requires access to
*Files* section)

## Groups

Groups can have access to all sites, or to one or more specific subsites. If you have different staff in charge of each
subsite, you probably want to create a separate group for each subsite.

The dropdown in the upper left corner of the CMS indicates which subsite you are currently on. 

![Group subsites dropdown](_images/subsites-dropdown.png)

Once you make a selection from the dropdown, you see the appropriate groups in the *Security* section. In the Security section, click a group and go to its *Subsites* tab to assign the subsites to which the group has access. Click *Only
these subsites* to reveal a checklist of all available sites.

![Group subsites access](_images/group-subsites-access.png)

## Copying subsites
Duplicating subsites can be useful if you want to create several subsites based on the same general site structure. You can set up a collection of pages, files, and images and use it as a template. When you create a new subsite, instead of starting from scratch you can copy it all from your existing subsite. This will copy all pages, files and images from that subsite into your new subsite.

To create a new subsite template, create a new subsite described above under "Creating subsites" but don't add any domains. Add a name that will make it easy to see that it is a template. Select the new template from the subsites dropdown in the upper right and create the pages and add the files and images you'd like to become part of the
template.

When you create a new subsite, you can now choose to Copy structure from your template. All your pages, files and images will be copied over to your new subsite.

![Copy subsite structure](_images/copy-structure.jpg)

## Disable particular page types from a subsite
Sometimes, you will have two or more websites that are very similar, but have some small differences. For example, a head office and its 3 satellite offices may have 4 subsites, but only the head office site will have a "Company News" section on the site. In this instance, you can still use the subsites module, and use the 'Disallow page types' ability to remove certain page types from being created on subsites.

To do this, you create a subsite as you normally would, and when editing the subsite, click on the 'Disallow page types?' link. Then, select the page types you wish to remove from this subsite. Note that this process is exactly the same when editing an existing subsite, you just select the subsite you want to remove page types from instead of creating a new subsite.
![Disallow page types screenshot](_images/disallow-page-types.png)

Now, whenever someone wants to create a new page on the subsite (the 'London Branch' subsite in this case), they will not be able to select the page types you've selected, as you can see below. Note that this restriction doesn't affect full administrators, and admins needs to login to the subsite domain directly (e.g. http://london.site.com/admin/, not http://site.com/admin).
![Disallow page types result screenshot](_images/disallow-page-types-result.png)