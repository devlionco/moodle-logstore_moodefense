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

namespace logstore_moodefense\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use logstore_moodefense\local\algorithm_type;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(provider::class)]
/**
 * Tests for the privacy provider.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \logstore_moodefense\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass Has logs (one in a course, one as related user) and their own issue. */
    private $user1;

    /** @var \stdClass Has a log and an issue, and is the suspect in user1's shared-device issue. */
    private $user2;

    /** @var \stdClass Only appears as the real user behind a "login as" log entry. */
    private $user3;

    /** @var \context_course */
    private $coursecontext;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('logstore_moodefense');
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();
        $this->coursecontext = \context_course::instance($this->getDataGenerator()->create_course()->id);

        $generator->create_log(['userid' => $this->user1->id]);
        $generator->create_log([
            'userid' => $this->user1->id,
            'contextid' => $this->coursecontext->id,
            'contextlevel' => CONTEXT_COURSE,
        ]);
        $generator->create_log(['userid' => $this->user2->id, 'relateduserid' => $this->user1->id]);
        $generator->create_log(['userid' => $this->user2->id, 'realuserid' => $this->user3->id]);

        $generator->create_issue(['userid' => $this->user1->id]);
        $generator->create_issue(['userid' => $this->user2->id]);
        // The same shape algorithm_2 saves: keyed by the suspected user.
        $generator->create_issue([
            'userid' => $this->user1->id,
            'algorithm' => algorithm_type::SHARED_DEVICE->value,
            'issuekey' => (string)$this->user2->id,
            'data' => ['ip' => '10.0.0.2', 'suspecteduid' => (int)$this->user2->id, 'targetuid' => (int)$this->user1->id],
        ]);
        // An issue about no user (password guessing against unknown usernames): no one's personal data.
        $generator->create_issue(['userid' => 0, 'algorithm' => algorithm_type::BRUTE_FORCE->value]);
    }

    /**
     * Contexts of the user, as tool_log collects them from this log store.
     *
     * @param \stdClass $user
     * @return int[]
     */
    private function contextids_of(\stdClass $user): array {
        $contextlist = new contextlist();
        provider::add_contexts_for_userid($contextlist, $user->id);
        return $contextlist->get_contextids();
    }

    public function test_add_contexts_for_userid(): void {
        $system = \context_system::instance()->id;

        $this->assertEqualsCanonicalizing([$system, $this->coursecontext->id], $this->contextids_of($this->user1));
        $this->assertEquals([$system], array_values($this->contextids_of($this->user2)));
        $this->assertEquals([$system], array_values($this->contextids_of($this->user3)), 'Real user of a "login as" entry.');
        $this->assertEmpty($this->contextids_of($this->getDataGenerator()->create_user()));
    }

    public function test_add_userids_for_context(): void {
        $userlist = new userlist(\context_system::instance(), 'logstore_moodefense');
        provider::add_userids_for_context($userlist);
        $this->assertEqualsCanonicalizing(
            [$this->user1->id, $this->user2->id, $this->user3->id],
            $userlist->get_userids()
        );

        $userlist = new userlist($this->coursecontext, 'logstore_moodefense');
        provider::add_userids_for_context($userlist);
        $this->assertEquals([$this->user1->id], $userlist->get_userids());
    }

    public function test_export_user_data(): void {
        $system = \context_system::instance();
        provider::export_user_data(new approved_contextlist($this->user2, 'logstore_moodefense', [$system->id]));

        $path = [get_string('privacy:path:logs', 'tool_log'), get_string('pluginname', 'logstore_moodefense')];
        $writer = writer::with_context($system);
        $logs = $writer->get_data($path)->logs;
        $this->assertCount(2, $logs);
        $this->assertEquals('Mozilla/5.0 (phpunit)', $logs[0]['useragent']);

        // Their own issue and the one naming them as the suspect.
        $issues = $writer->get_data(array_merge($path, [get_string('privacy:path:issues', 'logstore_moodefense')]))->issues;
        $this->assertEqualsCanonicalizing(
            [$this->user2->id, $this->user1->id],
            array_column($issues, 'userid')
        );
    }

    public function test_delete_data_for_user_keeps_the_evidence(): void {
        global $DB;
        $contexts = [\context_system::instance()->id];
        provider::delete_data_for_user(new approved_contextlist($this->user2, 'logstore_moodefense', $contexts));

        $this->assertFalse($DB->record_exists('logstore_moodefense_log', ['userid' => $this->user2->id]));
        $this->assertEquals(2, $DB->count_records('logstore_moodefense_log', ['userid' => $this->user1->id]));
        // A suspect cannot have the issues about them erased.
        $this->assertTrue($DB->record_exists('logstore_moodefense_issues', ['userid' => $this->user2->id]));
        $this->assertTrue($DB->record_exists('logstore_moodefense_issues', ['algorithm' => algorithm_type::SHARED_DEVICE->value]));
    }

    public function test_delete_data_for_userlist_keeps_the_evidence(): void {
        global $DB;
        $userlist = new approved_userlist(\context_system::instance(), 'logstore_moodefense', [$this->user1->id]);
        provider::delete_data_for_userlist($userlist);

        // Only the system context was approved: the course log entry stays.
        $this->assertEquals(1, $DB->count_records('logstore_moodefense_log', ['userid' => $this->user1->id]));
        $this->assertEquals(4, $DB->count_records('logstore_moodefense_issues'));
    }

    public function test_delete_data_for_all_users_in_context_keeps_the_evidence(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertEquals(1, $DB->count_records('logstore_moodefense_log'), 'The course log entry stays.');
        $this->assertEquals(4, $DB->count_records('logstore_moodefense_issues'));
    }

    public function test_requests_are_routed_through_tool_log(): void {
        $contexts = \tool_log\privacy\provider::get_contexts_for_userid($this->user1->id)->get_contextids();
        $this->assertEqualsCanonicalizing([\context_system::instance()->id, $this->coursecontext->id], $contexts);
    }
}
