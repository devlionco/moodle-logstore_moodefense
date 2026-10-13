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
 * Scheduled task deleting old log entries.
 *
 * @package    logstore_moodefense
 * @author     Devlion <info@devlion.co>
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_moodefense\task;

/**
 * Scheduled task for cleaning up old log records in the Moodefense log store.
 *
 * This class extends the core scheduled task to remove log entries older than the configured lifetime.
 *
 * @package    logstore_moodefense
 * @author     Devlion <info@devlion.co>
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_task extends \core\task\scheduled_task {
    /**
     * The task name shown to admins in the scheduled tasks list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskcleanup', 'logstore_moodefense');
    }

    /**
     * Deletes log entries older than the 'loglifetime' setting, a day at a time and for at most
     * 5 minutes per run, so that enabling it on a big log does not lock the table for long.
     *
     * @throws \dml_exception
     */
    public function execute(): void {
        global $DB;

        $loglifetime = (int)get_config('logstore_moodefense', 'loglifetime');

        if (empty($loglifetime) || $loglifetime < 0) {
            return;
        }

        $loglifetime = time() - $loglifetime * DAYSECS; // Value in days.
        $lifetimep = [$loglifetime];
        $start = time();

        while ($min = $DB->get_field_select("logstore_moodefense_log", "MIN(timecreated)", "timecreated < ?", $lifetimep)) {
            // Break this down into chunks to avoid transaction for too long and generally thrashing database.
            // Experiments suggest deleting one day takes up to a few seconds; probably a reasonable chunk size usually.
            // If the cleanup has just been enabled, it might take e.g a month to clean the years of logs.
            $params = [min($min + DAYSECS, $loglifetime)];
            $DB->delete_records_select("logstore_moodefense_log", "timecreated < ?", $params);
            if (time() > $start + 300) {
                // Do not churn on log deletion for too long each run.
                break;
            }
        }

        mtrace(" Deleted old log records from moodefense store.");
    }
}
