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

namespace logstore_moodefense;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the IP-to-country lookup.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(geoip::class)]
final class geoip_test extends \advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        // Core's test database: 81.2.69.160 is in the United Kingdom.
        $CFG->geoip2file = "$CFG->dirroot/iplookup/tests/fixtures/GeoIP2-City-Test.mmdb";
    }

    public function test_country_is_found_in_the_site_database(): void {
        $result = geoip::lookup('81.2.69.160');

        $this->assertSame('GB', $result['countrycode']);
        $this->assertSame(get_string_manager()->get_list_of_countries(true)['GB'], $result['country']);
    }

    public function test_address_missing_from_the_database_is_unknown(): void {
        $this->assertNull(geoip::lookup('10.0.0.1'));
    }

    public function test_without_a_site_database_the_bundled_one_is_used(): void {
        global $CFG;
        $CFG->geoip2file = '';

        // Google's public DNS resolver: not in core's test database.
        $this->assertSame('US', geoip::lookup('8.8.8.8')['countrycode']);
    }
}
