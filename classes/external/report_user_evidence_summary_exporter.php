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
 * Class for user evidence exporter.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring\external;
defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_lp\external\user_evidence_summary_exporter;
use core_competency\url;

/**
 * Class extending from user_evidence_summary_exporter.
 *
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_user_evidence_summary_exporter extends user_evidence_summary_exporter {

    protected static function define_other_properties() {
        $properties = parent::define_other_properties();
        $properties['userevidenceurl'] = array('type' => PARAM_RAW);
        return $properties;
    }

    protected function get_other_values(renderer_base $output) {
        $id = $this->persistent->get('id');
        $othervalues = parent::get_other_values($output);
        $othervalues['userevidenceurl'] = url::user_evidence($id)->out(false);
        return $othervalues;
    }

}
