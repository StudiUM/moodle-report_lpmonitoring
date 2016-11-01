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

/**
 * Class for exporting scale info and users rated with the scale value.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring\external;
defined('MOODLE_INTERNAL') || die();

use report_lpmonitoring\external\scale_value_user_exporter;
use core_competency\external\exporter;
use renderer_base;

/**
 * Class for exporting scale info and users rated with the scale value.
 *
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scale_competency_incourse_statistics_exporter extends exporter {

    protected static function define_related() {
        return array('ratings' => '\\core_competency\user_competency_course[]');
    }

    protected static function define_properties() {
        return array(
            'value' => array(
                'type' => PARAM_INT
            ),
            'name' => array(
                'type' => PARAM_TEXT
            ),
            'color' => array(
                'type' => PARAM_TEXT
            )
        );
    }

    protected static function define_other_properties() {
        return array(
            'nbratings' => array(
                'type' => PARAM_INT
            )
        );
    }

    protected function get_other_values(renderer_base $output) {

        $result = new \stdClass();
        $result->nbratings = 0;
        foreach ($this->related['ratings'] as $ucc) {
            if ($this->data->value == $ucc->get_grade()) {
                $result->nbratings++;
            }
        }

        return (array) $result;
    }
}
