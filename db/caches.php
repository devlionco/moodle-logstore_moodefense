<?php
// This file is part of Moodle - https://moodle.org/
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
 * Defined caches used internally by the plugin.
 *
 * @package     logstore_moodefense
 * @category    cache
 * @author     Devlion <info@devlion.co>
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
        // IP-to-country lookups (\logstore_moodefense\geoip): an IP's geolocation essentially never
        // changes, and the issue-details modal can request a couple dozen of these per open (one
        // per IP in a "multiple IP addresses" incident).
        'geoip' => [
                'mode' => cache_store::MODE_APPLICATION,
                'simplekeys' => true,
                'simpledata' => true,
                'staticacceleration' => true,
                'staticaccelerationsize' => 30,
                'ttl' => 30 * 24 * 60 * 60,
        ],
];
