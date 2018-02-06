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
 * Class for exporting course data associated to a scale value
 *
 * @package    report_lpmonitoring
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring\external;
defined('MOODLE_INTERNAL') || die();

use renderer_base;
use stdClass;

/**
 * Class for exporting course data associated to a scale value.
 *
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scale_value_course_exporter extends \core\external\exporter {

    protected static function define_related() {
        return array('relatedinfo' => '\\stdClass');
    }

    protected static function define_other_properties() {
        return array(
            'url' => array(
                'type' => PARAM_RAW
            ),
            'shortname' => array(
                'type' => PARAM_RAW
            ),
            'grade' => array(
                'type' => PARAM_RAW
            ),
            'nbnotes' => array(
                'type' => PARAM_INT
            ),
            'lastcomment' => array(
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED
            )
        );
    }

    protected function get_other_values(renderer_base $output) {

        $coursedata = $this->data;

        $result = new stdClass();

        $urlparams = array('userid' => $this->related['relatedinfo']->userid,
                'competencyid' => $this->related['relatedinfo']->competencyid, 'courseid' => $coursedata->course->id);
        $url = (new \moodle_url('/admin/tool/lp/user_competency_in_course.php', $urlparams))->out();

        $nbnotes = 0;
        $lastcomment = null;
        $timemodified = null;
        foreach ($coursedata->courseevidences as $courseevidence) {
            if ($courseevidence->get('note') != null) {
                $nbnotes++;
                if ($timemodified == null || $timemodified < $courseevidence->get('timemodified')) {
                    $timemodified = $courseevidence->get('timemodified');
                    $lastcomment = $courseevidence->get('note');
                }
            }
        }

        $result->url = $url;
        $result->shortname = $coursedata->course->shortname;
        $result->grade = $coursedata->gradetxt;
        $result->nbnotes = $nbnotes;
        $result->lastcomment = $lastcomment;

        return (array) $result;
    }
}
