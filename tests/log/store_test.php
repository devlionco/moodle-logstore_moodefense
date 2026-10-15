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

namespace logstore_moodefense\log;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(store::class)]
/**
 * Tests for the Moodefense log store writer.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \logstore_moodefense\log\store
 */
final class store_test extends \advanced_testcase {
    /**
     * Returns a store with its protected methods callable.
     *
     * @return store
     */
    private function get_store(): store {
        set_config('enabled_stores', 'logstore_moodefense', 'tool_log');
        return new class (get_log_manager(true)) extends store {
            /**
             * Exposes insert_event_entries().
             *
             * @param array $entries
             */
            public function insert(array $entries): void {
                $this->insert_event_entries($entries);
            }

            /**
             * Exposes is_event_ignored().
             *
             * @param \core\event\base $event
             * @return bool
             */
            public function ignored(\core\event\base $event): bool {
                return $this->is_event_ignored($event);
            }
        };
    }

    /**
     * A raw log entry as tool_log's buffered writer would pass it on.
     *
     * @param string|null $ip
     * @return array
     */
    private function entry(?string $ip): array {
        return [
            'eventname' => '\core\event\user_loggedin', 'component' => 'core', 'action' => 'loggedin',
            'target' => 'user', 'objecttable' => 'user', 'objectid' => 2, 'crud' => 'r', 'edulevel' => 0,
            'contextid' => 1, 'contextlevel' => CONTEXT_SYSTEM, 'contextinstanceid' => 0, 'userid' => 2,
            'courseid' => 0, 'relateduserid' => null, 'anonymous' => 0, 'other' => null,
            'timecreated' => time(), 'origin' => 'web', 'ip' => $ip, 'realuserid' => null,
        ];
    }

    public function test_insert_derives_ip_and_device_fields(): void {
        global $DB;
        $this->resetAfterTest();

        $this->get_store()->insert([$this->entry('192.168.1.10'), $this->entry('2001:db8::1'), $this->entry('bogus')]);

        $rows = array_values($DB->get_records('logstore_moodefense_log', null, 'id'));
        $this->assertCount(3, $rows);
        $this->assertEquals(ip2long('192.168.1.10'), $rows[0]->ipint);
        $this->assertEquals(sprintf('%u', crc32('2001:db8::1')), $rows[1]->ipint);
        $this->assertEquals(0, $rows[2]->ipint);
        // Same user agent, different IP address: a different device.
        $this->assertEquals($rows[0]->useragentint, $rows[1]->useragentint);
        $this->assertNotEquals($rows[0]->sessionidentifier, $rows[1]->sessionidentifier);
    }

    public function test_insert_truncates_long_user_agent(): void {
        global $DB;
        $this->resetAfterTest();
        \core_useragent::instance(true, str_repeat('A', 400));

        $this->get_store()->insert([$this->entry('10.0.0.1')]);

        $this->assertSame(255, strlen($DB->get_field('logstore_moodefense_log', 'useragent', [])));
        \core_useragent::instance(true);
    }

    public function test_cli_events_are_ignored(): void {
        $this->resetAfterTest();
        $event = \core\event\user_loggedin::create(['userid' => 2, 'objectid' => 2, 'other' => ['username' => 'admin']]);

        $this->assertTrue($this->get_store()->ignored($event));
    }

    public function test_reader_restores_events(): void {
        $this->resetAfterTest();
        $store = $this->get_store();
        $store->insert([$this->entry('10.0.0.1')]);

        $events = $store->get_events_select('', [], 'id', 0, 0);

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\core\event\user_loggedin::class, reset($events));
        $this->assertSame(1, $store->get_events_select_count('', []));
        $this->assertTrue($store->get_events_select_exists('', []));
    }
}
