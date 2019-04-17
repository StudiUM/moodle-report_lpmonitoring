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
 * API for course module tests.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2019 Université de Montréal
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
 * API for course module tests.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2019 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_lpmonitoring_api_cm_testcase extends advanced_testcase {

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
        if (!api::is_cm_comptency_grading_enabled()) {
            $this->markTestSkipped('Skipped test, grading competency in course module is disabled');
        }
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
        assign_capability('moodle/competency:plancomment', CAP_ALLOW, $roleid, $syscontext->id);
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
     * Test get learning plans from templateid with scale filter in course module.
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
        // Create course modules.
        $pagegenerator = $this->getDataGenerator()->get_plugin_generator('mod_page');
        $page1 = $pagegenerator->create_instance(array('course' => $course1->id));
        $page2 = $pagegenerator->create_instance(array('course' => $course1->id));
        $cm1 = get_coursemodule_from_instance('page', $page1->id);
        $cm2 = get_coursemodule_from_instance('page', $page2->id);

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
        // Link competencies to course modules.
        $cpg->create_course_module_competency(array('competencyid' => $c1->get('id'), 'cmid' => $cm1->id));
        $cpg->create_course_module_competency(array('competencyid' => $c2->get('id'), 'cmid' => $cm1->id));
        $cpg->create_course_module_competency(array('competencyid' => $c1->get('id'), 'cmid' => $cm2->id));
        $cpg->create_course_module_competency(array('competencyid' => $c2->get('id'), 'cmid' => $cm2->id));

        // Rate users in courses.
        // User 1.
        \tool_cmcompetency\api::grade_competency_in_coursemodule($cm1, $user1->id, $c1->get('id'), 1);
        \tool_cmcompetency\api::grade_competency_in_coursemodule($cm2, $user1->id, $c2->get('id'), 2);

        // User 2.
        \tool_cmcompetency\api::grade_competency_in_coursemodule($cm1, $user2->id, $c1->get('id'), 3);
        \tool_cmcompetency\api::grade_competency_in_coursemodule($cm1, $user2->id, $c2->get('id'), 1);

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
            array('scaleid' => $scale1->id, 'scalevalue' => 1),
        );
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues, 'coursemodule');
        $this->assertCount(2, $users);
        $userinfo = array_values($users);
        $this->assertEquals(array($userinfo[0]['fullname'], $userinfo[1]['fullname']),
                array('User11 Lastname1', 'User12 Lastname2'));
        $this->assertEquals(1, $userinfo[0]['nbrating']);
        $this->assertEquals('User11 Lastname1', $userinfo[0]['fullname']);
        $this->assertEquals(2, $userinfo[1]['nbrating']);
        $this->assertEquals("User12 Lastname2", $userinfo[1]['fullname']);
        // Test with search query user12.
        $users = api::search_users_by_templateid($template->get('id'), 'user12', $scalevalues, 'coursemodule', 'ASC');
        $this->assertCount(1, $users);
        $userinfo = array_values($users);
        $this->assertEquals(2, $userinfo[0]['nbrating']);
        $this->assertEquals('User12 Lastname2', $userinfo[0]['fullname']);

        // Test with order DESC.
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues, 'coursemodule', 'DESC');
        $this->assertCount(2, $users);
        $userinfo = array_values($users);
        $this->assertEquals(2, $userinfo[0]['nbrating']);
        $this->assertEquals("User12 Lastname2", $userinfo[0]['fullname']);
        $this->assertEquals(1, $userinfo[1]['nbrating']);
        $this->assertEquals('User11 Lastname1', $userinfo[1]['fullname']);

        // Test in scales values in course module with value 3 in scale2.
        $scalevalues = array(
            array('scaleid' => $scale2->id, 'scalevalue' => 3),
        );
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues, 'coursemodule', 'ASC');
        $this->assertCount(1, $users);
        $userinfo = array_values($users);
        $this->assertEquals(1, $userinfo[0]['nbrating']);
        $this->assertEquals('User12 Lastname2', $userinfo[0]['fullname']);

        // Test with not found scale value.
        $scalevalues = array(
            array('scaleid' => $scale2->id, 'scalevalue' => 6),
        );
        $users = api::search_users_by_templateid($template->get('id'), 'coursemodule', $scalevalues, 'coursemodule');
        $this->assertCount(0, $users);

        // Test when user1 is unsubscribed from course 1.
        $this->setAdminUser();
        $enrol = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'manual'));
        $enrol->unenrol_user($instance, $user1->id);

        $this->setUser($appreciator);
        $scalevalues = array(
            array('scaleid' => $scale2->id, 'scalevalue' => 1),
            array('scaleid' => $scale2->id, 'scalevalue' => 2),
            array('scaleid' => $scale2->id, 'scalevalue' => 3),
            array('scaleid' => $scale1->id, 'scalevalue' => 1),
        );
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues, 'coursemodule', 'DESC');
        $this->assertCount(1, $users);
        $this->assertEquals('User12 Lastname2', $users[$user2->id]['fullname']);
        $this->assertEquals(2, $users[$user2->id]['nbrating']);

        // Test when competency 2 are removed from course module cm1.
        $this->setAdminUser();
        core_competency_api::remove_competency_from_course_module($cm1->id, $c2->get('id'));

        $this->setUser($appreciator);
        $scalevalues = array(
            array('scaleid' => $scale2->id, 'scalevalue' => 1),
            array('scaleid' => $scale2->id, 'scalevalue' => 2),
            array('scaleid' => $scale2->id, 'scalevalue' => 3),
            array('scaleid' => $scale1->id, 'scalevalue' => 1),
        );
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues, 'coursemodule', 'DESC');
        $this->assertCount(1, $users);
        $this->assertEquals('User12 Lastname2', $users[$user2->id]['fullname']);
        $this->assertEquals(1, $users[$user2->id]['nbrating']);
        // Filter with scale1 only.
        $scalevalues = array(
            array('scaleid' => $scale1->id, 'scalevalue' => 1),
        );
        $users = api::search_users_by_templateid($template->get('id'), '', $scalevalues, 'coursemodule', 'DESC');
        $this->assertCount(0, $users);

        // Test when user2 is unsubscribed from course 2.
        $this->setAdminUser();
        $enrol = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'manual'));
        $enrol->unenrol_user($instance, $user2->id);

        $this->setUser($appreciator);
        $users = api::search_users_by_templateid($template->get('id'), 'User12', $scalevalues, 'coursemodule', 'DESC');
        $this->assertCount(0, $users);

        // Test search_users_by_templateid when grading competency in course module is disabled.
        api::$iscmcompetencygradingenabled = false;
        try {
            api::search_users_by_templateid($template->get('id'), '', $scalevalues, 'coursemodule', 'DESC');
            $this->fail('Must fail because grading competency in course module is disabled');
        } catch (\Exception $ex) {
            $this->assertContains('Grading competency in course module is disabled', $ex->getMessage());
        }
    }
}
