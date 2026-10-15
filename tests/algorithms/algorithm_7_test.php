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

#[CoversClass(algorithm_7::class)]
#[CoversClass(algorithm::class)]
/**
 * Tests for algorithm 7: aggressive password guessing.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \logstore_moodefense\algorithms\algorithm_7
 * @covers \logstore_moodefense\algorithms\algorithm
 */
final class algorithm_7_test extends \advanced_testcase {
    /** @var \logstore_moodefense_generator */
    private $generator;

    /** @var \phpunit_message_sink */
    private $sink;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->sink = $this->redirectMessages();
        set_config('enabled_stores', 'logstore_moodefense', 'tool_log');
        set_config('algorithms_notifications', '7', 'logstore_moodefense');
        set_config('emailstosendnotification', '', 'logstore_moodefense');
        $this->generator = $this->getDataGenerator()->get_plugin_generator('logstore_moodefense');
    }

    /**
     * Logs failed login attempts.
     *
     * @param int $userid Targeted account, 0 for an unknown username.
     * @param string $ip
     * @param int $count
     */
    private function fail_logins(int $userid, string $ip, int $count = algorithm_7::MIN_FAILED_ATTEMPTS): void {
        for ($i = 0; $i < $count; $i++) {
            $this->generator->create_log([
                'userid' => $userid,
                'eventname' => '\core\event\user_login_failed',
                'ip' => $ip,
                'timecreated' => time() - 120 + $i,
            ]);
        }
    }

    public function test_repeated_failures_raise_medium_issue_and_notify(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->fail_logins($user->id, '10.0.0.1');

        (new algorithm_7())->run();

        $issue = $DB->get_record('logstore_moodefense_issues', ['algorithm' => 7], '*', MUST_EXIST);
        $this->assertEquals($user->id, $issue->userid);
        $this->assertSame('Medium', $issue->severity);
        $this->assertSame(algorithm_7::MIN_FAILED_ATTEMPTS, json_decode($issue->data)->attempt_count);
        $this->assertCount(1, $this->sink->get_messages_by_component('logstore_moodefense'));
    }

    public function test_few_failures_raise_nothing(): void {
        global $DB;
        $this->fail_logins(0, '10.0.0.1', algorithm_7::MIN_FAILED_ATTEMPTS - 1);

        (new algorithm_7())->run();

        $this->assertFalse($DB->record_exists('logstore_moodefense_issues', []));
    }

    public function test_unknown_username_failures_raise_medium_issue(): void {
        global $DB;
        $this->fail_logins(0, '10.0.0.1');

        (new algorithm_7())->run();

        $issue = $DB->get_record('logstore_moodefense_issues', ['algorithm' => 7], '*', MUST_EXIST);
        $this->assertEquals(0, $issue->userid);
        $this->assertSame('Medium', $issue->severity);
        $this->assertSame(get_string('bruteforceunknownuser', 'logstore_moodefense', ['ip' => '10.0.0.1']), $issue->message);
    }

    public function test_success_after_failures_escalates_to_high_and_notifies_again(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->fail_logins($user->id, '10.0.0.1');
        (new algorithm_7())->run();

        $this->generator->create_log(['userid' => $user->id, 'ip' => '10.0.0.1']);
        (new algorithm_7())->run();

        $issues = $DB->get_records('logstore_moodefense_issues', ['algorithm' => 7]);
        $this->assertCount(1, $issues);
        $this->assertSame('High', reset($issues)->severity);
        $this->assertCount(2, $this->sink->get_messages_by_component('logstore_moodefense'));
    }

    public function test_rerun_without_new_attempts_keeps_resolved_issue_and_is_silent(): void {
        global $DB;
        $this->fail_logins(0, '10.0.0.1');
        (new algorithm_7())->run();
        $DB->set_field('logstore_moodefense_issues', 'status', status::RESOLVED->value, []);
        $this->sink->clear();

        (new algorithm_7())->run();

        $this->assertEquals(status::RESOLVED->value, $DB->get_field('logstore_moodefense_issues', 'status', []));
        $this->assertCount(0, $this->sink->get_messages());
    }

    public function test_new_attempts_reopen_resolved_issue_within_cooldown_silently(): void {
        global $DB;
        $this->fail_logins(0, '10.0.0.1');
        (new algorithm_7())->run();
        $DB->set_field('logstore_moodefense_issues', 'status', status::RESOLVED->value, []);
        $DB->set_field('logstore_moodefense_issues', 'timecreated', time() - 600, []);
        $this->sink->clear();

        $this->fail_logins(0, '10.0.0.1', 1);
        (new algorithm_7())->run();

        $this->assertEquals(status::OPENED->value, $DB->get_field('logstore_moodefense_issues', 'status', []));
        $this->assertCount(0, $this->sink->get_messages());
    }

    public function test_ip_addresses_sharing_a_prefix_are_separate_incidents(): void {
        global $DB;
        $this->fail_logins(0, '10.0.0.1');
        (new algorithm_7())->run();
        $this->fail_logins(0, '10.0.0.12');
        (new algorithm_7())->run();

        $this->assertEquals(2, $DB->count_records('logstore_moodefense_issues', ['algorithm' => 7]));
    }
}
