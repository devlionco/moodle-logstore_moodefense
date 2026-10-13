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

namespace logstore_moodefense\admin;

use logstore_moodefense\local\algorithm_type;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * "Algorithms with notifications" setting: which free algorithms notify when they raise an issue.
 *
 * Saved like any multi-checkbox setting, but it renders nothing of its own - its checkboxes are the
 * "Notify" column of setting_algorithms, so both choices sit on one row per algorithm.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_algorithm_notifications extends \admin_setting_configmulticheckbox {
    /**
     * Creates the "Notify" setting: one choice per free algorithm, all notifying by default.
     *
     * @param string $name Unique setting name, e.g. 'logstore_moodefense/algorithms_notifications'.
     * @param string $visiblename Localised setting name.
     * @param string $description Localised setting description.
     */
    public function __construct($name, $visiblename, $description) {
        $choices = [];
        foreach (algorithm_type::cases() as $algorithm) {
            $choices[$algorithm->value] = $algorithm->label();
        }
        parent::__construct($name, $visiblename, $description, array_fill_keys(array_keys($choices), 1), $choices);
    }

    /**
     * Renders nothing: its checkboxes are the "Notify" column of setting_algorithms, which renders
     * both settings as one table.
     *
     * @param array|null $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        return '';
    }

    /**
     * Keeps the admin search from listing this setting on its own: setting_algorithms, which shows
     * its checkboxes, matches for both.
     *
     * @param string $query
     * @return bool
     */
    public function is_related($query) {
        return false;
    }

    /**
     * Whether an admin search matches this setting, for setting_algorithms::is_related() - the
     * match is_related() would normally report.
     *
     * @param string $query
     * @return bool
     */
    public function matches($query): bool {
        return parent::is_related($query);
    }
}
