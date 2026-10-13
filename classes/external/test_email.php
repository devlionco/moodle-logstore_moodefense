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
 * Web service to send a test email to the notification addresses.
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
use logstore_moodefense\notifier;

/**
 * Web service behind the "Test email" button of the settings page: sends a test email to the
 * given notification addresses.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_email extends external_api {
    /**
     * Describes the parameters of execute(): the addresses as typed in the setting.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'emails' => new external_value(PARAM_RAW_TRIMMED, 'Comma-separated email addresses to send a test email to'),
        ]);
    }

    /**
     * Sends a test email to the addresses typed in the notification email setting, for its "Test
     * email" button, so that admins can check them before saving.
     *
     * @param string $emails Comma-separated email addresses.
     * @return array ['success' => bool, 'message' => string]
     */
    public static function execute(string $emails): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('logstore/moodefense:manage', $context);

        ['emails' => $emails] = self::validate_parameters(self::execute_parameters(), ['emails' => $emails]);
        $result = notifier::send_test_email($emails);

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
            'success' => new external_value(PARAM_BOOL, 'Whether the test email was sent'),
            'message' => new external_value(PARAM_RAW, 'Result message'),
        ]);
    }
}
