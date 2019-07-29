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
 * Class for exporting data for the plan competency summary.
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
 * Class for exporting data for the plan competency summary.
 *
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @copyright  2019 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_plan_competency_summary_exporter extends exporter {

    public static function define_other_properties() {
        return array(
            'iscmcompetencygradingenabled' => array(
                'type' => PARAM_BOOL
            ),
            'competencies_list' => array(
                'type' => competency_summary_evaluations_exporter::read_properties_definition(),
                'multiple' => true
            ),
            'scale' => array(
                'type' => scale_competency_item_exporter::read_properties_definition(),
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

        // TODO EVOSTDM-1880 : retourner les vraies infos de scale
        $scaleinfo = new \stdClass();
        $scaleinfo->value = 1;
        $scaleinfo->name = 'Satisfait aux attentes';
        $scaleinfo->color = '#ff00ff';
        $relatedinfo = new \stdClass();
        $scalecompetencyitemexporter = new scale_competency_item_exporter($scaleinfo, array('courses' => array(),
                'relatedinfo' => $relatedinfo, 'cms' => array()));
        $result['scale'][] = $scalecompetencyitemexporter->export($output);

        $scaleinfo = new \stdClass();
        $scaleinfo->value = 2;
        $scaleinfo->name = 'Dépasse les attentes';
        $scaleinfo->color = '#ffff00';
        $relatedinfo = new \stdClass();
        $scalecompetencyitemexporter = new scale_competency_item_exporter($scaleinfo, array('courses' => array(),
                'relatedinfo' => $relatedinfo, 'cms' => array()));
        $result['scale'][] = $scalecompetencyitemexporter->export($output);

        // TODO EVOSTDM-1880 : s'assurer que les compétences sont retournées dans l'ordre, le parent juste avant ses enfants
        $result['competencies_list'] = array();
        $fakeparentid = 0;
        foreach ($resultcompetencies as $key => $r) {
            $usercomp = (isset($r->usercompetency)) ? $r->usercompetency : $r->usercompetencyplan;
            $r->competencydetail = api::get_competency_detail($plan->get('userid'), $usercomp->competencyid, $plan->get('id'));

            $data = new \stdClass();
            $data->allcourses = array();
            $data->competencydetailinfos = $r;

            // TODO EVOSTDM-1880 : faire le vrai code pour détecter que c'est un parent ou non
            $isparent = !($key % 3);
            $data->showasparent = $isparent;

            $exporter = new competency_summary_evaluations_exporter($data, ['plan' => $plan]);
            $exportedcompetency = $exporter->export($output);
            // TODO EVOSTDM-1880 : à enlever (utilisera les vrais id des parents)
            if ($isparent) {
                $fakeparentid = $exportedcompetency->competency->id;
            }
            $exportedcompetency->competency->parentid = $fakeparentid;

            $result['competencies_list'][] = $exportedcompetency;
        }

        return $result;
    }
}
