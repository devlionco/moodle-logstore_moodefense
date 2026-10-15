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

use logstore_moodefense\task\cleanup_task;
use logstore_moodefense\task\processing_task;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(process::class)]
#[CoversClass(processing_task::class)]
#[CoversClass(cleanup_task::class)]
/**
 * Tests for the processing run and the scheduled tasks around it.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \logstore_moodefense\process
 * @covers \logstore_moodefense\processing_task
 * @covers \logstore_moodefense\cleanup_task
 */
final class process_test extends \advanced_testcase {
    /** @var \logstore_moodefense_generator */
    private $generator;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->redirectMessages();
        set_config('enabled_stores', 'logstore_moodefense', 'tool_log');
        $this->generator = $this->getDataGenerator()->get_plugin_generator('logstore_moodefense');
    }

    public function test_free_algorithms_run_without_api(): void {
        global $DB;
        set_config('algorithms', implode(',', array_column(local\algorithm_type::cases(), 'value')), 'logstore_moodefense');
        for ($i = 0; $i < algorithms\algorithm_7::MIN_FAILED_ATTEMPTS; $i++) {
            $this->generator->create_log(['eventname' => '\core\event\user_login_failed', 'ip' => '10.0.0.9']);
        }

        $this->expectOutputRegex('/Running algorithm 7/');
        (new processing_task())->execute();

        $this->assertTrue($DB->record_exists('logstore_moodefense_issues', ['algorithm' => 7]));
    }

    public function test_premium_algorithm_ids_left_in_the_setting_are_ignored(): void {
        global $DB;
        set_config('algorithms', '1,7,9,10', 'logstore_moodefense');
        for ($i = 0; $i < algorithms\algorithm_7::MIN_FAILED_ATTEMPTS; $i++) {
            $this->generator->create_log(['eventname' => '\core\event\user_login_failed', 'ip' => '10.0.0.9']);
        }

        $this->expectOutputRegex('/^(?!.*failed).*Running algorithm 7/s');
        (new processing_task())->execute();

        $this->assertEquals([7], $DB->get_fieldset_select('logstore_moodefense_issues', 'DISTINCT algorithm', '1 = 1'));
    }

    public function test_processing_is_skipped_when_store_is_disabled(): void {
        global $DB;
        set_config('enabled_stores', '', 'tool_log');
        set_config('algorithms', '7', 'logstore_moodefense');
        for ($i = 0; $i < algorithms\algorithm_7::MIN_FAILED_ATTEMPTS; $i++) {
            $this->generator->create_log(['eventname' => '\core\event\user_login_failed']);
        }

        $this->expectOutputRegex('/Please enable logging MooDefense/');
        (new processing_task())->execute();

        $this->assertFalse($DB->record_exists('logstore_moodefense_issues', []));
    }

    public function test_cleanup_deletes_logs_past_lifetime(): void {
        global $DB;
        set_config('loglifetime', 10, 'logstore_moodefense');
        $this->generator->create_log(['timecreated' => time() - 11 * DAYSECS]);
        $recent = $this->generator->create_log(['timecreated' => time() - 9 * DAYSECS]);

        $this->expectOutputRegex('/Deleted old log records/');
        (new cleanup_task())->execute();

        $this->assertEquals([$recent->id], array_keys($DB->get_records('logstore_moodefense_log', null, '', 'id')));
    }
}
