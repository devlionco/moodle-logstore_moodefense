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

namespace logstore_moodefense\local;

/**
 * The detection algorithms this plugin ships, stored as their value in logstore_moodefense_issues.algorithm.
 * Each one is implemented by \logstore_moodefense\algorithms\algorithm_<value>.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum algorithm_type: int {
    // Staff and student login from the same device.
    case SHARED_DEVICE = 2;
    // User activity from multiple IP addresses.
    case MULTIPLE_IPS = 4;
    // Admin login from an unauthorised country.
    case UNPERMITTED_COUNTRY = 5;
    // Aggressive password guessing.
    case BRUTE_FORCE = 7;
    // Moodle security announcement affecting the installed version (needs the Moodefense API).
    case SECURITY_ANNOUNCEMENT = 10;

    /**
     * Algorithm IDs of MooDefense Premium, the commercial edition. Not shipped here: the settings list them next
     * to the free ones, and picking one shows how to get in touch (see admin\setting_algorithms).
     */
    public const PREMIUM = [1, 3, 6, 8, 9];

    /** Where to ask about MooDefense Premium (settings dialogue, free version banner). */
    public const PREMIUM_CONTACT = 'sales@fensive.co.il';

    /** Fields of the details modal of an issue about a user, see details(). */
    public const USER_DETAILS = ['username', 'roles', 'userid', 'ips', 'useragent', 'profilelink', 'loglink'];

    /**
     * The algorithms to run, from the 'algorithms' setting. An ID that is not shipped (e.g. a premium
     * algorithm selected before 2.0.0) is ignored.
     *
     * @return algorithm_type[]
     */
    public static function enabled(): array {
        $ids = explode(',', (string)get_config('logstore_moodefense', 'algorithms'));
        return array_values(array_filter(array_map(fn($id) => self::tryFrom((int)$id), $ids)));
    }

    /**
     * The algorithm name shown to admins, in settings, the issues page and notifications.
     *
     * @return string
     */
    public function label(): string {
        return get_string('algorithm' . $this->value, 'logstore_moodefense');
    }

    /**
     * The fields the issue details modal lists for its issues, in this order, below the ones every issue
     * has. Each one is a key of issue_details (see there how a key becomes a field): a field about the
     * issue's user, or else a key of the issue's data, shown as stored. Its label is the 'modal_<key>' string.
     *
     * To change an algorithm's fields, give it its own arm, e.g.:
     *
     *     // Everything but the user agent.
     *     self::BRUTE_FORCE => array_diff(self::USER_DETAILS, ['useragent']),
     *     // Fields of its own data (e.g. the 'attempt_count' it stores), with a 'modal_attempt_count' string.
     *     self::BRUTE_FORCE => [...self::USER_DETAILS, 'attempt_count'],
     *
     * @return string[]
     */
    public function details(): array {
        return match ($this) {
            // Site-wide: no user or device behind it, only the announcement.
            self::SECURITY_ANNOUNCEMENT => ['cve_identifier', 'versions_affected', 'versions_fixed', 'link'],
            default => self::USER_DETAILS,
        };
    }

    /**
     * The class implementing the algorithm, to run it.
     *
     * @return class-string<\logstore_moodefense\algorithms\algorithm>
     */
    public function classname(): string {
        return '\\logstore_moodefense\\algorithms\\algorithm_' . $this->value;
    }

    /**
     * Context of the free version banner (templates/banner.mustache), shown on the dashboard, the
     * issues page and the settings page.
     *
     * @return array
     */
    public static function premium_banner(): array {
        return [
            'premiumcount' => count(self::PREMIUM),
            'premiumcontact' => 'mailto:' . self::PREMIUM_CONTACT,
        ];
    }
}
