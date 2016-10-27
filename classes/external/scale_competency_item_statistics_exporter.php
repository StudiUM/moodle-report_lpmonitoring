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
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
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
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scale_competency_item_statistics_exporter extends exporter {

    protected static function define_related() {
        return array('users' => '\\stdClass[]');
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
            'nbusers' => array(
                'type' => PARAM_INT
            ),
            'listusers' => array(
                'type' => scale_value_user_exporter::read_properties_definition(),
                'multiple' => true
            )
        );
    }

    protected function get_other_values(renderer_base $output) {

        $result = new \stdClass();

        $result->nbusers = 0;
        $result->listusers = array();

        foreach ($this->related['users'] as $user) {
            $uc = (isset($user->usercompetency)) ? $user->usercompetency : $user->usercompetencyplan;
            if ($this->data->value == $uc->get_grade()) {
                $userexporter = new scale_value_user_exporter($user->userinfo);
                $result->listusers[] = $userexporter->export($output);
                $result->nbusers++;
            }
        }

        return (array) $result;
    }
}
