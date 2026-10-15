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

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(issue_details::class)]
/**
 * Tests for the fields of the issue details modal.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \logstore_moodefense\local\issue_details
 */
final class issue_details_test extends \advanced_testcase {
    public function test_fields_of_data_and_of_the_user(): void {
        $record = (object)['userid' => 0, 'uid' => null, 'roles' => '', 'dateissue' => 0];
        $data = ['cve_identifier' => 'CVE-2026-1', 'link' => 'https://moodle.org/x', 'ip' => '10.0.0.1'];

        $fields = issue_details::fields(['cve_identifier', 'link', 'versions_fixed', 'ips', 'username'], $record, $data);

        $this->assertSame([
            ['label' => 'CVE', 'text' => 'CVE-2026-1'],
            ['label' => 'Announcement', 'url' => 'https://moodle.org/x', 'text' => 'https://moodle.org/x'],
            ['label' => 'Fixed in', 'text' => 'N/A'],
            ['label' => 'IPs', 'ips' => [[
                'ip' => '10.0.0.1',
                'url' => 'https://www.example.com/moodle/iplookup/index.php?ip=10.0.0.1&user=0',
                'hidden' => false,
            ]], 'ipsmore' => 0],
            ['label' => 'User Name', 'text' => 'N/A'],
        ], $fields);
    }

    public function test_ips_past_the_visible_ones_are_hidden(): void {
        $record = (object)['userid' => 3];
        $ips = ['10.0.0.1', '', '10.0.0.2', '10.0.0.3', '10.0.0.4', '10.0.0.5'];

        [$field] = issue_details::fields(['ips'], $record, ['ips' => $ips]);

        $this->assertSame(['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4', '10.0.0.5'], array_column($field['ips'], 'ip'));
        $this->assertSame([false, false, false, true, true], array_column($field['ips'], 'hidden'));
        $this->assertSame(2, $field['ipsmore']);
        $this->assertSame([['label' => 'IPs', 'text' => 'N/A']], issue_details::fields(['ips'], $record, ['ip' => '']));
    }
}
