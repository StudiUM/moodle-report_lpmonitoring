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
 * Class for exporting lpmonitoring_competency_detail data.
 *
 * @package    report_lpmonitoring
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring\external;
defined('MOODLE_INTERNAL') || die();

use renderer_base;
use core_competency\user_evidence;
use core_competency\user_competency;
use tool_lp\external\competency_path_exporter;
use report_lpmonitoring\external\linked_course_exporter;
use report_lpmonitoring\external\scale_competency_item_exporter;
use report_lpmonitoring\external\report_user_evidence_summary_exporter;


/**
 * Class for exporting lpmonitoring_competency_detail data.
 *
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lpmonitoring_competency_detail_exporter extends \core\external\exporter {

    public static function define_other_properties() {
        return array(
            'competencyid' => array(
                'type' => PARAM_INT
            ),
            'scaleid' => array(
                'type' => PARAM_INT
            ),
            'isproficient' => array(
                'type' => PARAM_BOOL
            ),
            'isnotproficient' => array(
                'type' => PARAM_BOOL
            ),
            'isnotrated' => array(
                'type' => PARAM_BOOL
            ),
            'finalgradename' => array(
                'type' => PARAM_RAW,
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'finalgradecolor' => array(
                'type' => PARAM_RAW,
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'cangrade' => array(
                'type' => PARAM_BOOL
            ),
            'hasevidence' => array(
                'type' => PARAM_BOOL
            ),
            'hasrating' => array(
                'type' => PARAM_BOOL
            ),
            'hasratingincms' => array(
                'type' => PARAM_BOOL
            ),
            'nbevidence' => array(
                'type' => PARAM_INT
            ),
            'listevidence' => array(
                'type' => report_user_evidence_summary_exporter::read_properties_definition(),
                'multiple' => true
            ),
            'nbcoursestotal' => array(
                'type' => PARAM_INT
            ),
            'nbcoursesrated' => array(
                'type' => PARAM_INT
            ),
            'nbcmstotal' => array(
                'type' => PARAM_INT
            ),
            'nbcmsrated' => array(
                'type' => PARAM_INT
            ),
            'listtotalcourses' => array(
                'type' => linked_course_exporter::read_properties_definition(),
                'multiple' => true
            ),
            'listtotalcms' => array(
                'type' => linked_cm_exporter::read_properties_definition(),
                'multiple' => true
            ),
            'scalecompetencyitems' => array(
                'type' => scale_competency_item_exporter::read_properties_definition(),
                'multiple' => true
            ),
            'competencypath' => array(
                'type' => competency_path_exporter::read_properties_definition(),
                'multiple' => true
            ),
        );
    }

    protected function get_other_values(renderer_base $output) {

        $data = $this->data;
        $result = new \stdClass();

        $result->competencyid = $data->competency->get('id');
        $shoulddisplay = isset($data->displayrating) ? $data->displayrating : true;
        $uc = (isset($data->usercompetency)) ? $data->usercompetency : $data->usercompetencyplan;
        // Set the scaleid.
        $result->scaleid = $data->competency->get_scale()->id;
        // Proficiency and final grade.
        $proficiency = $uc->get('proficiency');
        $result->isnotrated = false;
        $result->isproficient = false;
        $result->isnotproficient = false;
        if (!isset($proficiency) || !$shoulddisplay) {
            $result->isnotrated = true;
        } else {
            if ($proficiency) {
                $result->isproficient = true;
            } else {
                $result->isnotproficient = true;
            }
            $grade = $uc->get('grade');
            $result->finalgradename = $data->scale[$grade];
            $result->finalgradecolor = $data->reportscaleconfig[$grade - 1]->color;
        }

        // If user can grade.
        $result->cangrade = user_competency::can_grade_user($uc->get('userid'));

        // Prior learning evidences.
        $result->nbevidence = count($data->userevidences);
        $result->hasevidence = $result->nbevidence > 0 ? true : false;
        $result->listevidence = array();
        foreach ($data->userevidences as $userevidence) {
            $userevidencerecord = new user_evidence($userevidence->id);
            $context = $userevidencerecord->get_context();
            $userevidencesummaryexporter = new report_user_evidence_summary_exporter($userevidencerecord,
                    array('context' => $context));
            $result->listevidence[] = $userevidencesummaryexporter->export($output);
        }

        // Liste of courses linked to the competency.
        $result->nbcoursestotal = 0;
        $result->nbcoursesrated = 0;
        $result->listtotalcourses = array();

        foreach ($data->courses as $coursedata) {
            $relatedinfo = new \stdClass();
            $relatedinfo->userid = $data->userid;
            $relatedinfo->competencyid = $data->competency->get('id');
            $totalcourseexporter = new linked_course_exporter($coursedata, array('relatedinfo' => $relatedinfo));
            $totalcourse = $totalcourseexporter->export($output);
            if ($totalcourse->rated) {
                $result->nbcoursesrated++;
            }
            $result->nbcoursestotal++;
            $result->listtotalcourses[] = $totalcourse;
        }
        $result->hasrating = $result->nbcoursesrated > 0 ? true : false;

        // List of courses modules linked to the competency.
        $result->nbcmstotal = 0;
        $result->nbcmsrated = 0;
        $result->listtotalcms = array();

        foreach ($data->cms as $cmdata) {
            $relatedinfo = new \stdClass();
            $relatedinfo->userid = $data->userid;
            $relatedinfo->competencyid = $data->competency->get('id');
            $totalcmexporter = new linked_cm_exporter($cmdata, array('relatedinfo' => $relatedinfo));
            $totalcm = $totalcmexporter->export($output);
            if ($totalcm->rated) {
                $result->nbcmsrated++;
            }
            $result->nbcmstotal++;
            $result->listtotalcms[] = $totalcm;
        }
        $result->hasratingincms = $result->nbcmsrated > 0 ? true : false;

        // Information for each scale value.
        $result->scalecompetencyitems = array();
        foreach ($data->scale as $id => $scalename) {
            $scaleinfo = new \stdClass();
            $scaleinfo->value = $id;
            $scaleinfo->name = $scalename;
            $scaleinfo->color = $data->reportscaleconfig[$id - 1]->color;

            $relatedinfo = new \stdClass();
            $relatedinfo->userid = $data->userid;
            $relatedinfo->competencyid = $data->competency->get('id');

            $scalecompetencyitemexporter = new scale_competency_item_exporter($scaleinfo, array('courses' => $data->courses,
                'relatedinfo' => $relatedinfo, 'cms' => $data->cms));
            $result->scalecompetencyitems[] = $scalecompetencyitemexporter->export($output);
        }

        // Competency path.
        $competencypathexporter = new competency_path_exporter([
            'ancestors' => $data->competency->get_ancestors(),
            'framework' => $data->framework,
            'context' => $data->framework->get_context()
        ]);
        $result->competencypath = array();
        $result->competencypath[] = $competencypathexporter->export($output);

        return (array) $result;
    }

}
