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
 * Renderer class for report_lpmonitoring
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lpmonitoring\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;
use renderable;

/**
 * Renderer class for competency breakdown report
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Defer to template.
     *
     * @param report $page
     * @return string html for the page
     */
    public function render_report(report $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('report_lpmonitoring/report', $data);
    }

    /**
     * Defer to template.
     *
     * @param user_report_page $page
     * @return string html for the page
     */
    public function render_user_report_page(user_report_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('report_lpmonitoring/user_report', $data);
    }

    /**
     * Defer to template.
     *
     * @param int $contextpageid
     * @return string html for the page
     */
    public function render_stats_page($contextpageid) {
        $data = new \stdClass();
        $data->contextpageid = $contextpageid;
        return parent::render_from_template('report_lpmonitoring/stats', $data);
    }

    /**
     * Defer to template.
     *
     * @param scalecolorconfiguration_page $page
     * @return string html for the page
     */
    public function render_scalecolorconfiguration_page(scalecolorconfiguration_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('report_lpmonitoring/scalecolorconfiguration', $data);
    }
}
