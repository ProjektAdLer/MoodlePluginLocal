<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace local_adler\external;


use backup;
use backup_controller;
use context_course;
use invalid_parameter_exception;
use local_adler\lib\adler_externallib_testcase;
use local_adler\local\exceptions\not_an_adler_course_exception;
use moodle_exception;

global $CFG;
require_once($CFG->dirroot . '/local/adler/tests/lib/adler_testcase.php');


class upload_course_test extends adler_externallib_testcase {
    public function generate_mbz(bool $is_adler_course=true, bool|null $enable_self_enrolment = null): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        // generate course
        $course = $this->getDataGenerator()->create_course();
        // adler course
        if ($is_adler_course) {
            $this->getDataGenerator()->get_plugin_generator('local_adler')->create_adler_course_object($course->id);
        }

        // self enrolment
        if ($enable_self_enrolment !== null) {
            $self_enrol_plugin = enrol_get_plugin('self');
            $self_enrol_instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
            $self_enrol_plugin->update_status($self_enrol_instance, $enable_self_enrolment ? ENROL_INSTANCE_ENABLED: ENROL_INSTANCE_DISABLED);
        }

        // create backup (mbz)
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            2
        );
        $bc->get_plan()->get_setting('users')->set_value('0');
        $bc->execute_plan();
        $bc->destroy();

        $file = $bc->get_results();
        $file = reset($file);
        $filepath = $file->get_contenthash();
        $filepath = $CFG->dataroot . '/filedir/' . substr($filepath, 0, 2) . '/' . substr($filepath, 2, 2) . '/' . $filepath;

        // delete temporary course
        delete_course($course->id);

        return $filepath;
    }

    public static function provide_enrolment_option_test_data() {
        return [
            'self enrolment active' => [
                'enable_self_enrolment' => true
            ],
            'self enrolment inactive' => [
                'enable_self_enrolment' => false
            ],
        ];
    }

    /**
     * @dataProvider provide_enrolment_option_test_data
     */
    public function test_execute_enrolment_option($enable_self_enrolment) {
        global $DB;

        $test_course_filepath = $this->generate_mbz(true, $enable_self_enrolment);

        $_FILES['mbz'] = [
            'name' => 'test.mbz',
            'type' => 'application/zip',
            'tmp_name' => $test_course_filepath,
            'error' => UPLOAD_ERR_OK,
            'size' => 123,
        ];

        // create user, required for restore
        $user = $this->getDataGenerator()->create_user();
        $role_id = $DB->get_record('role', array('shortname' => 'manager'))->id;
        role_assign($role_id, $user->id, 1);
        $this->setUser($user->id);

        $result = upload_course::execute(null, false);

        $self_enrol_instance = $DB->get_record('enrol', ['courseid' => $result['data']['course_id'], 'enrol' => 'self'], '*', MUST_EXIST);

        $this->assertEquals($enable_self_enrolment ? ENROL_INSTANCE_ENABLED : ENROL_INSTANCE_DISABLED, $self_enrol_instance->status);
    }

    public function test_execute_fail_after_empty_course_created() {
        global $DB;

        // From my understanding these checks are not possible for postgresql databases as they don't allow rolling back sub-transactions
        // Overall the behaviour should still be correct, but as this code is executed as part of a higher level transaction
        // the transaction is not yet rolled back and therefore the modifications are not yet undone
        if ($DB->get_dbfamily() === 'postgres') {
            $this->markTestSkipped('This test is not compatible with postgres');
        }


        $test_course_filepath = $this->generate_mbz(false);

        $_FILES['mbz'] = [
            'name' => 'test.mbz',
            'type' => 'application/zip',
            'tmp_name' => $test_course_filepath,
            'error' => UPLOAD_ERR_OK,
            'size' => 123,
        ];

        // create user, required for restore
        $user = $this->getDataGenerator()->create_user();
        $role_id = $DB->get_record('role', array('shortname' => 'manager'))->id;
        role_assign($role_id, $user->id, 1);
        $this->setUser($user->id);

        // verify there is only one course
        $this->assertCount(1, $DB->get_records('course'), 'Initially there sould be only one course');

        try {
            upload_course::execute(null, false);
        } catch (not_an_adler_course_exception $e) {
            // verify there is still only one course
            $this->assertCount(1, $DB->get_records('course'));
            return;
        }

        $this->fail();
    }


    public static function provide_test_execute_data() {
        return [
            'success' => [
                'upload_error' => UPLOAD_ERR_OK,
                'fail_validation' => false,
                'valid_user' => true,
                'specify_course_cat' => false,
                'dry_run' => false,
            ],
            'fail_validation' => [
                'upload_error' => UPLOAD_ERR_OK,
                'fail_validation' => true,
                'valid_user' => true,
                'specify_course_cat' => false,
                'dry_run' => false,
            ],
            'mbz_upload_failed' => [
                'upload_error' => UPLOAD_ERR_NO_FILE,
                'fail_validation' => false,
                'valid_user' => true,
                'specify_course_cat' => false,
                'dry_run' => false,
            ],
            'user_not_allowed' => [
                'upload_error' => UPLOAD_ERR_OK,
                'fail_validation' => false,
                'valid_user' => false,
                'specify_course_cat' => false,
                'dry_run' => false,
            ],
            'specified_course_cat' => [
                'upload_error' => UPLOAD_ERR_OK,
                'fail_validation' => false,
                'valid_user' => true,
                'specify_course_cat' => true,
                'dry_run' => false,
            ],
            'dry_run' => [
                'upload_error' => UPLOAD_ERR_OK,
                'fail_validation' => false,
                'valid_user' => true,
                'specify_course_cat' => true,
                'dry_run' => true,
            ]
        ];
    }

    /**
     * @dataProvider provide_test_execute_data
     *
     * # ANF-ID: [MVP11]
     */
    public function test_execute($upload_error, $fail_validation, $valid_user, $specify_course_cat, $dry_run) {
        $test_course_filepath = $this->generate_mbz();

        global $DB;
        $course_count_before = $DB->count_records('course');

        if ($upload_error !== UPLOAD_ERR_OK) {
            $this->expectException(invalid_parameter_exception::class);
        }

        if ($fail_validation) {
            $this->expectException(invalid_parameter_exception::class);
        } else {
            $_FILES['mbz'] = [
                'name' => 'test.mbz',
                'type' => 'application/zip',
                'tmp_name' => $test_course_filepath,
                'error' => $upload_error,
                'size' => 123,
            ];
        }

        if ($valid_user) {
            $user = $this->getDataGenerator()->create_user();
            $role_id = $DB->get_record('role', array('shortname' => 'manager'))->id;
            role_assign($role_id, $user->id, 1);
            $this->setUser($user->id);
        } else {
            $this->expectException(moodle_exception::class);
            $this->expectExceptionMessage('not_allowed');
        }

        // case specify_course_cat
        $course_cat = $this->getDataGenerator()->create_category();
        $param_course_cat = $specify_course_cat ? $course_cat->id : null;

        // case dry_run
        $param_dry_run = $dry_run ? true : false;
        $result = upload_course::execute($param_course_cat, $param_dry_run);


        $course_count_after = $DB->count_records('course');

        if ($dry_run) {
            $this->assertEquals($course_count_before, $course_count_after);
        } else {
            $this->assertEquals($course_count_before + 1, $course_count_after);
            // check role of user to be noneditingteacher
            $user_role = get_role_users(
                $DB->get_record('role', ['shortname' => 'teacher'])->id,
                context_course::instance($result['data']['course_id']),
            );
            $this->assertCount(1, $user_role);
        }
    }


    public static function provide_test_execute_returns_data() {
        return [
            'success' => [
                'success' => true,
            ],
            'fail' => [
                'success' => false,
            ],
        ];
    }

    /**
     * @dataProvider provide_test_execute_returns_data
     *
     *  # ANF-ID: [MVP11]
     */
    public function test_execute_returns($success) {
        if (!$success) {
            $this->expectException(invalid_parameter_exception::class);
            upload_course::validate_parameters(upload_course::execute_returns(), 'blub');
        } else {
            upload_course::validate_parameters(upload_course::execute_returns(), ['data' => [
                'course_id' => 1,
                'course_fullname' => 'blub'
            ]]);
        }
    }

    public static function provide_test_execute_parameters_data() {
        return [
            '1' => [
                'data' => [
                    'category_id' => 7,

                ]
            ],
            '2' => [
                'data' => [
                    'only_check_permissions' => true
                ],
            ],
            '3' => [
                'data' => []
            ]
        ];
    }

    /**
     * @dataProvider provide_test_execute_parameters_data
     *
     *  # ANF-ID: [MVP11]
     */
    public function test_execute_parameters($data) {
        upload_course::validate_parameters(upload_course::execute_parameters(), $data);
    }
}