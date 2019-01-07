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
 * External tests.
 *
 * @package    report_lpmonitoring
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use core_competency\plan;
use core_competency\user_competency;
use report_lpmonitoring\external;
use report_lpmonitoring\report_competency_config;
use core_competency\url;


/**
 * External testcase.
 *
 * @package    report_lpmonitoring
 * @author     Serge Gauthier <serge.gauthier.2@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_lpmonitoring_external_testcase extends externallib_advanced_testcase {

    /** @var stdClass $appreciator User with enough permissions to access lpmonitoring report in system context. */
    protected $appreciator = null;

    /** @var stdClass $creator User with enough permissions to manage lpmonitoring report in system context. */
    protected $creator = null;

    /** @var int appreciator role id. */
    protected $roleappreciator = null;

    /** @var int creator role id. */
    protected $rolecreator = null;

    /** @var stdClass appreciator context. */
    protected $contextappreciator = null;

    /** @var stdClass creator context. */
    protected $contextcreator = null;

    protected function setUp() {

        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');

        $creator = $dg->create_user(array('firstname' => 'Creator'));
        $appreciator = $dg->create_user(array('firstname' => 'Appreciator'));

        $this->contextcreator = context_user::instance($creator->id);
        $this->contextappreciator = context_user::instance($appreciator->id);
        $syscontext = context_system::instance();

        $this->rolecreator = create_role('Creator role', 'rolecreator', 'learning plan manager role description');
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $this->rolecreator, $syscontext->id);
        assign_capability('moodle/competency:coursecompetencyview', CAP_ALLOW, $this->rolecreator, $syscontext->id);
        assign_capability('moodle/competency:usercompetencyview', CAP_ALLOW, $this->rolecreator, $syscontext->id);
        assign_capability('moodle/competency:usercompetencymanage', CAP_ALLOW, $this->rolecreator, $syscontext->id);
        assign_capability('moodle/competency:templateview', CAP_ALLOW, $this->rolecreator, $syscontext->id);
        assign_capability('moodle/competency:planview', CAP_ALLOW, $this->rolecreator, $syscontext->id);
        assign_capability('moodle/competency:planviewdraft', CAP_ALLOW, $this->rolecreator, $syscontext->id);
        role_assign($this->rolecreator, $creator->id, $syscontext->id);

        $this->roleappreciator = create_role('Appreciator role', 'roleappreciator', 'learning plan appreciator role description');
        assign_capability('moodle/competency:competencyview', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        assign_capability('moodle/competency:coursecompetencyview', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        assign_capability('moodle/competency:usercompetencyview', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        assign_capability('moodle/competency:usercompetencymanage', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        assign_capability('moodle/competency:templateview', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        assign_capability('moodle/competency:planview', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        assign_capability('moodle/competency:planviewdraft', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        role_assign($this->roleappreciator, $appreciator->id, $syscontext->id);

        $this->creator = $creator;
        $this->appreciator = $appreciator;

        $this->setUser($this->creator);
    }

    /**
     * Assign letter bondary.
     *
     * @param int $contextid Context id
     */
    private function assign_good_letter_boundary($contextid) {
        global $DB;
        $newlettersscale = array(
                array('contextid' => $contextid, 'lowerboundary' => 90.00000, 'letter' => 'A'),
                array('contextid' => $contextid, 'lowerboundary' => 85.00000, 'letter' => 'A-'),
                array('contextid' => $contextid, 'lowerboundary' => 80.00000, 'letter' => 'B+'),
                array('contextid' => $contextid, 'lowerboundary' => 75.00000, 'letter' => 'B'),
                array('contextid' => $contextid, 'lowerboundary' => 70.00000, 'letter' => 'B-'),
                array('contextid' => $contextid, 'lowerboundary' => 65.00000, 'letter' => 'C+'),
                array('contextid' => $contextid, 'lowerboundary' => 54.00000, 'letter' => 'C'),
                array('contextid' => $contextid, 'lowerboundary' => 50.00000, 'letter' => 'C-'),
                array('contextid' => $contextid, 'lowerboundary' => 40.00000, 'letter' => 'D+'),
                array('contextid' => $contextid, 'lowerboundary' => 25.00000, 'letter' => 'D'),
                array('contextid' => $contextid, 'lowerboundary' => 0.00000, 'letter' => 'F'),
            );

        $DB->delete_records('grade_letters', array('contextid' => $contextid));
        foreach ($newlettersscale as $record) {
            // There is no API to do this, so we have to manually insert into the database.
            $DB->insert_record('grade_letters', $record);
        }
    }

    /**
     * Validate the url.
     *
     * @param string $url  The url to validate
     * @param string $page  The page to find in url
     * @param array $params  The parameters to find in url
     *
     * @return string $errormsg The error message
     */
    private function validate_url($url, $page, $params = array()) {

        $errormsg = '';

        if (!strrpos($url, $page)) {
            $errormsg = 'URL missing page: ' . $page;
        } else if (count($params) > 0) {
            $urlparamspos = strrpos($url, '?');
            if (!$urlparamspos) {
                $errormsg = 'URL missing parameters.';
            } else {
                $urlparams = explode('&amp;', substr($url, $urlparamspos + 1));
                $listurlparam = array();
                foreach ($urlparams as $urlparam) {
                    $urlparamname = substr($urlparam, 0, strrpos($urlparam, '='));
                    $urlparamvalue = substr($urlparam, strrpos($urlparam, '=') + 1);
                    $listurlparam[$urlparamname] = $urlparamvalue;
                }

                foreach ($params as $name => $value) {
                    if (!array_key_exists($name, $listurlparam)) {
                        $errormsg = 'Missing parameter: ' . $name;
                        break;
                    } else if (!in_array($listurlparam[$name], $value)) {
                        $errormsg = 'Bad value for parameter: ' . $name;
                        break;
                    }
                }
            }
        }

        return $errormsg;
    }

    /**
     * Get value for a parameter in a url.
     *
     * @param string $url  The url to validate
     * @param string $param  The name of the parameter
     *
     * @return string $paramvalue The value of the parameter
     */
    private function get_url_param_value($url, $param) {

        $paramvalue = null;

        $urlparamspos = strrpos($url, '?');
        if ($urlparamspos) {
            $urlparams = explode('&amp;', substr($url, $urlparamspos + 1));
            $listurlparam = array();
            foreach ($urlparams as $urlparam) {
                $urlparamname = substr($urlparam, 0, strrpos($urlparam, '='));
                $urlparamvalue = substr($urlparam, strrpos($urlparam, '=') + 1);
                $listurlparam[$urlparamname] = $urlparamvalue;
            }
            if (array_key_exists($param, $listurlparam)) {
                $paramvalue = $listurlparam[$param];
            }
        }

        return $paramvalue;
    }

    /**
     * Test we can read a report competency configuration.
     */
    public function test_read_scale_configuration() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $result = external::read_report_competency_config($framework->get('id'), $scale->id);
        $result = (object) external_api::clean_returnvalue(external::read_report_competency_config_returns(), $result);

        $this->assertEquals($framework->get('id'), $result->competencyframeworkid);
        $this->assertEquals($scale->id, $result->scaleid);

        $scaleconfig = $result->scaleconfiguration;
        $this->assertEquals($scaleconfig[0]['color'], report_competency_config::DEFAULT_COLOR);
        $this->assertEquals($scaleconfig[1]['color'], report_competency_config::DEFAULT_COLOR);
        $this->assertEquals($scaleconfig[2]['color'], report_competency_config::DEFAULT_COLOR);
        $this->assertEquals($scaleconfig[3]['color'], report_competency_config::DEFAULT_COLOR);

    }

    /**
     * Test missing capability to create configuration for a framework and a scale.
     */
    public function test_no_capability_to_create_scale_configuration() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $record = array();
        $record['competencyframeworkid'] = $framework->get('id');
        $record['scaleid'] = $scale->id;
        $record['scaleconfiguration'] = json_encode($scaleconfig);

        $this->setUser($this->appreciator);

        try {
            external::create_report_competency_config($framework->get('id'), $scale->id, json_encode($scaleconfig));
            $this->fail('Configuration can not be created when user does not have capability');
        } catch (required_capability_exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test we can read a report competency configuration.
     */
    public function test_create_scale_configuration() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $record = array();
        $record['competencyframeworkid'] = $framework->get('id');
        $record['scaleid'] = $scale->id;
        $record['scaleconfiguration'] = json_encode($scaleconfig);

        $result = external::create_report_competency_config($framework->get('id'), $scale->id, json_encode($scaleconfig));
        $result = (object) external_api::clean_returnvalue(external::create_report_competency_config_returns(), $result);

        $this->assertEquals($framework->get('id'), $result->competencyframeworkid);
        $this->assertEquals($scale->id, $result->scaleid);

        $scaleconfig = $result->scaleconfiguration;
        $this->assertEquals($scaleconfig[0]['color'], '#AAAAA');
        $this->assertEquals($scaleconfig[1]['color'], '#BBBBB');
        $this->assertEquals($scaleconfig[2]['color'], '#CCCCC');
        $this->assertEquals($scaleconfig[3]['color'], '#DDDDD');

    }

    /**
     * est missing capability to update configuration for a framework and a scale.
     */
    public function test_no_capability_to_update_scale_configuration() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 0, 'name' => 'A',  'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 1, 'name' => 'B',  'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 2, 'name' => 'C',  'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 3, 'name' => 'D',  'color' => '#DDDDD');

        $reportconfig = $lpg->create_report_competency_config(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scale->id,
                'scaleconfiguration' => $scaleconfig));

        // Change de colors for scale.
        $record = array();
        $record['competencyframeworkid'] = $framework->get('id');
        $record['scaleid'] = $scale->id;

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 0, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 1, 'color' => '#XXXXX');
        $scaleconfig[] = array('id' => 2, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 3, 'color' => '#ZZZZZ');
        $record['scaleconfiguration'] = json_encode($scaleconfig);

        $this->setUser($this->appreciator);

        try {
            external::update_report_competency_config($framework->get('id'), $scale->id,
                json_encode($scaleconfig));
            $this->fail('Configuration can not be updated when user does not have capability');
        } catch (required_capability_exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test we can update a report competency configuration.
     */
    public function test_update_scale_configuration() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 0, 'name' => 'A',  'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 1, 'name' => 'B',  'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 2, 'name' => 'C',  'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 3, 'name' => 'D',  'color' => '#DDDDD');

        $reportconfig = $lpg->create_report_competency_config(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scale->id,
                'scaleconfiguration' => $scaleconfig));

        // Change de colors for scale.
        $record = array();
        $record['competencyframeworkid'] = $framework->get('id');
        $record['scaleid'] = $scale->id;

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 0, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 1, 'color' => '#XXXXX');
        $scaleconfig[] = array('id' => 2, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 3, 'color' => '#ZZZZZ');
        $record['scaleconfiguration'] = json_encode($scaleconfig);

        $result = external::update_report_competency_config($framework->get('id'), $scale->id,
                json_encode($scaleconfig));
        $result = external_api::clean_returnvalue(external::update_report_competency_config_returns(), $result);

        $this->assertTrue($result);

        $reportconfig = external::read_report_competency_config($framework->get('id'), $scale->id);
        $reportconfig = (object) external_api::clean_returnvalue(external::read_report_competency_config_returns(), $reportconfig);

        $this->assertEquals($reportconfig->competencyframeworkid, $framework->get('id'));
        $this->assertEquals($reportconfig->scaleid, $scale->id);

        $scaleconfig = $reportconfig->scaleconfiguration;
        $this->assertEquals($scaleconfig[0]['color'], '#AAAAA');
        $this->assertEquals($scaleconfig[1]['color'], '#XXXXX');
        $this->assertEquals($scaleconfig[2]['color'], '#CCCCC');
        $this->assertEquals($scaleconfig[3]['color'], '#ZZZZZ');
    }

    /**
     * Test we can read plan.
     * @group read_plan
     */
    public function test_read_plan() {
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');

        $user1 = $dg->create_user(array('lastname' => 'Austin', 'firstname' => 'Sharon'));
        $user2 = $dg->create_user(array('lastname' => 'Cortez', 'firstname' => 'Jonathan'));
        $user3 = $dg->create_user(array('lastname' => 'Underwood', 'firstname' => 'Alicia'));

        $f1 = $lpg->create_framework();

        $c1a = $lpg->create_competency(array('competencyframeworkid' => $f1->get('id')));
        $c1b = $lpg->create_competency(array('competencyframeworkid' => $f1->get('id')));
        $c1c = $lpg->create_competency(array('competencyframeworkid' => $f1->get('id')));

        $tpl = $lpg->create_template();
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $c1a->get('id')));
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $c1c->get('id')));

        $plan1 = $lpg->create_plan(array('userid' => $user1->id, 'templateid' => $tpl->get('id')));
        $plan2 = $lpg->create_plan(array('userid' => $user2->id, 'templateid' => $tpl->get('id'),
                'status' => plan::STATUS_ACTIVE));
        $plan3 = $lpg->create_plan(array('userid' => $user3->id, 'templateid' => $tpl->get('id'),
                'status' => plan::STATUS_ACTIVE));
        $plan4 = $lpg->create_plan(array('userid' => $user1->id, 'status' => plan::STATUS_COMPLETE));

        // Some ratings for user2.
        $lpg->create_user_competency(array('userid' => $user2->id, 'competencyid' => $c1a->get('id'),
            'grade' => 1, 'proficiency' => 0));
        $lpg->create_user_competency(array('userid' => $user2->id, 'competencyid' => $c1c->get('id'),
            'grade' => 2, 'proficiency' => 1));

        // Some ratings for user3.
        $lpg->create_user_competency(array('userid' => $user3->id, 'competencyid' => $c1a->get('id'),
            'grade' => 2, 'proficiency' => 1));

        // Get plans urls.
        $plan1url = url::plan($plan1->get('id'))->out(false);
        $plan2url = url::plan($plan2->get('id'))->out(false);
        $plan3url = url::plan($plan3->get('id'))->out(false);
        $plan4url = url::plan($plan4->get('id'))->out(false);

        // Status names string.
        $statusnamecomplete = get_string('planstatuscomplete', 'core_competency');
        $statusnameactive = get_string('planstatusactive', 'core_competency');
        $statusnamedraft = get_string('planstatusdraft', 'core_competency');

        // Test plan not based on a template.
        $result = external::read_plan($plan4->get('id'), 0);
        $result = external::clean_returnvalue(external::read_plan_returns(), $result);
        $this->assertEquals($plan4->get('id'), $result['plan']['id']);
        $this->assertEquals($plan4->get('name'), $result['plan']['name']);
        $this->assertEquals($user1->id, $result['plan']['user']['id']);
        $this->assertEquals('Sharon Austin', $result['plan']['user']['fullname']);
        $this->assertFalse($result['plan']['isactive']);
        $this->assertFalse($result['plan']['isdraft']);
        $this->assertTrue($result['plan']['iscompleted']);
        $this->assertEquals($statusnamecomplete, $result['plan']['statusname']);
        $this->assertEquals($plan4url, $result['plan']['url']);
        $this->assertFalse($result['hasnavigation']);
        $this->assertArrayNotHasKey('navprev', $result);
        $this->assertArrayNotHasKey('navnext', $result);

        // Test plan based on a template that is is the first in the list of plans.
        $result = external::read_plan($plan1->get('id'), $tpl->get('id'));
        $result = external::clean_returnvalue(external::read_plan_returns(), $result);
        $this->assertEquals($plan1->get('id'), $result['plan']['id']);
        $this->assertEquals($plan1->get('name'), $result['plan']['name']);
        $this->assertEquals($user1->id, $result['plan']['user']['id']);
        $this->assertEquals('Sharon Austin', $result['plan']['user']['fullname']);
        $this->assertFalse($result['plan']['isactive']);
        $this->assertTrue($result['plan']['isdraft']);
        $this->assertEquals($statusnamedraft, $result['plan']['statusname']);
        $this->assertFalse($result['plan']['iscompleted']);
        $this->assertEquals($plan1url, $result['plan']['url']);
        $this->assertTrue($result['hasnavigation']);
        $this->assertArrayNotHasKey('navprev', $result);
        $this->assertArrayHasKey('navnext', $result);
        $this->assertEquals($user2->id, $result['navnext']['userid']);
        $this->assertEquals('Jonathan Cortez', $result['navnext']['fullname']);
        $this->assertEquals($plan2->get('id'), $result['navnext']['planid']);

        // Test plan based on a template that is in the middle in the list of plans.
        $result = external::read_plan($plan2->get('id'), $tpl->get('id'));
        $result = external::clean_returnvalue(external::read_plan_returns(), $result);
        $this->assertEquals($plan2->get('id'), $result['plan']['id']);
        $this->assertEquals($plan2->get('name'), $result['plan']['name']);
        $this->assertEquals($user2->id, $result['plan']['user']['id']);
        $this->assertEquals('Jonathan Cortez', $result['plan']['user']['fullname']);
        $this->assertTrue($result['plan']['isactive']);
        $this->assertEquals($statusnameactive, $result['plan']['statusname']);
        $this->assertFalse($result['plan']['isdraft']);
        $this->assertFalse($result['plan']['iscompleted']);
        $this->assertEquals($plan2url, $result['plan']['url']);
        $this->assertTrue($result['hasnavigation']);
        $this->assertArrayHasKey('navprev', $result);
        $this->assertEquals($user1->id, $result['navprev']['userid']);
        $this->assertEquals('Sharon Austin', $result['navprev']['fullname']);
        $this->assertEquals($plan1->get('id'), $result['navprev']['planid']);
        $this->assertArrayHasKey('navnext', $result);
        $this->assertEquals($user3->id, $result['navnext']['userid']);
        $this->assertEquals('Alicia Underwood', $result['navnext']['fullname']);
        $this->assertEquals($plan3->get('id'), $result['navnext']['planid']);
        $this->assertEquals(2, $result['plan']['stats']['nbcompetenciestotal']);
        $this->assertEquals(1, $result['plan']['stats']['nbcompetenciesnotproficient']);
        $this->assertEquals(1, $result['plan']['stats']['nbcompetenciesproficient']);
        $this->assertEquals(0, $result['plan']['stats']['nbcompetenciesnotrated']);

        // Test plan based on a template that is the last in the list of plans.
        $result = external::read_plan($plan3->get('id'), $tpl->get('id'));
        $result = external::clean_returnvalue(external::read_plan_returns(), $result);
        $this->assertEquals($plan3->get('id'), $result['plan']['id']);
        $this->assertEquals($plan3->get('name'), $result['plan']['name']);
        $this->assertEquals($user3->id, $result['plan']['user']['id']);
        $this->assertEquals('Alicia Underwood', $result['plan']['user']['fullname']);
        $this->assertTrue($result['plan']['isactive']);
        $this->assertEquals($statusnameactive, $result['plan']['statusname']);
        $this->assertFalse($result['plan']['isdraft']);
        $this->assertFalse($result['plan']['iscompleted']);
        $this->assertEquals($plan3url, $result['plan']['url']);
        $this->assertTrue($result['hasnavigation']);
        $this->assertArrayHasKey('navprev', $result);
        $this->assertEquals($user2->id, $result['navprev']['userid']);
        $this->assertEquals('Jonathan Cortez', $result['navprev']['fullname']);
        $this->assertEquals($plan2->get('id'), $result['navprev']['planid']);
        $this->assertArrayNotHasKey('navnext', $result);
        $this->assertEquals(2, $result['plan']['stats']['nbcompetenciestotal']);
        $this->assertEquals(0, $result['plan']['stats']['nbcompetenciesnotproficient']);
        $this->assertEquals(1, $result['plan']['stats']['nbcompetenciesproficient']);
        $this->assertEquals(1, $result['plan']['stats']['nbcompetenciesnotrated']);

        // Test reading of plan when passing only the template ID.
        $result = external::read_plan(0, $tpl->get('id'));
        $result = external::clean_returnvalue(external::read_plan_returns(), $result);
        $this->assertEquals($plan1->get('id'), $result['plan']['id']);
        $this->assertEquals($plan1->get('name'), $result['plan']['name']);
        $this->assertEquals($user1->id, $result['plan']['user']['id']);
        $this->assertEquals('Sharon Austin', $result['plan']['user']['fullname']);
        $this->assertFalse($result['plan']['isactive']);
        $this->assertTrue($result['plan']['isdraft']);
        $this->assertEquals($statusnamedraft, $result['plan']['statusname']);
        $this->assertFalse($result['plan']['iscompleted']);
        $this->assertTrue($result['hasnavigation']);
        $this->assertArrayNotHasKey('navprev', $result);
        $this->assertArrayHasKey('navnext', $result);
        $this->assertEquals($user2->id, $result['navnext']['userid']);
        $this->assertEquals('Jonathan Cortez', $result['navnext']['fullname']);
        $this->assertEquals($plan2->get('id'), $result['navnext']['planid']);
        // Test display rating settings.
        // Template on , plan off.
        $this->setAdminUser();
        \tool_lp\external::set_display_rating_for_template($tpl->get('id'), 1);
        \tool_lp\external::set_display_rating_for_plan($plan2->get('id'), 0);
        $this->setUser($user2);
        $result = external::read_plan($plan2->get('id'), 0);
        $result = external::clean_returnvalue(external::read_plan_returns(), $result);
        $this->assertEquals(2, $result['plan']['stats']['nbcompetenciestotal']);
        $this->assertEquals(0, $result['plan']['stats']['nbcompetenciesnotproficient']);
        $this->assertEquals(0, $result['plan']['stats']['nbcompetenciesproficient']);
        $this->assertEquals(2, $result['plan']['stats']['nbcompetenciesnotrated']);
        // Reset display rating of plan to be identical to template.
        $this->setAdminUser();
        \tool_lp\external::reset_display_rating_for_plan($plan2->get('id'));
        $this->setUser($user2);
        $result = external::read_plan($plan2->get('id'), 0);
        $result = external::clean_returnvalue(external::read_plan_returns(), $result);
        $this->assertEquals(2, $result['plan']['stats']['nbcompetenciestotal']);
        $this->assertEquals(1, $result['plan']['stats']['nbcompetenciesnotproficient']);
        $this->assertEquals(1, $result['plan']['stats']['nbcompetenciesproficient']);
        $this->assertEquals(0, $result['plan']['stats']['nbcompetenciesnotrated']);
        // Template off , plan on.
        $this->setAdminUser();
        \tool_lp\external::set_display_rating_for_template($tpl->get('id'), 0);
        \tool_lp\external::set_display_rating_for_plan($plan2->get('id'), 1);
        $this->setUser($user2);
        $result = external::read_plan($plan2->get('id'), 0);
        $result = external::clean_returnvalue(external::read_plan_returns(), $result);
        $this->assertEquals(2, $result['plan']['stats']['nbcompetenciestotal']);
        $this->assertEquals(1, $result['plan']['stats']['nbcompetenciesnotproficient']);
        $this->assertEquals(1, $result['plan']['stats']['nbcompetenciesproficient']);
        $this->assertEquals(0, $result['plan']['stats']['nbcompetenciesnotrated']);
        // Reset display rating of plan to be identical to template.
        $this->setAdminUser();
        \tool_lp\external::reset_display_rating_for_plan($plan2->get('id'));
        $this->setUser($user2);
        $result = external::read_plan($plan2->get('id'), 0);
        $result = external::clean_returnvalue(external::read_plan_returns(), $result);
        $this->assertEquals(2, $result['plan']['stats']['nbcompetenciestotal']);
        $this->assertEquals(0, $result['plan']['stats']['nbcompetenciesnotproficient']);
        $this->assertEquals(0, $result['plan']['stats']['nbcompetenciesproficient']);
        $this->assertEquals(2, $result['plan']['stats']['nbcompetenciesnotrated']);
    }

    /**
     * Test get competency detail for lpmonitoring report.
     */
    public function test_get_competency_detail() {
        global $DB;

        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $mpg = $dg->get_plugin_generator('report_lpmonitoring');

        $c1 = $dg->create_course();
        $c2 = $dg->create_course();
        $c3 = $dg->create_course();
        $c4 = $dg->create_course();
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();

        // Create framework with competencies.
        $framework = $lpg->create_framework();
        $comp0 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id'),
            'parentid' => $comp0->get('id')));   // In C1, and C2.
        $comp2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));   // In C2.
        $comp3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));   // In None.
        $comp4 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));   // In C4.

        // Create plan for user1.
        $plan = $lpg->create_plan(array('userid' => $u1->id, 'status' => plan::STATUS_ACTIVE));
        $lpg->create_plan_competency(array('planid' => $plan->get('id'), 'competencyid' => $comp1->get('id')));

        // Associated competencies to courses.
        $lpg->create_course_competency(array('competencyid' => $comp1->get('id'), 'courseid' => $c1->id));
        $lpg->create_course_competency(array('competencyid' => $comp1->get('id'), 'courseid' => $c3->id));
        $lpg->create_course_competency(array('competencyid' => $comp1->get('id'), 'courseid' => $c2->id));
        $lpg->create_course_competency(array('competencyid' => $comp2->get('id'), 'courseid' => $c2->id));
        $lpg->create_course_competency(array('competencyid' => $comp4->get('id'), 'courseid' => $c4->id));

        // Create scale report configuration.
        $scaleconfig[] = array('id' => 1, 'name' => 'A',  'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'name' => 'B',  'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'name' => 'C',  'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'name' => 'D',  'color' => '#DDDDD');

        $record = new stdclass();
        $record->competencyframeworkid = $framework->get('id');
        $record->scaleid = $framework->get('scaleid');
        $record->scaleconfiguration = json_encode($scaleconfig);
        $mpg->create_report_competency_config($record);

        // Enrol the user 1 in C1, C2, and C3.
        $dg->enrol_user($u1->id, $c1->id);
        $dg->enrol_user($u1->id, $c2->id);
        $dg->enrol_user($u1->id, $c3->id);

        // Enrol the user 2 in C4.
        $dg->enrol_user($u2->id, $c4->id);

        // Assigne rates to comptencies in courses C1 and C2.
        $record1 = new \stdClass();
        $record1->userid = $u1->id;
        $record1->courseid = $c1->id;
        $record1->competencyid = $comp1->get('id');
        $record1->proficiency = 1;
        $record1->grade = 1;
        $record1->timecreated = 10;
        $record1->timemodified = 10;
        $record1->usermodified = $u1->id;

        $record2 = new \stdClass();
        $record2->userid = $u1->id;
        $record2->courseid = $c2->id;
        $record2->competencyid = $comp1->get('id');
        $record2->proficiency = 0;
        $record2->grade = 2;
        $record2->timecreated = 10;
        $record2->timemodified = 10;
        $record2->usermodified = $u1->id;;
        $DB->insert_records('competency_usercompcourse', array($record1, $record2));

        // Create user competency and add an evidence.
        $uc = $lpg->create_user_competency(array('userid' => $u1->id, 'competencyid' => $comp1->get('id')));

        // Add prior learning evidence.
        $ue1 = $lpg->create_user_evidence(array('userid' => $u1->id));

        // Associate the prior learning evidence to competency.
        $lpg->create_user_evidence_competency(array('userevidenceid' => $ue1->get('id'), 'competencyid' => $comp1->get('id')));

        // Create modules.
        $data = $dg->create_module('data', array('assessed' => 1, 'scale' => 100, 'course' => $c1->id));
        $datacm = get_coursemodule_from_id('data', $data->cmid);

        // Insert student grades for the activity.
        $gi = grade_item::fetch(array('itemtype' => 'mod', 'itemmodule' => 'data', 'iteminstance' => $data->id,
            'courseid' => $c1->id));
        $datagrade = 50;
        $gradegrade = new grade_grade();
        $gradegrade->itemid = $gi->id;
        $gradegrade->userid = $u1->id;
        $gradegrade->rawgrade = $datagrade;
        $gradegrade->finalgrade = $datagrade;
        $gradegrade->rawgrademax = 50;
        $gradegrade->rawgrademin = 0;
        $gradegrade->timecreated = time();
        $gradegrade->timemodified = time();
        $gradegrade->insert();

        // Create an evidence for the user prior learning evidence.
        $e1 = $lpg->create_evidence(array('usercompetencyid' => $uc->get('id'),
            'contextid' => \context_user::instance($u1->id)->id));

        // Add evidences for courses C1, C2.
        $lpg->create_evidence(array('usercompetencyid' => $uc->get('id'), 'note' => 'Note text',
            'contextid' => \context_course::instance($c1->id)->id));
        $lpg->create_evidence(array('usercompetencyid' => $uc->get('id'),
            'contextid' => \context_course::instance($c2->id)->id));

        // Assign final grade for the course C1.
        $courseitem = \grade_item::fetch_course_item($c1->id);
        $result = $courseitem->update_final_grade($u1->id, 67, 'import', null);

        $context = context_course::instance($c1->id);
        $this->assign_good_letter_boundary($context->id);

        // Assign final grade for the course C2.
        $courseitem = \grade_item::fetch_course_item($c2->id);
        $result = $courseitem->update_final_grade($u1->id, 88, 'import', null);

        $context = context_course::instance($c2->id);
        $this->assign_good_letter_boundary($context->id);

        $result = external::get_competency_detail($u1->id, $comp1->get('id'), $plan->get('id'));
        $result = (object) external_api::clean_returnvalue(external::get_competency_detail_returns(), $result);

        $this->assertEquals($result->competencyid, $comp1->get('id'));
        $this->assertTrue($result->hasevidence);
        $this->assertEquals($result->nbevidence, 1);
        $this->assertEquals(count($result->listevidence), 1);
        $this->assertEquals($result->nbcoursestotal, 3);
        $this->assertEquals($result->nbcoursesrated, 2);
        $this->assertEquals(count($result->listtotalcourses), 3);
        // Test url user evidence.
        $urluserevidence = url::user_evidence($ue1->get('id'))->out(false);
        $this->assertEquals($result->listevidence[0]['userevidenceurl'], $urluserevidence);

        // Check courses linked to the competency.
        $urlpage = 'user_competency_in_course.php';
        $urlcompetencyids = array($comp1->get('id'));
        $urluseridids = array($u1->id);
        $urlcourseids = array($c1->id, $c2->id, $c3->id);
        foreach ($result->listtotalcourses as $course) {
            $errormsg = self::validate_url($course['url'], $urlpage,
                array('userid' => $urluseridids, 'competencyid' => $urlcompetencyids, 'courseid' => $urlcourseids));
            $this->assertEmpty($errormsg, $errormsg);

            $courseid = self::get_url_param_value ($course['url'], 'courseid');
            if ($courseid == $c1->id) {
                $this->assertTrue($course['rated']);
                $this->assertEquals($course['coursename'], $c1->shortname);
            } else {
                if ($courseid == $c2->id) {
                    $this->assertTrue($course['rated']);
                    $this->assertEquals($course['coursename'], $c2->shortname);
                } else {
                    $this->assertFalse($course['rated']);
                    $this->assertEquals($course['coursename'], $c3->shortname);
                }
            }
        }

        // Check scale competency items.
        $listscaleid = array(1, 2, 3, 4);
        $urlpage = 'user_competency_in_course.php';
        $urlcompetencyids = array($comp1->get('id'));
        $urluseridids = array($u1->id);

        foreach ($result->scalecompetencyitems as $scalecompetencyitem) {
            $this->assertTrue(in_array($scalecompetencyitem['value'], $listscaleid ));
            if ($scalecompetencyitem['value'] == '1') {
                $this->assertEquals($scalecompetencyitem['name'], 'A');
                $this->assertEquals($scalecompetencyitem['color'], '#AAAAA');
                $this->assertEquals($scalecompetencyitem['nbcourse'], 1);

                // This scale value must have cours 1 associated.
                $this->assertEquals(count($scalecompetencyitem['listcourses']), 1);
                $this->assertEquals($scalecompetencyitem['listcourses'][0]['shortname'], $c1->shortname);
                $this->assertEquals($scalecompetencyitem['listcourses'][0]['grade'], 'C+');
                $this->assertEquals($scalecompetencyitem['listcourses'][0]['nbnotes'], 1);
                $errormsg = self::validate_url($scalecompetencyitem['listcourses'][0]['url'], $urlpage,
                    array('userid' => $urluseridids, 'competencyid' => $urlcompetencyids, 'courseid' => array($c1->id)));
                $this->assertEmpty($errormsg, $errormsg);
            } else if ($scalecompetencyitem['value'] == '2') {
                    $this->assertEquals($scalecompetencyitem['name'], 'B');
                    $this->assertEquals($scalecompetencyitem['color'], '#BBBBB');
                    $this->assertEquals($scalecompetencyitem['nbcourse'], 1);

                    // This scale value must have cours 2 associated.
                    $this->assertEquals(count($scalecompetencyitem['listcourses']), 1);
                    $this->assertEquals($scalecompetencyitem['listcourses'][0]['shortname'], $c2->shortname);
                    $this->assertEquals($scalecompetencyitem['listcourses'][0]['grade'], 'A-');
                    $this->assertEquals($scalecompetencyitem['listcourses'][0]['nbnotes'], 0);
                    $errormsg = self::validate_url($scalecompetencyitem['listcourses'][0]['url'], $urlpage,
                        array('userid' => $urluseridids, 'competencyid' => $urlcompetencyids, 'courseid' => array($c2->id)));
                    $this->assertEmpty($errormsg, $errormsg);
            } else if ($scalecompetencyitem['value'] == '3') {
                    $this->assertEquals($scalecompetencyitem['name'], 'C');
                    $this->assertEquals($scalecompetencyitem['color'], '#CCCCC');
                    $this->assertEquals($scalecompetencyitem['nbcourse'], 0);

                    // This scale value does not have courses associated.
                    $this->assertEquals(count($scalecompetencyitem['listcourses']), 0);
            } else {
                    $this->assertEquals($scalecompetencyitem['name'], 'D');
                    $this->assertEquals($scalecompetencyitem['color'], '#DDDDD');
                    $this->assertEquals($scalecompetencyitem['nbcourse'], 0);

                    // This scale value does not have courses associated.
                    $this->assertEquals(count($scalecompetencyitem['listcourses']), 0);
            }
        }
    }

    /**
     * Test list plan competencies for lpmonitoring report.
     */
    public function test_list_plan_competencies() {
        $this->setUser($this->creator);

        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');

        $f1 = $lpg->create_framework();
        $f2 = $lpg->create_framework();
        $user = $dg->create_user();

        $c1a = $lpg->create_competency(array('competencyframeworkid' => $f1->get('id')));
        $c1b = $lpg->create_competency(array('competencyframeworkid' => $f1->get('id')));
        $c1c = $lpg->create_competency(array('competencyframeworkid' => $f1->get('id')));
        $c2a = $lpg->create_competency(array('competencyframeworkid' => $f2->get('id')));
        $c2b = $lpg->create_competency(array('competencyframeworkid' => $f2->get('id')));

        $tpl = $lpg->create_template();
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $c1a->get('id')));
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $c1c->get('id')));
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $c2b->get('id')));

        $plan = $lpg->create_plan(array('userid' => $user->id, 'templateid' => $tpl->get('id'),
                'status' => plan::STATUS_ACTIVE));

        $uc1a = $lpg->create_user_competency(array('userid' => $user->id, 'competencyid' => $c1a->get('id'),
            'status' => user_competency::STATUS_IN_REVIEW, 'reviewerid' => $this->creator->id));
        $uc1c = $lpg->create_user_competency(array('userid' => $user->id, 'competencyid' => $c1c->get('id'),
            'grade' => 1, 'proficiency' => 0));
        $uc2b = $lpg->create_user_competency(array('userid' => $user->id, 'competencyid' => $c2b->get('id'),
            'grade' => 2, 'proficiency' => 1));

        $result = external::list_plan_competencies($plan->get('id'));
        $result = external::clean_returnvalue(external::list_plan_competencies_returns(), $result);

        $this->assertCount(3, $result);
        $this->assertEquals($c1a->get('id'), $result[0]['competency']['id']);
        $this->assertEquals(true, $result[0]['isnotrated']);
        $this->assertEquals(false, $result[0]['isproficient']);
        $this->assertEquals(false, $result[0]['isnotproficient']);
        $this->assertEquals($user->id, $result[0]['usercompetency']['userid']);
        $this->assertArrayNotHasKey('usercompetencyplan', $result[0]);
        $this->assertEquals($user->id, $result[1]['usercompetency']['userid']);
        $this->assertEquals(true, $result[1]['isnotproficient']);
        $this->assertEquals(false, $result[1]['isproficient']);
        $this->assertEquals(false, $result[1]['isnotrated']);
        $this->assertArrayNotHasKey('usercompetencyplan', $result[1]);
        $this->assertEquals($c2b->get('id'), $result[2]['competency']['id']);
        $this->assertEquals($user->id, $result[2]['usercompetency']['userid']);
        $this->assertEquals(true, $result[2]['isproficient']);
        $this->assertEquals(false, $result[2]['isnotproficient']);
        $this->assertEquals(false, $result[2]['isnotrated']);
        $this->assertArrayNotHasKey('usercompetencyplan', $result[2]);
        $this->assertEquals(user_competency::STATUS_IN_REVIEW, $result[0]['usercompetency']['status']);
        $this->assertEquals(2, $result[2]['usercompetency']['grade']);
        $this->assertEquals(1, $result[2]['usercompetency']['proficiency']);

        // Check the return values when the plan status is complete.
        $completedplan = $lpg->create_plan(array('userid' => $user->id, 'templateid' => $tpl->get('id'),
                'status' => plan::STATUS_COMPLETE));

        $uc1a = $lpg->create_user_competency_plan(array('userid' => $user->id, 'competencyid' => $c1a->get('id'),
                'planid' => $completedplan->get('id')));
        $uc1b = $lpg->create_user_competency_plan(array('userid' => $user->id, 'competencyid' => $c1c->get('id'),
                'planid' => $completedplan->get('id')));
        $uc2b = $lpg->create_user_competency_plan(array('userid' => $user->id, 'competencyid' => $c2b->get('id'),
                'planid' => $completedplan->get('id'), 'grade' => 2, 'proficiency' => 1));

        $result = external::list_plan_competencies($completedplan->get('id'));
        $result = external::clean_returnvalue(external::list_plan_competencies_returns(), $result);

        $this->assertCount(3, $result);
        $this->assertEquals($c1a->get('id'), $result[0]['competency']['id']);
        $this->assertEquals($user->id, $result[0]['usercompetencyplan']['userid']);
        $this->assertEquals(true, $result[0]['isnotrated']);
        $this->assertEquals(false, $result[0]['isproficient']);
        $this->assertEquals(false, $result[0]['isnotproficient']);
        $this->assertArrayNotHasKey('usercompetency', $result[0]);
        $this->assertEquals($c1c->get('id'), $result[1]['competency']['id']);
        $this->assertEquals($user->id, $result[1]['usercompetencyplan']['userid']);
        $this->assertEquals(true, $result[1]['isnotrated']);
        $this->assertEquals(false, $result[1]['isproficient']);
        $this->assertEquals(false, $result[1]['isnotproficient']);
        $this->assertArrayNotHasKey('usercompetency', $result[1]);
        $this->assertEquals($c2b->get('id'), $result[2]['competency']['id']);
        $this->assertEquals($user->id, $result[2]['usercompetencyplan']['userid']);
        $this->assertEquals(false, $result[2]['isnotrated']);
        $this->assertEquals(true, $result[2]['isproficient']);
        $this->assertEquals(false, $result[2]['isnotproficient']);
        $this->assertArrayNotHasKey('usercompetency', $result[2]);
        $this->assertEquals(null, $result[1]['usercompetencyplan']['grade']);
        $this->assertEquals(2, $result[2]['usercompetencyplan']['grade']);
        $this->assertEquals(1, $result[2]['usercompetencyplan']['proficiency']);
        // Test display rating.
        // Display rating template off.
        $this->setAdminUser();
        \tool_lp\external::set_display_rating_for_template($tpl->get('id'), 0);
        // User should not see ratings.
        $this->setUser($user);
        $result = external::list_plan_competencies($plan->get('id'));
        $result = external::clean_returnvalue(external::list_plan_competencies_returns(), $result);
        // Take competency 2 as example.
        $this->assertEquals(false, $result[2]['isproficient']);
        $this->assertEquals(false, $result[2]['isnotproficient']);
        $this->assertEquals(true, $result[2]['isnotrated']);
        $this->assertEquals('-', $result[2]['usercompetency']['gradename']);
        $this->assertEquals('-', $result[2]['usercompetency']['proficiencyname']);
        $this->assertNull($result[2]['usercompetency']['grade']);
        $this->assertNull($result[2]['usercompetency']['proficiency']);
        // Display rating template on.
        $this->setAdminUser();
        \tool_lp\external::set_display_rating_for_template($tpl->get('id'), 1);
        // User should see ratings.
        $this->setUser($user);
        $result = external::list_plan_competencies($plan->get('id'));
        $result = external::clean_returnvalue(external::list_plan_competencies_returns(), $result);
        // Take competency 2 as example.
        $this->assertEquals(true, $result[2]['isproficient']);
        $this->assertEquals(false, $result[2]['isnotproficient']);
        $this->assertEquals(false, $result[2]['isnotrated']);
        $this->assertNotEquals('-', $result[2]['usercompetency']['gradename']);
        $this->assertNotEquals('-', $result[2]['usercompetency']['proficiencyname']);
        $this->assertEquals(2, $result[2]['usercompetency']['grade']);
        $this->assertEquals(1, $result[2]['usercompetency']['proficiency']);
        // Display rating template off, plan on.
        $this->setAdminUser();
        \tool_lp\external::set_display_rating_for_template($tpl->get('id'), 0);
        \tool_lp\external::set_display_rating_for_plan($plan->get('id'), 1);
        // User should see ratings.
        $this->setUser($user);
        $result = external::list_plan_competencies($plan->get('id'));
        $result = external::clean_returnvalue(external::list_plan_competencies_returns(), $result);
        // Take competency 2 as example.
        $this->assertEquals(true, $result[2]['isproficient']);
        $this->assertEquals(false, $result[2]['isnotproficient']);
        $this->assertEquals(false, $result[2]['isnotrated']);
        $this->assertNotEquals('-', $result[2]['usercompetency']['gradename']);
        $this->assertNotEquals('-', $result[2]['usercompetency']['proficiencyname']);
        $this->assertEquals(2, $result[2]['usercompetency']['grade']);
        $this->assertEquals(1, $result[2]['usercompetency']['proficiency']);
        // Reset display rating to be identical to template.
        $this->setAdminUser();
        \tool_lp\external::reset_display_rating_for_plan($plan->get('id'));
        $this->setUser($user);
        $result = external::list_plan_competencies($plan->get('id'));
        $result = external::clean_returnvalue(external::list_plan_competencies_returns(), $result);
        // Take competency 2 as example.
        $this->assertEquals(false, $result[2]['isproficient']);
        $this->assertEquals(false, $result[2]['isnotproficient']);
        $this->assertEquals(true, $result[2]['isnotrated']);
        $this->assertEquals('-', $result[2]['usercompetency']['gradename']);
        $this->assertEquals('-', $result[2]['usercompetency']['proficiencyname']);
        $this->assertNull($result[2]['usercompetency']['grade']);
        $this->assertNull($result[2]['usercompetency']['proficiency']);
        // Display rating template on, plan off.
        $this->setAdminUser();
        \tool_lp\external::set_display_rating_for_template($tpl->get('id'), 1);
        \tool_lp\external::set_display_rating_for_plan($plan->get('id'), 0);
        // User should not see ratings.
        $this->setUser($user);
        $result = external::list_plan_competencies($plan->get('id'));
        $result = external::clean_returnvalue(external::list_plan_competencies_returns(), $result);
        // Take competency 2 as example.
        $this->assertEquals(false, $result[2]['isproficient']);
        $this->assertEquals(false, $result[2]['isnotproficient']);
        $this->assertEquals(true, $result[2]['isnotrated']);
        $this->assertEquals('-', $result[2]['usercompetency']['gradename']);
        $this->assertEquals('-', $result[2]['usercompetency']['proficiencyname']);
        $this->assertNull($result[2]['usercompetency']['grade']);
        $this->assertNull($result[2]['usercompetency']['proficiency']);
        // Reset display rating to be identical to template.
        $this->setAdminUser();
        \tool_lp\external::reset_display_rating_for_plan($plan->get('id'));
        $this->setUser($user);
        $result = external::list_plan_competencies($plan->get('id'));
        $result = external::clean_returnvalue(external::list_plan_competencies_returns(), $result);
        // Take competency 2 as example.
        $this->assertEquals(true, $result[2]['isproficient']);
        $this->assertEquals(false, $result[2]['isnotproficient']);
        $this->assertEquals(false, $result[2]['isnotrated']);
        $this->assertNotEquals('-', $result[2]['usercompetency']['gradename']);
        $this->assertNotEquals('-', $result[2]['usercompetency']['proficiencyname']);
        $this->assertEquals(2, $result[2]['usercompetency']['grade']);
        $this->assertEquals(1, $result[2]['usercompetency']['proficiency']);
    }

    /**
     * Test get competency statistics for lpmonitoring report.
     */
    public function test_get_lp_monitoring_competency_statistics() {
        global $DB;

        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $mpg = $dg->get_plugin_generator('report_lpmonitoring');

        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();
        $u4 = $dg->create_user();

        // Create scale.
        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));

        // Create framework with the scale configuration.
        $scaleconfig = array(array('scaleid' => $scale->id));
        $scaleconfig[] = array('name' => 'A', 'id' => 1, 'scaledefault' => 0, 'proficient' => 1);
        $scaleconfig[] = array('name' => 'B', 'id' => 2, 'scaledefault' => 1, 'proficient' => 1);
        $framework = $lpg->create_framework(array('scaleid' => $scale->id, 'scaleconfiguration' => $scaleconfig));

        // Associate competencies to framework.
        $comp0 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id'),
                'parentid' => $comp0->get('id'), 'path' => '0/'. $comp0->get('id')));
        $comp2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp4 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));

        // Create template with competencies.
        $template = $lpg->create_template();
        $tempcomp0 = $lpg->create_template_competency(array('templateid' => $template->get('id'),
            'competencyid' => $comp0->get('id')));
        $tempcomp1 = $lpg->create_template_competency(array('templateid' => $template->get('id'),
            'competencyid' => $comp1->get('id')));
        $tempcomp2 = $lpg->create_template_competency(array('templateid' => $template->get('id'),
            'competencyid' => $comp2->get('id')));
        $tempcomp3 = $lpg->create_template_competency(array('templateid' => $template->get('id'),
            'competencyid' => $comp3->get('id')));

        // Create scale report configuration.
        $scaleconfigcomp = array(array('scaleid' => $scale->id));
        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'name' => 'A',  'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'name' => 'B',  'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'name' => 'C',  'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'name' => 'D',  'color' => '#DDDDD');

        $record = new stdclass();
        $record->competencyframeworkid = $framework->get('id');
        $record->scaleid = $framework->get('scaleid');
        $record->scaleconfiguration = json_encode($scaleconfig);
        $mpg->create_report_competency_config($record);

        // Create plan from template for all users.
        $plan = $lpg->create_plan(array('userid' => $u1->id, 'templateid' => $template->get('id'), 'status' => plan::STATUS_ACTIVE));
        $plan = $lpg->create_plan(array('userid' => $u2->id, 'templateid' => $template->get('id'), 'status' => plan::STATUS_ACTIVE));
        $plan = $lpg->create_plan(array('userid' => $u3->id, 'templateid' => $template->get('id'), 'status' => plan::STATUS_ACTIVE));
        $plan = $lpg->create_plan(array('userid' => $u4->id, 'templateid' => $template->get('id'), 'status' => plan::STATUS_ACTIVE));

        // Rate user competency1 for all users 1 to 3.
        $uc = $lpg->create_user_competency(array('userid' => $u1->id, 'competencyid' => $comp1->get('id'),
            'proficiency' => true, 'grade' => 1));
        $uc = $lpg->create_user_competency(array('userid' => $u2->id, 'competencyid' => $comp1->get('id'),
            'proficiency' => false, 'grade' => 3));
        $uc = $lpg->create_user_competency(array('userid' => $u3->id, 'competencyid' => $comp1->get('id'),
            'proficiency' => true, 'grade' => 2));

        $result = external::get_competency_statistics($comp1->get('id'), $template->get('id'));
        $result = external::clean_returnvalue(external::get_competency_statistics_returns(), $result);

        // Check info returned.
        $this->assertEquals($comp1->get('id'), $result['competencyid']);
        $this->assertEquals(3, $result['nbuserrated']);
        $this->assertEquals(4, $result['nbusertotal']);
        $this->assertCount(4, $result['totaluserlist']);
        $this->assertTrue($result['totaluserlist'][0]['rated']);
        $this->assertEquals($u1->id, $result['totaluserlist'][0]['userid']);
        $this->assertTrue($result['totaluserlist'][1]['rated']);
        $this->assertEquals($u2->id, $result['totaluserlist'][1]['userid']);
        $this->assertTrue($result['totaluserlist'][2]['rated']);
        $this->assertEquals($u3->id, $result['totaluserlist'][2]['userid']);
        $this->assertFalse($result['totaluserlist'][3]['rated']);
        $this->assertEquals($u4->id, $result['totaluserlist'][3]['userid']);

        // Check info for scale items.
        foreach ($result['scalecompetencyitems'] as $scalecompetencyitem) {
            if ($scalecompetencyitem['value'] == 1) {
                $this->assertEquals('A', $scalecompetencyitem['name']);
                $this->assertEquals('#AAAAA', $scalecompetencyitem['color']);
                $this->assertEquals(1, $scalecompetencyitem['nbusers']);
                $this->assertEquals($u1->id, $scalecompetencyitem['listusers'][0]['userid']);
            } else {
                if ($scalecompetencyitem['value'] == 2) {
                    $this->assertEquals('B', $scalecompetencyitem['name']);
                    $this->assertEquals('#BBBBB', $scalecompetencyitem['color']);
                    $this->assertEquals(1, $scalecompetencyitem['nbusers']);
                    $this->assertEquals($u3->id, $scalecompetencyitem['listusers'][0]['userid']);
                } else {
                    if ($scalecompetencyitem['value'] == 3) {
                        $this->assertEquals('C', $scalecompetencyitem['name']);
                        $this->assertEquals('#CCCCC', $scalecompetencyitem['color']);
                        $this->assertEquals(1, $scalecompetencyitem['nbusers']);
                        $this->assertEquals($u2->id, $scalecompetencyitem['listusers'][0]['userid']);
                    } else {
                        $this->assertEquals('D', $scalecompetencyitem['name']);
                        $this->assertEquals('#DDDDD', $scalecompetencyitem['color']);
                        $this->assertEquals(0, $scalecompetencyitem['nbusers']);
                    }
                }
            }
        }
    }

    /**
     * Test get competency statistics in course for lpmonitoring report.
     */
    public function test_get_lp_monitoring_competency_statistics_incourse() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        // Create some users.
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        // Create some courses.
        $course1 = $dg->create_course();
        $course2 = $dg->create_course();
        $course3 = $dg->create_course();
        $course4 = $dg->create_course();

        // Create scale.
        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));

        // Create framework with the scale configuration.
        $scaleconfig = array(array('scaleid' => $scale->id));
        $scaleconfig[] = array('name' => 'A', 'id' => 1, 'scaledefault' => 0, 'proficient' => 1);
        $scaleconfig[] = array('name' => 'B', 'id' => 2, 'scaledefault' => 1, 'proficient' => 1);
        $framework = $lpg->create_framework(array('scaleid' => $scale->id, 'scaleconfiguration' => $scaleconfig));

        // Associate competencies to framework.
        $comp1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));

        // Create template with competencies.
        $template = $lpg->create_template();
        $lpg->create_template_competency(array('templateid' => $template->get('id'),
            'competencyid' => $comp1->get('id')));
        $lpg->create_template_competency(array('templateid' => $template->get('id'),
            'competencyid' => $comp2->get('id')));

        // Create plan from template for all users.
        $lpg->create_plan(array('userid' => $u1->id, 'templateid' => $template->get('id'), 'status' => plan::STATUS_ACTIVE));
        $lpg->create_plan(array('userid' => $u2->id, 'templateid' => $template->get('id'), 'status' => plan::STATUS_ACTIVE));

        // Link some courses.
        // Associated competencies to courses.
        $lpg->create_course_competency(array('competencyid' => $comp1->get('id'), 'courseid' => $course1->id));
        $lpg->create_course_competency(array('competencyid' => $comp1->get('id'), 'courseid' => $course3->id));
        $lpg->create_course_competency(array('competencyid' => $comp1->get('id'), 'courseid' => $course2->id));
        $lpg->create_course_competency(array('competencyid' => $comp1->get('id'), 'courseid' => $course4->id));
        $lpg->create_course_competency(array('competencyid' => $comp2->get('id'), 'courseid' => $course1->id));
        $lpg->create_course_competency(array('competencyid' => $comp2->get('id'), 'courseid' => $course3->id));
        $lpg->create_course_competency(array('competencyid' => $comp2->get('id'), 'courseid' => $course2->id));
        $lpg->create_course_competency(array('competencyid' => $comp2->get('id'), 'courseid' => $course4->id));

        // Enrol all users in course 1, 2, 3 and 4.
        $dg->enrol_user($u1->id, $course1->id);
        $dg->enrol_user($u1->id, $course2->id);
        $dg->enrol_user($u1->id, $course3->id);
        $dg->enrol_user($u1->id, $course4->id);
        $dg->enrol_user($u2->id, $course1->id);
        $dg->enrol_user($u2->id, $course2->id);
        $dg->enrol_user($u2->id, $course3->id);
        $dg->enrol_user($u2->id, $course4->id);

        // Rate some competencies in courses.
        // Some ratings in courses for user1 and user2.
        $lpg->create_user_competency_course(array('userid' => $u1->id, 'competencyid' => $comp1->get('id'),
            'grade' => 1, 'courseid' => $course1->id, 'proficiency' => 1));
        $lpg->create_user_competency_course(array('userid' => $u1->id, 'competencyid' => $comp1->get('id'),
            'grade' => 1, 'courseid' => $course2->id, 'proficiency' => 1));
        $lpg->create_user_competency_course(array('userid' => $u1->id, 'competencyid' => $comp1->get('id'),
            'grade' => 1, 'courseid' => $course3->id, 'proficiency' => 1));
        $lpg->create_user_competency_course(array('userid' => $u1->id, 'competencyid' => $comp1->get('id'),
            'grade' => 2, 'courseid' => $course4->id, 'proficiency' => 1));
        // User2.
        $lpg->create_user_competency_course(array('userid' => $u2->id, 'competencyid' => $comp1->get('id'),
            'grade' => 1, 'courseid' => $course1->id, 'proficiency' => 1));
        $lpg->create_user_competency_course(array('userid' => $u2->id, 'competencyid' => $comp1->get('id'),
            'grade' => 1, 'courseid' => $course2->id, 'proficiency' => 1));
        $lpg->create_user_competency_course(array('userid' => $u2->id, 'competencyid' => $comp1->get('id'),
            'grade' => 2, 'courseid' => $course3->id, 'proficiency' => 1));

        $result = external::get_competency_statistics_incourse($comp1->get('id'), $template->get('id'));
        $result = external::clean_returnvalue(external::get_competency_statistics_incourse_returns(), $result);

        // Check info returned.
        $this->assertEquals($comp1->get('id'), $result['competencyid']);
        $this->assertEquals(8, $result['nbratingtotal']);
        $this->assertEquals(7, $result['nbratings']);
        $this->assertEquals(1, $result['scalecompetencyitems'][0]['value']);
        $this->assertEquals(2, $result['scalecompetencyitems'][1]['value']);
        $this->assertEquals(3, $result['scalecompetencyitems'][2]['value']);
        $this->assertEquals(4, $result['scalecompetencyitems'][3]['value']);
        // Test we have 5 rating for the scale value 1 (A).
        $this->assertEquals(5, $result['scalecompetencyitems'][0]['nbratings']);
        // Test we have 2 rating for the scale value 2 (B).
        $this->assertEquals(2, $result['scalecompetencyitems'][1]['nbratings']);

        // Test no rating for the competency 2.
        $result = external::get_competency_statistics_incourse($comp2->get('id'), $template->get('id'));
        $result = external::clean_returnvalue(external::get_competency_statistics_incourse_returns(), $result);
        $this->assertEquals($comp2->get('id'), $result['competencyid']);
        $this->assertEquals(8, $result['nbratingtotal']);
        $this->assertEquals(0, $result['nbratings']);
    }

    /**
     * Search templates.
     */
    public function test_search_templates() {
        $user = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        $syscontextid = context_system::instance()->id;
        $catcontextid = context_coursecat::instance($category->id)->id;

        // User role.
        $userrole = create_role('User role', 'userrole', 'learning plan user role description');

        // Creating a few templates.
        $this->setUser($this->creator);
        $sys1 = $this->create_template('Medicine', 'Gastroenterology', $syscontextid, true);
        $sys2 = $this->create_template('History', 'US Independence Day', $syscontextid, false);
        $template1 = $this->create_template('Law', 'Defending Yourself Against a Criminal Charge', $catcontextid, true);
        $template2 = $this->create_template('Art', 'Painting', $catcontextid, false);

        // User without permission.
        $this->setUser($user);
        assign_capability('moodle/competency:templateview', CAP_PROHIBIT, $userrole, $syscontextid, true);
        accesslib_clear_all_caches_for_unit_testing();
        try {
            external::search_templates($syscontextid, '', 0, 10, 'children', false);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
            $this->assertTrue(true);
        }

        // User with category permissions.
        assign_capability('moodle/competency:templateview', CAP_PREVENT, $userrole, $syscontextid, true);
        assign_capability('moodle/competency:templateview', CAP_ALLOW, $userrole, $catcontextid, true);
        role_assign($userrole, $user->id, $syscontextid);
        accesslib_clear_all_caches_for_unit_testing();
        $result = external::search_templates($syscontextid, '', 0, 10, 'children', false);
        $result = external_api::clean_returnvalue(external::search_templates_returns(), $result);
        $this->assertCount(2, $result);
        $this->assertEquals($template2->get('id'), $result[0]['id']);
        $this->assertEquals($template1->get('id'), $result[1]['id']);

        // User with category permissions and query search.
        $result = external::search_templates($syscontextid, 'Painting', 0, 10, 'children', false);
        $result = external_api::clean_returnvalue(external::search_templates_returns(), $result);
        $this->assertCount(1, $result);
        $this->assertEquals($template2->get('id'), $result[0]['id']);

        // User with category permissions and query search and only visible.
        $result = external::search_templates($syscontextid, 'US Independence', 0, 10, 'children', true);
        $result = external_api::clean_returnvalue(external::search_templates_returns(), $result);
        $this->assertCount(0, $result);
    }

    /**
     * Create template from params.
     *
     * @param string $shortname
     * @param string $description
     * @param int $contextid
     * @param boolean $visible
     * @return boolean
     */
    protected function create_template($shortname, $description, $contextid, $visible) {
        $template = array(
            'shortname' => $shortname,
            'description' => $description,
            'descriptionformat' => FORMAT_HTML,
            'duedate' => 0,
            'visible' => $visible,
            'contextid' => $contextid
        );
        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        return $lpg->create_template($template);
    }

    /**
     * Test get plans for specific scales values in plans.
     */
    public function test_get_plans_for_scale_values_in_plans() {
        global $DB;

        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $mpg = $dg->get_plugin_generator('report_lpmonitoring');

        $user1 = $dg->create_user(array('firstname' => 'User1', 'lastname' => 'Test'));
        $user2 = $dg->create_user(array('firstname' => 'User2', 'lastname' => 'Test'));
        $user3 = $dg->create_user(array('firstname' => 'User3', 'lastname' => 'Test'));

        $framework = $lpg->create_framework();
        $comp1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp4 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));

        $tpl = $lpg->create_template();
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $comp1->get('id')));
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $comp2->get('id')));
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $comp3->get('id')));
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $comp4->get('id')));

        $plan1 = $lpg->create_plan(array('userid' => $user1->id, 'templateid' => $tpl->get('id'),
                'status' => plan::STATUS_ACTIVE));
        $plan2 = $lpg->create_plan(array('userid' => $user2->id, 'templateid' => $tpl->get('id'),
                'status' => plan::STATUS_ACTIVE));
        $plan3 = $lpg->create_plan(array('userid' => $user3->id, 'templateid' => $tpl->get('id'),
                'status' => plan::STATUS_COMPLETE));

        // Some ratings in plan for user1.
        $lpg->create_user_competency(array('userid' => $user1->id, 'competencyid' => $comp1->get('id'),
            'grade' => 1, 'proficiency' => 0));
        $lpg->create_user_competency(array('userid' => $user1->id, 'competencyid' => $comp2->get('id'),
            'grade' => 2, 'proficiency' => 1));

        // Some ratings for user2.
        $lpg->create_user_competency(array('userid' => $user2->id, 'competencyid' => $comp3->get('id'),
            'grade' => 2, 'proficiency' => 0));

        // Some ratings for user3.
        $lpg->create_user_competency_plan(array('userid' => $user3->id, 'competencyid' => $comp2->get('id'),
            'planid' => $plan3->get('id'), 'grade' => 3, 'proficiency' => 1));
        // Some ratings for user3.
        $lpg->create_user_competency_plan(array('userid' => $user3->id, 'competencyid' => $comp3->get('id'),
            'planid' => $plan3->get('id'), 'grade' => 3, 'proficiency' => 1));

        // Specify one scale value as filter.
        $scalevalues = '[{"scalevalue" : 2, "scaleid" :' . $framework->get('scaleid') . '}]';
        $scalefilterbycourse = 0;
        $result = external::read_plan(0, $tpl->get('id'), $scalevalues, $scalefilterbycourse);

        $result = (object) external_api::clean_returnvalue(external::read_plan_returns(), $result);

        // Check plan for user 1 is found.
        $this->assertEquals($result->plan['id'], $plan1->get('id'));
        $this->assertEquals($result->plan['user']['id'], $user1->id);

        // Check next plan selected is user 2.
        $this->assertEquals($result->navnext['userid'], $user2->id);
        $this->assertEquals($result->navnext['planid'], $plan2->get('id'));

        // Specify 2 scale values as filter.
        $scalevalues = '[{"scalevalue" : 1, "scaleid" :' . $framework->get('scaleid') . '}, '
                . '{"scalevalue" : 3, "scaleid" :' . $framework->get('scaleid') .'}]';
        $scalefilterbycourse = 0;
        $result = external::read_plan(0, $tpl->get('id'), $scalevalues, $scalefilterbycourse);

        $result = (object) external_api::clean_returnvalue(external::read_plan_returns(), $result);

        // Check plan for user 1 is found.
        $this->assertEquals($result->plan['id'], $plan1->get('id'));
        $this->assertEquals($result->plan['user']['id'], $user1->id);

        // Check next plan selected is user 3.
        $this->assertEquals($result->navnext['userid'], $user3->id);
        $this->assertEquals($result->navnext['planid'], $plan3->get('id'));

    }

    /**
     * Test get plans for specific scales values in courses.
     */
    public function test_get_plans_for_scale_values_in_courses() {
        global $DB;

        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $mpg = $dg->get_plugin_generator('report_lpmonitoring');

        $course1 = $dg->create_course();
        $course2 = $dg->create_course();
        $course3 = $dg->create_course();
        $course4 = $dg->create_course();
        $user1 = $dg->create_user(array('firstname' => 'User1', 'lastname' => 'Test'));
        $user2 = $dg->create_user(array('firstname' => 'User2', 'lastname' => 'Test'));
        $user3 = $dg->create_user(array('firstname' => 'User3', 'lastname' => 'Test'));

        // Create framework with competencies.
        $framework = $lpg->create_framework();
        $comp1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp4 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));

        $tpl = $lpg->create_template();
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $comp1->get('id')));
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $comp2->get('id')));
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $comp3->get('id')));
        $lpg->create_template_competency(array('templateid' => $tpl->get('id'), 'competencyid' => $comp4->get('id')));

        $plan1 = $lpg->create_plan(array('userid' => $user1->id, 'templateid' => $tpl->get('id'),
                'status' => plan::STATUS_ACTIVE));
        $plan2 = $lpg->create_plan(array('userid' => $user2->id, 'templateid' => $tpl->get('id'),
                'status' => plan::STATUS_ACTIVE));
        $plan3 = $lpg->create_plan(array('userid' => $user3->id, 'templateid' => $tpl->get('id'),
                'status' => plan::STATUS_COMPLETE));

        // Associated competencies to courses.
        $lpg->create_course_competency(array('competencyid' => $comp1->get('id'), 'courseid' => $course1->id));
        $lpg->create_course_competency(array('competencyid' => $comp1->get('id'), 'courseid' => $course3->id));
        $lpg->create_course_competency(array('competencyid' => $comp1->get('id'), 'courseid' => $course2->id));
        $lpg->create_course_competency(array('competencyid' => $comp2->get('id'), 'courseid' => $course2->id));
        $lpg->create_course_competency(array('competencyid' => $comp4->get('id'), 'courseid' => $course4->id));

        // Enrol all users in course 1, 2, and 3.
        $dg->enrol_user($user1->id, $course1->id);
        $dg->enrol_user($user1->id, $course2->id);
        $dg->enrol_user($user1->id, $course3->id);

        // Enrol the user 2 in course 4.
        $dg->enrol_user($user2->id, $course4->id);

        // Enrol the user 3 in course 1, 2, and 3.
        $dg->enrol_user($user3->id, $course1->id);
        $dg->enrol_user($user3->id, $course2->id);
        $dg->enrol_user($user3->id, $course3->id);

        // Assigne rates for user 1 to comptencies in courses 1 and 2.
        $record1 = new \stdClass();
        $record1->userid = $user1->id;
        $record1->courseid = $course1->id;
        $record1->competencyid = $comp1->get('id');
        $record1->proficiency = 1;
        $record1->grade = 1;
        $record1->timecreated = 10;
        $record1->timemodified = 10;
        $record1->usermodified = $user1->id;

        $record2 = new \stdClass();
        $record2->userid = $user1->id;
        $record2->courseid = $course2->id;
        $record2->competencyid = $comp1->get('id');
        $record2->proficiency = 0;
        $record2->grade = 2;
        $record2->timecreated = 10;
        $record2->timemodified = 10;
        $record2->usermodified = $user1->id;;
        $DB->insert_records('competency_usercompcourse', array($record1, $record2));

        // Assigne rates for user 2 to comptencies in course 4.
        $record1 = new \stdClass();
        $record1->userid = $user2->id;
        $record1->courseid = $course4->id;
        $record1->competencyid = $comp1->get('id');
        $record1->proficiency = 0;
        $record1->grade = 2;
        $record1->timecreated = 10;
        $record1->timemodified = 10;
        $record1->usermodified = $user1->id;
        $DB->insert_records('competency_usercompcourse', array($record1));

        // Assigne rates for user 3 to comptencies in courses 1 and 3.
        $record1 = new \stdClass();
        $record1->userid = $user3->id;
        $record1->courseid = $course1->id;
        $record1->competencyid = $comp1->get('id');
        $record1->proficiency = 0;
        $record1->grade = 4;
        $record1->timecreated = 10;
        $record1->timemodified = 10;
        $record1->usermodified = $user1->id;

        $record2 = new \stdClass();
        $record2->userid = $user3->id;
        $record2->courseid = $course3->id;
        $record2->competencyid = $comp2->get('id');
        $record2->proficiency = 0;
        $record2->grade = 3;
        $record2->timecreated = 10;
        $record2->timemodified = 10;
        $record2->usermodified = $user1->id;
        $DB->insert_records('competency_usercompcourse', array($record1, $record2));

        // Specify one scale value as filter.
        $scalevalues = '[{"scalevalue" : 2, "scaleid" :' . $framework->get('scaleid') . '}]';
        $scalefilterbycourse = 1;
        $result = external::read_plan(0, $tpl->get('id'), $scalevalues, $scalefilterbycourse);

        $result = (object) external_api::clean_returnvalue(external::read_plan_returns(), $result);

        // Check plan for user 1 is found.
        $this->assertEquals($result->plan['id'], $plan1->get('id'));
        $this->assertEquals($result->plan['user']['id'], $user1->id);

        // Check that there is no next plan because comp 2 is not associated to course 3.
        $this->assertFalse(isset($result->navnext));

        // Specify 2 scale values as filter.
        $scalevalues = '[{"scalevalue" : 1, "scaleid" :' . $framework->get('scaleid') . '}, '
                . '{"scalevalue" : 3, "scaleid" :' . $framework->get('scaleid') .'}]';
        $scalefilterbycourse = 1;
        $result = external::read_plan(0, $tpl->get('id'), $scalevalues, $scalefilterbycourse);

        $result = (object) external_api::clean_returnvalue(external::read_plan_returns(), $result);

        // Check plan for user 1 is found.
        $this->assertEquals($result->plan['id'], $plan1->get('id'));
        $this->assertEquals($result->plan['user']['id'], $user1->id);

        // Check that there is no next plan because comp 2 is not associated to course 3.
        $this->assertFalse(isset($result->navnext));

    }

}
