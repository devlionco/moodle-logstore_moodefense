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
 * Web service to clear all issues from the Moodefense log store.
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
use Exception;
use invalid_parameter_exception;
use moodle_exception;

/**
 * Web service for clearing the Moodefense issues table.
 *
 * Requires the logstore/moodefense:manage capability.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clear_table extends external_api {
    /**
     * Deletes all issues, for the "Clear all history" button of the issues page, and logs it with
     * the issues_cleared event so that the deletion leaves a trace.
     *
     * @return array [success => true].
     * @throws moodle_exception If the issues could not be deleted.
     * @throws invalid_parameter_exception
     */
    public static function execute(): array {
        global $DB;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('logstore/moodefense:manage', $context);

        try {
            $count = $DB->count_records('logstore_moodefense_issues');
            $DB->delete_records('logstore_moodefense_issues');
            \logstore_moodefense\event\issues_cleared::create(['context' => $context, 'other' => ['count' => $count]])->trigger();
            return ['success' => true];
        } catch (Exception $e) {
            throw new moodle_exception('errorclearingtable', 'logstore_moodefense', '', $e->getMessage());
        }
    }

    /**
     * Describes the parameters of execute(): none.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Describes the result of execute(), checked by the web service layer.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
        ]);
    }
}
