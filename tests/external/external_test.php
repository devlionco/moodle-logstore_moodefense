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

namespace logstore_moodefense\external;

use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the plugin's web services.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(save_modal_data::class)]
#[CoversClass(clear_table::class)]
#[CoversClass(get_country_code::class)]
#[CoversClass(test_email::class)]
final class external_test extends \advanced_testcase {
    /** @var \logstore_moodefense_generator */
    private $generator;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('logstore_moodefense');
    }

    public function test_save_modal_data_updates_status_and_comment(): void {
        global $DB;
        $this->setAdminUser();
        $issue = $this->generator->create_issue(['comment' => 'old']);
        $events = $this->redirectEvents();

        $result = external_api::clean_returnvalue(
            save_modal_data::execute_returns(),
            save_modal_data::execute($issue->id, 2, '  Checked, false alarm ')
        );

        $this->assertTrue($result['success']);
        $record = $DB->get_record('logstore_moodefense_issues', ['id' => $issue->id]);
        $this->assertEquals(2, $record->status);
        $this->assertSame('Checked, false alarm', $record->comment);
        $this->assertInstanceOf(\logstore_moodefense\event\issue_updated::class, $events->get_events()[0]);
    }

    public function test_save_modal_data_rejects_unknown_status(): void {
        $this->setAdminUser();
        $issue = $this->generator->create_issue();

        $this->expectException(\invalid_parameter_exception::class);
        save_modal_data::execute($issue->id, 99, '');
    }

    public function test_save_modal_data_requires_manage_capability(): void {
        $issue = $this->generator->create_issue();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        save_modal_data::execute($issue->id, 2, '');
    }

    public function test_clear_table_removes_all_issues(): void {
        global $DB;
        $this->setAdminUser();
        $this->generator->create_issue();
        $this->generator->create_issue(['algorithm' => 4]);
        $events = $this->redirectEvents();

        $this->assertTrue(clear_table::execute()['success']);
        $this->assertEquals(0, $DB->count_records('logstore_moodefense_issues'));
        $this->assertSame(2, $events->get_events()[0]->other['count']);
    }

    public function test_clear_table_requires_manage_capability(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        clear_table::execute();
    }

    public function test_get_country_code(): void {
        global $CFG;
        $this->setAdminUser();
        $CFG->geoip2file = "$CFG->dirroot/iplookup/tests/fixtures/GeoIP2-City-Test.mmdb";

        $this->assertSame(['success' => true, 'countrycode' => 'GB'], get_country_code::execute('81.2.69.160'));
        $this->assertFalse(get_country_code::execute('10.0.0.2')['success']);
        $this->assertFalse(get_country_code::execute('nonsense')['success']);
    }

    public function test_test_email_is_sent(): void {
        $this->setAdminUser();
        $sink = $this->redirectEmails();

        $result = test_email::execute('admin-test@example.com');

        $this->assertTrue($result['success']);
        $this->assertSame('admin-test@example.com', $sink->get_messages()[0]->to);
    }
}
