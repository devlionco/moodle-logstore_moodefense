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

#[CoversClass(issues_filter::class)]
/**
 * Tests for the issues table filter.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \logstore_moodefense\local\issues_filter
 */
final class issues_filter_test extends \advanced_testcase {
    /**
     * Filters with nothing set.
     *
     * @return array
     */
    private function none(): array {
        return ['filterusername' => '', 'filterseverity' => '', 'filterstatus' => -1, 'filterip' => '',
            'filtercomments' => '', 'filterroles' => ''];
    }

    /**
     * Ids of the issues matching the filters.
     *
     * @param array $filters
     * @return array
     */
    private function matching(array $filters): array {
        global $DB;
        [$sql, $params] = issues_filter::get_sql(array_merge($this->none(), $filters), 'i.id');
        return array_keys($DB->get_records_sql("$sql ORDER BY i.id", $params));
    }

    public function test_filters(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('logstore_moodefense');
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Dana', 'lastname' => 'Levi']);

        $a = $generator->create_issue(['userid' => $user->id, 'severity' => 'High', 'roles' => 'editingteacher',
            'data' => ['ip' => '10.0.0.1'], 'comment' => 'Checked by IT']);
        $b = $generator->create_issue(['severity' => 'Medium', 'status' => 2, 'data' => ['ips' => ['10.0.0.12', '10.0.0.13']]]);

        $this->assertEquals([$a->id, $b->id], $this->matching([]));
        $this->assertEquals([$a->id], $this->matching(['filterseverity' => 'High']));
        $this->assertEquals([$b->id], $this->matching(['filterstatus' => 2]));
        $this->assertEquals([$a->id], $this->matching(['filterstatus' => 0]));
        $this->assertEquals([$a->id], $this->matching(['filterroles' => 'teacher']));
        $this->assertEquals([$a->id], $this->matching(['filtercomments' => 'checked']));
        $this->assertEquals([$a->id], $this->matching(['filterusername' => 'dana lev']));
        $this->assertEquals([$b->id], $this->matching(['filterip' => '10.0.0.13']));
        $this->assertEquals([$a->id, $b->id], $this->matching(['filterip' => '10.0.0.1']));
        $this->assertEquals([], $this->matching(['filtercomments' => '100%']));
    }

    public function test_is_active(): void {
        $this->assertFalse(issues_filter::is_active($this->none()));
        $this->assertTrue(issues_filter::is_active(array_merge($this->none(), ['filterstatus' => 0])));
        $this->assertTrue(issues_filter::is_active(array_merge($this->none(), ['filterip' => '10.'])));
    }
}
