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
 * Class for exporting data of an evaluation of a competency.
 *
 * @package    report_lpmonitoring
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @copyright  2019 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring\external;
defined('MOODLE_INTERNAL') || die();

use renderer_base;
use context_system;

/**
 * Class for exporting data of an evaluation of a competency.
 *
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @copyright  2019 Université de Montréalal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluations_exporter extends \core\external\exporter {

    protected static function define_other_properties() {
        return array(
            'iscourse' => array(
                'type' => PARAM_BOOL
            ),
            'elementid' => array(
                'type' => PARAM_INT
            ),
            'color' => array(
                'type' => PARAM_TEXT
            ),
            'name' => array(
                'type' => PARAM_TEXT
            )
        );
    }

    protected function get_other_values(renderer_base $output) {
        $evaluationdata = $this->data;
        $result = new \stdClass();
        $result->name = null;
        $result->color = null;

        foreach ($evaluationdata->competencydetail->reportscaleconfig as $scaleitem) {
            if ($scaleitem->id == $evaluationdata->grade) {
                $result->name = $evaluationdata->competencydetail->scale[$evaluationdata->grade];
                $result->color = $scaleitem->color;
            }
        }
        return (array) $result;
    }

    /**
     * Get the format parameters for color.
     *
     * @return array
     */
    protected function get_format_parameters_for_color() {
        return [
            'context' => context_system::instance(), // The system context is cached, so we can get it right away.
        ];
    }

    /**
     * Get the format parameters for name.
     *
     * @return array
     */
    protected function get_format_parameters_for_name() {
        return [
            'context' => context_system::instance(), // The system context is cached, so we can get it right away.
        ];
    }
}
