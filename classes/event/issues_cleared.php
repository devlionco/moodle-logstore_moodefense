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

namespace logstore_moodefense\event;

/**
 * An admin deleted all MooDefense issues.
 *
 * @property-read array $other {
 *      - int count: Number of issues deleted.
 * }
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issues_cleared extends \core\event\base {
    /**
     * Sets what the event is about: a deletion, of all issues.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * The event name shown in the logs and the event list.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventissuescleared', 'logstore_moodefense');
    }

    /**
     * The event description shown in the logs: who deleted how many issues.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' deleted all {$this->other['count']} MooDefense issues.";
    }
}
