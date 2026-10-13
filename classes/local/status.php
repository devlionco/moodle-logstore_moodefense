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
 * Review status of an issue, stored as its value in logstore_moodefense_issues.status.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum status: int {
    // The issue requires attention.
    case OPENED = 0;
    // The issue was reviewed and is not relevant.
    case NOT_RELEVANT = 1;
    // The issue was resolved.
    case RESOLVED = 2;

    /**
     * The status name shown to admins.
     *
     * @return string
     */
    public function label(): string {
        return get_string(match ($this) {
            self::OPENED => 'status_opened',
            self::NOT_RELEVANT => 'status_notrelevant',
            self::RESOLVED => 'status_resolved',
        }, 'logstore_moodefense');
    }

    /**
     * The status name of a stored status value, "Unknown" for a value that is not a status, for
     * pages that show stored issues.
     *
     * @param int $value
     * @return string
     */
    public static function label_of(int $value): string {
        return self::tryFrom($value)?->label() ?? get_string('status_unknown', 'logstore_moodefense');
    }
}
