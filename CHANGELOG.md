# Changelog

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](http://semver.org/).

## [2.0.0 (unreleased)]

* Updating to be compatible with SilverStripe 4
* Subsite specific theme is now added to default theme, as themes are now cascadable
* Global subsite information moved to injectable `SubsiteState` singleton service
* `FileExtension:::default_root_folders_global` converted to a configuration property
* `Subsite::$check_is_public` converted to a configuration property
* `Subsite::$strict_subdomain_matching` converted to a configuration property
* `Subsite::$force_subsite` deprecated and will be removed in future -  use `SubsiteState::singleton()->withState()` instead
* `Subsite::$write_hostmap` converted to a configuration property
* `Subsite::$allowed_themes` made protected

## [1.2.3]

* BUG Fix issue with urlsegment being renamed in subsites

## [1.2.2]

* Update translations.
* Added attributes to menu link

## [1.2.1]

* BUG: The move to subsite folder dropdown in files is gone
* Update templates for 3.3 compatibility
* Update userhelp documentation
* Fix Subsite module does not picks up themes
* Update translations

## [1.2.0]

* API Add option to specify http / https on subsite domains

## [1.1.0]

* Changelog added.
* Fixes #135: LeftAndMain switching between subsites
* BUG Fix incompatibility with framework 3.2
* Adjusted tests to new SiteTree->canCreate() logic in 3.1.11+
* Fix subsites to use correct permissions
* Wrong edit link in SubsitesVirtualPage
* Added missing route to `SubsiteXHRController` for SilverStripe 3.2 compatibility.
* Add sticky nav toggle button
* BUG Subsites selection on SubsitesVirtualPage (fixes #45 and #47)
* Update translations
