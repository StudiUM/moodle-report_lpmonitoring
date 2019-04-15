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
 * Class for exporting data of course module linked to a competency.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2019 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring\external;
defined('MOODLE_INTERNAL') || die();

use renderer_base;

/**
 * Class for exporting data of course module linked to a competency.
 *
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2019 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class linked_cm_exporter extends \core\external\exporter {

    protected static function define_related() {
        return array('relatedinfo' => '\\stdClass');
    }

    protected static function define_other_properties() {
        return array(
            'url' => array(
                'type' => PARAM_RAW
            ),
            'rated' => array(
                'type' => PARAM_BOOL
            ),
            'cmname' => array(
                'type' => PARAM_RAW
            ),
            'cmicon' => array(
                'type' => PARAM_RAW
            ),
            'coursename' => array(
                'type' => PARAM_RAW
            )
        );
    }

    protected function get_other_values(renderer_base $output) {
        $cmdata = $this->data;
        $result = new \stdClass();

        $urlparams = array('user' => $this->related['relatedinfo']->userid, 'id' => $cmdata->cmid);
        $url = (new \moodle_url('/report/cmcompetency/index.php', $urlparams))->out();

        $cm = get_coursemodule_from_id('', $cmdata->cmid, 0, true);
        $modinfo = get_fast_modinfo($cm->course);
        $result->coursename = $modinfo->cms[$cmdata->cmid]->get_course()->shortname;
        $result->cmname = $modinfo->cms[$cmdata->cmid]->name;
        $result->cmicon = $modinfo->cms[$cmdata->cmid]->get_icon_url()->out();
        $result->url = $url;
        $result->rated = (isset($cmdata->usecompetencyincm) && !empty($cmdata->usecompetencyincm->get('grade'))) ? true : false;

        return (array) $result;
    }
}
