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
 * API tests.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

use core_competency\plan;
use report_lpmonitoring\api;
use core_competency\api as core_competency_api;
use tool_cohortroles\api as tool_cohortroles_api;
use report_lpmonitoring\report_competency_config;
use core\invalid_persistent_exception;

/**
 * API tests.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_lpmonitoring_api_testcase extends advanced_testcase {

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

    /** @var stdClass $appreciator User with enough permissions to access lpmonitoring report in category context. */
    protected $appreciatorforcategory = null;

    /** @var stdClass $category Category. */
    protected $category = null;

    /** @var stdClass $category Category. */
    protected $templateincategory = null;

    /** @var stdClass $frameworkincategory Competency framework in category context. */
    protected $frameworkincategory = null;

    /** @var stdClass $user1 User for generating plans. */
    protected $user1 = null;

    /** @var stdClass $user1 User for generating plans. */
    protected $user2 = null;

    /** @var stdClass $user1 User for generating plans. */
    protected $user3 = null;

    /** @var stdClass $comp1 Competency to be added to the framework. */
    protected $comp1 = null;

    /** @var stdClass $comp2 Competency to be added to the framework. */
    protected $comp2 = null;

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
        assign_capability('moodle/competency:planview', CAP_ALLOW, $this->rolecreator, $syscontext->id);
        role_assign($this->rolecreator, $creator->id, $syscontext->id);

        $this->roleappreciator = create_role('Appreciator role', 'roleappreciator', 'learning plan appreciator role description');
        assign_capability('moodle/competency:competencyview', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        assign_capability('moodle/competency:coursecompetencyview', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        assign_capability('moodle/competency:usercompetencyview', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        assign_capability('moodle/competency:usercompetencymanage', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        assign_capability('moodle/competency:planview', CAP_ALLOW, $this->roleappreciator, $syscontext->id);
        role_assign($this->roleappreciator, $appreciator->id, $syscontext->id);
        $this->creator = $creator;
        $this->appreciator = $appreciator;

        $this->setAdminUser();
        // Create category.
        $this->category = $dg->create_category(array('name' => 'Cat test 1'));
        $cat1ctx = context_coursecat::instance($this->category->id);

        // Create templates in category.
        $this->templateincategory = $cpg->create_template(array('shortname' => 'Medicine Year 1', 'contextid' => $cat1ctx->id));

        // Create scales.
        $scale = $dg->create_scale(array("name" => "Scale default", "scale" => "not good, good"));

        $scaleconfiguration = '[{"scaleid":"'.$scale->id.'"},' .
                '{"name":"not good","id":1,"scaledefault":1,"proficient":0},' .
                '{"name":"good","id":2,"scaledefault":0,"proficient":1}]';

        // Create the framework competency.
        $framework = array(
            'shortname' => 'Framework Medicine',
            'idnumber' => 'fr-medicine',
            'scaleid' => $scale->id,
            'scaleconfiguration' => $scaleconfiguration,
            'visible' => true,
            'contextid' => $cat1ctx->id
        );
        $this->frameworkincategory = $cpg->create_framework($framework);
        $this->comp1 = $cpg->create_competency(array(
            'competencyframeworkid' => $this->frameworkincategory->get('id'),
            'shortname' => 'Competency A')
        );

        $this->comp2 = $cpg->create_competency(array(
            'competencyframeworkid' => $this->frameworkincategory->get('id'),
            'shortname' => 'Competency B')
        );
        // Create template competency.
        $cpg->create_template_competency(array('templateid' => $this->templateincategory->get('id'),
            'competencyid' => $this->comp1->get('id')));
        $cpg->create_template_competency(array('templateid' => $this->templateincategory->get('id'),
            'competencyid' => $this->comp2->get('id')));

        $this->user1 = $dg->create_user(array(
            'firstname' => 'Rebecca',
            'lastname' => 'Armenta',
            'email' => 'user11test@nomail.com',
            'phone1' => 1111111111,
            'phone2' => 2222222222,
            'institution' => 'Institution Name',
            'department' => 'Dep Name')
        );
        $this->user2 = $dg->create_user(array(
            'firstname' => 'Donald',
            'lastname' => 'Fletcher',
            'email' => 'user12test@nomail.com',
            'phone1' => 1111111111,
            'phone2' => 2222222222,
            'institution' => 'Institution Name',
            'department' => 'Dep Name')
        );
        $this->user3 = $dg->create_user(array(
            'firstname' => 'Stepanie',
            'lastname' => 'Grant',
            'email' => 'user13test@nomail.com',
            'phone1' => 1111111111,
            'phone2' => 2222222222,
            'institution' => 'Institution Name',
            'department' => 'Dep Name')
        );

        $appreciatorforcategory = $dg->create_user(
                array(
                    'firstname' => 'Appreciator',
                    'lastname' => 'Test',
                    'username' => 'appreciator',
                    'password' => 'appreciator'
                )
        );

        $cohort = $dg->create_cohort(array('contextid' => $cat1ctx->id));
        cohort_add_member($cohort->id, $this->user1->id);
        cohort_add_member($cohort->id, $this->user2->id);

        // Generate plans for cohort.
        core_competency_api::create_plans_from_template_cohort($this->templateincategory->get('id'), $cohort->id);
        // Create plan from template for Stephanie.
        $syscontext = context_system::instance();

        $roleid = create_role('Appreciator role', 'roleappreciatortest', 'learning plan appreciator role description');
        assign_capability('moodle/competency:competencyview', CAP_ALLOW, $roleid, $cat1ctx->id);
        assign_capability('moodle/competency:coursecompetencyview', CAP_ALLOW, $roleid, $cat1ctx->id);
        assign_capability('moodle/competency:usercompetencyview', CAP_ALLOW, $roleid, $cat1ctx->id);
        assign_capability('moodle/competency:usercompetencymanage', CAP_ALLOW, $roleid, $cat1ctx->id);
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $roleid, $cat1ctx->id);
        assign_capability('moodle/competency:planview', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('moodle/competency:planviewdraft', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('moodle/competency:planmanage', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('moodle/competency:competencygrade', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('moodle/competency:templateview', CAP_ALLOW, $roleid, $cat1ctx->id);
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $roleid, $syscontext->id);

        role_assign($roleid, $appreciatorforcategory->id, $cat1ctx->id);
        $params = (object) array(
            'userid' => $appreciatorforcategory->id,
            'roleid' => $roleid,
            'cohortid' => $cohort->id
        );
        tool_cohortroles_api::create_cohort_role_assignment($params);
        tool_cohortroles_api::sync_all_cohort_roles();
        $this->appreciatorforcategory = $appreciatorforcategory;

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


    public function test_get_scales_from_competencyframework() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $cat = $dg->create_category();
        // Create scales.
        $scale1 = $dg->create_scale(array('scale' => 'A,B,C,D', 'name' => 'scale 1'));
        $scaleconfig = array(array('scaleid' => $scale1->id));
        $scaleconfig[] = array('name' => 'B', 'id' => 2, 'scaledefault' => 1, 'proficient' => 0);
        $scaleconfig[] = array('name' => 'C', 'id' => 3, 'scaledefault' => 0, 'proficient' => 1);
        $scaleconfig[] = array('name' => 'D', 'id' => 4, 'scaledefault' => 0, 'proficient' => 1);

        $scale2 = $dg->create_scale(array('scale' => 'E,F,G', 'name' => 'scale 2'));
        $c2scaleconfig = array(array('scaleid' => $scale2->id));
        $c2scaleconfig[] = array('name' => 'E', 'id' => 2, 'scaledefault' => 0, 'proficient' => 0);
        $c2scaleconfig[] = array('name' => 'F', 'id' => 3, 'scaledefault' => 0, 'proficient' => 0);
        $c2scaleconfig[] = array('name' => 'G', 'id' => 4, 'scaledefault' => 1, 'proficient' => 1);

        $scale3 = $dg->create_scale(array('scale' => 'H,I,J', 'name' => 'scale 3'));
        $c3scaleconfig = array(array('scaleid' => $scale3->id));
        $c3scaleconfig[] = array('name' => 'H', 'id' => 2, 'scaledefault' => 0, 'proficient' => 0);
        $c3scaleconfig[] = array('name' => 'I', 'id' => 3, 'scaledefault' => 0, 'proficient' => 1);
        $c3scaleconfig[] = array('name' => 'J', 'id' => 4, 'scaledefault' => 1, 'proficient' => 1);

        $scale4 = $dg->create_scale(array('scale' => 'K,L,M', 'name' => 'scale 4'));
        $c4scaleconfig = array(array('scaleid' => $scale4->id));
        $c4scaleconfig[] = array('name' => 'K', 'id' => 2, 'scaledefault' => 0, 'proficient' => 0);
        $c4scaleconfig[] = array('name' => 'L', 'id' => 3, 'scaledefault' => 0, 'proficient' => 1);
        $c4scaleconfig[] = array('name' => 'M', 'id' => 4, 'scaledefault' => 1, 'proficient' => 1);

        $catctx = context_coursecat::instance($cat->id);
        $sysctx = context_system::instance();

        // Create a list of frameworks.
        $framework1 = $lpg->create_framework(array(
            'shortname' => 'frameworktest_1',
            'idnumber' => 'frmtest_1',
            'visible' => true,
            'contextid' => $sysctx->id,
            'scaleid' => $scale1->id,
            'scaleconfiguration' => $scaleconfig
        ));

        $framework2 = $lpg->create_framework(array(
            'shortname' => 'frameworktest_2',
            'idnumber' => 'frmtest_2',
            'visible' => true,
            'contextid' => $catctx->id,
            'scaleid' => $scale2->id,
            'scaleconfiguration' => $c2scaleconfig
        ));

        $lpg->create_competency(array(
            'competencyframeworkid' => $framework1->get('id'),
            'scaleid' => $scale3->id,
            'scaleconfiguration' => $c3scaleconfig
        ));
        $lpg->create_competency(array(
            'competencyframeworkid' => $framework2->get('id'),
            'scaleid' => $scale4->id,
            'scaleconfiguration' => $c4scaleconfig
        ));

        $this->setAdminUser();
        $scales = api::get_scales_from_framework($framework1->get('id'));
        $this->assertCount(2, $scales);
        $this->assertEquals(array(
            $scale1->id => array('id' => $scale1->id, 'name' => $scale1->name),
            $scale3->id => array('id' => $scale3->id, 'name' => $scale3->name)
        ), $scales);

        $scales = api::get_scales_from_framework($framework2->get('id'));
        $this->assertCount(2, $scales);
        $this->assertEquals(array(
            $scale2->id => array('id' => $scale2->id, 'name' => $scale2->name),
            $scale4->id => array('id' => $scale4->id, 'name' => $scale4->name)
        ), $scales);

    }

    /**
     * Test that default color is assigned to each scale value when scale configuration does not exist.
     */
    public function test_missing_scale_configuration() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig = api::read_report_competency_config($framework->get('id'), $scale->id);

        $scaleconfig = json_decode($scaleconfig->get('scaleconfiguration'));
        $this->assertEquals($scaleconfig[0]->color, report_competency_config::DEFAULT_COLOR);
        $this->assertEquals($scaleconfig[1]->color, report_competency_config::DEFAULT_COLOR);
        $this->assertEquals($scaleconfig[2]->color, report_competency_config::DEFAULT_COLOR);
        $this->assertEquals($scaleconfig[3]->color, report_competency_config::DEFAULT_COLOR);
    }

    /**
     * Test missing capability to create configuration for a framework and a scale.
     */
    public function test_no_capability_to_create_scale_configuration() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $record = new stdclass();
        $record->competencyframeworkid = $framework->get('id');
        $record->scaleid = $scale->id;
        $record->scaleconfiguration = json_encode($scaleconfig);

        $this->setUser($this->appreciator);

        try {
            api::create_report_competency_config($record);
            $this->fail('Configuration can not be created when user does not have capability');
        } catch (required_capability_exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test create configuration for a framework and a scale.
     */
    public function test_create_config_normal() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $record = new stdclass();
        $record->competencyframeworkid = $framework->get('id');
        $record->scaleid = $scale->id;
        $record->scaleconfiguration = json_encode($scaleconfig);

        $reportconfig = api::create_report_competency_config($record);
        $this->assertEquals($reportconfig->get('competencyframeworkid'), $framework->get('id'));
        $this->assertEquals($reportconfig->get('scaleid'), $scale->id);

        $scaleconfig = json_decode($reportconfig->get('scaleconfiguration'));
        $this->assertEquals($scaleconfig[0]->color, '#AAAAA');
        $this->assertEquals($scaleconfig[1]->color, '#BBBBB');
        $this->assertEquals($scaleconfig[2]->color, '#CCCCC');
        $this->assertEquals($scaleconfig[3]->color, '#DDDDD');
    }

    /**
     * Test create configuration for a framework and a scale with missing color.
     */
    public function test_create_config_missing_color() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');

        $record = new stdclass();
        $record->competencyframeworkid = $framework->get('id');
        $record->scaleid = $scale->id;
        $record->scaleconfiguration = json_encode($scaleconfig);

        try {
            api::create_report_competency_config($record);
            $this->fail('Report competency configuration can not be created');
        } catch (invalid_persistent_exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test create configuration for a framework and a scale with unknown scale id.
     */
    public function test_create_config_unknown_scale() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $record = new stdclass();
        $record->competencyframeworkid = $framework->get('id');
        $record->scaleid = $scale->id + 10;
        $record->scaleconfiguration = json_encode($scaleconfig);

        try {
            api::create_report_competency_config($record);
            $this->fail('Report competency configuration can not be created with unknown scale');
        } catch (invalid_persistent_exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test create configuration for a framework and a scale with unknown framework id.
     */
    public function test_create_config_unknown_framework() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $record = new stdclass();
        $record->competencyframeworkid = $framework->get('id');
        $record->scaleid = $scale->id + 10;
        $record->scaleconfiguration = json_encode($scaleconfig);

        try {
            api::create_report_competency_config($record);
            $this->fail('Report competency configuration can not be created with unknown framework');
        } catch (invalid_persistent_exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test missing capability to update configuration for a framework and a scale.
     */
    public function test_no_capability_to_update_config() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $reportconfig = $lpg->create_report_competency_config(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scale->id,
                'scaleconfiguration' => $scaleconfig));

        $record = $reportconfig->to_record();

        // Change de colors for scale.
        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#XXXXX');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#ZZZZZ');

        $record->scaleconfiguration = json_encode($scaleconfig);

        $this->setUser($this->appreciator);

        try {
            api::update_report_competency_config($record);
            $this->fail('Configuration can not be updated when user does not have capability');
        } catch (required_capability_exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test update configuration for a framework and a scale.
     */
    public function test_update_config() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $reportconfig = $lpg->create_report_competency_config(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scale->id,
                'scaleconfiguration' => $scaleconfig));

        $record = $reportconfig->to_record();

        // Change de colors for scale.
        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#XXXXX');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#ZZZZZ');

        $record->scaleconfiguration = json_encode($scaleconfig);

        $result = api::update_report_competency_config($record);
        $this->assertTrue($result);

        $reportconfig = api::read_report_competency_config($framework->get('id'), $scale->id);
        $this->assertEquals($reportconfig->get('competencyframeworkid'), $framework->get('id'));
        $this->assertEquals($reportconfig->get('scaleid'), $scale->id);

        $scaleconfig = json_decode($reportconfig->get('scaleconfiguration'));
        $this->assertEquals($scaleconfig[0]->color, '#AAAAA');
        $this->assertEquals($scaleconfig[1]->color, '#XXXXX');
        $this->assertEquals($scaleconfig[2]->color, '#CCCCC');
        $this->assertEquals($scaleconfig[3]->color, '#ZZZZZ');
    }

    /**
     * Test update configuration that does not exist.
     */
    public function test_update_none_existing_config() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $reportconfig = $lpg->create_report_competency_config(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scale->id,
                'scaleconfiguration' => $scaleconfig));

        $record = $reportconfig->to_record();

        // Change de colors for scale.
        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#XXXXX');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#ZZZZZ');

        $record->scaleconfiguration = json_encode($scaleconfig);
        $record->scaleid = 0;

        try {
            api::update_report_competency_config($record);
            $this->fail('Report competency configuration can not be updated if does not existe');
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test create configuration thta already exist.
     */
    public function test_create_existing_config() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $reportconfig = $lpg->create_report_competency_config(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scale->id,
                'scaleconfiguration' => $scaleconfig));

        $record = $reportconfig->to_record();

        // Change de colors for scale.
        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#XXXXX');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#ZZZZZ');

        $record->scaleconfiguration = json_encode($scaleconfig);

        try {
            api::create_report_competency_config($record);
            $this->fail('Report competency configuration can not be created if already exist');
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test missing capability to delete configuration for a framework and a scale.
     */
    public function test_no_capability_to_delete_config() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $framework = $cpg->create_framework();

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $reportconfig = $lpg->create_report_competency_config(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scale->id,
                'scaleconfiguration' => $scaleconfig));

        $record = $reportconfig->to_record();

        $this->setUser($this->appreciator);

        try {
            api::delete_report_competency_config($framework->get('id'), $scale->id);
            $this->fail('Configuration can not be deleted when user does not have capability');
        } catch (required_capability_exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test delete all scales configuration associated to framework.
     */
    public function test_delete_config_framework() {
        global $DB;

        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale1 = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $scale2 = $dg->create_scale(array('scale' => 'W,X,Y,Z'));
        $framework = $cpg->create_framework();

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $reportconfig1 = $lpg->create_report_competency_config(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scale1->id,
                'scaleconfiguration' => $scaleconfig));

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#WWWWW');
        $scaleconfig[] = array('id' => 2, 'color' => '#XXXXX');
        $scaleconfig[] = array('id' => 3, 'color' => '#YYYYY');
        $scaleconfig[] = array('id' => 4, 'color' => '#ZZZZZ');

        $reportconfig2 = $lpg->create_report_competency_config(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scale2->id,
                'scaleconfiguration' => $scaleconfig));

        $this->assertEquals(2, $DB->count_records(report_competency_config::TABLE));

        $result = api::delete_report_competency_config($framework->get('id'));
        // Check all configurations associated to the framework are deleted.
        $this->assertTrue($result);
        $this->assertEquals(0, $DB->count_records(report_competency_config::TABLE));
    }

    /**
     * Test delete scale configuration associated to a framework and a scale.
     */
    public function test_delete_config_scale() {
        global $DB;

        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lpg = $this->getDataGenerator()->get_plugin_generator('report_lpmonitoring');

        $scale1 = $dg->create_scale(array('scale' => 'A,B,C,D'));
        $scale2 = $dg->create_scale(array('scale' => 'W,X,Y,Z'));
        $framework = $cpg->create_framework();

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'color' => '#DDDDD');

        $reportconfig1 = $lpg->create_report_competency_config(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scale1->id,
                'scaleconfiguration' => $scaleconfig));

        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'color' => '#WWWWW');
        $scaleconfig[] = array('id' => 2, 'color' => '#XXXXX');
        $scaleconfig[] = array('id' => 3, 'color' => '#YYYYY');
        $scaleconfig[] = array('id' => 4, 'color' => '#ZZZZZ');

        $reportconfig2 = $lpg->create_report_competency_config(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scale2->id,
                'scaleconfiguration' => $scaleconfig));

        $this->assertEquals(2, $DB->count_records(report_competency_config::TABLE));

        $result = api::delete_report_competency_config($framework->get('id'), $scale1->id);

        // Check specific scale configurations is deleted.
        $this->assertTrue($result);
        $this->assertEquals(1, $DB->count_records(report_competency_config::TABLE));
    }

    /**
     * Test we can read plan with no permissions.
     */
    public function test_read_plan_with_nopermissions() {
        $this->setUser($this->appreciatorforcategory);
        // Test we can read the first plan for the template (Rebecca).
        $result = api::read_plan(0, $this->templateincategory->get('id'));
        $this->assertEquals($this->user1->id, $result->current->get('userid'));
        $this->setAdminUser();
        $planstephanie = core_competency_api::create_plan_from_template($this->templateincategory->get('id'), $this->user3->id);

        $this->setUser($this->appreciatorforcategory);
        // Test we can not read Stephanie learning plan (do not belong to the cohort).
        try {
            api::read_plan($planstephanie->get('id'), $this->templateincategory->get('id'));
            $this->fail("We don't have read plan permission for Stephanie Grant");
        } catch (Exception $ex) {
            $this->assertContains('Stepanie Grant', $ex->getMessage());
        }
    }

    /**
     * Test read current plan and get previous and next user plans.
     *
     * @expectedException moodle_exception
     */
    public function test_get_plans() {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');

        $user1 = $dg->create_user(array('lastname' => 'Austin', 'firstname' => 'Sharon'));
        $user2 = $dg->create_user(array('lastname' => 'Cortez', 'firstname' => 'Jonathan'));
        $user3 = $dg->create_user(array('lastname' => 'Underwood', 'firstname' => 'Alicia'));

        $f1 = $lpg->create_framework();

        $c1a = $lpg->create_competency(array('competencyframeworkid' => $f1->get('id')));
        $c1b = $lpg->create_competency(array('competencyframeworkid' => $f1->get('id')));
        $c1c = $lpg->create_competency(array('competencyframeworkid' => $f1->get('id')));

        $tpl1 = $lpg->create_template();
        $tpl2 = $lpg->create_template();
        $lpg->create_template_competency(array('templateid' => $tpl1->get('id'), 'competencyid' => $c1a->get('id')));
        $lpg->create_template_competency(array('templateid' => $tpl1->get('id'), 'competencyid' => $c1c->get('id')));

        $plan1 = $lpg->create_plan(array('userid' => $user1->id, 'templateid' => $tpl1->get('id')));
        $plan2 = $lpg->create_plan(array('userid' => $user2->id, 'templateid' => $tpl1->get('id')));
        $plan3 = $lpg->create_plan(array('userid' => $user3->id, 'templateid' => $tpl1->get('id')));
        $plan4 = $lpg->create_plan(array('userid' => $user1->id));

        // Test plan not based on a template.
        $result = api::read_plan($plan4->get('id'));
        $apiplan = core_competency_api::read_plan($plan4->get('id'));
        $this->assertEquals($apiplan, $result->current);
        $this->assertNull($result->previous);
        $this->assertNull($result->next);

        // Test plan based on a template that is is the first in the list of plans.
        $result = api::read_plan($plan1->get('id'), $tpl1->get('id'));
        $apiplan = core_competency_api::read_plan($plan1->get('id'));
        $this->assertEquals($apiplan, $result->current);
        $this->assertNull($result->previous);
        $this->assertNotNull($result->next);
        $this->assertEquals($user2->id, $result->next->userid);
        $this->assertEquals('Jonathan Cortez', $result->next->fullname);
        $this->assertEquals($user2->email, $result->next->email);
        $this->assertEquals($plan2->get('id'), $result->next->planid);

        // Test plan based on a template that is in the middle in the list of plans.
        $result = api::read_plan($plan2->get('id'), $tpl1->get('id'));
        $apiplan = core_competency_api::read_plan($plan2->get('id'));
        $this->assertEquals($apiplan, $result->current);
        $this->assertNotNull($result->previous);
        $this->assertEquals($user1->id, $result->previous->userid);
        $this->assertEquals('Sharon Austin', $result->previous->fullname);
        $this->assertEquals($user1->email, $result->previous->email);
        $this->assertEquals($plan1->get('id'), $result->previous->planid);
        $this->assertNotNull($result->next);
        $this->assertEquals($user3->id, $result->next->userid);
        $this->assertEquals('Alicia Underwood', $result->next->fullname);
        $this->assertEquals($user3->email, $result->next->email);
        $this->assertEquals($plan3->get('id'), $result->next->planid);

        // Test plan based on a template that is the last in the list of plans.
        $result = api::read_plan($plan3->get('id'), $tpl1->get('id'));
        $apiplan = core_competency_api::read_plan($plan3->get('id'));
        $this->assertEquals($apiplan, $result->current);
        $this->assertNotNull($result->previous);
        $this->assertEquals($user2->id, $result->previous->userid);
        $this->assertEquals('Jonathan Cortez', $result->previous->fullname);
        $this->assertEquals($user2->email, $result->previous->email);
        $this->assertEquals($plan2->get('id'), $result->previous->planid);
        $this->assertNull($result->next);

        // Test reading of plan when passing only the template ID.
        $result = api::read_plan(0, $tpl1->get('id'));
        $apiplan = core_competency_api::read_plan($plan1->get('id'));
        $this->assertEquals($apiplan, $result->current);
        $this->assertNull($result->previous);
        $this->assertNotNull($result->next);
        $this->assertEquals($user2->id, $result->next->userid);
        $this->assertEquals('Jonathan Cortez', $result->next->fullname);
        $this->assertEquals($user2->email, $result->next->email);
        $this->assertEquals($plan2->get('id'), $result->next->planid);

        // Test template with no plan.
        $result = api::read_plan(0, $tpl2->get('id'));
    }

    /**
     * Test get learning plans from templateid.
     */
    public function test_search_users_by_templateid() {
        $this->setUser($this->appreciatorforcategory);
        $users = api::search_users_by_templateid($this->templateincategory->get('id'), 'Re');
        $this->assertCount(1, $users);

        $users = api::search_users_by_templateid($this->templateincategory->get('id'), 't');
        $this->assertCount(2, $users);
    }

    /**
     * Test we can search users with identity informations.
     */
    public function test_search_users_by_templateid_withidentityuser() {
        $this->setUser($this->appreciatorforcategory);

        // Test with show user identity disabled.
        set_config('showuseridentity', '');
        $users = api::search_users_by_templateid($this->templateincategory->get('id'), 'Rebecca');
        $this->assertCount(1, $users);
        $this->assertEmpty(isset($users[$this->user1->id]['email']));
        $this->assertFalse(isset($users[$this->user1->id]['phone1']));
        $this->assertFalse(isset($users[$this->user1->id]['phone2']));
        $this->assertFalse(isset($users[$this->user1->id]['institution']));
        $this->assertFalse(isset($users[$this->user1->id]['department']));

        // Add email to show user identity.
        set_config('showuseridentity', 'email');
        $users = api::search_users_by_templateid($this->templateincategory->get('id'), 'Rebecca');
        $this->assertCount(1, $users);
        $this->assertEquals('user11test@nomail.com', $users[$this->user1->id]['email']);
        $this->assertFalse(isset($users[$this->user1->id]['phone1']));
        $this->assertFalse(isset($users[$this->user1->id]['phone2']));
        $this->assertFalse(isset($users[$this->user1->id]['institution']));
        $this->assertFalse(isset($users[$this->user1->id]['department']));

        // Add phone1 to show user identity.
        set_config('showuseridentity', 'email,phone1');
        $users = api::search_users_by_templateid($this->templateincategory->get('id'), 'Rebecca');
        $this->assertCount(1, $users);
        $this->assertEquals('user11test@nomail.com', $users[$this->user1->id]['email']);
        $this->assertEquals(1111111111, $users[$this->user1->id]['phone1']);
        $this->assertFalse(isset($users[$this->user1->id]['phone2']));
        $this->assertFalse(isset($users[$this->user1->id]['institution']));
        $this->assertFalse(isset($users[$this->user1->id]['department']));

        // Add phone2 to show user identity.
        set_config('showuseridentity', 'email,phone1,phone2');
        $users = api::search_users_by_templateid($this->templateincategory->get('id'), 'Rebecca');
        $this->assertCount(1, $users);
        $this->assertEquals('user11test@nomail.com', $users[$this->user1->id]['email']);
        $this->assertEquals(1111111111, $users[$this->user1->id]['phone1']);
        $this->assertEquals(2222222222, $users[$this->user1->id]['phone2']);
        $this->assertFalse(isset($users[$this->user1->id]['institution']));
        $this->assertFalse(isset($users[$this->user1->id]['department']));

        // Add institution to show user identity.
        set_config('showuseridentity', 'email,phone1,phone2,institution');
        $users = api::search_users_by_templateid($this->templateincategory->get('id'), 'Rebecca');
        $this->assertCount(1, $users);
        $this->assertEquals('user11test@nomail.com', $users[$this->user1->id]['email']);
        $this->assertEquals(1111111111, $users[$this->user1->id]['phone1']);
        $this->assertEquals(2222222222, $users[$this->user1->id]['phone2']);
        $this->assertEquals('Institution Name', $users[$this->user1->id]['institution']);
        $this->assertFalse(isset($users[$this->user1->id]['department']));

        // Add department to show user identity.
        set_config('showuseridentity', 'email,phone1,phone2,institution,department');
        $users = api::search_users_by_templateid($this->templateincategory->get('id'), 'Rebecca');
        $this->assertCount(1, $users);
        $this->assertEquals('user11test@nomail.com', $users[$this->user1->id]['email']);
        $this->assertEquals(1111111111, $users[$this->user1->id]['phone1']);
        $this->assertEquals(2222222222, $users[$this->user1->id]['phone2']);
        $this->assertEquals('Institution Name', $users[$this->user1->id]['institution']);
        $this->assertEquals('Dep Name', $users[$this->user1->id]['department']);
    }

    /**
     * Test get learning plans from templateid with scale filter.
     */
    public function test_search_users_by_templateid_and_scalefilter() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $cpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        // Create courses.
        $course1 = $dg->create_course();
        $course2 = $dg->create_course();

        // Create scales.
        $scale1 = $dg->create_scale(array('scale' => 'A,B,C,D', 'name' => 'scale 1'));
        $scaleconfig = array(array('scaleid' => $scale1->id));
        $scaleconfig[] = array('name' => 'B', 'id' => 2, 'scaledefault' => 1, 'proficient' => 0);
        $scaleconfig[] = array('name' => 'C', 'id' => 3, 'scaledefault' => 0, 'proficient' => 1);
        $scaleconfig[] = array('name' => 'D', 'id' => 4, 'scaledefault' => 0, 'proficient' => 1);

        $scale2 = $dg->create_scale(array('scale' => 'E,F,G', 'name' => 'scale 2'));
        $c2scaleconfig = array(array('scaleid' => $scale2->id));
        $c2scaleconfig[] = array('name' => 'E', 'id' => 1, 'scaledefault' => 0, 'proficient' => 0);
        $c2scaleconfig[] = array('name' => 'F', 'id' => 2, 'scaledefault' => 0, 'proficient' => 0);
        $c2scaleconfig[] = array('name' => 'G', 'id' => 3, 'scaledefault' => 1, 'proficient' => 1);

        $framework = $cpg->create_framework(array(
            'scaleid' => $scale1->id,
            'scaleconfiguration' => $scaleconfig
        ));
        $c1 = $cpg->create_competency(array(
                    'competencyframeworkid' => $framework->get('id'),
                    'shortname' => 'c1',
                    'scaleid' => $scale2->id,
                    'scaleconfiguration' => $c2scaleconfig));
        $c2 = $cpg->create_competency(array('competencyframeworkid' => $framework->get('id'), 'shortname' => 'c2'));
        $cat1 = $dg->create_category();
        $cat1ctx = context_coursecat::instance($cat1->id);
        $template = $cpg->create_template(array('contextid' => $cat1ctx->id));
        $user1 = $dg->create_user(array('firstname' => 'User11', 'lastname' => 'Lastname1'));
        $user2 = $dg->create_user(array('firstname' => 'User12', 'lastname' => 'Lastname2'));
        $user3 = $dg->create_user(array('firstname' => 'User3', 'lastname' => 'Lastname3'));
        $user4 = $dg->create_user(array('firstname' => 'User4', 'lastname' => 'Lastname4'));
        $user5 = $dg->create_user(array('firstname' => 'User5', 'lastname' => 'Lastname5'));
        // Enrol users in courses.
        $dg->enrol_user($user1->id, $course1->id);
        $dg->enrol_user($user1->id, $course2->id);
        $dg->enrol_user($user2->id, $course1->id);
        $dg->enrol_user($user2->id, $course2->id);
        $dg->enrol_user($user3->id, $course1->id);
        $dg->enrol_user($user3->id, $course2->id);
        $dg->enrol_user($user4->id, $course1->id);
        $dg->enrol_user($user4->id, $course2->id);

        $appreciator = $dg->create_user(array('firstname' => 'Appreciator', 'lastname' => 'Test'));

        $roleprevent = create_role('Allow', 'allow', 'Allow read');
        assign_capability('moodle/competency:templateview', CAP_ALLOW, $roleprevent, $cat1ctx->id);
        role_assign($roleprevent, $appreciator->id, $cat1ctx->id);

        $tc1 = $cpg->create_template_competency(array(
            'templateid' => $template->get('id'),
            'competencyid' => $c1->get('id')
        ));
        $tc2 = $cpg->create_template_competency(array(
            'templateid' => $template->get('id'),
            'competencyid' => $c2->get('id')
        ));
        $plan1 = $cpg->create_plan(array('templateid' => $template->get('id'), 'userid' => $user1->id));
        $plan2 = $cpg->create_plan(array('templateid' => $template->get('id'), 'userid' => $user2->id));
        $plan3 = $cpg->create_plan(array('templateid' => $template->get('id'), 'userid' => $user3->id));
        $plan4 = $cpg->create_plan(array('templateid' => $template->get('id'), 'userid' => $user4->id));

        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $user1->id);
        cohort_add_member($cohort->id, $user2->id);
        cohort_add_member($cohort->id, $user3->id);
        cohort_add_member($cohort->id, $user4->id);

        // Create some course competencies.
        $cpg->create_course_competency(array('competencyid' => $c1->get('id'), 'courseid' => $course1->id));
        $cpg->create_course_competency(array('competencyid' => $c2->get('id'), 'courseid' => $course1->id));
        $cpg->create_course_competency(array('competencyid' => $c1->get('id'), 'courseid' => $course2->id));
        $cpg->create_course_competency(array('competencyid' => $c2->get('id'), 'courseid' => $course2->id));

        // Rate users in courses.
        // User 1.
        core_competency_api::grade_competency_in_course($course1, $user1->id, $c1->get('id'), 1);
        core_competency_api::grade_competency_in_course($course2, $user1->id, $c2->get('id'), 2);

        // User 2.
        core_competency_api::grade_competency_in_course($course1, $user2->id, $c1->get('id'), 2);
        core_competency_api::grade_competency_in_course($course2, $user2->id, $c1->get('id'), 1);
        core_competency_api::grade_competency_in_course($course2, $user2->id, $c2->get('id'), 3);

        // User 3.
        core_competency_api::grade_competency_in_course($course1, $user3->id, $c1->get('id'), 3);
        core_competency_api::grade_competency_in_course($course2, $user3->id, $c2->get('id'), 4);

        // Rate users in plan.
        // User1.
        core_competency_api::grade_competency_in_plan($plan1, $c1->get('id'), 1);
        core_competency_api::grade_competency_in_plan($plan1, $c2->get('id'), 2);
        // User2.
        core_competency_api::grade_competency_in_plan($plan2, $c1->get('id'), 2);

        $roleid = create_role('Role', 'appreciatorrole', 'mmmm');
        $params = (object) array(
            'userid' => $appreciator->id,
            'roleid' => $roleid,
            'cohortid' => $cohort->id
        );
        tool_cohortroles_api::create_cohort_role_assignment($params);
        tool_cohortroles_api::sync_all_cohort_roles();

        $this->setUser($appreciator);
        $scalevalues = array(
            array('scaleid' => $scale2->id, 'scalevalue' => 1),
            array('scaleid' => $scale2->id, 'scalevalue' => 2),
            array('scaleid' => $scale2->id, 'scalevalue' => 3),
        );
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues);
        $this->assertCount(3, $users);
        $userinfo = array_values($users);
        $this->assertEquals(array($userinfo[0]['fullname'], $userinfo[1]['fullname'], $userinfo[2]['fullname']),
                array('User11 Lastname1', 'User3 Lastname3', 'User12 Lastname2'));
        $this->assertEquals(1, $userinfo[0]['nbrating']);
        $this->assertEquals('User11 Lastname1', $userinfo[0]['fullname']);
        $this->assertEquals(1, $userinfo[1]['nbrating']);
        $this->assertEquals('User3 Lastname3', $userinfo[1]['fullname']);
        $this->assertEquals(2, $userinfo[2]['nbrating']);
        $this->assertEquals("User12 Lastname2", $userinfo[2]['fullname']);
        // Test with order DESC.
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues, true, 'DESC');
        $userinfo = array_values($users);
        $this->assertEquals(2, $userinfo[0]['nbrating']);
        $this->assertEquals("User12 Lastname2", $userinfo[0]['fullname']);
        $this->assertEquals(1, $userinfo[1]['nbrating']);
        $this->assertEquals('User11 Lastname1', $userinfo[1]['fullname']);
        $this->assertEquals(1, $userinfo[2]['nbrating']);

        // Test in scales values in plan.
        $scalevalues = array(
            array('scaleid' => $scale1->id, 'scalevalue' => 2),
            array('scaleid' => $scale2->id, 'scalevalue' => 1),
            array('scaleid' => $scale2->id, 'scalevalue' => 2),
        );
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues, false, 'ASC');
        $this->assertCount(2, $users);
        $userinfo = array_values($users);
        $this->assertEquals(1, $userinfo[0]['nbrating']);
        $this->assertEquals('User12 Lastname2', $userinfo[0]['fullname']);
        $this->assertEquals(2, $userinfo[1]['nbrating']);
        $this->assertEquals('User11 Lastname1', $userinfo[1]['fullname']);
        // Test with scales order DESC.
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues, false, 'DESC');
        $this->assertCount(2, $users);
        $userinfo = array_values($users);
        $this->assertEquals(2, $userinfo[0]['nbrating']);
        $this->assertEquals('User11 Lastname1', $userinfo[0]['fullname']);
        $this->assertEquals(1, $userinfo[1]['nbrating']);
        $this->assertEquals('User12 Lastname2', $userinfo[1]['fullname']);

        $scalevalues = array(
            array('scaleid' => $scale2->id, 'scalevalue' => 2),
            array('scaleid' => $scale2->id, 'scalevalue' => 3),
            array('scaleid' => $scale1->id, 'scalevalue' => 2),
        );
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues);
        $this->assertCount(3, $users);
        $userinfo = array_values($users);
        $this->assertEquals(array($userinfo[0]['fullname'], $userinfo[1]['fullname'], $userinfo[2]['fullname']),
                array('User11 Lastname1', 'User12 Lastname2', 'User3 Lastname3'));

        // Test with not found scale value.
        $scalevalues = array(
            array('scaleid' => $scale2->id, 'scalevalue' => 6),
        );
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues);
        $this->assertCount(0, $users);

        // Test search users in completed plans.
        $this->setAdminUser();
        core_competency_api::complete_plan($plan1);
        $scalevalues = array(
            array('scaleid' => $scale2->id, 'scalevalue' => 1),
            array('scaleid' => $scale1->id, 'scalevalue' => 2)
        );
        // Create new plan for the same user/comptencies and make some different ratings.
        $manualplan = $cpg->create_plan(array('userid' => $user1->id, 'status' => plan::STATUS_ACTIVE));
        $cpg->create_plan_competency(array('planid' => $manualplan->get('id'), 'competencyid' => $c1->get('id')));
        $cpg->create_plan_competency(array('planid' => $manualplan->get('id'), 'competencyid' => $c2->get('id')));

        core_competency_api::grade_competency_in_plan($manualplan, $c1->get('id'), 3);
        core_competency_api::grade_competency_in_plan($manualplan, $c2->get('id'), 4);

        // Now we have 2 different ratings for user1/competencies in active plan.
        $this->setUser($appreciator);
        $users = api::search_users_by_templateid($template->get('id'), 'User11', $scalevalues, false, 'DESC');
        $this->assertCount(1, $users);
        $this->assertEquals('User11 Lastname1', $users[$user1->id]['fullname']);
        // The 2 ratings from completed plan should be returned.
        $this->assertEquals(2, $users[$user1->id]['nbrating']);

        // Assert that the user is not returned if we search with the scale values from ratings
        // in the user_competency table.
        $scalevalues = array(
            array('scaleid' => $scale2->id, 'scalevalue' => 3),
            array('scaleid' => $scale1->id, 'scalevalue' => 4)
        );
        $users = api::search_users_by_templateid($template->get('id'), 'User11', $scalevalues, false, 'DESC');
        $this->assertCount(0, $users);

        // Test when user is unsubscribed from course 1.
        $this->setAdminUser();
        $enrol = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'manual'));
        $enrol->unenrol_user($instance, $user3->id);

        $this->setUser($appreciator);
        $scalevalues = array(
            array('scaleid' => $scale2->id, 'scalevalue' => 3),
            array('scaleid' => $scale1->id, 'scalevalue' => 4)
        );
        $users = api::search_users_by_templateid($template->get('id'), 'User3', $scalevalues, true, 'DESC');
        $this->assertCount(1, $users);
        $this->assertEquals('User3 Lastname3', $users[$user3->id]['fullname']);
        $this->assertEquals(1, $users[$user3->id]['nbrating']);

        // Test when user is unsubscribed from course 2.
        $this->setAdminUser();
        $enrol = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'manual'));
        $enrol->unenrol_user($instance, $user3->id);

        $this->setUser($appreciator);
        $users = api::search_users_by_templateid($template->get('id'), 'User3', $scalevalues, true, 'DESC');
        $this->assertCount(0, $users);

        // Test when competency 1 removed from course 1.
        $this->setAdminUser();
        core_competency_api::remove_competency_from_course($course1->id, $c1->get('id'));

        $this->setUser($appreciator);
        $scalevalues = array(
            array('scaleid' => $scale2->id, 'scalevalue' => 1),
            array('scaleid' => $scale2->id, 'scalevalue' => 2)
        );
        $users = api::search_users_by_templateid($template->get('id'), 'User12', $scalevalues, true, 'DESC');
        $this->assertCount(1, $users);
        $this->assertEquals('User12 Lastname2', $users[$user2->id]['fullname']);
        $this->assertEquals(1, $users[$user2->id]['nbrating']);

        // Test when competency 1 removed from course 2.
        $this->setAdminUser();
        core_competency_api::remove_competency_from_course($course2->id, $c1->get('id'));

        $this->setUser($appreciator);
        $users = api::search_users_by_templateid($template->get('id'), 'User12', $scalevalues, true, 'DESC');
        $this->assertCount(0, $users);

    }

    /**
     * Test get competency detail for lpmonitoring report when scale is defined in framework.
     */
    public function test_get_lp_monitoring_competency_detail_framework_scale() {
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
                'parentid' => $comp0->get('id'), 'path' => '0/'. $comp0->get('id')));                 // In C1, and C2.
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
        $record2->usermodified = $u1->id;
        $DB->insert_records('competency_usercompcourse', array($record1, $record2));

        // Create user competency and add an evidence.
        $uc = $lpg->create_user_competency(array('userid' => $u1->id, 'competencyid' => $comp1->get('id'),
            'proficiency' => true, 'grade' => 1));

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
        $lpg->create_evidence(array('usercompetencyid' => $uc->get('id'),
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

        $result = api::get_competency_detail($u1->id, $comp1->get('id'), $plan->get('id'));

        // User competency is found and has the right information.
        $this->assertNotNull($result->usercompetency);
        $this->assertEquals($uc->get('userid'), $result->usercompetency->get('userid'));
        $this->assertEquals($uc->get('competencyid'), $result->usercompetency->get('competencyid'));
        $this->assertEquals($uc->get('proficiency'), $result->usercompetency->get('proficiency'));
        $this->assertEquals($uc->get('grade'), $result->usercompetency->get('grade'));

        // Check scale configuration of the framework is found.
        $this->assertCount(2, $result->scaleconfig);
        $this->assertEquals(1, $result->scaleconfig[0]->scaledefault);
        $this->assertEquals(1, $result->scaleconfig[0]->proficient);
        $this->assertEquals(1, $result->scaleconfig[1]->proficient);

        // Check scale names are found.
        $this->assertCount(4, $result->scale);
        $this->assertEquals('A', $result->scale[1]);
        $this->assertEquals('B', $result->scale[2]);
        $this->assertEquals('C', $result->scale[3]);
        $this->assertEquals('D', $result->scale[4]);

        // Check scale colors of the framework are found.
        $this->assertCount(4, $result->reportscaleconfig);
        $this->assertEquals(1, $result->reportscaleconfig[0]->id);
        $this->assertEquals('A', $result->reportscaleconfig[0]->name);
        $this->assertEquals('#AAAAA', $result->reportscaleconfig[0]->color);
        $this->assertEquals(2, $result->reportscaleconfig[1]->id);
        $this->assertEquals('B', $result->reportscaleconfig[1]->name);
        $this->assertEquals('#BBBBB', $result->reportscaleconfig[1]->color);
        $this->assertEquals(3, $result->reportscaleconfig[2]->id);
        $this->assertEquals('C', $result->reportscaleconfig[2]->name);
        $this->assertEquals('#CCCCC', $result->reportscaleconfig[2]->color);
        $this->assertEquals(4, $result->reportscaleconfig[3]->id);
        $this->assertEquals('D', $result->reportscaleconfig[3]->name);
        $this->assertEquals('#DDDDD', $result->reportscaleconfig[3]->color);

        // Check that one prior learning evidence is found.
        $this->assertCount(1, $result->userevidences);

        // Check that all courses linked to the competency are found.
        $this->assertCount(3, $result->courses);
        $listcourses = array($c1->id, $c2->id, $c3->id);
        $this->assertTrue(in_array($result->courses[0]->course->id, $listcourses));
        $this->assertTrue(in_array($result->courses[1]->course->id, $listcourses));
        $this->assertTrue(in_array($result->courses[2]->course->id, $listcourses));

        // Check rate for course C1 is 1, rate for course C2 is 2 and C3 is not rated.
        // Check litteral note: C1 = C+, C2 = A-, C3 not evaluated.
        foreach ($result->courses as $element) {
            if ($element->course->id == $c1->id) {
                $this->assertEquals(1, $element->usecompetencyincourse->get('grade'));
                $this->assertEquals(1, $element->usecompetencyincourse->get('proficiency'));
                $this->assertEquals('C+', $element->gradetxt);
            } else {
                if ($element->course->id == $c2->id) {
                    $this->assertEquals(2, $element->usecompetencyincourse->get('grade'));
                    $this->assertEquals(0, $element->usecompetencyincourse->get('proficiency'));
                    $this->assertEquals('A-', $element->gradetxt);
                } else {
                    $this->assertNull($element->usecompetencyincourse->get('grade'));
                    $this->assertNull($element->usecompetencyincourse->get('proficiency'));
                    $this->assertEquals('-', $element->gradetxt);
                }
            }
        }
    }

    /**
     * Test get competency detail for lpmonitoring report when scale is defined in competency.
     */
    public function test_get_lp_monitoring_competency_detail_competency_scale() {
        global $DB;

        $this->resetAfterTest(true);
        $generator = phpunit_util::get_data_generator();
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $mpg = $dg->get_plugin_generator('report_lpmonitoring');

        $c1 = $dg->create_course();
        $c2 = $dg->create_course();
        $c3 = $dg->create_course();
        $c4 = $dg->create_course();
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();

        $scalecomp = $generator->create_scale(array('scale' => 'W,X,Y,Z'));
        $scaleconfigcomp = array(array('scaleid' => $scalecomp->id));
        $scaleconfigcomp[] = array('name' => 'W', 'id' => 1, 'scaledefault' => 0, 'proficient' => 1);
        $scaleconfigcomp[] = array('name' => 'X', 'id' => 2, 'scaledefault' => 0, 'proficient' => 1);
        $scaleconfigcomp[] = array('name' => 'Y', 'id' => 3, 'scaledefault' => 1, 'proficient' => 1);

        // Create framework with competencies.
        $framework = $lpg->create_framework();
        $comp0 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id'),
                'scaleid' => $scalecomp->id, 'scaleconfiguration' => $scaleconfigcomp,
                'parentid' => $comp0->get('id'), 'path' => '0/'. $comp0->get('id')));                 // In C1, and C2.
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

        // Create scale report configuration for the scale of framework.
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

        // Create scale report configuration for the scale of the competency.
        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'name' => 'W',  'color' => '#WWWWW');
        $scaleconfig[] = array('id' => 2, 'name' => 'X',  'color' => '#XXXXX');
        $scaleconfig[] = array('id' => 3, 'name' => 'Y',  'color' => '#YYYYY');
        $scaleconfig[] = array('id' => 4, 'name' => 'Z',  'color' => '#ZZZZZ');

        $record = new stdclass();
        $record->competencyframeworkid = $framework->get('id');
        $record->scaleid = $scalecomp->id;
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
        $record1->grade = 2;
        $record1->timecreated = 10;
        $record1->timemodified = 10;
        $record1->usermodified = $u1->id;

        $record2 = new \stdClass();
        $record2->userid = $u1->id;
        $record2->courseid = $c2->id;
        $record2->competencyid = $comp1->get('id');
        $record2->proficiency = 0;
        $record2->grade = 4;
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
        $lpg->create_evidence(array('usercompetencyid' => $uc->get('id'),
            'contextid' => \context_course::instance($c1->id)->id));
        $lpg->create_evidence(array('usercompetencyid' => $uc->get('id'),
            'contextid' => \context_course::instance($c2->id)->id));

        // Assign final grade for the course C1.
        $courseitem = \grade_item::fetch_course_item($c1->id);
        $result = $courseitem->update_final_grade($u1->id, 81, 'import', null);

        $context = context_course::instance($c1->id);
        $this->assign_good_letter_boundary($context->id);

        // Assign final grade for the course C2.
        $courseitem = \grade_item::fetch_course_item($c2->id);
        $result = $courseitem->update_final_grade($u1->id, 45, 'import', null);

        $context = context_course::instance($c2->id);
        $this->assign_good_letter_boundary($context->id);

        $result = api::get_competency_detail($u1->id, $comp1->get('id'), $plan->get('id'));

        // User competency is found and has the right information.
        $this->assertNotNull($result->usercompetency);
        $this->assertEquals($uc->get('userid'), $result->usercompetency->get('userid'));
        $this->assertEquals($uc->get('competencyid'), $result->usercompetency->get('competencyid'));
        $this->assertNull($result->usercompetency->get('proficiency'));
        $this->assertNull($result->usercompetency->get('grade'));

        // Check scale configuration of the competency is found.
        $this->assertCount(3, $result->scaleconfig);
        $this->assertEquals(1, $result->scaleconfig[0]->proficient);
        $this->assertEquals(1, $result->scaleconfig[1]->proficient);
        $this->assertEquals(1, $result->scaleconfig[2]->scaledefault);
        $this->assertEquals(1, $result->scaleconfig[2]->proficient);

        // Check scale names are found.
        $this->assertCount(4, $result->scale);
        $this->assertEquals('W', $result->scale[1]);
        $this->assertEquals('X', $result->scale[2]);
        $this->assertEquals('Y', $result->scale[3]);
        $this->assertEquals('Z', $result->scale[4]);

        // Check scale colors of the framework are found.
        $this->assertCount(4, $result->reportscaleconfig);
        $this->assertEquals(1, $result->reportscaleconfig[0]->id);
        $this->assertEquals('W', $result->reportscaleconfig[0]->name);
        $this->assertEquals('#WWWWW', $result->reportscaleconfig[0]->color);
        $this->assertEquals(2, $result->reportscaleconfig[1]->id);
        $this->assertEquals('X', $result->reportscaleconfig[1]->name);
        $this->assertEquals('#XXXXX', $result->reportscaleconfig[1]->color);
        $this->assertEquals(3, $result->reportscaleconfig[2]->id);
        $this->assertEquals('Y', $result->reportscaleconfig[2]->name);
        $this->assertEquals('#YYYYY', $result->reportscaleconfig[2]->color);
        $this->assertEquals(4, $result->reportscaleconfig[3]->id);
        $this->assertEquals('Z', $result->reportscaleconfig[3]->name);
        $this->assertEquals('#ZZZZZ', $result->reportscaleconfig[3]->color);

        // Check that one prior learning evidence is found.
        $this->assertCount(1, $result->userevidences);

        // Check that all courses linked to the competency are found.
        $this->assertCount(3, $result->courses);
        $listcourses = array($c1->id, $c2->id, $c3->id);
        $this->assertTrue(in_array($result->courses[0]->course->id, $listcourses));
        $this->assertTrue(in_array($result->courses[1]->course->id, $listcourses));
        $this->assertTrue(in_array($result->courses[2]->course->id, $listcourses));

        // Check rate for course C1 is 1, rate for course C2 is 2 and C3 is not rated.
        // Check litteral note: C1 = C+, C2 = A-, C3 not evaluated.
        foreach ($result->courses as $element) {
            if ($element->course->id == $c1->id) {
                $this->assertEquals(2, $element->usecompetencyincourse->get('grade'));
                $this->assertEquals(1, $element->usecompetencyincourse->get('proficiency'));
                $this->assertEquals('B+', $element->gradetxt);
            } else {
                if ($element->course->id == $c2->id) {
                    $this->assertEquals(4, $element->usecompetencyincourse->get('grade'));
                    $this->assertEquals(0, $element->usecompetencyincourse->get('proficiency'));
                    $this->assertEquals('D+', $element->gradetxt);
                } else {
                    $this->assertNull($element->usecompetencyincourse->get('grade'));
                    $this->assertNull($element->usecompetencyincourse->get('proficiency'));
                    $this->assertEquals('-', $element->gradetxt);
                }
            }
        }
    }

    /**
     * Test get competency statistics for lpmonitoring report when scale is defined in framework.
     */
    public function test_get_lp_monitoring_competency_stat_framework_scale() {
        global $DB;

        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $mpg = $dg->get_plugin_generator('report_lpmonitoring');

        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();

        // Create scale.
        $scale = $dg->create_scale(array('scale' => 'A,B,C,D'));

        // Create framework with the scale configuration.
        $scaleconfig = array(array('scaleid' => $scale->id));
        $scaleconfig[] = array('name' => 'A', 'id' => 1, 'scaledefault' => 1, 'proficient' => 1);
        $scaleconfig[] = array('name' => 'B', 'id' => 2, 'scaledefault' => 0, 'proficient' => 1);
        $framework = $lpg->create_framework(array('scaleid' => $scale->id, 'scaleconfiguration' => $scaleconfig));

        // Associate competencies to framework.
        $comp0 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id'),
                'parentid' => $comp0->get('id'), 'path' => '0/'. $comp0->get('id')));
        $comp2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));

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

        // Rate user competency1 for all users.
        $uc = $lpg->create_user_competency(array('userid' => $u1->id, 'competencyid' => $comp1->get('id'),
            'proficiency' => true, 'grade' => 1));
        $uc = $lpg->create_user_competency(array('userid' => $u2->id, 'competencyid' => $comp1->get('id'),
            'proficiency' => false, 'grade' => 3));
        $uc = $lpg->create_user_competency(array('userid' => $u3->id, 'competencyid' => $comp1->get('id'),
            'proficiency' => true, 'grade' => 2));

        $result = api::get_competency_statistics($comp1->get('id'), $template->get('id'));
        $this->assertEquals($comp1->get('id'), $result->competency->get('id'));

        // Check scale configuration of the framework is found.
        $this->assertCount(2, $result->scaleconfig);
        $this->assertEquals(1, $result->scaleconfig[0]->scaledefault);
        $this->assertEquals(1, $result->scaleconfig[0]->proficient);
        $this->assertEquals(1, $result->scaleconfig[1]->proficient);

        // Check scale names are found.
        $this->assertCount(4, $result->scale);
        $this->assertEquals('A', $result->scale[1]);
        $this->assertEquals('B', $result->scale[2]);
        $this->assertEquals('C', $result->scale[3]);
        $this->assertEquals('D', $result->scale[4]);

        // Check scale colors of the framework are found.
        $this->assertCount(4, $result->reportscaleconfig);
        $this->assertEquals(1, $result->reportscaleconfig[0]->id);
        $this->assertEquals('A', $result->reportscaleconfig[0]->name);
        $this->assertEquals('#AAAAA', $result->reportscaleconfig[0]->color);
        $this->assertEquals(2, $result->reportscaleconfig[1]->id);
        $this->assertEquals('B', $result->reportscaleconfig[1]->name);
        $this->assertEquals('#BBBBB', $result->reportscaleconfig[1]->color);
        $this->assertEquals(3, $result->reportscaleconfig[2]->id);
        $this->assertEquals('C', $result->reportscaleconfig[2]->name);
        $this->assertEquals('#CCCCC', $result->reportscaleconfig[2]->color);
        $this->assertEquals(4, $result->reportscaleconfig[3]->id);
        $this->assertEquals('D', $result->reportscaleconfig[3]->name);
        $this->assertEquals('#DDDDD', $result->reportscaleconfig[3]->color);

        // Check that all user plans are found.
        $this->assertCount(3, $result->listusers);

        // Check grade is found for each users.
        foreach ($result->listusers as $user) {
            if ($user->userinfo->id == $u1->id) {
                $this->assertEquals(1, $user->usercompetency->get('grade'));
            } else {
                if ($user->userinfo->id == $u2->id) {
                    $this->assertEquals(3, $user->usercompetency->get('grade'));

                } else {
                    $this->assertEquals(2, $user->usercompetency->get('grade'));
                }
            }
        }
    }

    /**
     * Test get competency statistics for lpmonitoring report when no permissions.
     */
    public function test_get_lp_monitoring_competency_stat_permissions() {
        $this->setAdminUser();

        // Create plan from template for Stephanie.
        $planstephanie = core_competency_api::create_plan_from_template($this->templateincategory->get('id'), $this->user3->id);
        $syscontext = context_system::instance();
        $this->setUser($this->appreciatorforcategory);
        // Test we can read the first plan for the template (Rebecca).
        $result = api::read_plan(0, $this->templateincategory->get('id'));
        $this->assertEquals($this->user1->id, $result->current->get('userid'));

        // Test we can not read competency stats because of permissions for Stephanie user competency.
        try {
            api::get_competency_statistics($this->comp1->get('id'), $this->templateincategory->get('id'));
            $this->fail("We don't have read plan permission for Stephanie Grant");
        } catch (Exception $ex) {
            $this->assertContains('Stepanie Grant', $ex->getMessage());
        }
    }

    /**
     * Test get competency statistics for lpmonitoring report when scale is defined in competency.
     */
    public function test_get_lp_monitoring_competency_stat_specific_scale() {
        global $DB;

        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $mpg = $dg->get_plugin_generator('report_lpmonitoring');

        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();

        // Create scale.
        $scale1 = $dg->create_scale(array('scale' => 'A,B,C,D'));

        // Create framework with the scale configuration.
        $scaleconfig = array(array('scaleid' => $scale1->id));
        $scaleconfig[] = array('name' => 'A', 'id' => 1, 'scaledefault' => 1, 'proficient' => 1);
        $scaleconfig[] = array('name' => 'B', 'id' => 2, 'scaledefault' => 0, 'proficient' => 1);
        $framework = $lpg->create_framework(array('scaleid' => $scale1->id, 'scaleconfiguration' => $scaleconfig));

        // Associate competencies to framework.
        $comp0 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        $comp1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id'),
                'parentid' => $comp0->get('id'), 'path' => '0/'. $comp0->get('id')));

        // Create second scale and associate it with a competency.
        $scale2 = $dg->create_scale(array('scale' => 'W,X,Y,Z'));

        $scaleconfig = array(array('scaleid' => $scale2->id));
        $scaleconfig[] = array('name' => 'W', 'id' => 1, 'scaledefault' => 0, 'proficient' => 1);
        $scaleconfig[] = array('name' => 'X', 'id' => 2, 'scaledefault' => 1, 'proficient' => 1);
        $comp2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id'),
            'scaleid' => $scale2->id, 'scaleconfiguration' => $scaleconfig));

        // Create template with competencies.
        $template = $lpg->create_template();
        $tempcomp0 = $lpg->create_template_competency(array('templateid' => $template->get('id'),
            'competencyid' => $comp0->get('id')));
        $tempcomp1 = $lpg->create_template_competency(array('templateid' => $template->get('id'),
            'competencyid' => $comp1->get('id')));
        $tempcomp2 = $lpg->create_template_competency(array('templateid' => $template->get('id'),
            'competencyid' => $comp2->get('id')));

        // Create scale report configuration.
        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'name' => 'A',  'color' => '#AAAAA');
        $scaleconfig[] = array('id' => 2, 'name' => 'B',  'color' => '#BBBBB');
        $scaleconfig[] = array('id' => 3, 'name' => 'C',  'color' => '#CCCCC');
        $scaleconfig[] = array('id' => 4, 'name' => 'D',  'color' => '#DDDDD');

        $record = new stdclass();
        $record->competencyframeworkid = $framework->get('id');
        $record->scaleid = $scale1->id;
        $record->scaleconfiguration = json_encode($scaleconfig);
        $mpg->create_report_competency_config($record);

        // Create second scale report configuration.
        $scaleconfig = array();
        $scaleconfig[] = array('id' => 1, 'name' => 'W',  'color' => '#WWWWW');
        $scaleconfig[] = array('id' => 2, 'name' => 'X',  'color' => '#XXXXX');
        $scaleconfig[] = array('id' => 3, 'name' => 'Y',  'color' => '#YYYYY');
        $scaleconfig[] = array('id' => 4, 'name' => 'Z',  'color' => '#ZZZZZ');

        $record = new stdclass();
        $record->competencyframeworkid = $framework->get('id');
        $record->scaleid = $scale2->id;
        $record->scaleconfiguration = json_encode($scaleconfig);
        $mpg->create_report_competency_config($record);

        // Create plan from template for all users.
        $p1 = $lpg->create_plan(array('userid' => $u1->id, 'templateid' => $template->get('id'), 'status' => plan::STATUS_ACTIVE));
        $p2 = $lpg->create_plan(array('userid' => $u2->id, 'templateid' => $template->get('id'), 'status' => plan::STATUS_ACTIVE));
        $p3 = $lpg->create_plan(array('userid' => $u3->id, 'templateid' => $template->get('id'), 'status' => plan::STATUS_ACTIVE));

        // Rate user competency1 for all users.
        $uc = $lpg->create_user_competency(array('userid' => $u1->id, 'competencyid' => $comp2->get('id'),
            'proficiency' => true, 'grade' => 1));
        $uc = $lpg->create_user_competency(array('userid' => $u2->id, 'competencyid' => $comp2->get('id'),
            'proficiency' => false, 'grade' => 3));
        $uc = $lpg->create_user_competency(array('userid' => $u3->id, 'competencyid' => $comp2->get('id'),
            'proficiency' => true, 'grade' => 2));

        $result = api::get_competency_statistics($comp2->get('id'), $template->get('id'));
        $this->assertEquals($comp2->get('id'), $result->competency->get('id'));

        // Check scale configuration of the competency is found.
        $this->assertCount(2, $result->scaleconfig);
        $this->assertEquals(1, $result->scaleconfig[0]->proficient);
        $this->assertEquals(1, $result->scaleconfig[1]->scaledefault);
        $this->assertEquals(1, $result->scaleconfig[1]->proficient);

        // Check scale names are found.
        $this->assertCount(4, $result->scale);
        $this->assertEquals('W', $result->scale[1]);
        $this->assertEquals('X', $result->scale[2]);
        $this->assertEquals('Y', $result->scale[3]);
        $this->assertEquals('Z', $result->scale[4]);

        // Check scale colors of the framework are found.
        $this->assertCount(4, $result->reportscaleconfig);
        $this->assertEquals(1, $result->reportscaleconfig[0]->id);
        $this->assertEquals('W', $result->reportscaleconfig[0]->name);
        $this->assertEquals('#WWWWW', $result->reportscaleconfig[0]->color);
        $this->assertEquals(2, $result->reportscaleconfig[1]->id);
        $this->assertEquals('X', $result->reportscaleconfig[1]->name);
        $this->assertEquals('#XXXXX', $result->reportscaleconfig[1]->color);
        $this->assertEquals(3, $result->reportscaleconfig[2]->id);
        $this->assertEquals('Y', $result->reportscaleconfig[2]->name);
        $this->assertEquals('#YYYYY', $result->reportscaleconfig[2]->color);
        $this->assertEquals(4, $result->reportscaleconfig[3]->id);
        $this->assertEquals('Z', $result->reportscaleconfig[3]->name);
        $this->assertEquals('#ZZZZZ', $result->reportscaleconfig[3]->color);

        // Check that all user plans are found.
        $this->assertCount(3, $result->listusers);

        // Check grade is found for each users.
        foreach ($result->listusers as $user) {
            if ($user->userinfo->id == $u1->id) {
                $this->assertEquals(1, $user->usercompetency->get('grade'));
            } else {
                if ($user->userinfo->id == $u2->id) {
                    $this->assertEquals(3, $user->usercompetency->get('grade'));

                } else {
                    $this->assertEquals(2, $user->usercompetency->get('grade'));
                }
            }
        }
        // Test read competency stats on non existing competency for user plan completed.
        $this->setAdminUser();
        core_competency_api::complete_plan($p3->get('id'));
        $comp3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get('id')));
        core_competency_api::add_competency_to_template($template->get('id'), $comp3->get('id'));
        $result = api::get_competency_statistics($comp3->get('id'), $template->get('id'));
        $this->assertCount(2, $result->listusers);
    }

    /**
     * Search templates.
     */
    public function test_search_templates() {
        $user = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        $syscontext = context_system::instance();
        $syscontextid = $syscontext->id;
        $catcontext = context_coursecat::instance($category->id);
        $catcontextid = $catcontext->id;

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
            api::search_templates($syscontext, '', 0, 10, 'children', false);
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
        $result = api::search_templates($syscontext, '', 0, 10, 'children', false);
        $result = array_values($result);
        $this->assertCount(2, $result);
        $this->assertEquals($template2->get('id'), $result[0]->get('id'));
        $this->assertEquals($template1->get('id'), $result[1]->get('id'));

        // Test with limit 1.
        $result = api::search_templates($syscontext, '', 0, 1, 'children', false);
        $result = array_values($result);
        $this->assertCount(1, $result);
        $this->assertEquals($template2->get('id'), $result[0]->get('id'));

        // User with category permissions and query search.
        $result = api::search_templates($syscontext, 'Painting', 0, 10, 'children', false);
        $result = array_values($result);
        $this->assertCount(1, $result);
        $this->assertEquals($template2->get('id'), $result[0]->get('id'));

        // User with category permissions and query search and only visible.
        $result = api::search_templates($syscontext, 'US Independence', 0, 10, 'children', true);
        $result = array_values($result);
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

}
