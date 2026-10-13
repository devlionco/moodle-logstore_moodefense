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

use logstore_moodefense\local\status;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for algorithm 2: staff and student login from the same device.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(algorithm_2::class)]
#[CoversClass(algorithm::class)]
final class algorithm_2_test extends \advanced_testcase {
    /** @var \logstore_moodefense_generator */
    private $generator;

    /** @var \stdClass */
    private $teacher;

    /** @var \stdClass */
    private $student;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->redirectMessages();
        set_config('enabled_stores', 'logstore_moodefense', 'tool_log');

        $this->generator = $this->getDataGenerator()->get_plugin_generator('logstore_moodefense');
        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        set_config('staffroles', $teacherrole, 'logstore_moodefense');

        $course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($course, 'student');
    }

    /**
     * Logs in both users from the same IP and user agent.
     *
     * @param int $timecreated
     */
    private function log_shared_device(int $timecreated): void {
        foreach ([$this->student, $this->teacher] as $user) {
            $this->generator->create_log(['userid' => $user->id, 'ip' => '10.1.1.1', 'timecreated' => $timecreated]);
        }
    }

    public function test_shared_device_raises_issue_for_staff_member(): void {
        global $DB;
        $this->log_shared_device(time() - 60);

        (new algorithm_2())->run();

        $issues = $DB->get_records('logstore_moodefense_issues', ['algorithm' => 2]);
        $this->assertCount(1, $issues);
        $issue = reset($issues);
        $this->assertEquals($this->teacher->id, $issue->userid);
        $this->assertSame('High', $issue->severity);
        $this->assertEquals(status::OPENED->value, $issue->status);
        $this->assertStringContainsString('editingteacher', $issue->roles);
        $data = json_decode($issue->data);
        $this->assertSame((int)$this->student->id, $data->suspecteduid);
        $this->assertSame('10.1.1.1', $data->ip);
    }

    public function test_every_other_user_of_the_device_is_reported(): void {
        global $DB;
        $other = $this->getDataGenerator()->create_user();
        $this->log_shared_device(time() - 60);
        $this->generator->create_log(['userid' => $other->id, 'ip' => '10.1.1.1']);

        (new algorithm_2())->run();

        $suspects = $DB->get_fieldset_select('logstore_moodefense_issues', 'issuekey', 'algorithm = 2');
        $this->assertEqualsCanonicalizing([(string)$this->student->id, (string)$other->id], $suspects);
    }

    public function test_two_students_on_one_device_raise_nothing(): void {
        global $DB;
        $other = $this->getDataGenerator()->create_user();
        foreach ([$this->student, $other] as $user) {
            $this->generator->create_log(['userid' => $user->id, 'ip' => '10.1.1.1']);
        }

        (new algorithm_2())->run();

        $this->assertFalse($DB->record_exists('logstore_moodefense_issues', []));
    }

    public function test_logins_outside_period_are_ignored(): void {
        global $DB;
        $this->log_shared_device(time() - algorithm_2::PERIOD - 60);

        (new algorithm_2())->run();

        $this->assertFalse($DB->record_exists('logstore_moodefense_issues', []));
    }

    public function test_rerun_keeps_single_issue_and_does_not_reopen_it(): void {
        global $DB;
        $this->log_shared_device(time() - 60);
        (new algorithm_2())->run();
        $issue = $DB->get_record('logstore_moodefense_issues', ['algorithm' => 2]);
        $DB->set_field('logstore_moodefense_issues', 'status', status::RESOLVED->value, ['id' => $issue->id]);

        (new algorithm_2())->run();

        $this->assertEquals(1, $DB->count_records('logstore_moodefense_issues'));
        $status = $DB->get_field('logstore_moodefense_issues', 'status', ['id' => $issue->id]);
        $this->assertEquals(status::RESOLVED->value, $status);
    }

    public function test_existing_issue_for_other_suspect_with_same_id_prefix_is_not_reused(): void {
        global $DB;
        // A suspect whose id merely starts with the student's id must not count as the same incident.
        $this->generator->create_issue([
            'algorithm' => 2,
            'userid' => $this->teacher->id,
            'data' => [
                'ip' => '10.9.9.9',
                'suspecteduid' => (int)($this->student->id . '7'),
                'targetuid' => (int)$this->teacher->id,
            ],
            'timecreated' => time() - HOURSECS,
        ]);
        $this->log_shared_device(time() - 60);

        (new algorithm_2())->run();

        $this->assertEquals(2, $DB->count_records('logstore_moodefense_issues', ['algorithm' => 2]));
    }
}
