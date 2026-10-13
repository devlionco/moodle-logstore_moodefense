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

use moodle_url;
use stdClass;

/**
 * The fields of an issue the details modal lists, below the ones every issue has (those are fixed in
 * templates/issue_details.mustache). The template renders whatever this class builds; amd/src/table.js
 * only adds the countries of the IP addresses and expands a long list of them.
 *
 * - Which fields, in which order: algorithm_type::details(), per algorithm. Each is labelled by the
 *   'modal_<key>' string (tests/local/algorithm_type_test.php checks they all exist).
 * - The keys about the issue's user (username, roles, userid, ips, profilelink, loglink) are computed in
 *   field() and win over a key of the same name in the issue's data.
 * - Any other key is read from the issue's data, which must hold a scalar there: shown as stored, a web
 *   address as a link, nothing as N/A.
 * - A field shown some other way than text, link or IP list takes an arm in field() and a branch in the
 *   template.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_details {
    /** IP addresses listed before the "N more" button. */
    public const VISIBLE_IPS = 3;

    /**
     * The fields of an issue, in the template's shape: each has a label and one of text, url (with
     * text as the link's text) or ips (each {ip, url, hidden}, with ipsmore the number hidden).
     *
     * @param string[] $keys See algorithm_type::details().
     * @param stdClass $record The issue, with the user's name fields and uid (see issues_filter::get_page()).
     * @param array $data The issue's decoded data.
     * @return array[]
     */
    public static function fields(array $keys, stdClass $record, array $data): array {
        $fields = [];
        foreach ($keys as $key) {
            $fields[] = ['label' => get_string('modal_' . $key, 'logstore_moodefense')] + self::field($key, $record, $data);
        }
        return $fields;
    }

    /**
     * The IP addresses behind an issue: algorithm 4 stores a list of them, the others a single one.
     *
     * @param array $data The issue's decoded data.
     * @return string[]
     */
    public static function ips(array $data): array {
        return array_values(array_filter($data['ips'] ?? [$data['ip'] ?? ''], fn($ip) => is_string($ip) && trim($ip) !== ''));
    }

    /**
     * The log report of the issue's user, on the day of the issue.
     *
     * @param stdClass $record The issue.
     * @return moodle_url
     */
    public static function log_url(stdClass $record): moodle_url {
        return new moodle_url('/report/log/index.php', [
            'chooselog'   => 1,
            'showusers'   => 0,
            'showcourses' => 0,
            'id'          => 0,
            'user'        => $record->userid,
            'date'        => $record->dateissue,
            'logreader'   => 'logstore_moodefense',
        ]);
    }

    /**
     * The value of one field.
     *
     * @param string $key
     * @param stdClass $record
     * @param array $data
     * @return array text, url and text, or ips.
     */
    private static function field(string $key, stdClass $record, array $data): array {
        return match ($key) {
            'username' => self::text($record->uid ? fullname($record) : ''),
            'roles' => self::text($record->roles),
            'userid' => self::text($record->userid ?: ''),
            'ips' => self::ips_field(self::ips($data), (int)$record->userid),
            'profilelink' => [
                'url' => (new moodle_url('/user/profile.php', ['id' => $record->userid]))->out(false),
                'text' => get_string('viewprofile', 'logstore_moodefense'),
            ],
            'loglink' => [
                'url' => self::log_url($record)->out(false),
                'text' => get_string('viewlogs', 'logstore_moodefense'),
            ],
            // Evidence of the algorithm: shown as stored, a web address as a link.
            default => preg_match('#^https?://#', $value = (string)($data[$key] ?? ''))
                ? ['url' => $value, 'text' => $value]
                : self::text($value),
        };
    }

    /**
     * The IP addresses, each linked to its lookup, those past VISIBLE_IPS hidden until expanded.
     *
     * @param string[] $ips
     * @param int $userid
     * @return array
     */
    private static function ips_field(array $ips, int $userid): array {
        if (!$ips) {
            return self::text('');
        }
        $list = [];
        foreach ($ips as $index => $ip) {
            $list[] = [
                'ip' => $ip,
                'url' => (new moodle_url('/iplookup/index.php', ['ip' => $ip, 'user' => $userid]))->out(false),
                'hidden' => $index >= self::VISIBLE_IPS,
            ];
        }
        return ['ips' => $list, 'ipsmore' => max(count($ips) - self::VISIBLE_IPS, 0)];
    }

    /**
     * A text field, N/A when empty.
     *
     * @param string|int $text
     * @return array
     */
    private static function text(string|int $text): array {
        return ['text' => (string)$text !== '' ? (string)$text : get_string('notapplicable', 'logstore_moodefense')];
    }
}
