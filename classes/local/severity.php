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

namespace logstore_moodefense\local;

/**
 * Severity of an issue, stored as its value in logstore_moodefense_issues.severity.
 *
 * Cases are declared from the most to the least severe: cases() is the display order.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum severity: string {
    case CRITICAL = 'Critical';
    case HIGH = 'High';
    case MEDIUM = 'Medium';
    case LOW = 'Low';

    /**
     * The severity name shown to admins, in the issues filter and the dashboard chart.
     *
     * @return string
     */
    public function label(): string {
        return get_string('severity' . strtolower($this->value), 'logstore_moodefense');
    }

    /**
     * The colour of the severity in the dashboard chart, matching the badges of the issues table.
     *
     * @return string
     */
    public function color(): string {
        return match ($this) {
            self::CRITICAL => '#c82333',
            self::HIGH => '#c2410c',
            self::MEDIUM => '#ffc107',
            self::LOW => '#1e7e34',
        };
    }

    /**
     * Whether this severity is worse than the other one, so that a stored issue that became more
     * severe is refreshed and notified about again.
     *
     * @param severity $other
     * @return bool
     */
    public function outranks(severity $other): bool {
        $order = self::cases();
        return array_search($this, $order, true) < array_search($other, $order, true);
    }
}
