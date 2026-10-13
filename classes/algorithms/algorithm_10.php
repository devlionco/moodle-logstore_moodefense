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

namespace logstore_moodefense\algorithms;

use logstore_moodefense\api;
use logstore_moodefense\local\algorithm_type;
use logstore_moodefense\local\severity;
use stdClass;

/**
 * Algorithm 10 (free, needs the Moodefense API): a moodle.org security announcement lists the
 * installed Moodle version among the affected ones. One site-wide incident per announcement; the
 * announcement's severity (Minor, Serious, Critical) maps to Low, High, Critical. Only the severities
 * selected in 'securityannouncementseverities' notify. Run daily by security_announcement_task, not by
 * the processing task.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class algorithm_10 extends algorithm {
    /** The algorithm, see algorithm::new_issue(). */
    protected const TYPE = algorithm_type::SECURITY_ANNOUNCEMENT;

    /**
     * Finds the announcements affecting this site. Nothing without the API configured.
     *
     * @return stdClass[]
     */
    protected function detect(): array {
        global $CFG;

        if (!api::configured()) {
            mtrace('Moodefense API URL or token is not set');
            return [];
        }

        $issues = [];
        foreach (api::security_issues() as $announcement) {
            if (!empty($announcement['id']) && self::affects($CFG->release, $announcement['versions_affected_ranges'] ?? [])) {
                $issues[] = $this->issue($announcement);
            }
        }
        return $issues;
    }

    /**
     * Whether the announcement's own severity is one to notify about.
     *
     * @param stdClass $issue
     * @return bool
     */
    protected function notifies(stdClass $issue): bool {
        $selected = explode(',', (string)get_config('logstore_moodefense', 'securityannouncementseverities'));
        return in_array(json_decode($issue->data)->severity ?? '', $selected, true);
    }

    /**
     * Builds the issue about one announcement. Dated by the announcement, so that a resolved one
     * is not reopened by the next run.
     *
     * @param array $announcement See api::security_issues().
     * @return stdClass
     */
    private function issue(array $announcement): stdClass {
        $na = get_string('notavailable', 'moodle');

        return $this->new_issue(
            userid: 0,
            severity: match ($announcement['severity'] ?? '') {
                'Minor' => severity::LOW,
                'Serious' => severity::HIGH,
                'Critical' => severity::CRITICAL,
                default => severity::MEDIUM,
            },
            message: get_string('securityannouncement', 'logstore_moodefense', [
                'ticketid' => $announcement['id'],
                'title' => $announcement['title'] ?? '',
                'cve' => $announcement['cve_identifier'] ?? $na,
                'affected' => $announcement['versions_affected'] ?? $na,
                'fixed' => $announcement['versions_fixed'] ?? $na,
                'link' => $announcement['link'] ?? '',
            ]),
            dateissue: strtotime($announcement['date'] ?? '') ?: 0,
            data: array_intersect_key($announcement, array_flip(
                ['id', 'title', 'severity', 'cve_identifier', 'versions_affected', 'versions_fixed', 'link']
            )),
            issuekey: $announcement['id'],
        );
    }

    /**
     * Whether a Moodle release (e.g. "5.0.2+ (Build: 20250815)") is in one of the version ranges.
     *
     * @param string $release
     * @param array[] $ranges [['min' => '4.5', 'max' => '4.5.5'], ...], a missing bound is open.
     * @return bool
     */
    public static function affects(string $release, array $ranges): bool {
        if (!preg_match('/\d+(?:\.\d+){1,2}/', $release, $match)) {
            return false;
        }
        foreach ($ranges as $range) {
            if (
                version_compare($match[0], $range['min'] ?? '0', '>=')
                && version_compare($match[0], $range['max'] ?? '999999', '<=')
            ) {
                return true;
            }
        }
        return false;
    }
}
