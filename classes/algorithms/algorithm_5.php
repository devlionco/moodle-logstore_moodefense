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

use logstore_moodefense\geoip;
use logstore_moodefense\local\algorithm_type;
use logstore_moodefense\local\severity;
use stdClass;

/**
 * Algorithm 5 (free): a site admin logged in from a country outside the 'permittedgeolocation' setting.
 * One incident per admin; the latest such login is reported.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class algorithm_5 extends algorithm {
    /** The algorithm, see algorithm::new_issue(). */
    protected const TYPE = algorithm_type::UNPERMITTED_COUNTRY;

    /** How far back logins are examined, in seconds. */
    public const PERIOD = 4 * HOURSECS;

    /**
     * Finds site admin logins from countries outside the permitted ones: one issue per admin, about
     * the latest such login. Does nothing until permitted countries are configured.
     *
     * @return stdClass[]
     */
    protected function detect(): array {
        $permitted = $this->permitted_countries();
        $adminids = array_keys(get_admins());
        if (!$permitted || !$adminids) {
            return [];
        }

        $issues = [];
        foreach ($this->logins($adminids, time() - self::PERIOD) as $login) {
            $location = geoip::lookup((string)$login->ip);
            if ($location && !in_array($location['countrycode'], $permitted, true)) {
                $issues[] = $this->issue($login, $location);
            }
        }
        return $issues;
    }

    /**
     * The countries admins may log in from: the 'permittedgeolocation' setting as upper-case codes.
     *
     * @return string[]
     */
    private function permitted_countries(): array {
        $setting = (string)get_config('logstore_moodefense', 'permittedgeolocation');
        return array_values(array_filter(array_map(fn($code) => strtoupper(trim($code)), explode(',', $setting))));
    }

    /**
     * Builds the issue about an admin login from an unpermitted country, with the address and country
     * as evidence.
     *
     * @param stdClass $login
     * @param array $location As returned by geoip::lookup().
     * @return stdClass
     */
    private function issue(stdClass $login, array $location): stdClass {
        $userid = (int)$login->userid;

        return $this->new_issue(
            userid: $userid,
            severity: severity::HIGH,
            message: get_string('ipaddressfromunpermittedlocation', 'logstore_moodefense', [
                'userid' => $userid,
                'country' => $location['country'],
            ]),
            dateissue: (int)$login->timecreated,
            data: [
                'ip' => $login->ip,
                'useragent' => $login->useragent ?? '',
                'country' => $location['country'],
                'countryCode' => $location['countrycode'],
            ],
        );
    }

    /**
     * The logins of the given users in the period, oldest first, to look up where they came from.
     *
     * @param int[] $userids
     * @param int $since Only logins after this time.
     * @return stdClass[] Records with id, userid, ip, useragent and timecreated.
     */
    private function logins(array $userids, int $since): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        return $DB->get_records_select(
            'logstore_moodefense_log',
            "eventname = :eventname AND timecreated > :since AND userid $insql",
            $params + ['eventname' => '\\' . \core\event\user_loggedin::class, 'since' => $since],
            'timecreated ASC, id ASC',
            'id, userid, ip, useragent, timecreated'
        );
    }
}
