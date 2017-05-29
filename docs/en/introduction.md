# Subsites

[User guide](userguide/index.md)

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
