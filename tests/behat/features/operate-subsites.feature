@javascript
Feature: Create and select a subsite
  As a CMS user
  I want to be able to select a subsite
  So that I can edit content for a specific subsite

  Background:
    # There's a bug where you need CMS_ACCESS_CMSMain rather than CMS_ACCESS_LeftAndMain permissions to
    # use subsites as expected
    Given the "group" "EDITOR" has permissions "CMS_ACCESS_CMSMain" and "CMS_ACCESS_AssetAdmin" and "FILE_EDIT_ALL"
    And a "page" "My page" with "URLSegment"="my-page", "Content"="My page content"
    And an "image" "file1.jpg"
    And an "image" "file2.jpg"

  Scenario: I can operate subsites

    # Create subsite as Admin
    Given I am logged in with "ADMIN" permissions
    Then I go to "admin/subsites"

    # Add subsites button is not a regular button, so click using css selector
    And I click on the ".btn-toolbar .btn__title" element
    And I fill in "Subsite Name" with "My subsite"
    And I press "Create"

    # Add a file to the main site
    When I go to "admin/assets"
    And I press the "Add folder" button
    And I select "Main site" from "SubsitesSelect"
    # Using a short folder name so that it doesn't get truncated on the frontend
    And I fill in "Folder name" with "mfol"
    And I press the "Create" button
    When I go to "admin/assets"

    And I click on the file named "mfol" in the gallery
    And I attach the file "file1.jpg" to dropzone "gallery-container"

    # Change to Editor user
    When I go to "/Security/login"
    And I press the "Log in as someone else" button
    When I am logged in as a member of "EDITOR" group
    And I go to "admin/pages"

    # Can see main site page on main site
    When I go to "admin/pages"
    Then I should see "My page" in the tree

    # Cannot see main site page on subsite
    When I select "My subsite" from "SubsitesSelect"
    And I go to "admin/pages"
    Then I should not see "My page" in the tree

    # Create a page on the subsite
    When I press the "Add new" button
    And I select the "Page" radio button
    And I press the "Create" button
    When I fill in "Page name" with "My subsite page"
    And I press the "Publish" button
    Then I should see "My subsite page"

    # Can see main site folders/files from subsite
    When I go to "admin/assets"
    Then I should see "mfol"
    When I click on the file named "mfol" in the gallery
    Then I should see "file1"

    # Add a file to the subsite
    When I go to "admin/assets"
    And I select "My subsite" from "SubsitesSelect"
    And I press the "Add folder" button
    And I fill in "Folder name" with "sfol"
    And I press the "Create" button
    When I go to "admin/assets"
    And I click on the file named "sfol" in the gallery
    And I attach the file "file2.jpg" to dropzone "gallery-container"

    # Change back to main subsite - cannot see subsite folders/files
    When I go to "admin/assets"
    And I select "Main site" from "SubsitesSelect"
    Then I should see "mfol"
    Then I should not see "My subsite page"
