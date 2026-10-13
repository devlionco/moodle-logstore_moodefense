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
 * Upgrade steps for logstore_moodefense.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the plugin's data and schema from an older version, step by step.
 *
 * @param int $oldversion Version being upgraded from.
 * @return bool
 */
function xmldb_logstore_moodefense_upgrade($oldversion) {
    global $CFG, $DB;

    if ($oldversion < 2026093000) {
        // The premium algorithms (1, 3, 6, 8, 9, 10) are no longer part of this plugin: drop their issues,
        // keep only free algorithms selected, and drop what only they used.
        $DB->delete_records_list('logstore_moodefense_issues', 'algorithm', [1, 3, 6, 8, 9, 10]);

        $free = [2, 4, 5, 7];
        foreach (['algorithms', 'algorithms_notifications'] as $name) {
            $selected = explode(',', (string)get_config('logstore_moodefense', $name));
            set_config($name, implode(',', array_intersect($free, $selected)), 'logstore_moodefense');
        }
        unset_config('securityannouncementseverities', 'logstore_moodefense');

        // Algorithm 9's file integrity snapshot (build.json).
        fulldelete($CFG->dataroot . '/moodefense');

        upgrade_plugin_savepoint(true, 2026093000, 'logstore', 'moodefense');
    }

    if ($oldversion < 2026093002) {
        // Countries are only looked up in Moodle's GeoIP2 database: the Moodefense API is not used.
        unset_config('apiurl', 'logstore_moodefense');
        unset_config('token', 'logstore_moodefense');

        upgrade_plugin_savepoint(true, 2026093002, 'logstore', 'moodefense');
    }

    if ($oldversion < 2026100700) {
        $dbman = $DB->get_manager();

        // Issues are matched to an incident by algorithm, user and an indexed key instead of LIKE on JSON.
        $table = new xmldb_table('logstore_moodefense_issues');
        $field = new xmldb_field('issuekey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '', 'algorithm');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Algorithm 2 keys its issues by the suspected user, algorithm 7 by the attacking IP address.
        $keys = [2 => 'suspecteduid', 7 => 'ip'];
        [$insql, $params] = $DB->get_in_or_equal(array_keys($keys));
        $rs = $DB->get_recordset_select(
            'logstore_moodefense_issues',
            "algorithm $insql AND issuekey = ''",
            $params,
            '',
            'id, algorithm, data'
        );
        foreach ($rs as $issue) {
            $key = (string)(json_decode($issue->data, true)[$keys[$issue->algorithm]] ?? '');
            if ($key !== '') {
                $DB->set_field('logstore_moodefense_issues', 'issuekey', $key, ['id' => $issue->id]);
            }
        }
        $rs->close();

        $indexes = [
            'logstore_moodefense_issues' => [
                'drop' => [['severity', ['severity']], ['status', ['status']], ['algorithm', ['algorithm']]],
                'add' => [
                    ['severity-date', ['severity', 'dateissue']],
                    ['status-date', ['status', 'dateissue']],
                    ['algorithm-key', ['algorithm', 'issuekey']],
                    ['dateissue', ['dateissue']],
                ],
            ],
            // The detection queries now read covering indexes; indexes no query uses only slowed down logging.
            'logstore_moodefense_log' => [
                'drop' => [
                    ['timecreated', ['timecreated']],
                    ['ipint', ['ipint']],
                    ['origin', ['origin']],
                    ['useragent', ['useragent']],
                    ['useragentint', ['useragentint']],
                    ['sessionidentifier', ['sessionidentifier']],
                ],
                'add' => [
                    ['time-user-agent-ip', ['timecreated', 'userid', 'useragentint', 'ipint']],
                    ['event-time', ['eventname', 'timecreated', 'userid', 'ipint', 'sessionidentifier', 'origin']],
                ],
            ],
        ];
        foreach ($indexes as $tablename => $changes) {
            $table = new xmldb_table($tablename);
            foreach ($changes['drop'] as [$name, $fields]) {
                $index = new xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, $fields);
                if ($dbman->index_exists($table, $index)) {
                    $dbman->drop_index($table, $index);
                }
            }
            foreach ($changes['add'] as [$name, $fields]) {
                $index = new xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, $fields);
                if (!$dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026100700, 'logstore', 'moodefense');
    }

    return true;
}
