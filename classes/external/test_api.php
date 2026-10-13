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
 * Web service to check the Moodefense API URL and token.
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
use logstore_moodefense\api;

/**
 * Web service behind the "Test API" button of the settings page: checks the given API URL and
 * token, as typed and not saved yet.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_api extends external_api {
    /**
     * Describes the parameters of execute(): the URL and token as typed in the settings.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'apiurl' => new external_value(PARAM_URL, 'API URL to check'),
            'token' => new external_value(PARAM_RAW_TRIMMED, 'API token to check'),
        ]);
    }

    /**
     * Checks the API URL and token, see api::test_connection().
     *
     * @param string $apiurl
     * @param string $token
     * @return array ['success' => bool, 'message' => string]
     */
    public static function execute(string $apiurl, string $token): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('logstore/moodefense:manage', $context);

        $params = self::validate_parameters(self::execute_parameters(), ['apiurl' => $apiurl, 'token' => $token]);
        $result = api::test_connection($params['apiurl'], $params['token']);

        return [
            'success' => (bool)($result['response'] ?? false),
            'message' => (string)($result['message'] ?? ''),
        ];
    }

    /**
     * Describes the result of execute(), checked by the web service layer.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the API answered'),
            'message' => new external_value(PARAM_RAW, 'Result message'),
        ]);
    }
}
