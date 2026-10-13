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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Web services definitions for the Moodefense log store plugin.
 *
 * Defines all external functions (web services) provided by the Moodefense plugin.
 *
 * @package    logstore_moodefense
 * @author     Devlion <info@devlion.co>
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'logstore_moodefense_clear_table' => [
        'classname'     => 'logstore_moodefense\\external\\clear_table',
        'methodname'    => 'execute',
        'description'   => 'Clear all issues from the Moodefense issues table',
        'type'          => 'write',
        'capabilities'  => 'logstore/moodefense:manage',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'logstore_moodefense_save_modal_data' => [
        'classname'     => 'logstore_moodefense\\external\\save_modal_data',
        'methodname'    => 'execute',
        'description'   => 'Save status and comment for a Moodefense issue from modal',
        'type'          => 'write',
        'capabilities'  => 'logstore/moodefense:manage',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'logstore_moodefense_get_country_code' => [
        'classname'     => 'logstore_moodefense\\external\\get_country_code',
        'methodname'    => 'execute',
        'description'   => 'Get ISO country code for a given IP address',
        'type'          => 'read',
        'capabilities'  => 'logstore/moodefense:view',
        'ajax'          => true,
        'loginrequired' => true,
        // Doesn't touch the session, so parallel lookups needn't queue behind each other's session lock.
        'readonlysession' => true,
    ],
    'logstore_moodefense_test_api' => [
        'classname'     => 'logstore_moodefense\\external\\test_api',
        'methodname'    => 'execute',
        'description'   => 'Check the given Moodefense API URL and token',
        'type'          => 'read',
        'capabilities'  => 'logstore/moodefense:manage',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'logstore_moodefense_test_email' => [
        'classname'     => 'logstore_moodefense\\external\\test_email',
        'methodname'    => 'execute',
        'description'   => 'Send a test email to the given notification addresses',
        'type'          => 'write',
        'capabilities'  => 'logstore/moodefense:manage',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];
