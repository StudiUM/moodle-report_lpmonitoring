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
 * Class for exporting data of course linked to a competency.
 *
 * @package    report_lpmonitoring
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring\external;
defined('MOODLE_INTERNAL') || die();

use context_course;
use renderer_base;
use stdClass;

/**
 * Class for exporting data of course linked to a competency.
 *
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class linked_course_exporter extends \core_competency\external\exporter {

    protected static function define_related() {
        return array('relatedinfo' => '\\stdClass');
    }

    protected static function define_other_properties() {
        return array(
            'url' => array(
                'type' => PARAM_TEXT
            ),
            'rated' => array(
                'type' => PARAM_BOOL
            ),
            'coursename' => array(
                'type' => PARAM_TEXT
            )
        );
    }

    protected function get_other_values(renderer_base $output) {

        $coursedata = $this->data;
        $result = new \stdClass();

        $urlparams = array('userid' => $this->related['relatedinfo']->userid,
                'competencyid' => $this->related['relatedinfo']->competencyid, 'courseid' => $coursedata->course->id);
        $url = (new \moodle_url('/admin/tool/lp/user_competency_in_course.php', $urlparams))->out();

        $result->url = $url;
        $result->coursename = $coursedata->course->shortname;
        $result->rated = !empty($coursedata->usecompetencyincourse->get_grade()) ? true : false;

        return (array) $result;
    }
}
