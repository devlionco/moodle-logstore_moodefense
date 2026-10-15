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

#[CoversClass(algorithm_type::class)]
/**
 * Tests for the list of algorithms.
 *
 * @package    logstore_moodefense
 * @category   test
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \logstore_moodefense\local\algorithm_type
 */
final class algorithm_type_test extends \advanced_testcase {
    public function test_free_algorithms_have_classes_and_strings(): void {
        foreach (algorithm_type::cases() as $algorithm) {
            $this->assertTrue(class_exists($algorithm->classname()));
            $this->assertTrue(get_string_manager()->string_exists("algorithm{$algorithm->value}_case", 'logstore_moodefense'));
        }
    }

    public function test_details_fields_have_labels(): void {
        foreach (algorithm_type::cases() as $algorithm) {
            foreach ($algorithm->details() as $key) {
                $this->assertTrue(get_string_manager()->string_exists("modal_$key", 'logstore_moodefense'), $key);
            }
        }
    }

    public function test_premium_algorithms_are_listed_but_not_shipped(): void {
        foreach (algorithm_type::PREMIUM as $i) {
            $this->assertNull(algorithm_type::tryFrom($i));
            $this->assertFalse(class_exists('\logstore_moodefense\algorithms\algorithm_' . $i));
            $this->assertTrue(get_string_manager()->string_exists('algorithm' . $i, 'logstore_moodefense'));
            $this->assertTrue(get_string_manager()->string_exists('algorithm' . $i . '_case', 'logstore_moodefense'));
        }
    }
}
