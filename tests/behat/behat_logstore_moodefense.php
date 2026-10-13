<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../../../lib/behat/behat_base.php');

/**
 * Behat steps for logstore_moodefense.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_logstore_moodefense extends behat_base {
    /**
     * Submits the login form with a wrong password, the given number of times.
     *
     * The logins go through the browser, so they are recorded like real (non-CLI) requests.
     *
     * @When /^I fail to log in as "(?P<username_string>(?:[^"]|\\")*)" (?P<count_number>\d+) times$/
     * @param string $username
     * @param int $count
     */
    public function i_fail_to_log_in_times(string $username, int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $this->execute('behat_general::i_visit', ['/login/index.php']);
            $this->execute('behat_forms::i_set_the_field_to', ['Username', $username]);
            $this->execute('behat_forms::i_set_the_field_to', ['Password', 'wrong-password']);
            $this->execute('behat_forms::press_button', ['Log in']);
        }
    }

    /**
     * Makes Moodle look IP addresses up in core's GeoIP2 test database, in which 81.2.69.160 is in
     * the United Kingdom (GB) and 175.16.199.0 in China (CN).
     *
     * @Given /^the GeoIP2 test database is used$/
     */
    public function the_geoip2_test_database_is_used(): void {
        global $CFG;
        set_config('geoip2file', "$CFG->dirroot/iplookup/tests/fixtures/GeoIP2-City-Test.mmdb");
    }
}
