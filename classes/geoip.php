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
 * IP-to-country lookup in a GeoIP2 database: the site's own one when it is set up in Site
 * administration > Location > Location settings ($CFG->geoip2file), otherwise the GeoLite2 City
 * database shipped in this plugin's source/ directory. Results are cached, as an IP's country
 * essentially never changes.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class geoip {
    /** Database shipped with the plugin, relative to its directory. */
    private const BUNDLED_DATABASE = '/source/GeoLite2-City.mmdb';

    /** @var \GeoIp2\Database\Reader[] Open databases by file: opening one parses its metadata. */
    private static array $readers = [];

    /**
     * The GeoIP2 database to look addresses up in: the site's (Location settings), else the one
     * shipped with the plugin.
     *
     * @return string|null Path of a readable database, or null if there is none.
     */
    private static function database_file(): ?string {
        global $CFG;

        foreach ([$CFG->geoip2file ?? '', __DIR__ . '/..' . self::BUNDLED_DATABASE] as $file) {
            if ($file !== '' && is_readable($file)) {
                return $file;
            }
        }
        return null;
    }

    /**
     * Resolves the country an IP address comes from, for algorithm 5 and the issue details dialogue.
     * Results are cached per database: an address keeps its country.
     *
     * @param string $ip IPv4 or IPv6 address.
     * @return array|null ['country' => name, 'countrycode' => ISO 3166-1 alpha-2], or null if unknown.
     */
    public static function lookup(string $ip): ?array {
        $file = self::database_file();
        if (!filter_var($ip, FILTER_VALIDATE_IP) || $file === null) {
            return null;
        }

        // Keyed by database too: switching or updating it must not keep serving old answers.
        $cache = \cache::make('logstore_moodefense', 'geoip');
        $cachekey = sha1($file . '|' . filemtime($file) . '|' . $ip);
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            // An empty array records an IP that was looked up but could not be resolved.
            return $cached ?: null;
        }

        try {
            $record = (self::$readers[$file] ??= new \GeoIp2\Database\Reader($file))->city($ip);
            $result = self::make_result($record->country->isoCode, $record->country->name);
        } catch (\Exception $e) {
            // Private/reserved or unknown address, or an unreadable database.
            $result = null;
        }

        $cache->set($cachekey, $result ?? []);
        return $result;
    }

    /**
     * Shapes a lookup result, naming the country in the site's language when Moodle knows it.
     *
     * @param string|null $code ISO country code.
     * @param string|null $name Country name reported by the database.
     * @return array|null
     */
    protected static function make_result(?string $code, ?string $name): ?array {
        if (empty($code)) {
            return null;
        }
        $code = strtoupper($code);
        $countries = get_string_manager()->get_list_of_countries(true);
        return ['country' => $countries[$code] ?? ($name ?: $code), 'countrycode' => $code];
    }
}
