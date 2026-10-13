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
 * Web service: get the country code of an IP address.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_moodefense\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use logstore_moodefense\geoip;

/**
 * Web service that returns the ISO country code for a given IP address.
 *
 * Looked up in the GeoIP2 database (see \logstore_moodefense\geoip).
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_country_code extends external_api {
    /**
     * Describes the parameters of execute(): the IP address.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ip' => new external_value(PARAM_TEXT, 'IP address to lookup', VALUE_REQUIRED),
        ]);
    }

    /**
     * Looks up the country of an IP address, shown next to each address in the issue details
     * dialogue. Asked for after the dialogue opens, so that it never waits for the lookups.
     *
     * @param string $ip
     * @return array [success, countrycode].
     */
    public static function execute(string $ip): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('logstore/moodefense:view', $context);

        $params = self::validate_parameters(self::execute_parameters(), ['ip' => $ip]);
        $location = geoip::lookup(trim($params['ip']));

        return [
            'success' => $location !== null,
            'countrycode' => $location['countrycode'] ?? null,
        ];
    }

    /**
     * Describes the result of execute(), checked by the web service layer.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the lookup was successful'),
            'countrycode' => new external_value(PARAM_TEXT, 'ISO 3166-1 alpha-2 country code', VALUE_OPTIONAL),
        ]);
    }
}
