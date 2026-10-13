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

namespace logstore_moodefense;

/**
 * Client of the Moodefense API, configured by the 'apiurl' and 'token' settings. Only the request
 * itself goes out: no data about the site or its users is sent.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /**
     * Whether both the API URL and the token are set.
     *
     * @return bool
     */
    public static function configured(): bool {
        return get_config('logstore_moodefense', 'apiurl') && get_config('logstore_moodefense', 'token');
    }

    /**
     * The moodle.org security announcements, as parsed by the API, from the configured URL and token.
     *
     * @return array[] Announcements with id, title, link, date, severity, cve_identifier, versions_affected,
     *     versions_affected_ranges ([['min' => ..., 'max' => ...]]) and versions_fixed.
     * @throws \moodle_exception When the API cannot be reached or answers with an error.
     */
    public static function security_issues(): array {
        return self::request(
            (string)get_config('logstore_moodefense', 'apiurl'),
            (string)get_config('logstore_moodefense', 'token')
        );
    }

    /**
     * Checks an API URL and token, for the "Test API" button of the settings page, so that admins
     * can check them before saving. Makes the same request as security_issues().
     *
     * @param string $url
     * @param string $token
     * @return array ['response' => bool success, 'message' => string error details].
     */
    public static function test_connection(string $url, string $token): array {
        if (!$url || !$token) {
            return ['response' => false, 'message' => get_string('testapi_empty', 'logstore_moodefense')];
        }
        try {
            self::request($url, $token);
            return ['response' => true, 'message' => ''];
        } catch (\moodle_exception $e) {
            return ['response' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetches the security announcements from the API.
     *
     * @param string $url The API URL.
     * @param string $token The API key.
     * @return array[] See security_issues().
     * @throws \moodle_exception When the API cannot be reached or answers with an error.
     */
    private static function request(string $url, string $token): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $response = $curl->get(rtrim($url, '/') . '/securityissues', [], [
            'CURLOPT_HTTPHEADER' => ["x-api-key: $token"],
            'CURLOPT_TIMEOUT' => 10,
        ]);
        $httpcode = $curl->get_info()['http_code'] ?? 0;
        $issues = json_decode($response, true);
        if ($curl->get_errno() || $httpcode !== 200 || !is_array($issues)) {
            // A URL blocked by the site's HTTP security settings is answered by curl itself, with no HTTP code.
            $error = $curl->error ?: ($httpcode ? "HTTP $httpcode" : $response);
            throw new \moodle_exception('apierror', 'logstore_moodefense', '', $error);
        }
        return $issues;
    }
}
