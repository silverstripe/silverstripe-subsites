@javascript
Feature: Select a subsite
  As a CMS user
  I want to be able to select a subsite
  So that I can edit content for a specific subsite

  Background:
    Given a "subsite" "Subsite B"
    And a "page" "My page" with "URLSegment"="my-page", "Content"="My page content"
    And I am logged in with "ADMIN" permissions
    Then I go to "admin/pages"

  Scenario: Default site contains default pages
    When I select "Main site" from "SubsitesSelect"
    And I go to "admin/pages"
    Then I should see "My page" in the tree

  Scenario: I can switch to another subsite
    When I select "Subsite B" from "SubsitesSelect"
    And I go to "admin/pages"
    Then I should not see "My page" in the tree
