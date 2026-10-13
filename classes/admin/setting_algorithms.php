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
 * "Active incident types" setting, shown as one table with a row per algorithm: whether it runs
 * ("Detect", this setting) and whether it notifies ("Notify", the setting_algorithm_notifications
 * it is given). The premium algorithms follow the free ones and cannot be enabled - selecting one
 * shows how to get MooDefense Premium instead.
 *
 * Stored like admin_setting_configmulticheckbox (comma-separated IDs), so only free algorithms
 * can ever be saved.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_algorithms extends \admin_setting_configmulticheckbox {
    /** @var setting_algorithm_notifications Setting whose checkboxes form the "Notify" column. */
    protected setting_algorithm_notifications $notifications;

    /**
     * Creates the "Detect" setting: one choice per free algorithm, all enabled by default. Keeps the
     * "Notify" setting to render its checkboxes in the same table.
     *
     * @param string $name Unique setting name, e.g. 'logstore_moodefense/algorithms'.
     * @param string $visiblename Localised setting name.
     * @param string $description Localised setting description.
     * @param setting_algorithm_notifications $notifications Setting shown as the "Notify" column; it
     *     must be added to the same settings page.
     */
    public function __construct($name, $visiblename, $description, setting_algorithm_notifications $notifications) {
        $choices = [];
        foreach (algorithm_type::cases() as $algorithm) {
            $choices[$algorithm->value] = $algorithm->label();
        }
        parent::__construct($name, $visiblename, $description, array_fill_keys(array_keys($choices), 1), $choices);
        $this->notifications = $notifications;
    }

    /**
     * Matches an admin search on either setting, so that searching for a notification option still
     * finds the table where it is edited.
     *
     * @param string $query
     * @return bool
     */
    public function is_related($query) {
        return parent::is_related($query) || $this->notifications->matches($query);
    }

    /**
     * Renders the settings table: a Detect and a Notify checkbox per free algorithm, then the premium
     * ones as buttons opening the contact dialogue (amd/src/settings.js).
     *
     * @param array|null $data Currently enabled algorithm IDs, as keys.
     * @param string $query Admin search query, highlighted in the names.
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT, $PAGE;

        $notify = $this->notifications->get_setting() ?? $this->notifications->get_defaultsetting();
        $describe = fn(int $id): array => [
            'key' => $id,
            'title' => get_string('algorithm' . $id, 'logstore_moodefense'),
            // Not "name": inside the template's loops that would hide the settings' field names.
            'label' => highlightfast($query, get_string('algorithm' . $id, 'logstore_moodefense')),
            'description' => get_string('algorithm' . $id . '_case', 'logstore_moodefense'),
        ];

        $free = [];
        foreach (algorithm_type::cases() as $algorithm) {
            $id = $algorithm->value;
            $free[] = $describe($id) + ['detect' => !empty($data[$id]), 'notify' => !empty($notify[$id])];
        }

        $PAGE->requires->js_call_amd(
            'logstore_moodefense/settings',
            'initAlgorithms',
            [$this->get_id(), algorithm_type::PREMIUM_CONTACT]
        );
        $element = $OUTPUT->render_from_template('logstore_moodefense/algorithms', [
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'notifyid' => $this->notifications->get_id(),
            'notifyname' => $this->notifications->get_full_name(),
            'readonly' => $this->is_readonly(),
            'notifyreadonly' => $this->notifications->is_readonly(),
            'free' => $free,
            'premium' => array_map($describe, algorithm_type::PREMIUM),
        ]);

        return format_admin_setting(
            $this,
            $this->visiblename,
            $element,
            $this->description,
            false,
            '',
            implode(', ', $this->choices),
            $query
        );
    }
}
