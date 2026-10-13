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

use logstore_moodefense\local\algorithm_type;
use logstore_moodefense\local\severity;
use stdClass;

/**
 * Algorithm 2 (free): a staff member and a non-staff user logged in from the same device.
 *
 * A "device" is the sessionidentifier the log store derives from the request's IP address and user
 * agent (see \logstore_moodefense\log\store::insert_event_entries()). One incident per staff member
 * and suspected user.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class algorithm_2 extends algorithm {
    /** The algorithm, see algorithm::new_issue(). */
    protected const TYPE = algorithm_type::SHARED_DEVICE;

    /** How far back logins are compared, in seconds. */
    public const PERIOD = 4 * HOURSECS;

    /**
     * Finds every staff member who shared a device with another user in the period: one issue per
     * staff member and suspected user.
     *
     * @return stdClass[]
     */
    protected function detect(): array {
        $staffids = array_flip($this->staff_ids());

        $issues = [];
        foreach ($this->shared_devices(time() - self::PERIOD) as $logins) {
            $others = array_diff_key($logins, $staffids);
            foreach (array_intersect_key($logins, $staffids) as $targetuid => $teacherlogin) {
                foreach ($others as $suspecteduid => $suspectlogin) {
                    $issues[] = $this->issue($targetuid, $suspecteduid, $teacherlogin, $suspectlogin);
                }
            }
        }
        return $issues;
    }

    /**
     * Builds the issue about a staff member's device used by a suspected user, with both users'
     * first logins on that device as evidence.
     *
     * @param int $targetuid Staff member.
     * @param int $suspecteduid
     * @param stdClass $teacherlogin The staff member's first login on the device in the period.
     * @param stdClass $suspectlogin The suspected user's first login on the device in the period.
     * @return stdClass
     */
    private function issue(int $targetuid, int $suspecteduid, stdClass $teacherlogin, stdClass $suspectlogin): stdClass {
        return $this->new_issue(
            userid: $targetuid,
            severity: severity::HIGH,
            message: get_string('user_suspected_access', 'logstore_moodefense', [
                'suspecteduid' => $suspecteduid,
                'targetuid' => $targetuid,
            ]),
            dateissue: max((int)$suspectlogin->timecreated, (int)$teacherlogin->timecreated),
            data: [
                'ip' => $suspectlogin->ip,
                'useragent' => $suspectlogin->useragent ?? '',
                'suspecteduid' => $suspecteduid,
                'suspect_time' => (int)$suspectlogin->timecreated,
                'teacher_time' => (int)$teacherlogin->timecreated,
            ],
            issuekey: (string)$suspecteduid,
        );
    }

    /**
     * The users whose devices are watched: site admins plus users holding one of the staff roles
     * configured in 'staffroles' in a course or category.
     *
     * @return int[]
     */
    private function staff_ids(): array {
        global $DB;

        $staffids = explode(',', get_config('core', 'siteadmins'));
        $roles = array_filter(array_map('intval', explode(',', (string)get_config('logstore_moodefense', 'staffroles'))));
        if ($roles) {
            [$insql, $params] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED);
            $staffids = array_merge($staffids, $DB->get_fieldset_sql(
                "SELECT DISTINCT ra.userid
                   FROM {role_assignments} ra
                   JOIN {context} ctx ON ctx.id = ra.contextid
                  WHERE ra.roleid $insql AND ctx.contextlevel IN (:courselevel, :categorylevel)",
                $params + ['courselevel' => CONTEXT_COURSE, 'categorylevel' => CONTEXT_COURSECAT]
            ));
        }
        return array_map('intval', $staffids);
    }

    /**
     * Finds the devices more than one user logged in from in the period, with each user's first web
     * login on each of them - the evidence of the issue.
     *
     * The first query reads only the (eventname, timecreated, userid, ipint, sessionidentifier, origin)
     * index; then only the first logins' rows are read. Log IDs grow with time, so the lowest is the first.
     *
     * @param int $since
     * @return stdClass[][] sessionidentifier => [userid => login with ip, useragent and timecreated].
     */
    private function shared_devices(int $since): array {
        global $DB;

        $params = ['eventname1' => '\\' . \core\event\user_loggedin::class, 'since1' => $since];
        $params += ['eventname2' => $params['eventname1'], 'since2' => $since];
        $firstlogins = $DB->get_fieldset_sql(
            "SELECT MIN(l.id)
               FROM {logstore_moodefense_log} l
               JOIN (SELECT sessionidentifier
                       FROM {logstore_moodefense_log}
                      WHERE eventname = :eventname1 AND origin = 'web' AND timecreated > :since1 AND userid > 0
                   GROUP BY sessionidentifier
                     HAVING COUNT(DISTINCT userid) > 1) shared ON shared.sessionidentifier = l.sessionidentifier
              WHERE l.eventname = :eventname2 AND l.origin = 'web' AND l.timecreated > :since2 AND l.userid > 0
           GROUP BY l.sessionidentifier, l.userid",
            $params
        );

        $devices = [];
        foreach (array_chunk($firstlogins, self::IN_CHUNK) as $chunk) {
            $fields = 'id, sessionidentifier, userid, ip, useragent, timecreated';
            $logins = $DB->get_records_list('logstore_moodefense_log', 'id', $chunk, '', $fields);
            foreach ($logins as $login) {
                $devices[$login->sessionidentifier][(int)$login->userid] = $login;
            }
        }
        return $devices;
    }
}
