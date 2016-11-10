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
 * Class for doing reports for competency.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');

use core_user;
use context;
use core_competency\api as core_competency_api;
use core_competency\course_competency;
use core_competency\competency;
use core_competency\template;
use core_competency\plan;
use core_competency\template_competency;
use core_competency\competency_framework;
use core_competency\user_competency;
use core_competency\user_competency_plan;
use report_lpmonitoring\report_competency_config;
use stdClass;
use Exception;
use required_capability_exception;
use moodle_exception;

/**
 * Class for doing reports for competency.
 *
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Get scales from frameworkid.
     *
     * @param int $frameworkid The framework ID
     *
     * @return array Scale info
     */
    public static function get_scales_from_framework($frameworkid) {
        global $DB;
        // Read the framework.
        $framework = core_competency_api::read_framework($frameworkid);
        $scales = array();

        // Get the scale of the framework.
        $frameworkscale = $framework->get_scale();
        $scales[$frameworkscale->id] = array('id' => $frameworkscale->id, 'name' => $frameworkscale->name);

        $sql = "SELECT s.id, s.name
                  FROM {scale} s
                  JOIN {" . competency::TABLE . "} c
                    ON c.scaleid = s.id
                 WHERE c.competencyframeworkid = :frameworkid
              ORDER BY s.name ASC";

        // Extracting the results.
        $records = $DB->get_recordset_sql($sql, array('frameworkid' => $frameworkid));
        foreach ($records as $record) {
            $scales[$record->id] = array('id' => $record->id, 'name' => $record->name);
        }
        $records->close();

        return (array) (object) $scales;
    }

    /**
     * Get scales from templateid.
     *
     * @param int $templateid The template ID
     *
     * @return array Scale info
     */
    public static function get_scales_from_templateid($templateid) {
        // Read the template.
        $competencies = core_competency_api::list_competencies_in_template($templateid);
        $scales = array();
        foreach ($competencies as $competency) {
            $framework = $competency->get_framework();
            $scale = $competency->get_scale();
            if (isset($scale)) {
                $scaleid = $scale->id;
                $scalename = $scale->name;
            } else {
                $scaleid = $framework->get_scaleid();
                $scalename = $framework->get_scale()->name;
            }

            $scales[$scaleid] = array('frameworkid' => $framework->get_id(), 'scalename' => $scalename);
        }

        return $scales;
    }

    /**
     * Read the configuration associated to a competency framework and a scale. Return a record.
     *
     * @param int $frameworkid The id of the competency framework.
     * @param int $scaleid The id of the scale.
     *
     * @return report_competency_config
     */
    public static function read_report_competency_config($frameworkid, $scaleid) {

        // User has necessary capapbility if he can read the framework.
        $framework = core_competency_api::read_framework($frameworkid);

        $config = report_competency_config::read_framework_scale_config($frameworkid, $scaleid);
        if (!$config) {
            $record = new stdClass();
            $record->competencyframeworkid = $frameworkid;
            $record->scaleid = $scaleid;
            $config = new report_competency_config(0, $record);
            $config->set_default_scaleconfiguration();
        } else {
            $config->set_default_scaleconfiguration();
        }

        return $config;
    }

    /**
     * Create the configuration associated to a competency framework and a scale.
     *
     * @param stdClass $record Record containing all the data for an instance of the class.
     *
     * @return report_competency_config
     */
    public static function create_report_competency_config(stdClass $record) {
        global $DB;

        // Check the permissions before accessing configuration.
        $framework = new competency_framework($record->competencyframeworkid);
        if (!$framework->can_manage()) {
            throw new required_capability_exception($framework->get_context(), 'moodle/competency:competencymanage',
                'nopermissions', '');
        }

        if ($DB->record_exists(report_competency_config::TABLE,
                array('competencyframeworkid' => $record->competencyframeworkid, 'scaleid' => $record->scaleid))) {
            throw new exception('Can not create: configuration already exist');
        }

        $config = new report_competency_config(0, $record);
        $config->create();

        return $config;
    }

    /**
     * Update the configuration associated to a competency framework and a scale.
     *
     * @param stdClass $record The new details for the configuration.
     *                         Note - must contain an id that points to the configuration to update.
     *
     * @return boolean
     */
    public static function update_report_competency_config($record) {
        global $DB;

        // Check the permissions before accessing configuration.
        $framework = new competency_framework($record->competencyframeworkid);
        if (!$framework->can_manage()) {
            throw new required_capability_exception($framework->get_context(), 'moodle/competency:competencymanage',
                'nopermissions', '');
        }

        // Check for existing record.
        $recordconfig = $DB->get_record(report_competency_config::TABLE,
                array('competencyframeworkid' => $record->competencyframeworkid, 'scaleid' => $record->scaleid));

        if (!$recordconfig) {
            throw new Exception('Can not update: configuration does not exist');
        }

        $config = new report_competency_config($recordconfig->id);
        $config->from_record($record);

        // OK - all set.
        $result = $config->update();

        return $result;
    }

    /**
     * Delete the configuration associated to a competency framework and a scale.
     *
     * @param int $competencyframeworkid The cometency framework id.
     * @param int $scaleid The scale id.
     *
     * @return boolean
     */
    public static function delete_report_competency_config($competencyframeworkid, $scaleid = null) {
        global $DB;

        // Check the permissions before accessing configuration.
        if ($DB->record_exists(competency_framework::TABLE, array('id' => $competencyframeworkid))) {
            $framework = new competency_framework($competencyframeworkid);
            if (!$framework->can_manage()) {
                throw new required_capability_exception($framework->get_context(), 'moodle/competency:competencymanage',
                'nopermissions', '');
            }
        }

        $params = array('competencyframeworkid' => $competencyframeworkid);
        if ($scaleid != null) {
            $params['scaleid'] = $scaleid;
        }

        $result = $DB->delete_records(report_competency_config::TABLE, $params);

        return $result;
    }

    /**
     * Get learning plans from templateid.
     *
     * @param int $templateid The template ID
     * @param string $query The search query
     * @param array $scalesvalues scales values filter
     * @param boolean $scalefilterbycourse Apply the scale filters on grade in course
     * @param string $scalesortorder Order by rating number ASC or DESC
     * @return array( array(
     *                      'profileimage' => string,
     *                      'fullname' => string,
     *                      'email' => string,
     *                      'username' => string,
     *                      'userid' => int,
     *                      'planid' => int,
     *                      )
     *              )
     */
    public static function search_users_by_templateid($templateid, $query, $scalesvalues = array(), $scalefilterbycourse = true,
            $scalesortorder = "ASC") {
        global $CFG, $DB;
        if (!in_array(strtolower($scalesortorder), array('asc', 'desc'))) {
            throw new coding_exception('Sort order must be ASC or DESC');
        }

        $template = core_competency_api::read_template($templateid);
        $context = $template->get_context();

        $extrasearchfields = array();
        if (!empty($CFG->showuseridentity)) {
            $extrasearchfields = explode(',', $CFG->showuseridentity);
        }
        $fields = \user_picture::fields('u', $extrasearchfields);
        list($wheresql, $whereparams) = users_search_sql($query, 'u', true, $extrasearchfields);
        list($sortsql, $sortparams) = users_order_by_sql('u', $query, $context);

        // Group scales values by scaleid.
        $scalefilter = array();
        if (!empty($scalesvalues)) {
            foreach ($scalesvalues as $scale) {
                $scalefilter[$scale['scaleid']][] = $scale['scalevalue'];
            }
        }
        $i = 1;
        $paramsfilter = array();
        $sqlfilterin = '';
        $sqlfilterinforplan = '';
        $sqlscalefilter = '';
        // Build scale filters SQL and params by final rating or by course rating.
        foreach ($scalefilter as $scaleid => $scalevalues) {
            list($insqlframework, $params1) = $DB->get_in_or_equal($scalevalues,
                    SQL_PARAMS_NAMED, 'gradeframework');
            list($insqlcompetency, $params2) = $DB->get_in_or_equal($scalevalues,
                    SQL_PARAMS_NAMED, 'gradecompetency');
            $querykeyname1 = 'scaleid1' . $i;
            $querykeyname2 = 'scaleid2' . $i;
            $or = ($i > 1) ? ' OR ' : '';
            $sqlfilterin .= $or . "(cf.scaleid = :$querykeyname1 AND ucc.grade $insqlframework AND c.scaleid IS NULL)
                            OR (c.scaleid = :$querykeyname2 AND ucc.grade $insqlcompetency)";

            $queryparams = array($querykeyname1 => $scaleid) + array($querykeyname2 => $scaleid);
            $paramsfilter = $paramsfilter + $params1 + $params2 + $queryparams;

            // If scale values in plan, we should build the "IN" SQL for both usercomp and usercompplan.
            if (!$scalefilterbycourse) {
                list($insqlframework, $params1) = $DB->get_in_or_equal($scalevalues,
                    SQL_PARAMS_NAMED, 'gradeframework');
                list($insqlcompetency, $params2) = $DB->get_in_or_equal($scalevalues,
                    SQL_PARAMS_NAMED, 'gradecompetency');
                $querykeyname3 = 'scaleid3' . $i;
                $querykeyname4 = 'scaleid4' . $i;

                $sqlfilterinforplan .= $or . "(cf.scaleid = :$querykeyname3 AND ucp.grade $insqlframework AND c.scaleid IS NULL)
                            OR (c.scaleid = :$querykeyname4 AND ucp.grade $insqlcompetency)";

                $queryparams = array($querykeyname3 => $scaleid) + array($querykeyname4 => $scaleid);
                $paramsfilter = $paramsfilter + $params1 + $params2 + $queryparams;
            }
            $i++;
        }

        if ($sqlfilterin != '') {
            // Depending in filterbycourse param, we choose which table to use.
            if ($scalefilterbycourse) {
                // We have to check if users are enroled in course and competency is linked to the course.
                $sqlscalefilter = "SELECT useridentifier,
                                          gradecount,
                                          tempid,
                                          $fields,
                                          p.id AS planid
                                    FROM  (
                                            (SELECT ucc.userid AS useridentifier, Count(ucc.grade) gradecount,
                                                    tc.templateid AS tempid
                                               FROM {" . \core_competency\template_competency::TABLE . "} tc
                                               JOIN {" . \core_competency\course_competency::TABLE . "} cc
                                                    ON tc.competencyid = cc.competencyid AND tc.templateid = :templateid
                                               JOIN {" . \core_competency\user_competency_course::TABLE . "} ucc
                                                    ON ucc.competencyid = tc.competencyid AND cc.courseid = ucc.courseid
                                               JOIN {user_enrolments} ue
						    ON ue.userid = ucc.userid
                                                    AND ue.status = :active
					       JOIN {enrol} e
						    ON ue.enrolid = e.id AND e.courseid = ucc.courseid
                                                    AND e.status = :enabled
                                               JOIN {" . \core_competency\competency::TABLE . "} c
                                                    ON c.id = ucc.competencyid
                                               JOIN {" . \core_competency\competency_framework::TABLE . "} cf
                                                    ON cf.id = c.competencyframeworkid
                                             WHERE ($sqlfilterin)
                                          GROUP BY useridentifier)
                                        ) usergrade";

                $paramsfilter += array('active' => ENROL_USER_ACTIVE);
                $paramsfilter += array('enabled' => ENROL_INSTANCE_ENABLED);
            } else {
                // SQL for usercomp and completed plans.
                $sqlscalefilter = "SELECT useridentifier,
                                          gradecount,
                                          tempid,
                                          $fields,
                                          p.id AS planid,
                                          planstatus
                                    FROM  (
                                            (SELECT ucc.userid AS useridentifier, Count(ucc.grade) gradecount,
                                                    tc.templateid AS tempid, 'notcompleted' AS planstatus
                                               FROM {" . \core_competency\template_competency::TABLE . "} tc
                                               JOIN {" . \core_competency\user_competency::TABLE . "} ucc
                                                    ON ucc.competencyid = tc.competencyid AND tc.templateid = :templateid
                                               JOIN {" . \core_competency\competency::TABLE . "} c
                                                    ON c.id = ucc.competencyid
                                               JOIN {" . \core_competency\competency_framework::TABLE . "} cf
                                                    ON cf.id = c.competencyframeworkid
                                             WHERE ($sqlfilterin)
                                          GROUP BY useridentifier)
                                          UNION
                                           (SELECT ucp.userid AS useridentifier, Count(ucp.grade) gradecount,
                                                   tc.templateid AS tempid, 'completed' AS planstatus
                                              FROM {" . \core_competency\template_competency::TABLE . "} tc
                                              JOIN {" . \core_competency\plan::TABLE . "} p
                                                   ON p.templateid = tc.templateid AND tc.templateid = :templateid2
                                              JOIN {" . \core_competency\user_competency_plan::TABLE . "} ucp
                                                   ON ucp.competencyid = tc.competencyid AND p.id = ucp.planid
                                              JOIN {" . \core_competency\competency::TABLE . "} c
                                                   ON c.id = ucp.competencyid
                                              JOIN {" . \core_competency\competency_framework::TABLE . "} cf
                                                   ON cf.id = c.competencyframeworkid
                                             WHERE ($sqlfilterinforplan)
                                          GROUP BY useridentifier)
                                        ) usergrade";
            }
            // We sort by rating number.
            $sort = "gradecount $scalesortorder,$sortsql";
            $sql = "$sqlscalefilter
                    JOIN {" . \core_competency\plan::TABLE . "} p
                         ON usergrade.useridentifier = p.userid
                         AND p.templateid = usergrade.tempid
                    JOIN {user} u ON u.id = p.userid
                   WHERE $wheresql
                ORDER BY $sort";
        } else {
            // If no scale filter defined.
            $sql = "SELECT $fields, p.id as planid
                  FROM {" . \core_competency\plan::TABLE . "} p
                  JOIN {user} u ON u.id = p.userid
                 WHERE p.templateid = :templateid
                       AND $wheresql
              ORDER BY $sortsql";
        }

        $params = $paramsfilter + $whereparams + $sortparams;
        $params += array('templateid' => $template->get_id()) + array('templateid2' => $template->get_id());
        $result = $DB->get_recordset_sql($sql, $params);

        $users = array();
        foreach ($result as $key => $user) {
            // Add user picture.
            $userplan = array();
            $userplan['profileimage'] = new \user_picture($user);
            $userplan['fullname'] = fullname($user);
            $userplan['userid'] = $user->id;
            $userplan['planid'] = $user->planid;
            $userplan['nbrating'] = (isset($user->gradecount)) ? $user->gradecount : 0;
            $usercontext = \context_user::instance($user->id);
            // Build identity fields.
            if (!empty($extrasearchfields) && has_capability('moodle/site:viewuseridentity', $usercontext)) {
                foreach ($extrasearchfields as $field) {
                    $userplan[$field] = $user->$field;
                }
            }

            if (isset($users[$user->id]) && isset($user->planstatus)) {
                if ($user->planstatus == 'notcompleted') {
                    continue;
                } else {
                    unset($users[$user->id]);
                }
            }
            $users[$user->id] = $userplan;
        }
        $result->close();
        return $users;
    }

    /**
     * Get scales proficient values from frameworkid.
     *
     * @param int $frameworkid The framework ID
     * @param int $scaleid The scale ID
     *
     * @return array Scale information
     */
    public static function get_scale_configuration_other_info($frameworkid, $scaleid) {
        global $DB;

        $scaleotherinfo = array();
        $scaleconfigurations = '';

        // Get scale configuration from competency first or framework second.
        $sql = "SELECT c.scaleconfiguration
                  FROM {" . competency::TABLE . "} c
                 WHERE c.competencyframeworkid = :frameworkid
                   AND c.scaleid = :scaleid";

        // Extracting the results.
        $records = $DB->get_recordset_sql($sql, array('frameworkid' => $frameworkid, 'scaleid' => $scaleid));
        foreach ($records as $record) {
            $scaleconfigurations = $record->scaleconfiguration;
        }
        if (empty($scaleconfigurations)) {
            // Read the framework.
            $framework = core_competency_api::read_framework($frameworkid);
            $scaleconfigurations = $framework->get_scaleconfiguration();
        }

        $scaleconfigurations = json_decode($scaleconfigurations);
        if (is_array($scaleconfigurations)) {
            // The first element of the array contains the scale ID.
            $scaleinfo = array_shift($scaleconfigurations);
        }

        // Get scale items.
        $scale = \grade_scale::fetch(array('id' => $scaleid));
        $scale->load_items();
        $scaleitems = $scale->scale_items;

        // Build scale other info.
        foreach ($scaleitems as $key => $value) {
            $proficient = false;
            foreach ($scaleconfigurations as $scaleconfiguration) {
                if ($key + 1 == $scaleconfiguration->id) {
                    if (isset($scaleconfiguration->proficient) && $scaleconfiguration->proficient) {
                        $proficient = true;
                    }
                    break;
                }
            }
            $scaleotherinfo[$key] = ['name' => $value, 'proficient' => $proficient];
        }

        return $scaleotherinfo;
    }

    /**
     * Read the plan information by plan ID or
     * template ID and return if possible, the previous and next plan having
     * the same template.
     *
     * @param int $planid The plan ID
     * @param int $templateid The template ID
     * @param array $scalesvalues The Scales values filter
     * @param boolean $scalefilterbycourse Apply the scale filters on grade in course
     * @param string $sortorder Scale sort order
     * @return array((object) array(
     *                            'current' => \core_competency\plan,
     *                            'previous' => \stdClass
     *                            'next' => \stdClass
     *                        ))
     */
    public static function read_plan($planid = null, $templateid = null, $scalesvalues = array(), $scalefilterbycourse = true,
            $sortorder = 'ASC') {

        if (empty($planid) && empty($templateid)) {
            throw new coding_exception('A plan ID and/or a template ID must be specified');
        }

        $currentplan = null;
        $prevplan = null;
        $nextplan = null;
        // Get the current plan depending on the values passed in parameter.
        $currentplanid = $planid;
        if (!empty($templateid)) {
            $userplans = array_values(self::search_users_by_templateid($templateid , '', $scalesvalues, $scalefilterbycourse,
                    $sortorder));
            $currentindex = null;
            // We throw an exception if the template has no plans.
            if (empty($userplans)) {
                throw new \moodle_exception('emptytemplate', 'report_lpmonitoring');
            } else {
                // When the plan ID is not specified, we set the first plan as the current one.
                if (empty($planid)) {
                    $currentindex = 0;
                    $currentplanid = $userplans[$currentindex]['planid'];
                } else {
                    // Search for the current plan in the list of plans based on the template.
                    foreach ($userplans as $index => $userplan) {
                        if ($userplan['planid'] == $planid) {
                            $currentindex = $index;
                            break;
                        }
                    }
                }

                // Get the previous and next plans based on the current plan index.
                if (isset($currentindex)) {
                    if (isset($userplans[$currentindex - 1])) {
                        $prevplan = (object) $userplans[$currentindex - 1];
                    }
                    if (isset($userplans[$currentindex + 1])) {
                        $nextplan = (object) $userplans[$currentindex + 1];
                    }
                }
            }
        }

        if (!empty($currentplanid)) {
            $plan = new plan($currentplanid);
            if (!$plan->can_read()) {
                $currentuserfullname = $userplans[$currentindex]['fullname'];
                throw new moodle_exception('nopermissionsplanview', 'report_lpmonitoring', '', $currentuserfullname);
            }
            $currentplan = core_competency_api::read_plan($currentplanid);
        }

        return (object) array(
            'current' => $currentplan,
            'previous' => $prevplan,
            'next' => $nextplan
        );
    }

    /**
     * Get comptency information for lpmonitoring report.
     *
     * @param int $userid User id.
     * @param int $competencyid Competency id.
     * @param int $planid Plan id.
     * @return \stdClass The record of competency detail
     */
    public static function get_competency_detail($userid, $competencyid, $planid) {
        global $DB;

        $competencydetails = new \stdClass();

        $plancompetency = core_competency_api::get_plan_competency($planid, $competencyid);
        $competency = $plancompetency->competency;

        // User has necessary capapbility if he can read the framework.
        $framework = core_competency_api::read_framework($competency->get_competencyframeworkid());

        $competencydetails->userid = $userid;
        $competencydetails->planid = $planid;
        $competencydetails->competency = $competency;
        $competencydetails->framework = $framework;

        // Find de scale configuration associated to the competency.
        $scaleid = $competency->get_scaleid();
        $scaleconfig = $competency->get_scaleconfiguration();
        if ($scaleid === null) {
            $scaleid = $framework->get_scaleid();
            $scaleconfig = $framework->get_scaleconfiguration();
        }

        // Remove the scale ID from the config.
        $scaleconfig = json_decode($scaleconfig);
        if (!is_array($scaleconfig)) {
            throw new coding_exception('Unexpected scale configuration.');
        }
        array_shift($scaleconfig);
        $competencydetails->scaleconfig = $scaleconfig;

        // Find the scale infos.
        $scale = \grade_scale::fetch(array('id' => $scaleid));
        $scale = $scale->load_items();
        $newscale = array();
        foreach ($scale as $key => $value) {
            $newscale[$key + 1] = $value;
        }
        $competencydetails->scale = $newscale;

        // Find de scale configuration for the report.
        $reportscaleconfig = self::read_report_competency_config($framework->get_id(), $scaleid);
        $reportscaleconfig = json_decode($reportscaleconfig->get_scaleconfiguration());
        if (!is_array($reportscaleconfig)) {
            throw new coding_exception('Unexpected report scale configuration.');
        }
        $competencydetails->reportscaleconfig = $reportscaleconfig;

        // Find rate for the competency.
        $competencydetails->usercompetency = $plancompetency->usercompetency;
        $competencydetails->usercompetencyplan = $plancompetency->usercompetencyplan;

        // Find the prior learning evidence linked to the competency.
        $competencydetails->userevidences = array();
        $evidences = core_competency_api::list_evidence($userid, $competencyid);
        $sql = "SELECT ue.*
                  FROM {competency_userevidence} ue
                  JOIN {competency_userevidencecomp} uec ON (ue.id = uec.userevidenceid)
                  WHERE ue.userid = ?
                  AND uec.competencyid = ?";
        $competencydetails->userevidences = $DB->get_records_sql($sql, array($userid, $competencyid));

        $courses = course_competency::get_courses_with_competency_and_user($competencyid, $userid);

        $competencydetails->courses = array();
        foreach ($courses as $course) {
            $courseinfo = new \stdClass();
            $courseinfo->course = $course;

            // Find rating in course.
            $courseinfo->usecompetencyincourse = core_competency_api::get_user_competency_in_course($course->id, $userid,
                    $competencyid);

            // Find most recent course evidences.
            $sort = 'timecreated';
            $order = 'DESC';
            $courseinfo->courseevidences = core_competency_api::list_evidence_in_course($userid, $course->id, $competencyid,
                    $sort, $order);

            // Find litteral note.
            $gradeitem = \grade_item::fetch_course_item($course->id);
            $gradegrade = new \grade_grade(array('itemid' => $gradeitem->id, 'userid' => $userid));
            $courseinfo->gradetxt = grade_format_gradevalue($gradegrade->finalgrade, $gradeitem, true, GRADE_DISPLAY_TYPE_LETTER);

            $competencydetails->courses[] = $courseinfo;
        }

        return $competencydetails;
    }

    /**
     * Get competency statistics for lpmonitoring report.
     *
     * @param int $competencyid Competency id.
     * @param int $templateid Template id.
     * @return \stdClass The record of competency statistics.
     */
    public static function get_competency_statistics($competencyid, $templateid) {
        // Prepare some data for competency stats (scale, colors configuration, ...).
        $competencystatistics = self::prepare_competency_stats_data($competencyid, $templateid);

        // Get plans by template.
        $userplans = plan::get_records_for_template($templateid);

        // Find rate for each user in the plan for the the competency.
        $competencystatistics->listusers = array();
        foreach ($userplans as $userplan) {
            $user = new stdClass();
            $user->userinfo = core_user::get_user($userplan->get_userid(), '*', \MUST_EXIST);
            // Throw an exception if user can not read the user competency.
            if (!user_competency::can_read_user($userplan->get_userid())) {
                $userfullname = fullname($user->userinfo);
                throw new moodle_exception('nopermissionsusercompetencyview', 'report_lpmonitoring', '', $userfullname);
            }
            if ($userplan->get_status() == plan::STATUS_COMPLETE &&
                    !self::has_records_for_competency_user_in_plan($userplan->get_id(), $competencyid)) {
                continue;
            }

            $plancompetency = core_competency_api::get_plan_competency($userplan->get_id(), $competencyid);
            $user->usercompetency = $plancompetency->usercompetency;
            $user->usercompetencyplan = $plancompetency->usercompetencyplan;
            $competencystatistics->listusers[] = $user;
        }

        return $competencystatistics;
    }

    /**
     * Get competency statistics in course.
     *
     * @param int $competencyid Competency id.
     * @param int $templateid Template id.
     * @return \stdClass The record of competency statistics.
     */
    public static function get_competency_statistics_in_course($competencyid, $templateid) {
        // Prepare some data for competency stats (scale, colors configuration, ...).
        $competencystatistics = self::prepare_competency_stats_data($competencyid, $templateid);

        // Get course competency by template.
        $userplans = plan::get_records_for_template($templateid);

        // Find rate for each user in the plan for the the competency.
        $competencystatistics->listratings = array();
        foreach ($userplans as $plan) {
            $userid = $plan->get_userid();
            $courses = course_competency::get_courses_with_competency_and_user($competencyid, $userid);

            foreach ($courses as $course) {
                $courseinfo = new \stdClass();
                $courseinfo->course = $course;

                // Find ratings in course.
                $ucc = core_competency_api::get_user_competency_in_course($course->id, $userid,
                        $competencyid);
                $competencystatistics->listratings[] = $ucc;
            }
        }

        return $competencystatistics;
    }

    /**
     * Prepare data for competency statistics.
     *
     * @param int $competencyid Competency id.
     * @param int $templateid Template id.
     * @return \stdClass The record of competency statistics.
     */
    protected static function prepare_competency_stats_data($competencyid, $templateid) {
        $competencystatistics = new \stdClass();

        $competency = template_competency::get_competency($templateid, $competencyid);

        // User has necessary capapbility if he can read the framework.
        $framework = core_competency_api::read_framework($competency->get_competencyframeworkid());

        $competencystatistics->competency = $competency;
        $competencystatistics->framework = $framework;

        // Find de scale configuration associated to the competency.
        $scaleid = $competency->get_scaleid();
        $scaleconfig = $competency->get_scaleconfiguration();
        if ($scaleid === null) {
            $scaleid = $framework->get_scaleid();
            $scaleconfig = $framework->get_scaleconfiguration();
        }

        // Remove the scale ID from the config.
        $scaleconfig = json_decode($scaleconfig);
        if (!is_array($scaleconfig)) {
            throw new coding_exception('Unexpected scale configuration.');
        }
        array_shift($scaleconfig);
        $competencystatistics->scaleconfig = $scaleconfig;

        // Find the scale infos.
        $scale = \grade_scale::fetch(array('id' => $scaleid));
        $scale = $scale->load_items();
        $newscale = array();
        foreach ($scale as $key => $value) {
            $newscale[$key + 1] = $value;
        }
        $competencystatistics->scale = $newscale;

        // Find de scale configuration for the report.
        $reportscaleconfig = self::read_report_competency_config($framework->get_id(), $scaleid);
        $reportscaleconfig = json_decode($reportscaleconfig->get_scaleconfiguration());
        if (!is_array($reportscaleconfig)) {
            throw new coding_exception('Unexpected report scale configuration.');
        }
        $competencystatistics->reportscaleconfig = $reportscaleconfig;
        return $competencystatistics;
    }

    /**
     * Search templates by contextid.
     *
     * @param context $context The context
     * @param string $query The search query
     * @param int $skip Number of records to skip (pagination)
     * @param int $limit Max of records to return (pagination)
     * @param string $includes Defines what other contexts to fetch frameworks from.
     *                         Accepted values are:
     *                          - children: All descendants
     *                          - parents: All parents, grand parents, etc...
     *                          - self: Context passed only.
     * @param bool $onlyvisible If should list only visible templates
     * @return array of competency_template
     */
    public static function search_templates($context, $query, $skip = 0, $limit = 0, $includes = 'children', $onlyvisible = false) {
        global $DB;

        // Get all the relevant contexts.
        $contexts = core_competency_api::get_related_contexts($context, $includes,
            array('moodle/competency:templateview', 'moodle/competency:templatemanage'));

        // First we do a permissions check.
        if (empty($contexts)) {
             throw new required_capability_exception($context, 'moodle/competency:templateview', 'nopermissions', '');
        }

        // Make the order by.
        $orderby = 'shortname ASC';

        // OK - all set.
        $template = new template();
        list($insql, $params) = $DB->get_in_or_equal(array_keys($contexts), SQL_PARAMS_NAMED);
        $select = "contextid $insql";

        if ($onlyvisible) {
            $select .= " AND visible = :visible";
            $params['visible'] = 1;
        }
        if ($query) {
            list($sqlquery, $paramsquery) = self::get_template_query_search($query);
            $select .= " AND $sqlquery";
            $params += $paramsquery;
        }

        return $template->get_records_select($select, $params, $orderby, '*', $skip, $limit);
    }

    /**
     * Produces a part of SQL query to filter template by the search string.
     *
     * @param string $search search string
     * @param string $tablealias alias of template table in the SQL query
     * @return array of two elements - SQL condition and array of named parameters
     */
    static protected function get_template_query_search($search, $tablealias = '') {
        global $DB;
        $params = array();
        if (empty($search)) {
            // This function should not be called if there is no search string, just in case return dummy query.
            return array('1=1', $params);
        }
        if ($tablealias && substr($tablealias, -1) !== '.') {
            $tablealias .= '.';
        }
        $searchparam = '%' . $DB->sql_like_escape($search) . '%';
        $conditions = array();
        $fields = array('shortname', 'description');
        $cnt = 0;
        foreach ($fields as $field) {
            $conditions[] = $DB->sql_like($tablealias . $field, ':csearch' . $cnt, false);
            $params['csearch' . $cnt] = $searchparam;
            $cnt++;
        }
        $sql = '(' . implode(' OR ', $conditions) . ')';
        return array($sql, $params);
    }

    /**
     * Check if competency exist for plan in the user_competency_plan Table.
     *
     * @param int $planid The plan ID
     * @param int $competencyid The competency ID
     * @return bool True if record exist
     */
    static protected function has_records_for_competency_user_in_plan($planid, $competencyid) {
        global $DB;
        $sql = "SELECT c.*
                  FROM {" . user_competency_plan::TABLE . "} ucp
                  JOIN {" . competency::TABLE . "} c
                    ON c.id = ucp.competencyid
                 WHERE ucp.planid = ?
                   AND ucp.competencyid = ?";
        return $DB->record_exists_sql($sql, array($planid, $competencyid));
    }

}
