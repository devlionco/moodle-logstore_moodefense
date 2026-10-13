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
 * Tests for algorithm 10: Moodle security announcement match.
 *
 * The Moodefense API is replaced by curl::mock_response().
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(algorithm_10::class)]
#[CoversClass(\logstore_moodefense\api::class)]
final class algorithm_10_test extends \advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->redirectMessages();
        require_once($CFG->libdir . '/filelib.php');
        set_config('apiurl', 'https://api.example.com/', 'logstore_moodefense');
        set_config('token', 'secret', 'logstore_moodefense');
        // Notifying is covered by notifier_test.
        set_config('algorithms_notifications', '', 'logstore_moodefense');
        $CFG->release = '4.5.2 (Build: 20250210)';
    }

    public function test_announcement_affecting_the_site_raises_one_issue(): void {
        global $DB;
        $announcements = json_encode([
            ['id' => 'MSA-25-0001', 'title' => 'XSS', 'severity' => 'Serious', 'date' => '2025-02-17T00:00:00+00:00',
                'versions_affected_ranges' => [['min' => '4.5', 'max' => '4.5.2']]],
            ['id' => 'MSA-25-0002', 'title' => 'Fixed here', 'severity' => 'Critical',
                'versions_affected_ranges' => [['min' => '4.5', 'max' => '4.5.1']]],
        ]);
        \curl::mock_response($announcements);
        \curl::mock_response($announcements);

        (new algorithm_10())->run();
        // The next day's run does not duplicate the issue.
        (new algorithm_10())->run();

        $issue = $DB->get_record('logstore_moodefense_issues', ['algorithm' => 10], '*', MUST_EXIST);
        $this->assertSame('MSA-25-0001', $issue->issuekey);
        $this->assertSame('High', $issue->severity);
        $this->assertEquals(strtotime('2025-02-17T00:00:00+00:00'), $issue->dateissue);
        $this->assertStringStartsWith('MSA-25-0001: XSS', $issue->message);
    }

    public function test_only_selected_severities_notify(): void {
        global $DB;
        // Notifications of a disabled log store are not sent.
        set_config('enabled_stores', 'logstore_moodefense', 'tool_log');
        set_config('algorithms_notifications', '10', 'logstore_moodefense');
        set_config('emailstosendnotification', '', 'logstore_moodefense');
        set_config('securityannouncementseverities', 'Serious,Critical', 'logstore_moodefense');
        $sink = $this->redirectMessages();
        $range = [['min' => '4.5', 'max' => '4.5.2']];
        \curl::mock_response(json_encode([
            ['id' => 'MSA-25-0001', 'title' => 'Minor one', 'severity' => 'Minor', 'versions_affected_ranges' => $range],
            ['id' => 'MSA-25-0002', 'title' => 'Serious one', 'severity' => 'Serious', 'versions_affected_ranges' => $range],
        ]));

        (new algorithm_10())->run();

        $this->assertSame(2, $DB->count_records('logstore_moodefense_issues', ['algorithm' => 10]));
        $messages = $sink->get_messages_by_component('logstore_moodefense');
        $this->assertCount(count(get_admins()), $messages);
        $this->assertStringContainsString('High', reset($messages)->subject);
    }

    public function test_api_error_fails_the_algorithm(): void {
        \curl::mock_response('not json');

        $this->expectException(\moodle_exception::class);
        (new algorithm_10())->run();
    }

    public function test_affects(): void {
        $ranges = [['min' => '4.4', 'max' => '4.4.9'], ['min' => '5.0', 'max' => '5.0.2']];
        $this->assertTrue(algorithm_10::affects('5.0.2+ (Build: 20250815)', $ranges));
        $this->assertFalse(algorithm_10::affects('5.0.3 (Build: 20250915)', $ranges));
        $this->assertTrue(algorithm_10::affects('4.4', [['max' => '4.4.9']]));
        $this->assertFalse(algorithm_10::affects('', $ranges));
    }
}
