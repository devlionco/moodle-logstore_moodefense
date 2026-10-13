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
 * Algorithm 7 (free): many failed logins from one IP address against one account (or against
 * unknown usernames). Medium severity, escalated to High once the account logs in from that address.
 * One incident per account and IP address.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class algorithm_7 extends algorithm {
    /** The algorithm, see algorithm::new_issue(). */
    protected const TYPE = algorithm_type::BRUTE_FORCE;

    /** How far back failed logins are counted, in seconds. */
    public const PERIOD = 30 * MINSECS;

    /** Number of failed logins from one IP address against one account that raises an issue. */
    public const MIN_FAILED_ATTEMPTS = 50;

    /**
     * Finds the accounts attacked by password guessing: one issue per account and IP address.
     *
     * @return stdClass[]
     */
    protected function detect(): array {
        $issues = [];
        $attacks = $this->attacks(time() - self::PERIOD);
        foreach ($attacks as $attack) {
            $issues[] = $this->issue($attack);
        }
        $attacks->close();

        return $issues;
    }

    /**
     * Builds the issue about one attacked account: Medium, or High once the attacker logged in.
     *
     * @param stdClass $attack
     * @return stdClass
     */
    private function issue(stdClass $attack): stdClass {
        $userid = (int)$attack->userid;
        $ip = (string)$attack->ip;

        return $this->new_issue(
            userid: $userid,
            severity: $userid && $attack->compromised ? severity::HIGH : severity::MEDIUM,
            message: $userid
                ? get_string('bruteforce', 'logstore_moodefense', ['ip' => $ip, 'userid' => $userid])
                : get_string('bruteforceunknownuser', 'logstore_moodefense', ['ip' => $ip]),
            dateissue: (int)$attack->timecreated,
            data: [
                'ip' => $ip,
                'attempt_count' => (int)$attack->attempts,
                'useragent' => $attack->useragent,
            ],
            issuekey: $ip,
        );
    }

    /**
     * Finds the accounts with at least MIN_FAILED_ATTEMPTS failed logins from one IP address in the
     * period, and whether that address then logged in successfully.
     *
     * Both aggregates read only the (eventname, timecreated, userid, ipint, ...) index; the IP address
     * and user agent come from the last failure. "compromised" compares log IDs, which unlike
     * timestamps also order a login made within the same second as the last failure.
     *
     * @param int $since Only failures after this time.
     * @return \moodle_recordset Records with userid, ip, timecreated (latest), attempts, useragent, compromised.
     */
    private function attacks(int $since): \moodle_recordset {
        global $DB;

        return $DB->get_recordset_sql(
            "SELECT failed.userid, lastfailed.ip, failed.timecreated, failed.attempts, lastfailed.useragent,
                    CASE WHEN success.lastloginid > failed.lastfailedid THEN 1 ELSE 0 END AS compromised
               FROM (SELECT ipint, userid, MAX(timecreated) AS timecreated, MAX(id) AS lastfailedid, COUNT(*) AS attempts
                       FROM {logstore_moodefense_log}
                      WHERE eventname = :failed AND timecreated > :since1
                   GROUP BY ipint, userid
                     HAVING COUNT(*) >= :minattempts) failed
               JOIN {logstore_moodefense_log} lastfailed ON lastfailed.id = failed.lastfailedid
          LEFT JOIN (SELECT ipint, userid, MAX(id) AS lastloginid
                       FROM {logstore_moodefense_log}
                      WHERE eventname = :loggedin AND timecreated > :since2
                   GROUP BY ipint, userid) success
                 ON success.ipint = failed.ipint AND success.userid = failed.userid",
            [
                'failed' => '\\' . \core\event\user_login_failed::class,
                'loggedin' => '\\' . \core\event\user_loggedin::class,
                'since1' => $since,
                'since2' => $since,
                'minattempts' => self::MIN_FAILED_ATTEMPTS,
            ]
        );
    }
}
