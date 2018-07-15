Feature: Publish a page
  As a CMS user
  I can author pages in a new subsite
  So that I can separate my website content by site

  Background:
    Given a "subsite" "Subsite B"
    And a "page" "My page" with "URLSegment"="my-page", "Content"="My page content"
    And I am logged in with "ADMIN" permissions
    Then I go to "admin/pages"

  @javascript
  Scenario: I can publish a new page
    Given I select "Subsite B" from "SubsitesSelect"
    When I press the "Add new" button
    And I press the "Create" button
    And I set the CMS mode to "Edit mode"
    And I fill in the "Content" HTML field with "<p>Some test content</p>"
    Then I should see a "Publish" button
    And I should not see a "Published" button

    When I press the "Publish" button
    And I wait for 3 seconds
    Then I should see a "Published" button
    And I should see a "Saved" button
