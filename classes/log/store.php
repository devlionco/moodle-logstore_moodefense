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
 * Moodefense log reader/writer.
 *
 * @package    logstore_moodefense
 * @author     Devlion <info@devlion.co>
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_moodefense\log;

/**
 * Log store implementation for Moodefense.
 *
 * This class implements the log writer and reader interfaces to handle logging
 * and retrieval of events for the Moodefense log store, including IP and user agent data.
 *
 * @package    logstore_moodefense
 * @author     Devlion <info@devlion.co>
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class store implements \core\log\sql_internal_table_reader, \tool_log\log\writer {
    use \tool_log\helper\store;
    use \tool_log\helper\buffered_writer;
    use \tool_log\helper\reader;

    /** @var bool Whether guests and not-logged-in users are logged (setting logguests). */
    protected $logguests;

    /**
     * Creates the store for the log manager, reading the settings it needs on every event.
     *
     * @param \tool_log\log\manager $manager The log manager instance.
     */
    public function __construct(\tool_log\log\manager $manager) {
        $this->helper_setup($manager);
        // Log everything before setting is saved for the first time.
        $this->logguests = $this->get_config('logguests', 1);
        // JSON writing defaults to false (table format compatibility with older versions).
        // Note: This variable is defined in the buffered_writer trait.
        $this->jsonformat = (bool)$this->get_config('jsonformat', false);
    }

    /**
     * Whether an event is not logged: CLI events have no request to analyse, and guests are only
     * logged with logguests - except failed logins, which password guessing detection needs.
     *
     * @param \core\event\base $event The event to check.
     * @return bool True if the event should be ignored, false otherwise.
     */
    protected function is_event_ignored(\core\event\base $event) {
        if (CLI_SCRIPT) {
            // CLI events have no real IP/useragent to analyse, so they are just noise for this store.
            return true;
        }
        // Failed logins happen before anyone is logged in, and algorithm 7 needs them whatever logguests says.
        if (!$this->logguests && (!isloggedin() || isguestuser()) && !$event instanceof \core\event\user_login_failed) {
            return true;
        }
        return false;
    }

    /**
     * Writes a buffer of events to the log table, with the IP address and device columns the
     * algorithms query. Called by the buffered writer when its buffer is full or the request ends.
     *
     * @param array $evententries Raw event data to be stored.
     */
    protected function insert_event_entries($evententries) {
        global $DB;

        // The useragent column is 255 characters long, real user agents can be longer.
        $useragent = \core_text::substr(\core_useragent::get_user_agent_string() ?: 'CLI/NoUserAgent', 0, 255);

        foreach ($evententries as &$entry) {
            $entry = self::request_fields($entry['ip'] ?? null, $useragent) + $entry;
        }

        $DB->insert_records('logstore_moodefense_log', $evententries);
    }

    /**
     * The columns this store derives from the request's IP address and user agent, for the log and
     * the tests' generator alike.
     *
     * The session identifier stands for the device: a 60-bit hash of both, which unlike a 32-bit one
     * does not make two of the many devices active at once look alike.
     *
     * @param string|null $ip
     * @param string $useragent At most 255 characters.
     * @return array ipint, useragent, useragentint and sessionidentifier.
     */
    public static function request_fields(?string $ip, string $useragent): array {
        $ip = filter_var((string)$ip, FILTER_VALIDATE_IP) ?: '';
        return [
            // IPv6 addresses do not fit an int: their crc32 is a numeric fingerprint.
            'ipint' => $ip === '' ? 0 : (ip2long($ip) ?: (int)sprintf('%u', crc32($ip))),
            'useragent' => $useragent,
            'useragentint' => (int)sprintf('%u', crc32($useragent)),
            'sessionidentifier' => hexdec(substr(sha1($ip . '|' . $useragent), 0, 15)),
        ];
    }

    /**
     * Reads logged events as event objects, for log reports (report_log) reading this store.
     *
     * @param string $selectwhere SQL WHERE clause for selecting events.
     * @param array $params Parameters for the SQL query.
     * @param string $sort SQL ORDER BY clause for sorting results.
     * @param int $limitfrom Offset for pagination.
     * @param int $limitnum Number of records to return.
     * @return array Array of event objects.
     */
    public function get_events_select($selectwhere, array $params, $sort, $limitfrom, $limitnum) {
        global $DB;

        $sort = self::tweak_sort_by_id($sort);

        $events = [];
        $records = $DB->get_recordset_select('logstore_moodefense_log', $selectwhere, $params, $sort, '*', $limitfrom, $limitnum);

        foreach ($records as $data) {
            if ($event = $this->get_log_event($data)) {
                $events[$data->id] = $event;
            }
        }

        $records->close();

        return $events;
    }

    /**
     * Reads logged events one by one, for log reports reading this store without loading all of
     * them into memory.
     *
     * @param string $selectwhere SQL WHERE clause for selecting events.
     * @param array $params Parameters for the SQL query.
     * @param string $sort SQL ORDER BY clause for sorting results.
     * @param int $limitfrom Offset for pagination.
     * @param int $limitnum Number of records to return.
     * @return \core\dml\recordset_walk|\core\event\base[] Traversable object of events.
     */
    public function get_events_select_iterator($selectwhere, array $params, $sort, $limitfrom, $limitnum) {
        global $DB;

        $sort = self::tweak_sort_by_id($sort);

        $recordset = $DB->get_recordset_select('logstore_moodefense_log', $selectwhere, $params, $sort, '*', $limitfrom, $limitnum);

        return new \core\dml\recordset_walk($recordset, [$this, 'get_log_event']);
    }

    /**
     * Restores the event a log row was made from, for the readers above.
     *
     * @param object $data Log data record.
     * @return \core\event\base|null The restored event object or null if restoration fails.
     */
    public function get_log_event($data) {
        $extra = [
            'origin' => $data->origin,
            'ip' => $data->ip,
            'realuserid' => $data->realuserid,
            'ipint' => $data->ipint,
            'useragent' => $data->useragent,
            'sessionidentifier' => $data->sessionidentifier,
            'useragentint' => $data->useragentint,
        ];
        $data = (array)$data;
        $id = $data['id'];
        $data['other'] = self::decode_other($data['other']);
        if ($data['other'] === false) {
            $data['other'] = [];
        }
        unset($data['origin']);
        unset($data['ip']);
        unset($data['realuserid']);
        unset($data['id']);
        unset($data['ipint']);
        unset($data['useragent']);
        unset($data['useragentint']);
        unset($data['sessionidentifier']);

        if (!$event = \core\event\base::restore($data, $extra)) {
            return null;
        }

        return $event;
    }

    /**
     * Counts logged events, for paging in log reports.
     *
     * @param string $selectwhere SQL WHERE clause for selecting events.
     * @param array $params Parameters for the SQL query.
     * @return int Number of events available for the given conditions.
     */
    public function get_events_select_count($selectwhere, array $params) {
        global $DB;
        return $DB->count_records_select('logstore_moodefense_log', $selectwhere, $params);
    }

    /**
     * Whether any logged event matches, for log reports.
     *
     * @param string $selectwhere SQL WHERE clause for selecting events.
     * @param array $params Parameters for the SQL query.
     * @return bool Whether events are available for the given conditions.
     */
    public function get_events_select_exists(string $selectwhere, array $params): bool {
        global $DB;
        return $DB->record_exists_select('logstore_moodefense_log', $selectwhere, $params);
    }

    /**
     * The log table, for reports that query it with SQL directly.
     *
     * @return string The name of the log table.
     */
    public function get_internal_log_table_name() {
        return 'logstore_moodefense_log';
    }

    /**
     * Whether new events keep appearing in this reader: always, as only enabled stores are read.
     *
     * @return bool True means new log events are being added, false means no new data will be added.
     */
    public function is_logging() {
        // Only enabled stores are queried,
        // this means we can return true here unless store has some extra switch.
        return true;
    }
}
