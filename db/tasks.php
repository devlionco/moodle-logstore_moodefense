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
 * Scheduled tasks of the MooDefense log store.
 *
 * @package    logstore_moodefense
 * @author     Devlion <info@devlion.co>
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\logstore_moodefense\task\cleanup_task',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '4',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    [
        'classname' => '\logstore_moodefense\task\processing_task',
        'blocking' => 0,
        // Every 5 minutes: the shortest look-back window of the algorithms is 30 minutes.
        'minute' => '*/5',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    [
        'classname' => '\logstore_moodefense\task\security_announcement_task',
        'blocking' => 0,
        // Daily: the Moodefense API refreshes the announcements once a day, before 09:00.
        'minute' => '0',
        'hour' => '9',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
];
