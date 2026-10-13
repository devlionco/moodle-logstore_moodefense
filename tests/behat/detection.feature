@logstore @logstore_moodefense
Feature: Detect password guessing
  In order to be warned about brute-force attacks
  As an administrator
  I need repeated failed logins to show up as an issue

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following config values are set as admin:
      | enabled_stores | logstore_moodefense | tool_log            |
      | algorithms     | 2,4,5,7             | logstore_moodefense |

  Scenario: Repeated failed logins raise an Aggressive Password Guessing issue
    When I fail to log in as "student1" 50 times
    And I run the scheduled task "\logstore_moodefense\task\processing_task"
    And I log in as "admin"
    And I visit "/admin/tool/log/store/moodefense/issues.php"
    Then I should see "Aggressive Password Guessing"
    And I should see "Medium" in the "Aggressive Password Guessing" "table_row"
    And I should see "Sam Student" in the "Aggressive Password Guessing" "table_row"

  @javascript @accessibility
  Scenario: Choose what is detected and what notifies; premium incident types explain how to get them
    Given I log in as "admin"
    When I navigate to "Plugins > Logging > MooDefense" in site administration
    And the "[id='id_s_logstore_moodefense_algorithms']" "css_element" should meet accessibility standards
    And I set the field "Notify about User Activity from Multiple IP Addresses" to "0"
    And I set the field "Detect Aggressive Password Guessing" to "0"
    Then the "Notify about Aggressive Password Guessing" "field" should be disabled
    And I press "Save changes"
    And the field "Detect Aggressive Password Guessing" matches value "0"
    And the field "Detect User Activity from Multiple IP Addresses" matches value "1"
    And the field "Notify about User Activity from Multiple IP Addresses" matches value "0"
    And the field "Notify about Admin Login from an Unauthorized Country" matches value "1"
    And I click on "Premium" "button" in the "Staff Activity Without Prior Login" "table_row"
    And I should see "MooDefense Premium" in the ".modal-dialog" "css_element"
