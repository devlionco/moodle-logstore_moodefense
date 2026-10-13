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
 * Privacy API implementation for logstore_moodefense.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_moodefense\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use logstore_moodefense\local\algorithm_type;
use tool_log\local\privacy\helper;

/**
 * Privacy provider for the MooDefense log store.
 *
 * As a log store, requests reach this plugin through tool_log (the logstore_provider interfaces),
 * exactly like logstore_standard. The plugin stores personal data in two tables:
 *   - logstore_moodefense_log: raw event logs, in the context of each event. Exported and
 *     deleted like logstore_standard's (tool_log helper and trait), matching userid, relateduserid
 *     and realuserid.
 *   - logstore_moodefense_issues: detected security incidents, all in the system context. An issue
 *     belongs to its userid, and algorithm 2 issues also hold a second (suspected) user in issuekey.
 *     Issues are exported but never deleted on request: they are the evidence of security incidents,
 *     kept on the grounds of the site's legitimate interest in its security (GDPR articles 6(1)(f)
 *     and 17(3)(e)) - otherwise a suspect could have the evidence against them erased. Admins delete
 *     issues themselves ("Clear all history"), which is logged.
 *
 * It also sends personal data to admin-configured email addresses and in site admins' notifications
 * when an issue is detected.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \tool_log\local\privacy\logstore_provider,
    \tool_log\local\privacy\logstore_userlist_provider {
    // Deletes log entries like logstore_standard does; issues are kept, see the class description.
    use \tool_log\local\privacy\moodle_database_export_and_delete;

    /** @var string[] Log columns beyond logstore_standard's, which core's event restore rejects. */
    protected const EXTRA_LOG_FIELDS = ['ipint', 'useragent', 'useragentint', 'sessionidentifier'];

    /**
     * Describes the personal data the plugin stores and sends, for the privacy registry.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('logstore_moodefense_log', [
            'eventname'         => 'privacy:metadata:logstore_moodefense_log:eventname',
            'userid'            => 'privacy:metadata:logstore_moodefense_log:userid',
            'courseid'          => 'privacy:metadata:logstore_moodefense_log:courseid',
            'relateduserid'     => 'privacy:metadata:logstore_moodefense_log:relateduserid',
            'realuserid'        => 'privacy:metadata:logstore_moodefense_log:realuserid',
            'anonymous'         => 'privacy:metadata:logstore_moodefense_log:anonymous',
            'other'             => 'privacy:metadata:logstore_moodefense_log:other',
            'origin'            => 'privacy:metadata:logstore_moodefense_log:origin',
            'ip'                => 'privacy:metadata:logstore_moodefense_log:ip',
            'ipint'             => 'privacy:metadata:logstore_moodefense_log:ipint',
            'useragent'         => 'privacy:metadata:logstore_moodefense_log:useragent',
            'useragentint'      => 'privacy:metadata:logstore_moodefense_log:useragentint',
            'sessionidentifier' => 'privacy:metadata:logstore_moodefense_log:sessionidentifier',
            'timecreated'       => 'privacy:metadata:logstore_moodefense_log:timecreated',
        ], 'privacy:metadata:logstore_moodefense_log');

        $collection->add_database_table('logstore_moodefense_issues', [
            'userid'      => 'privacy:metadata:logstore_moodefense_issues:userid',
            'roles'       => 'privacy:metadata:logstore_moodefense_issues:roles',
            'severity'    => 'privacy:metadata:logstore_moodefense_issues:severity',
            'status'      => 'privacy:metadata:logstore_moodefense_issues:status',
            'message'     => 'privacy:metadata:logstore_moodefense_issues:message',
            'comment'     => 'privacy:metadata:logstore_moodefense_issues:comment',
            'data'        => 'privacy:metadata:logstore_moodefense_issues:data',
            'dateissue'   => 'privacy:metadata:logstore_moodefense_issues:dateissue',
            'timecreated' => 'privacy:metadata:logstore_moodefense_issues:timecreated',
        ], 'privacy:metadata:logstore_moodefense_issues');

        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');

        $collection->add_external_location_link('moodefense_email', [
            'fullname'   => 'privacy:metadata:moodefense_email:fullname',
            'username'   => 'privacy:metadata:moodefense_email:username',
            'idnumber'   => 'privacy:metadata:moodefense_email:idnumber',
            'lastaccess' => 'privacy:metadata:moodefense_email:lastaccess',
            'lastip'     => 'privacy:metadata:moodefense_email:lastip',
        ], 'privacy:metadata:moodefense_email');

        return $collection;
    }

    /**
     * Adds the contexts holding data of the user, for a data request: those of their log entries,
     * plus the system context when any issue involves them.
     *
     * @param contextlist $contextlist The contextlist to add the contexts to.
     * @param int $userid The user to find the contexts for.
     */
    public static function add_contexts_for_userid(contextlist $contextlist, $userid) {
        global $DB;

        $contextlist->add_from_sql("
            SELECT contextid
              FROM {logstore_moodefense_log}
             WHERE userid = :userid1 OR relateduserid = :userid2 OR realuserid = :userid3", [
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
        ]);

        [$select, $params] = static::issues_of_users_select([$userid]);
        if ($DB->record_exists_select('logstore_moodefense_issues', $select, $params)) {
            $contextlist->add_system_context();
        }
    }

    /**
     * Adds the users with data in the context, for a data request: log users of that context and,
     * for the system context, every user an issue involves.
     *
     * @param userlist $userlist The userlist to add the users to.
     */
    public static function add_userids_for_context(userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $sql = "SELECT userid, relateduserid, realuserid
                  FROM {logstore_moodefense_log}
                 WHERE contextid = :contextid";
        $params = ['contextid' => $context->id];
        $userlist->add_from_sql('userid', $sql, $params);
        $userlist->add_from_sql('relateduserid', $sql, $params);
        $userlist->add_from_sql('realuserid', $sql, $params);

        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {logstore_moodefense_issues} WHERE userid > 0", []);
        $userlist->add_from_sql(
            'suspecteduid',
            "SELECT " . $DB->sql_cast_char2int('issuekey') . " AS suspecteduid
               FROM {logstore_moodefense_issues}
              WHERE algorithm = :algorithm",
            ['algorithm' => algorithm_type::SHARED_DEVICE->value]
        );
    }

    /**
     * Exports the user's data, for a data request: their log entries (per context) and the issues
     * involving them (system context).
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        static::export_log_data($contextlist);

        $system = \context_system::instance();
        if (!in_array($system->id, $contextlist->get_contextids())) {
            return;
        }
        [$select, $params] = static::issues_of_users_select([$contextlist->get_user()->id]);
        $issues = $DB->get_records_select('logstore_moodefense_issues', $select, $params, 'timecreated, id');
        if ($issues) {
            writer::with_context($system)->export_data(
                static::get_issues_export_subcontext(),
                (object)['issues' => array_values($issues)]
            );
        }
    }

    /**
     * Exports the user's log entries, one file per context, like the tool_log trait does - but
     * without this store's extra columns, which the event restore rejects, and with the user agent
     * of the user's own entries, which is personal data the trait would drop.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    protected static function export_log_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        [$insql, $inparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $select = "(userid = :userid1 OR relateduserid = :userid2 OR realuserid = :userid3) AND contextid $insql";
        $params = array_merge($inparams, ['userid1' => $userid, 'userid2' => $userid, 'userid3' => $userid]);

        $flush = function (int $contextid, array $logs): void {
            writer::with_context(\context::instance_by_id($contextid))
                ->export_data(static::get_export_subcontext(), (object)['logs' => $logs]);
        };

        $lastcontextid = null;
        $logs = [];
        $rs = $DB->get_recordset_select('logstore_moodefense_log', $select, $params, 'contextid, timecreated, id');
        foreach ($rs as $record) {
            if ($lastcontextid && $lastcontextid != $record->contextid) {
                $flush($lastcontextid, $logs);
                $logs = [];
            }
            $useragent = $record->useragent;
            foreach (static::EXTRA_LOG_FIELDS as $field) {
                unset($record->$field);
            }
            $log = helper::transform_standard_log_record_for_userid($record, $userid);
            if ($record->userid == $userid) {
                $log['useragent'] = $useragent;
            }
            $logs[] = $log;
            $lastcontextid = (int)$record->contextid;
        }
        $rs->close();
        if ($lastcontextid) {
            $flush($lastcontextid, $logs);
        }
    }

    /**
     * SQL selecting the issues involving the users, for finding and exporting them: their own, plus
     * algorithm 2 issues naming them as the suspected user (algorithm_2 keys its issues by them).
     *
     * @param int[] $userids
     * @return array [select, params]
     */
    protected static function issues_of_users_select(array $userids): array {
        global $DB;

        [$useridsql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');
        [$keysql, $keyparams] = $DB->get_in_or_equal(array_map('strval', $userids), SQL_PARAMS_NAMED, 'key');
        $select = "userid $useridsql OR (algorithm = :algorithm AND issuekey $keysql)";
        return [$select, $params + $keyparams + ['algorithm' => algorithm_type::SHARED_DEVICE->value]];
    }

    /**
     * The database and table of the log entries, for the tool_log export and delete trait.
     *
     * @return array [moodle_database, table name]
     */
    protected static function get_database_and_table() {
        global $DB;
        return [$DB, 'logstore_moodefense_log'];
    }

    /**
     * The path to export the log entries to, for the tool_log export and delete trait.
     *
     * @return array
     */
    protected static function get_export_subcontext() {
        return [get_string('privacy:path:logs', 'tool_log'), get_string('pluginname', 'logstore_moodefense')];
    }

    /**
     * The path to export the issues to, next to the log entries.
     *
     * @return array
     */
    protected static function get_issues_export_subcontext(): array {
        return [get_string('privacy:path:logs', 'tool_log'), get_string('pluginname', 'logstore_moodefense'),
            get_string('privacy:path:issues', 'logstore_moodefense')];
    }
}
