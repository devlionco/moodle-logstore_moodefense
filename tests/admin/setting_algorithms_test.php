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

namespace logstore_moodefense\admin;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(setting_algorithms::class)]
#[CoversClass(setting_algorithm_notifications::class)]
/**
 * Tests for the "Active incident types" setting.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \logstore_moodefense\admin\setting_algorithms
 * @covers \logstore_moodefense\admin\setting_algorithm_notifications
 */
final class setting_algorithms_test extends \advanced_testcase {
    /** @var setting_algorithm_notifications */
    private $notifications;

    /** @var setting_algorithms */
    private $setting;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->notifications = new setting_algorithm_notifications(
            'logstore_moodefense/algorithms_notifications',
            'Algorithms with notifications',
            ''
        );
        $this->setting = new setting_algorithms('logstore_moodefense/algorithms', 'Algorithms', '', $this->notifications);
    }

    public function test_only_free_algorithms_are_saved(): void {
        $this->assertSame('', $this->setting->write_setting(['xxxxx' => 1, 1 => 1, 4 => 1, 7 => 1, 9 => 1]));
        $this->assertSame('', $this->notifications->write_setting(['xxxxx' => 1, 7 => 1, 9 => 1]));

        $this->assertSame('4,7', get_config('logstore_moodefense', 'algorithms'));
        $this->assertSame('7', get_config('logstore_moodefense', 'algorithms_notifications'));
    }

    public function test_renders_both_columns_and_premium_buttons(): void {
        set_config('algorithms_notifications', '2,4', 'logstore_moodefense');

        $html = $this->setting->output_html([2 => 1, 7 => 1]);

        $field = fn(string $setting, int $id) => '/<input type="checkbox" name="s_logstore_moodefense_' . $setting .
            '\[' . $id . '\]"[^>]*>/';
        $this->assertMatchesRegularExpression($field('algorithms', 7), $html);
        preg_match($field('algorithms_notifications', 2), $html, $notify2);
        preg_match($field('algorithms_notifications', 4), $html, $notify4);
        preg_match($field('algorithms_notifications', 7), $html, $notify7);
        $this->assertStringContainsString('checked', $notify2[0]);
        // Algorithm 4 is not detected: it cannot notify, whatever is stored.
        $this->assertStringContainsString('disabled', $notify4[0]);
        $this->assertStringNotContainsString('checked', $notify4[0]);
        $this->assertStringNotContainsString('checked', $notify7[0]);
        $this->assertStringNotContainsString('name="s_logstore_moodefense_algorithms[1]"', $html);
        $this->assertSame(5, substr_count($html, 'data-action="moodefense-premium-algorithm"'));
        $this->assertSame('', $this->notifications->output_html([]));
    }
}
