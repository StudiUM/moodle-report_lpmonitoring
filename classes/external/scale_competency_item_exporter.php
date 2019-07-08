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
 * Class for exporting scale info and courses that rated with the scale value.
 *
 * @package    report_lpmonitoring
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring\external;
defined('MOODLE_INTERNAL') || die();

use report_lpmonitoring\external\scale_value_course_exporter;
use report_lpmonitoring\external\scale_value_cm_exporter;
use renderer_base;

/**
 * Class for exporting scale info and courses that rated with the scale value.
 *
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scale_competency_item_exporter extends \core\external\exporter {

    protected static function define_related() {
        return array('courses' => '\\stdClass[]',
                     'cms' => '\\stdClass[]',
                     'relatedinfo' => '\\stdClass');
    }

    protected static function define_properties() {
        return array(
            'value' => array(
                'type' => PARAM_INT
            ),
            'name' => array(
                'type' => PARAM_RAW
            ),
            'color' => array(
                'type' => PARAM_RAW
            )
        );
    }

    protected static function define_other_properties() {
        return array(
            'nbcourse' => array(
                'type' => PARAM_INT
            ),
            'listcourses' => array(
                'type' => scale_value_course_exporter::read_properties_definition(),
                'multiple' => true
            ),
            'nbcm' => array(
                'type' => PARAM_INT
            ),
            'listcms' => array(
                'type' => scale_value_cm_exporter::read_properties_definition(),
                'multiple' => true
            )
        );
    }

    protected function get_other_values(renderer_base $output) {

        $result = new \stdClass();

        $result->nbcourse = 0;
        $result->listcourses = array();

        foreach ($this->related['courses'] as $course) {
            if ($this->data->value == $course->usecompetencyincourse->get('grade')) {
                $courseexporter = new scale_value_course_exporter($course, array('relatedinfo' => $this->related['relatedinfo']));
                $result->listcourses[] = $courseexporter->export($output);
                $result->nbcourse++;
            }
        }

        $result->nbcm = 0;
        $result->listcms = array();
        foreach ($this->related['cms'] as $cm) {
            if ($this->data->value == $cm->usecompetencyincm->get('grade')) {
                $cmexporter = new scale_value_cm_exporter($cm, array('relatedinfo' => $this->related['relatedinfo']));
                $result->listcms[] = $cmexporter->export($output);
                $result->nbcm++;
            }
        }

        return (array) $result;
    }
}
