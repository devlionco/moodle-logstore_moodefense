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

use logstore_moodefense\local\severity;
use logstore_moodefense\local\status;
use logstore_moodefense\notifier;
use stdClass;

/**
 * A detection algorithm: finds issues in the log, stores them and notifies admins about them.
 *
 * A new incident is inserted. A known one is refreshed (and reopened) only when the detection carries
 * evidence newer than its last save - otherwise an issue an admin has just resolved would be reopened
 * on every run for as long as the same events stay inside the algorithm's look-back window - or when
 * it became more severe. A refreshed issue notifies again once NOTIFICATION_COOLDOWN has passed,
 * or right away when it became more severe.
 *
 * Issues are stored in chunks, with a fixed number of queries per chunk however many issues it holds.
 *
 * Each algorithm declares its algorithm_type as the TYPE constant.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class algorithm {
    /** Most IDs put in one IN (...) list: PostgreSQL allows 65535 parameters per query. */
    protected const IN_CHUNK = 1000;

    /** Minimum time between two notifications about the same (still updating) issue, in seconds. */
    public const NOTIFICATION_COOLDOWN = DAYSECS;

    /**
     * Runs the algorithm: called by the processing task every 5 minutes. Finds the issues, drops
     * duplicates of one incident (the last one counts), then stores and notifies in chunks of
     * IN_CHUNK, so the number of queries does not grow with the number of issues.
     */
    public function run(): void {
        $unique = [];
        foreach ($this->detect() as $issue) {
            $unique[self::identity($issue->userid, $issue->issuekey)] = $issue;
        }

        foreach (array_chunk($unique, self::IN_CHUNK) as $chunk) {
            notifier::notify(array_filter($this->save($chunk), fn(stdClass $issue) => $this->notifies($issue)));
        }
    }

    /**
     * Whether a stored issue of this algorithm may notify, on top of the 'algorithms_notifications'
     * setting checked by notifier::notify(). Every issue may, unless the algorithm says otherwise.
     *
     * @param stdClass $issue Made by new_issue().
     * @return bool
     */
    protected function notifies(stdClass $issue): bool {
        return true;
    }

    /**
     * The algorithm's own detection: queries the log and returns what it found, built with
     * new_issue(). Storing and notifying are left to run().
     *
     * @return stdClass[] Made by new_issue().
     */
    abstract protected function detect(): array;

    /**
     * Builds a detected issue as a new, opened logstore_moodefense_issues row, so that every
     * algorithm stores issues in the same shape. save() completes it with the roles and the time.
     *
     * @param int $userid The user the issue is about, 0 for none.
     * @param severity $severity
     * @param string $message Human-readable description.
     * @param int $dateissue Time of the latest event behind the detection.
     * @param array $data Evidence shown in the issue details.
     * @param string $issuekey The algorithm-specific part of the incident's identity (e.g. the attacking
     *     IP address), '' if the user alone identifies it. Detecting it again refreshes the stored issue.
     * @return stdClass
     */
    protected function new_issue(
        int $userid,
        severity $severity,
        string $message,
        int $dateissue,
        array $data,
        string $issuekey = ''
    ): stdClass {
        return (object)[
            'userid' => $userid,
            'algorithm' => static::TYPE->value,
            'issuekey' => $issuekey,
            'severity' => $severity->value,
            'status' => status::OPENED->value,
            'roles' => '',
            // A user agent may hold invalid UTF-8: substitute it rather than store an empty false.
            'data' => json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            'message' => $message,
            'dateissue' => $dateissue,
            'timecreated' => 0,
        ];
    }

    /**
     * Stores a chunk of issues: inserts the new incidents and refreshes (and reopens) the known ones
     * that have newer evidence or became more severe. A known incident without news is left alone,
     * so an issue an admin resolved is not reopened by the same events on every run. Returns the
     * issues admins should hear about.
     *
     * @param stdClass[] $issues Issues about distinct incidents, see new_issue().
     * @return stdClass[] The issues to notify about.
     */
    private function save(array $issues): array {
        global $DB;

        $userids = array_values(array_unique(array_column($issues, 'userid')));
        $stored = $this->stored_issues($userids);
        $roles = self::user_roles($userids);
        $now = time();

        $inserts = [];
        $notify = [];
        foreach ($issues as $issue) {
            $issue->roles = $roles[$issue->userid];
            $issue->timecreated = $now;
            $existing = $stored[self::identity($issue->userid, $issue->issuekey)] ?? null;

            if (!$existing) {
                $inserts[] = $issue;
                $notify[] = $issue;
                continue;
            }

            $escalated = severity::from($issue->severity)->outranks(severity::from($existing->severity));
            if (!$escalated && $issue->dateissue <= $existing->timecreated) {
                continue;
            }

            $issue->id = $existing->id;
            $issue->dateissue = $now;
            $DB->update_record('logstore_moodefense_issues', $issue);
            if ($escalated || $existing->dateissue < $now - self::NOTIFICATION_COOLDOWN) {
                $notify[] = $issue;
            }
        }

        $DB->insert_records('logstore_moodefense_issues', $inserts);
        return $notify;
    }

    /**
     * Loads, in one query, the issues this algorithm stored on earlier runs about the given users,
     * so that save() can tell new incidents from known ones.
     *
     * @param int[] $userids
     * @return stdClass[] identity() => record with id, severity, dateissue and timecreated.
     */
    private function stored_issues(array $userids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            'logstore_moodefense_issues',
            "algorithm = :algorithm AND userid $insql",
            $params + ['algorithm' => static::TYPE->value],
            '',
            'id, userid, issuekey, severity, dateissue, timecreated'
        );

        $stored = [];
        foreach ($records as $record) {
            $stored[self::identity($record->userid, $record->issuekey)] = $record;
        }
        return $stored;
    }

    /**
     * The users' role shortnames, comma-separated, "admin" first for site admins - stored with
     * each issue so that the issues page shows (and filters by) the roles at detection time.
     *
     * @param int[] $userids
     * @return string[] userid => roles; '' for users without any.
     */
    private static function user_roles(array $userids): array {
        global $DB;

        $roles = array_fill_keys($userids, []);
        $adminids = explode(',', get_config('core', 'siteadmins'));
        foreach (array_intersect($userids, $adminids) as $adminid) {
            $roles[$adminid][] = 'admin';
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $assignments = $DB->get_recordset_sql(
            "SELECT DISTINCT ra.userid, r.shortname
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid $insql
           ORDER BY ra.userid, r.shortname",
            $params
        );
        foreach ($assignments as $assignment) {
            $roles[$assignment->userid][] = $assignment->shortname;
        }
        $assignments->close();

        return array_map(fn(array $shortnames) => implode(', ', $shortnames), $roles);
    }

    /**
     * The key identifying an incident among the issues of one algorithm, to match new findings with
     * each other and with stored issues in arrays. The separator keeps user 1 with key "23" apart
     * from user 12 with key "3".
     *
     * @param int|string $userid
     * @param string $key The algorithm-specific part, see new_issue().
     * @return string
     */
    private static function identity(int|string $userid, string $key): string {
        return $userid . '|' . $key;
    }
}
