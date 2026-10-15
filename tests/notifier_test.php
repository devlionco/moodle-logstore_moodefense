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

use logstore_moodefense\local\algorithm_type;
use logstore_moodefense\local\severity;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(notifier::class)]
/**
 * Tests for admin notifications.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \logstore_moodefense\notifier
 */
final class notifier_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled_stores', 'logstore_moodefense', 'tool_log');
        set_config('algorithms_notifications', '2,7', 'logstore_moodefense');
        set_config('emailstosendnotification', '', 'logstore_moodefense');
    }

    /**
     * Notifies about one issue.
     *
     * @param algorithm_type $algorithm
     * @param severity $severity
     * @param int $userid
     */
    private function notify(algorithm_type $algorithm, severity $severity, int $userid = 0): void {
        notifier::notify([(object)['algorithm' => $algorithm->value, 'userid' => $userid, 'severity' => $severity->value]]);
    }

    public function test_notifies_site_admins_with_user_details(): void {
        $user = $this->getDataGenerator()->create_user(['username' => 'suspect']);
        $sink = $this->redirectMessages();

        $this->notify(algorithm_type::BRUTE_FORCE, severity::HIGH, $user->id);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals(get_admin()->id, $messages[0]->useridto);
        $this->assertStringContainsString('High', $messages[0]->subject);
        $this->assertStringContainsString('suspect', $messages[0]->fullmessage);
    }

    public function test_user_details_are_escaped_in_the_html_message(): void {
        // Profile names cannot hold tags, but an ID number can hold any text.
        $user = $this->getDataGenerator()->create_user(['idnumber' => '<img src=x onerror=alert(1)>']);
        $sink = $this->redirectMessages();

        $this->notify(algorithm_type::BRUTE_FORCE, severity::HIGH, $user->id);

        $message = $sink->get_messages()[0];
        $this->assertStringNotContainsString('<img', $message->fullmessagehtml);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $message->fullmessagehtml);
        $this->assertStringContainsString('<img src=x onerror=alert(1)>', $message->fullmessage);
    }

    public function test_algorithm_not_selected_sends_nothing(): void {
        $sink = $this->redirectMessages();

        $this->notify(algorithm_type::MULTIPLE_IPS, severity::HIGH);

        $this->assertCount(0, $sink->get_messages());
    }

    public function test_configured_addresses_receive_emails(): void {
        $recipient = $this->getDataGenerator()->create_user(['email' => 'member@example.com']);
        $this->getDataGenerator()->role_assign('manager', $recipient->id);
        set_config('emailstosendnotification', 'member@example.com, outsider@example.com', 'logstore_moodefense');
        $messages = $this->redirectMessages();
        $emails = $this->redirectEmails();

        $this->expectOutputRegex('/member@example.com/');
        $this->notify(algorithm_type::BRUTE_FORCE, severity::MEDIUM);

        $this->assertEquals($recipient->id, $messages->get_messages()[0]->useridto);
        $this->assertEqualsCanonicalizing(
            ['member@example.com', 'outsider@example.com'],
            array_column($emails->get_messages(), 'to')
        );
    }

    public function test_account_without_access_sharing_an_address_gets_no_notification(): void {
        $this->getDataGenerator()->create_user(['email' => 'shared@example.com']);
        set_config('emailstosendnotification', 'shared@example.com', 'logstore_moodefense');
        $messages = $this->redirectMessages();
        $emails = $this->redirectEmails();

        $this->expectOutputRegex('/shared@example.com/');
        $this->notify(algorithm_type::BRUTE_FORCE, severity::MEDIUM);

        $this->assertCount(0, $messages->get_messages());
        $this->assertSame('shared@example.com', $emails->get_messages()[0]->to);
    }
}
