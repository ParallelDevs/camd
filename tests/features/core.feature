
Feature: Drupal 8 core profile verification.

  Scenario: Ensure the Login link is available for anonymous users.
    Given I am an anonymous user
    When I visit "/user"
    Then I should see an "input#edit-name" element
    And I should see an "input#edit-pass" element

  @api
  Scenario: Ensure as a logged in user, I can log out.
    Given I am logged in as a user with the "authenticated user" role
    When I click "Log out"
    Then I should be on "/en"

  @api
  Scenario: Ensure the Reports page is available when the Reports menu item is
  clicked.
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/reports/status"
    Then I should see "General System Information" in the ".block-system-main-block" element

  @wip
  Scenario: This is a broken test scenario, which should be excluded with the
  example Gruntconfig.json due to the wip tag.
    Given I am logged in as a user with the "xyz" role
    When I am on the xyz page
    And I click "Xyz"
    Then I should be on "abc"
