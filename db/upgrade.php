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
 * Upgrade the report_lpmonitoring plugin.
 *
 * @package   report_lpmonitoring
 * @param int $oldversion
 * @copyright 2026 Université de Montréal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @return bool
 */
function xmldb_report_lpmonitoring_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026091700) {
        $table = new xmldb_table('report_competency_config');

        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'report_lpmonitoring_competency_config');
        }

        upgrade_plugin_savepoint(true, 2026091700, 'report', 'lpmonitoring');
    }

    return true;
}
