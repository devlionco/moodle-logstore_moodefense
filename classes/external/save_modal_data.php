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
 * Web service: Save status and comment for a Moodefense issue from the modal dialog.
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
use context_system;
use logstore_moodefense\local\status;
use moodle_exception;

/**
 * Web service to update status and/or comment of a Moodefense issue record.
 *
 * Requires the logstore/moodefense:manage capability.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_modal_data extends external_api {
    /**
     * Describes the parameters of execute(): the issue, and the status and comment, either optional.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'recordid' => new external_value(PARAM_INT, 'ID of the issue record', VALUE_REQUIRED),
            'status'   => new external_value(PARAM_INT, 'New status value (0,1,2)', VALUE_DEFAULT, null),
            'comment'  => new external_value(PARAM_TEXT, 'Administrator comment', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Saves the status and/or comment an admin set in the issue details dialogue, and logs it with
     * the issue_updated event.
     *
     * @param int $recordid The issue.
     * @param int|null $status New status, null to keep it.
     * @param string|null $comment New comment, null to keep it.
     * @return array Response with success status and message
     * @throws moodle_exception
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     */
    public static function execute(int $recordid, ?int $status = null, ?string $comment = null): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'recordid' => $recordid,
            'status'   => $status,
            'comment'  => $comment,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        require_capability('logstore/moodefense:manage', $context);

        $record = $DB->get_record('logstore_moodefense_issues', ['id' => $params['recordid']], '*', MUST_EXIST);

        $updatedata = (object)[
            'id' => $record->id,
        ];
        $needsupdate = false;

        if ($params['status'] !== null) {
            if (!status::tryFrom($params['status'])) {
                throw new \invalid_parameter_exception('Invalid status: ' . $params['status']);
            }
            $updatedata->status = $params['status'];
            $needsupdate = true;
        }

        if ($params['comment'] !== null) {
            $updatedata->comment = trim($params['comment']);
            $needsupdate = true;
        }

        if ($needsupdate) {
            $DB->update_record('logstore_moodefense_issues', $updatedata);
            \logstore_moodefense\event\issue_updated::create([
                'context' => $context,
                'objectid' => $record->id,
                'relateduserid' => $record->userid ?: null,
                'other' => ['status' => $params['status']],
            ])->trigger();
        }

        return [
            'success' => true,
            'message' => get_string('datasaved', 'logstore_moodefense'),
        ];
    }

    /**
     * Describes the result of execute(), checked by the web service layer.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the update was successful'),
            'message' => new external_value(PARAM_TEXT, 'Confirmation message'),
        ]);
    }
}
