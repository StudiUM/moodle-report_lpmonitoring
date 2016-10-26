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
 * Settings and links
 *
 * @package    report_lpmonitoring
 * @author     Jean-Philippe Gaudreau <jp.gaudreau@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig && get_config('core_competency', 'enabled')) {
    $systemcontextid = context_system::instance()->id;

    // Competency frameworks scale colors settings page.
    $adminpage = new admin_externalpage(
        'colorconfiguration',
        get_string('colorconfiguration', 'report_lpmonitoring'),
        new moodle_url('/report/lpmonitoring/scalecolorconfiguration.php', array('pagecontextid' => $systemcontextid)),
        array('moodle/competency:competencymanage')
    );
    $ADMIN->add('competencies', $adminpage);

    // Monitoring of learning plans report.
    $adminpage = new admin_externalpage(
        'reportlpmonitoring',
        get_string('pluginname', 'report_lpmonitoring'),
        new moodle_url('/report/lpmonitoring/index.php', array('pagecontextid' => $systemcontextid)),
        array('moodle/competency:templateview')
    );
    $ADMIN->add('reports', $adminpage);

    // Monitoring of learning plans statistics.
    $statsadminpage = new admin_externalpage(
        'statslpmonitoring',
        get_string('statslearningplan', 'report_lpmonitoring'),
        new moodle_url('/report/lpmonitoring/stats.php', array('pagecontextid' => $systemcontextid)),
        array('moodle/competency:templateview')
    );
    $ADMIN->add('reports', $statsadminpage);

    // No report settings.
    $settings = null;
}
