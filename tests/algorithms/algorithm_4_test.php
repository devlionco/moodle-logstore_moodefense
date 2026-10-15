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

#[CoversClass(algorithm_4::class)]
/**
 * Tests for algorithm 4: user activity from multiple IP addresses.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \logstore_moodefense\algorithms\algorithm_4
 */
final class algorithm_4_test extends \advanced_testcase {
    /** @var \logstore_moodefense_generator */
    private $generator;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->redirectMessages();
        set_config('enabled_stores', 'logstore_moodefense', 'tool_log');
        $this->generator = $this->getDataGenerator()->get_plugin_generator('logstore_moodefense');
    }

    /**
     * Logs one event per IP address 10.0.0.1 .. 10.0.0.$count for the user.
     *
     * @param int $userid
     * @param int $count
     * @param int $timecreated
     */
    private function log_from_ips(int $userid, int $count, int $timecreated = 0): void {
        for ($i = 1; $i <= $count; $i++) {
            $this->generator->create_log([
                'userid' => $userid,
                'eventname' => '\core\event\course_viewed',
                'ip' => "10.0.0.$i",
                'timecreated' => $timecreated ?: time() - 60,
            ]);
        }
    }

    public function test_many_ips_raise_issue_listing_all_of_them(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        // More addresses than MySQL's default GROUP_CONCAT limit of 1024 bytes would hold.
        $this->log_from_ips($user->id, 100);

        (new algorithm_4())->run();

        $issue = $DB->get_record('logstore_moodefense_issues', ['algorithm' => 4], '*', MUST_EXIST);
        $this->assertEquals($user->id, $issue->userid);
        $this->assertSame('High', $issue->severity);
        $data = json_decode($issue->data);
        $this->assertCount(100, $data->ips);
        $this->assertContains('10.0.0.100', $data->ips);
    }

    public function test_fewer_ips_raise_nothing(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->log_from_ips($user->id, algorithm_4::MIN_DISTINCT_IPS - 1);

        (new algorithm_4())->run();

        $this->assertFalse($DB->record_exists('logstore_moodefense_issues', []));
    }

    public function test_anonymous_and_guest_activity_is_ignored(): void {
        global $CFG, $DB;
        $this->log_from_ips(0, algorithm_4::MIN_DISTINCT_IPS);
        $this->log_from_ips($CFG->siteguest, algorithm_4::MIN_DISTINCT_IPS);

        (new algorithm_4())->run();

        $this->assertFalse($DB->record_exists('logstore_moodefense_issues', []));
    }

    public function test_several_user_agents_update_one_issue(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        foreach (['Agent A', 'Agent B'] as $agent) {
            for ($i = 1; $i <= algorithm_4::MIN_DISTINCT_IPS; $i++) {
                $this->generator->create_log(['userid' => $user->id, 'ip' => "10.0.1.$i", 'useragent' => $agent]);
            }
        }

        (new algorithm_4())->run();

        $this->assertEquals(1, $DB->count_records('logstore_moodefense_issues', ['algorithm' => 4]));
    }
}
