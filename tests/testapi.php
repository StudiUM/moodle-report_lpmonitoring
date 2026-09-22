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

namespace report_lpmonitoring;

use report_lpmonitoring\api as nontestable_api;

/**
 * Test subclass that makes some variables or methods we want to test public.
 *
 * @package    report_lpmonitoring
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @copyright  2019 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testapi extends nontestable_api {
    /**
     * Change value for the iscmcompetencygradingenabled variable.
     *
     * @param bool $value True or false value for iscmcompetencygradingenabled
     */
    public static function set_is_cm_comptency_grading_enabled($value) {
        self::$iscmcompetencygradingenabled = $value;
    }
}
