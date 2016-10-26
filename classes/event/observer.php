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
 * An event observer.
 *
 * @package    report_lpmonitoring
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring\event;

use report_lpmonitoring\api;
use report_lpmonitoring\report_competency_config;
use core_competency\competency_framework;
use core_competency\competency;

/**
 * An event observer.
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Listen to events and queue the submission for processing.
     * @param \core\event\competency_framework_updated $event
     */
    public static function framework_updated(\core\event\competency_framework_updated $event) {
        global $DB;

        $eventdata = $event->get_data();

        // Get data of framework.
        $record = $DB->get_record($eventdata['objecttable'], array('id' => $eventdata['objectid']));

        self::remove_report_config($record->id, $record->scaleid);
    }

    /**
     * Listen to events and queue the submission for processing.
     * @param \core\event\competency_framework_deleted $event
     */
    public static function framework_deleted(\core\event\competency_framework_deleted $event) {

        $eventdata = $event->get_data();

        // Get data of framework.
        $record = $event->get_record_snapshot($eventdata['objecttable'], $eventdata['objectid']);

        api::delete_report_competency_config($record->id);
    }

    /**
     * Listen to events and queue the submission for processing.
     * @param \core\event\competency_updated $event
     */
    public static function competency_updated(\core\event\competency_updated $event) {

        $eventdata = $event->get_data();

        // Get data of competency.
        $record = $event->get_record_snapshot($eventdata['objecttable'], $eventdata['objectid']);

        self::remove_report_config($record->competencyframeworkid);
    }

    /**
     * Determine which scales configuration must be removed and remove them
     * @param int $frameworkid The framework id
     * @param int $scaleid The scale id to ignore.
     */
    private static function remove_report_config($frameworkid, $scaleid = null) {
        global $DB;

        // Build a list of scaleid used in framework or competencies associated to the framework.
        $scalecond = "";
        $params = array();
        if ($scaleid != null) {
            $scalecond = ' AND scaleid <> :scaleid';
            $params = array('scaleid' => $scaleid);
        }

        $sql = "SELECT scaleid
                  FROM {" . competency_framework::TABLE ."}
                 WHERE id = :frameworkid1". $scalecond .
               " UNION
                SELECT scaleid
                  FROM {" . competency::TABLE ."}
                 WHERE competencyframeworkid = :frameworkid2" .
                 " AND scaleid IS NOT NULL";

        $params = array_merge($params, array('frameworkid1' => $frameworkid, 'frameworkid2' => $frameworkid));
        $frameworkscaleids = $DB->get_records_sql($sql, $params);

        // Build a list of report configuration scaleids associated to the framework.
        $sql = "SELECT scaleid
                  FROM {" . report_competency_config::TABLE ."}
                 WHERE competencyframeworkid = ?";

        $configscaleids = $DB->get_records_sql($sql, array($frameworkid));

        foreach ($configscaleids as $scaleid) {
            if (!array_key_exists($scaleid->scaleid, $frameworkscaleids)) {
                api::delete_report_competency_config($frameworkid, $scaleid->scaleid);
            }
        }
    }

}