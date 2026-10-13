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

namespace logstore_moodefense\task;

use logstore_moodefense\local\algorithm_type;

/**
 * Scheduled task running the detection algorithms enabled in the 'algorithms' setting, except
 * algorithm 10, run daily by security_announcement_task.
 *
 * @package    logstore_moodefense
 * @author     Devlion <info@devlion.co>
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class processing_task extends \core\task\scheduled_task {
    /**
     * The task name shown to admins in the scheduled tasks list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskprocessing', 'logstore_moodefense');
    }

    /**
     * Runs every enabled algorithm, every 5 minutes. A failing one is reported in the task log and
     * does not stop the others. It does not fail the task either: Moodle would then postpone the
     * task by up to a day, and every algorithm with it.
     */
    public function execute(): void {
        // Read the setting rather than get_log_manager(), whose store list is cached for the
        // lifetime of the process (e.g. a long-running cron runner).
        if (!in_array('logstore_moodefense', explode(',', (string)get_config('tool_log', 'enabled_stores')), true)) {
            mtrace('Please enable logging MooDefense');
            return;
        }

        foreach (algorithm_type::enabled() as $algorithm) {
            if ($algorithm === algorithm_type::SECURITY_ANNOUNCEMENT) {
                continue;
            }
            mtrace("Running algorithm {$algorithm->value}");
            try {
                (new ($algorithm->classname()))->run();
            } catch (\Throwable $e) {
                mtrace("Algorithm {$algorithm->value} failed: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }
    }
}
