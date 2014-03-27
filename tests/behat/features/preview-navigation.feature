Feature: Preview navigation
  As a CMS user
  I can navigate a subsite in the preview pane
  In order to preview my content

  Background:
    Given a "subsite" "My subsite"
    And a "page" "My page" with "URLSegment"="my-page", "Content"="My page content <a name='aname'>aname</a><a href='other-page'>ahref</a>" and "Subsite"="=>Subsite.My subsite"
    And a "page" "Other page" with "URLSegment"="other-page", "Content"="Other page content <form action='my-page'><input type='submit' value='Submit my form'></form>" and "Subsite"="=>Subsite.My subsite"
    Given a "member" "Joe" belonging to "Admin Group" with "Email"="joe@test.com" and "Password"="Password1"
    And the "group" "Admin Group" has permissions "Full administrative rights"
    And I log in with "joe@test.com" and "Password1"

  Scenario: I can navigate the subsite preview
    When I go to "admin"
    And I select "My subsite" from "SubsitesSelect"
    And I go to "admin/pages"
    And I click on "My page" in the tree
    And I wait for 3 seconds
    And I set the CMS mode to "Preview mode"
    And I follow "ahref" in preview
    Then the preview contains "Other page content"
    # We are already on the second page, submit the form to return to first one.
    When I wait for 3 seconds
    And I press "Submit my form" in preview
    Then the preview contains "My page content"
