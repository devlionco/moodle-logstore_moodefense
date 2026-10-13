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
 * Algorithm 4 (free): a user active from many different IP addresses within a short time.
 * One incident per user.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class algorithm_4 extends algorithm {
    /** The algorithm, see algorithm::new_issue(). */
    protected const TYPE = algorithm_type::MULTIPLE_IPS;

    /** How far back activity is examined, in seconds. */
    public const PERIOD = 30 * MINSECS;

    /** Number of distinct IP addresses (with the same user agent) that raises an issue. */
    public const MIN_DISTINCT_IPS = 10;

    /**
     * Finds the users hopping between IP addresses: one issue per user, listing the addresses.
     *
     * @return stdClass[]
     */
    protected function detect(): array {
        $message = get_string('manyips', 'logstore_moodefense');

        $issues = [];
        foreach ($this->hopping_users(time() - self::PERIOD) as $user) {
            $issues[] = $this->new_issue(
                userid: (int)$user->userid,
                severity: severity::HIGH,
                message: $message,
                dateissue: (int)$user->timecreated,
                data: [
                    'useragent' => $user->useragent ?? '',
                    'ips' => $user->ips,
                ],
            );
        }
        return $issues;
    }

    /**
     * Finds the users active from at least MIN_DISTINCT_IPS addresses with one user agent in the
     * period, with those addresses.
     *
     * First finds them on integer columns alone, which the (timecreated, userid, useragentint, ipint)
     * index covers, then reads only their own log rows for the details: two short queries instead of
     * one join the optimizer runs as a scan. Not-logged-in and guest requests all share one userid,
     * so they are left out.
     *
     * @param int $since Only activity after this time.
     * @return stdClass[] Records with userid, useragent, timecreated (latest) and ips.
     */
    private function hopping_users(int $since): array {
        global $CFG, $DB;

        $hopping = $DB->get_recordset_sql(
            "SELECT userid, useragentint
               FROM {logstore_moodefense_log}
              WHERE timecreated > :since AND userid > 0 AND userid <> :guestid
           GROUP BY userid, useragentint
             HAVING COUNT(DISTINCT ipint) >= :minips",
            ['since' => $since, 'guestid' => $CFG->siteguest, 'minips' => self::MIN_DISTINCT_IPS]
        );
        $devices = [];
        foreach ($hopping as $device) {
            $devices[$device->userid . '|' . $device->useragentint] = (int)$device->userid;
        }
        $hopping->close();

        $users = [];
        foreach (array_chunk(array_unique($devices), self::IN_CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $activity = $DB->get_recordset_sql(
                "SELECT userid, useragentint, ip, MAX(useragent) AS useragent, MAX(timecreated) AS timecreated
                   FROM {logstore_moodefense_log}
                  WHERE timecreated > :since AND userid $insql
               GROUP BY userid, useragentint, ip",
                $params + ['since' => $since]
            );
            foreach ($activity as $row) {
                $device = $row->userid . '|' . $row->useragentint;
                if (!isset($devices[$device])) {
                    continue; // The user's other user agents were not hopping.
                }
                $user = $users[$device] ??= (object)['userid' => $row->userid, 'useragent' => $row->useragent,
                    'timecreated' => 0, 'ips' => []];
                $user->timecreated = max($user->timecreated, (int)$row->timecreated);
                if ((string)$row->ip !== '') {
                    $user->ips[] = $row->ip;
                }
            }
            $activity->close();
        }
        return array_values($users);
    }
}
