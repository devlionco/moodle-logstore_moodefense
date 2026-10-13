@logstore @logstore_moodefense
Feature: MooDefense dashboard
  In order to get an overview of the site's security
  As an administrator
  I need to see issue statistics and the latest issues

  @javascript @accessibility
  Scenario: The dashboard lists the most severe latest issues
    Given the following "logstore_moodefense > issues" exist:
      | algorithm | severity | message              |
      | 7         | Medium   | Guessing from abroad |
      | 4         | High     | Many IP addresses    |
    And I log in as "admin"
    When I visit "/admin/tool/log/store/moodefense/dashboard.php"
    Then I should see "Count of Issues by Severity"
    And the page should meet accessibility standards
    And I should see "Count of Users By Role"
    And "Many IP addresses" "table_row" should appear before "Guessing from abroad" "table_row"
