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
 * Class for exporting rated and not rated users in the competency.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring\external;
defined('MOODLE_INTERNAL') || die();

use core_competency\external\exporter;
use core_competency\external\user_summary_exporter;
use renderer_base;

/**
 * Class for exporting rated and not rated users in the competency.
 *
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_stats_user_exporter extends exporter {

    protected static function define_properties() {
        return array(
            'email' => array(
                'type' => PARAM_TEXT
            )
        );
    }

    protected static function define_other_properties() {
        return array(
            'fullname' => array(
                'type' => PARAM_TEXT
            ),
            'userid' => array(
                'type' => PARAM_INT
            ),
            'profileimagesmall' => array(
                'type' => PARAM_TEXT
            ),
            'profileurl' => array(
                'type' => PARAM_TEXT
            ),
            'rated' => array(
                'type' => PARAM_BOOL
            )
        );
    }

    protected function get_other_values(renderer_base $output) {

        $userexporter = new user_summary_exporter($this->data, '*', \MUST_EXIST);
        $userexport = $userexporter->export($output);

        return array(
            'fullname' => $userexport->fullname,
            'profileimagesmall' => $userexport->profileimageurlsmall,
            'userid' => $userexport->id,
            'profileurl' => $userexport->profileurl,
            'rated' => $this->data->rateduser
        );
    }
}
