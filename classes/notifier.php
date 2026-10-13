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

/**
 * Admin notifications about detected issues.
 *
 * @package    logstore_moodefense
 * @author     Devlion <info@devlion.co>
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_moodefense;

use logstore_moodefense\local\algorithm_type;
use stdClass;

/**
 * Notifies admins about new or refreshed issues: a Moodle notification and, when addresses are
 * configured in 'emailstosendnotification', an email too. Kept apart from detection so that message
 * formatting and delivery aren't tangled up with it.
 *
 * Recipients and the users the issues are about are fetched once per batch of issues.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /**
     * Notifies admins about issues, for algorithm::run(). Only issues of algorithms selected in
     * 'algorithms_notifications' notify. Without configured addresses, every site admin gets a
     * Moodle notification; with them, the Moodle users owning those addresses get a notification
     * and an email, other addresses an email.
     *
     * @param stdClass[] $issues logstore_moodefense_issues rows.
     */
    public static function notify(array $issues): void {
        $selected = explode(',', (string)get_config('logstore_moodefense', 'algorithms_notifications'));
        $issues = array_filter($issues, fn(stdClass $issue) => in_array((string)$issue->algorithm, $selected, true));
        if (!$issues) {
            return;
        }

        $setting = (string)get_config('logstore_moodefense', 'emailstosendnotification');
        $emails = array_unique(array_filter(array_map('trim', explode(',', $setting))));
        [$recipients, $outsiders] = $emails ? self::users_by_email($emails) : [get_admins(), []];
        $users = self::users_of($issues);

        foreach ($issues as $issue) {
            self::send($issue, $users[$issue->userid] ?? null, $recipients, $outsiders, !empty($emails));
        }
    }

    /**
     * Sends the notifications and emails about one issue to every recipient.
     *
     * @param stdClass $issue
     * @param stdClass|null $user The user the issue is about, null for none.
     * @param stdClass[] $recipients Moodle users to notify.
     * @param string[] $outsiders Addresses without a Moodle user, to email.
     * @param bool $email Whether the Moodle users get an email too.
     */
    private static function send(stdClass $issue, ?stdClass $user, array $recipients, array $outsiders, bool $email): void {
        $params = self::message_params($issue, $user);
        $type = $user ? 'full' : 'simple';
        $subject = get_string('security_issue_subject', 'logstore_moodefense', [
            'levelsecurity' => $issue->severity,
            'algorithmname' => $params['algorithmname'],
        ]);

        foreach ($recipients as $recipient) {
            $params['admin_fullname'] = fullname($recipient);
            [$text, $html] = self::render($type, $params);
            $small = get_string("smallmessage_{$type}", 'logstore_moodefense', $params);
            self::notification($recipient, $subject, $text, $html, $small);
            if ($email) {
                self::email($recipient, $subject, $text, $html, $issue->algorithm);
            }
        }

        foreach ($outsiders as $address) {
            $params['admin_fullname'] = $address;
            [$text, $html] = self::render($type, $params);
            self::email(self::create_template_user_for_email($address), $subject, $text, $html, $issue->algorithm);
        }
    }

    /**
     * Renders the message body as text and as HTML. In the HTML every value is escaped: names and
     * other values come from user profiles, and URLs end up inside href attributes.
     *
     * @param string $type 'full' (about a user) or 'simple'.
     * @param array $params Values of the message strings.
     * @return string[] [text, html]
     */
    private static function render(string $type, array $params): array {
        return [
            get_string("fullmessage_{$type}", 'logstore_moodefense', $params),
            get_string("fullmessagehtml_{$type}", 'logstore_moodefense', array_map('s', $params)),
        ];
    }

    /**
     * The values the message strings are filled with: the algorithm and, for an issue about a user,
     * who they are and links to their profile and logs.
     *
     * @param stdClass $issue
     * @param stdClass|null $user The user the issue is about, null for none.
     * @return array
     */
    private static function message_params(stdClass $issue, ?stdClass $user): array {
        $params = [
            'algorithmname' => algorithm_type::from($issue->algorithm)->label(),
            'issuesurl' => (new \moodle_url('/admin/tool/log/store/moodefense/issues.php'))->out(),
        ];
        if (!$user) {
            return $params;
        }

        return $params + [
            'suspecteduserid' => $user->id,
            'user_fullname' => fullname($user),
            'username' => $user->username,
            'idnumber' => $user->idnumber ?: get_string('notavailable', 'moodle'),
            'lastaccess' => $user->lastaccess ? userdate($user->lastaccess, '%Y/%m/%d %H:%M') : get_string('never', 'moodle'),
            'lastip' => $user->lastip ?: get_string('notavailable', 'moodle'),
            'profileurl' => (new \moodle_url('/user/profile.php', ['id' => $user->id]))->out(),
            'logurl' => (new \moodle_url('/report/log/index.php', [
                'chooselog' => 1,
                'showusers' => 0,
                'showcourses' => 0,
                'id' => 0,
                'user' => $user->id,
                'date' => time(),
            ]))->out(),
        ];
    }

    /**
     * Loads, in one query, the users the issues are about, for the messages. Not real users (0, or a
     * system user such as noreply) and users deleted since are left out: theirs get the simple message.
     *
     * @param stdClass[] $issues
     * @return stdClass[] userid => user
     */
    private static function users_of(array $issues): array {
        global $DB;

        $userids = array_filter(
            array_unique(array_column($issues, 'userid')),
            fn(int $userid) => \core_user::is_real_user($userid)
        );
        return $userids ? $DB->get_records_list('user', 'id', $userids) : [];
    }

    /**
     * Splits the configured addresses into the Moodle users owning them who may view issues, and the
     * others. An address of a user who may not (e.g. another account sharing it) is only emailed,
     * so issue details never reach that account's notifications.
     *
     * @param string[] $emails
     * @return array [userid => user, addresses to email only]
     */
    private static function users_by_email(array $emails): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($emails);
        $users = array_filter(
            $DB->get_records_select('user', "deleted = 0 AND email $insql", $params),
            fn($user) => has_capability('logstore/moodefense:view', \context_system::instance(), $user)
        );
        $owned = array_map('strtolower', array_column($users, 'email'));
        $outsiders = array_filter($emails, fn(string $email) => !in_array(strtolower($email), $owned, true));
        return [$users, array_values($outsiders)];
    }

    /**
     * Sends a test email to the given addresses, for the 'emailstosendnotification' setting's "Test
     * email" button, so that admins can check deliverability before saving.
     *
     * @param string $emailscsv Comma-separated list of email addresses.
     * @return array ['response' => bool success, 'message' => string error details].
     */
    public static function send_test_email(string $emailscsv): array {
        $emails = array_unique(array_filter(array_map('trim', explode(',', $emailscsv))));

        if (empty($emails)) {
            return ['response' => false, 'message' => get_string('testemail_noaddresses', 'logstore_moodefense')];
        }

        $subject = get_string('testemail_subject', 'logstore_moodefense');
        $fullmessage = get_string('testemail_body', 'logstore_moodefense');
        $fullmessagehtml = format_text($fullmessage, FORMAT_PLAIN);

        $invalid = [];
        $failed = [];

        foreach ($emails as $email) {
            if (!validate_email($email)) {
                $invalid[] = $email;
                continue;
            }

            $recipient = self::create_template_user_for_email($email);
            $result = email_to_user($recipient, \core_user::get_noreply_user(), $subject, $fullmessage, $fullmessagehtml);

            if (!$result) {
                $failed[] = $email;
            }
        }

        $problems = [];
        if ($invalid) {
            $problems[] = get_string('testemail_invalid', 'logstore_moodefense', implode(', ', $invalid));
        }
        if ($failed) {
            $problems[] = get_string('testemail_failed', 'logstore_moodefense', implode(', ', $failed));
        }
        return ['response' => !$problems, 'message' => implode('; ', $problems)];
    }

    /**
     * Sends a Moodle notification (message provider securityalert).
     *
     * @param stdClass $recipient
     * @param string $subject
     * @param string $text
     * @param string $html
     * @param string $small Short text for popups.
     */
    private static function notification(stdClass $recipient, string $subject, string $text, string $html, string $small): void {
        $message = new \core\message\message();
        $message->component = 'logstore_moodefense';
        $message->name = 'securityalert';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $recipient;
        $message->subject = $subject;
        $message->fullmessage = $text;
        $message->fullmessagehtml = $html;
        $message->fullmessageformat = FORMAT_HTML;
        $message->smallmessage = $small;
        $message->notification = 1;
        message_send($message);
    }

    /**
     * A stand-in user for an address without a Moodle user, as email_to_user() needs a user.
     *
     * @param string $email Email address.
     * @return stdClass Temporary user object.
     */
    private static function create_template_user_for_email(string $email): stdClass {
        return (object)[
            'id' => -1,
            'email' => $email,
            'maildisplay' => 1,
            'firstname' => '',
            'lastname' => '',
            'username' => $email,
            'mailformat' => 1,
            'deleted' => 0,
            'suspended' => 0,
            'auth' => 'manual',
            'confirmed' => 1,
            'firstnamephonetic' => '',
            'lastnamephonetic' => '',
            'middlename' => '',
            'alternatename' => '',
        ];
    }

    /**
     * Sends an email and reports the result in the task output, where admins check deliveries.
     *
     * @param stdClass $recipient A user, or an address made into one by create_template_user_for_email().
     * @param string $subject
     * @param string $text
     * @param string $html
     * @param int $algorithmid The algorithm that detected the issue.
     */
    private static function email(stdClass $recipient, string $subject, string $text, string $html, int $algorithmid): void {
        $sent = email_to_user($recipient, \core_user::get_noreply_user(), $subject, $text, $html);
        mtrace(get_string('algorithmcompleteemail', 'logstore_moodefense', (object)[
            'algorithm' => $algorithmid,
            'email' => $recipient->email,
        ]) . ': ' . ($sent ? 'true' : 'false'));
    }
}
