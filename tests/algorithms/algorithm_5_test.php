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

namespace logstore_moodefense\algorithms;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for algorithm 5: admin login from an unauthorized country.
 *
 * Country lookups are served from the geoip cache primed by each test, so no GeoIP database or
 * Moodefense API is involved.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(algorithm_5::class)]
final class algorithm_5_test extends \advanced_testcase {
    /** @var \logstore_moodefense_generator */
    private $generator;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->redirectMessages();
        set_config('enabled_stores', 'logstore_moodefense', 'tool_log');
        set_config('permittedgeolocation', 'IL, us', 'logstore_moodefense');
        $this->generator = $this->getDataGenerator()->get_plugin_generator('logstore_moodefense');

        // Core's test database: 81.2.69.160 is in the United Kingdom, 216.160.83.56 in the US.
        $CFG->geoip2file = "$CFG->dirroot/iplookup/tests/fixtures/GeoIP2-City-Test.mmdb";
    }

    public function test_admin_login_from_unpermitted_country_raises_issue(): void {
        global $DB;
        $admin = get_admin();
        $this->generator->create_log(['userid' => $admin->id, 'ip' => '81.2.69.160']);

        (new algorithm_5())->run();

        $issue = $DB->get_record('logstore_moodefense_issues', ['algorithm' => 5], '*', MUST_EXIST);
        $this->assertEquals($admin->id, $issue->userid);
        $this->assertStringContainsString(get_string_manager()->get_list_of_countries(true)['GB'], $issue->message);
        $this->assertStringStartsWith('admin', $issue->roles);
        $this->assertSame('GB', json_decode($issue->data)->countryCode);
    }

    public function test_admin_login_from_permitted_country_raises_nothing(): void {
        global $DB;
        $this->generator->create_log(['userid' => get_admin()->id, 'ip' => '216.160.83.56']);

        (new algorithm_5())->run();

        $this->assertFalse($DB->record_exists('logstore_moodefense_issues', []));
    }

    public function test_non_admin_login_is_ignored(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->generator->create_log(['userid' => $user->id, 'ip' => '81.2.69.160']);

        (new algorithm_5())->run();

        $this->assertFalse($DB->record_exists('logstore_moodefense_issues', []));
    }
}
