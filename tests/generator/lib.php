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
 * Data generator for logstore_moodefense: log entries and issues.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logstore_moodefense_generator extends component_generator_base {
    /**
     * Creates a logstore_moodefense_log entry, deriving ipint/useragentint/sessionidentifier from
     * ip and useragent with \logstore_moodefense\log\store::request_fields().
     *
     * @param array $record Field overrides; typically userid, eventname, ip, useragent, timecreated.
     * @return stdClass
     */
    public function create_log(array $record = []): stdClass {
        global $DB;

        $record = (object)array_merge([
            'eventname' => '\core\event\user_loggedin',
            'component' => 'core',
            'action' => 'loggedin',
            'target' => 'user',
            'crud' => 'r',
            'edulevel' => 0,
            'contextid' => context_system::instance()->id,
            'contextlevel' => CONTEXT_SYSTEM,
            'contextinstanceid' => 0,
            'userid' => 0,
            'courseid' => 0,
            'anonymous' => 0,
            'timecreated' => time(),
            'origin' => 'web',
            'ip' => '10.0.0.1',
            'useragent' => 'Mozilla/5.0 (phpunit)',
        ], $record);

        $record = (object)(\logstore_moodefense\log\store::request_fields($record->ip, $record->useragent) + (array)$record);
        $record->id = $DB->insert_record('logstore_moodefense_log', $record);

        return $record;
    }

    /**
     * Creates a logstore_moodefense_issues record.
     *
     * @param array $record Field overrides; data may be given as an array.
     * @return stdClass
     */
    public function create_issue(array $record = []): stdClass {
        global $DB;

        $record = (object)array_merge([
            'userid' => 0,
            'algorithm' => 7,
            'issuekey' => '',
            'severity' => 'Medium',
            'status' => 0,
            'comment' => null,
            'roles' => '',
            'data' => [],
            'message' => 'Test issue',
            'dateissue' => time(),
            'timecreated' => time(),
        ], $record);

        if (!is_string($record->data)) {
            $record->data = json_encode($record->data);
        }
        $record->id = $DB->insert_record('logstore_moodefense_issues', $record);

        return $record;
    }
}
