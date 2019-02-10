# See https://github.com/silverstripe/silverstripe-subsites/issues/357
Feature: Insert an internal link into content
  As a CMS user
  I can insert internal links into my content
  So that I can direct users to different parts of my website

  Background:
    Given a "subsite" "Subsite B"
    And a "page" "My page" with "URLSegment"="my-page", "Content"="My page content"
    And a "page" "Another page" with "URLSegment"="another-page", "Content"="My other page content"
    And I am logged in with "CMS_ACCESS_CMSMain" permissions
    Then I go to "admin/pages"
    And I click on "My page" in the tree

  @javascript
  Scenario: I can insert an internal link
    # See "insert-a-link.feature" from silverstripe/cms
    When I select "My page" in the "Content" HTML field
    And I press the "Insert link" HTML field button
    And I click "Page on this site" in the ".mce-menu" element
    Then I should see an "form#Form_editorInternalLink" element
    When I click "(Choose Page)" in the ".Select-multi-value-wrapper" element
    And I click "Another page" in the ".treedropdownfield__menu" element
    And I fill in "my desc" for "Link description"
    And I press the "Insert" button
    Then the "Content" HTML field should contain "<a title="my desc" href="[sitetree_link"
    And the "Content" HTML field should contain "My page</a>"
    # Required to avoid "unsaved changes" browser dialog
    Then I press the "Save" button
