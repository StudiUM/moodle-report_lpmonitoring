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
 * Class for exporting data for the plan competency report.
 *
 * @package    report_lpmonitoring
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @copyright  2019 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring\external;
defined('MOODLE_INTERNAL') || die();

use core\external\exporter;
use renderer_base;
use report_lpmonitoring\api;

/**
 * Class for exporting data for the plan competency report.
 *
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @copyright  2019 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_plan_competency_report_exporter extends exporter {

    public static function define_other_properties() {
        return array(
            'iscmcompetencygradingenabled' => array(
                'type' => PARAM_BOOL
            ),
            'competencies_list' => array(
                'type' => competency_evaluations_exporter::read_properties_definition(),
                'multiple' => true
            ),
            'courses' => array(
                'type' => linked_course_and_modules_exporter::read_properties_definition(),
                'multiple' => true
            )
        );
    }

    protected static function define_related() {
        // We cache the plan so it does not need to be retrieved every time.
        return array('plan' => 'core_competency\\plan');
    }

    protected function get_other_values(renderer_base $output) {
        $resultcompetencies = $this->data;
        $plan = $this->related['plan'];

        $result = array();
        $result['iscmcompetencygradingenabled'] = api::is_cm_comptency_grading_enabled();
        $result['competencies_list'] = array();

        $allcourses = array();
        foreach ($resultcompetencies as $key => $r) {
            $usercomp = (isset($r->usercompetency)) ? $r->usercompetency : $r->usercompetencyplan;
            $r->competencydetail = api::get_competency_detail($plan->get('userid'), $usercomp->competencyid, $plan->get('id'));

            $r->tmpevalincourse = array(); // Save the evaluation in courses information the further usage.
            $r->tmpevalinmodule = array(); // Save the evaluation in modules information the further usage.
            foreach ($r->competencydetail->courses as $courseinfo) {
                // Add course to list of all courses.
                if (!isset ($allcourses[$courseinfo->course->id]) ) {
                    $allcourses[$courseinfo->course->id]['courseinfo'] = $courseinfo;
                    $allcourses[$courseinfo->course->id]['modulesinfo'] = array();
                }
                if (api::is_cm_comptency_grading_enabled()) {
                    // Add module to list for course.
                    foreach ($courseinfo->modules as $moduleevaluation) {
                        $cmid = $moduleevaluation->get('cmid');
                        $allcourses[$courseinfo->course->id]['modulesinfo'][$cmid] = $cmid;

                        $grade = $moduleevaluation->get('grade');
                        $grade = empty($grade) ? 0 : $grade;
                        $r->tmpevalinmodule[$cmid] = $grade;
                    }
                }

                $grade = $courseinfo->usecompetencyincourse->get('grade');
                $grade = empty($grade) ? 0 : $grade;
                $r->tmpevalincourse[$courseinfo->course->id] = $grade;
            }
        }

        // Now we have all competencies and all courses, we iterate again to fill in the evaluation details.
        // An empty evaluation is created for non evaluated courses or modules, so that we can iterate easily in the template.
        foreach ($resultcompetencies as $key => $r) {
            $data = new \stdClass();
            $data->allcourses = $allcourses;
            $data->competencydetailinfos = $r;

            $exporter = new competency_evaluations_exporter($data, ['plan' => $plan]);
            $result['competencies_list'][] = $exporter->export($output);
        }

        // Export the courses data (course and modules infos).
        $result['courses'] = array();
        foreach ($allcourses as $course) {
            $exporter = new linked_course_and_modules_exporter($course, ['plan' => $plan]);
            $result['courses'][] = $exporter->export($output);
        }
        return $result;
    }
}
