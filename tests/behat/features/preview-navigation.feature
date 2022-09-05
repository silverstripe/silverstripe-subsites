Feature: Preview navigation
  As a CMS user
  I can navigate a subsite in the preview pane
  In order to preview my content

  Background:
    Given a "subsite" "MySubsite"
    And a "page" "My page" with "URLSegment"="my-page", "Content"="My page content <a name='aname'>aname</a> <a href='[sitetree_link,id=5]'>ahref</a>" and "SubsiteID"="1"
    And a "page" "Other page" with "URLSegment"="other-page", "Content"="Other page content <a href='[sitetree_link,id=4]'>Goto my page</a>" and "SubsiteID"="1"
    And the "group" "EDITOR" has permissions "Access to 'Pages' section" and "Access to 'Subsites' section"
    And I am logged in as a member of "EDITOR" group

  @javascript
  Scenario: I can navigate the subsite preview
    When I go to "/admin/pages"
    And I select "MySubsite" from "SubsitesSelect"
    And I click on "My page" in the tree
    And I press the "Publish" button
    And I click on "Other page" in the tree
    And I press the "Publish" button
    And I click on "My page" in the tree
    And I set the CMS mode to "Preview mode"
    And I follow "ahref" in preview
    And I wait for 1 second
    Then the preview contains "Other page content"
    # We are already on the second page, follow a link to return to first one.
    And I follow "Goto my page" in preview
    And I wait for 1 second
    Then the preview contains "My page content"
