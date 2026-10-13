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

use logstore_moodefense\algorithms\algorithm_10;
use logstore_moodefense\local\algorithm_type;

/**
 * Scheduled task running algorithm 10 (security announcements) once a day, when it is enabled in the
 * 'algorithms' setting: the Moodefense API refreshes the announcements daily, so the processing task's
 * 5-minute schedule would only repeat the same request.
 *
 * @package    logstore_moodefense
 * @author     Devlion <info@devlion.co>
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class security_announcement_task extends \core\task\scheduled_task {
    /**
     * The task name shown to admins in the scheduled tasks list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksecurityannouncement', 'logstore_moodefense');
    }

    /**
     * Runs algorithm 10. A failure (e.g. the API is unreachable) fails this task alone: cron writes the
     * error to the task log, marks the task failed and goes on with the other tasks.
     */
    public function execute(): void {
        if (!in_array(algorithm_type::SECURITY_ANNOUNCEMENT, algorithm_type::enabled(), true)) {
            mtrace('Algorithm 10 is not enabled');
            return;
        }

        (new algorithm_10())->run();
    }
}
