@logstore @logstore_moodefense
Feature: Review MooDefense issues
  In order to respond to suspicious activity
  As an administrator
  I need to browse, filter and triage the detected issues

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "logstore_moodefense > issues" exist:
      | algorithm | user     | severity | message                    | data               |
      | 7         | student1 | High     | Password guessing on Sam   | {"ip":"10.0.0.1"}  |
      | 4         | student1 | Medium   | Many IP addresses for Sam  | {"ips":["10.0.0.2","10.0.0.3"]} |

  Scenario: Filter issues by severity and IP address
    Given I log in as "admin"
    When I visit "/admin/tool/log/store/moodefense/issues.php"
    Then I should see "Password guessing on Sam"
    And I should see "Many IP addresses for Sam"
    And I set the field "Severity" to "High"
    And I click on "Search" "button" in the "#filter-panel" "css_element"
    And I should see "Password guessing on Sam"
    And I should not see "Many IP addresses for Sam"
    And I visit "/admin/tool/log/store/moodefense/issues.php?filterip=10.0.0.3"
    And I should see "Many IP addresses for Sam"
    And I should not see "Password guessing on Sam"

  @javascript @accessibility
  Scenario: Resolve an issue from its details dialogue
    Given I log in as "admin"
    And I visit "/admin/tool/log/store/moodefense/issues.php"
    And the page should meet accessibility standards
    When I click on "View Details" "button" in the "Password guessing on Sam" "table_row"
    And I should see "Aggressive Password Guessing" in the ".modal-dialog" "css_element"
    And I set the field "statusSelect" to "Resolved"
    And I set the field "commentText" to "Blocked at the firewall"
    And I click on "Save" "button" in the ".modal-dialog" "css_element"
    Then I should see "Resolved" in the "Password guessing on Sam" "table_row"
    And I should see "Blocked at the firewall" in the "Password guessing on Sam" "table_row"
    And I should see "Opened" in the "Many IP addresses for Sam" "table_row"

  @javascript
  Scenario: Country codes are added to the IP addresses of an open details dialogue
    Given the following "logstore_moodefense > issues" exist:
      | algorithm | user     | severity | message               | data                                                       |
      | 4         | student1 | High     | Four addresses for Sam | {"ips":["81.2.69.160","10.0.1.2","10.0.1.3","175.16.199.0"]} |
    And the GeoIP2 test database is used
    And I log in as "admin"
    And I visit "/admin/tool/log/store/moodefense/issues.php"
    When I click on "View Details" "button" in the "Four addresses for Sam" "table_row"
    Then I should see "81.2.69.160 (GB)" in the ".modal-dialog" "css_element"
    And I should see "10.0.1.2" in the ".modal-dialog" "css_element"
    And I should not see "175.16.199.0" in the ".modal-dialog" "css_element"
    And I click on "1 more" "button" in the ".modal-dialog" "css_element"
    And I should see "175.16.199.0 (CN)" in the ".modal-dialog" "css_element"
    And I click on "less" "button" in the ".modal-dialog" "css_element"
    And I should not see "175.16.199.0" in the ".modal-dialog" "css_element"

  @javascript
  Scenario: The details dialogue only shows the fields of the issue's incident type
    Given the following "logstore_moodefense > issues" exist:
      | algorithm | severity | message                 | data                    |
      | 10        | High     | MSA-26-0042: Blind SQLi | {"id":"MSA-26-0042","cve_identifier":"CVE-2026-0042","link":"https://moodle.org/mod/forum/discuss.php?d=1"} |
    And I log in as "admin"
    And I visit "/admin/tool/log/store/moodefense/issues.php"
    When I click on "View Details" "button" in the "MSA-26-0042: Blind SQLi" "table_row"
    Then I should see "Moodle Security Announcement Match" in the ".modal-dialog" "css_element"
    And I should see "CVE-2026-0042" in the ".modal-dialog" "css_element"
    And "https://moodle.org/mod/forum/discuss.php?d=1" "link" should exist in the ".modal-dialog" "css_element"
    And I should not see "User Agent" in the ".modal-dialog" "css_element"
    And I should not see "IPs" in the ".modal-dialog" "css_element"
    And I should not see "Profile link" in the ".modal-dialog" "css_element"
    And I click on "Close" "button" in the ".modal-dialog" "css_element"
    And I click on "View Details" "button" in the "Password guessing on Sam" "table_row"
    And I should see "User Agent" in the ".modal-dialog" "css_element"
    And I should see "10.0.0.1" in the ".modal-dialog" "css_element"
