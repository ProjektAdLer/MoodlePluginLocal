<?php

namespace local_adler;


use coding_exception;
use completion_info;
use local_adler\lib\local_adler_testcase;
use local_adler\lib\static_mock_utilities_trait;
use mod_h5pactivity\local\grader;
use moodle_exception;
use ReflectionClass;
use stdClass;
use Throwable;

global $CFG;
require_once($CFG->dirroot . '/local/adler/tests/lib/adler_testcase.php');

class completion_info_mock extends completion_info {
    use static_mock_utilities_trait;

    public function is_enabled($cm = null) {
        return static::mock_this_function(__FUNCTION__, func_get_args());
    }

    public function get_data($cm, $wholecourse = false, $userid = 0, $unused = null) {
        return static::mock_this_function(__FUNCTION__, func_get_args());
    }
}

class helpers_mock extends helpers {
    use static_mock_utilities_trait;

    public static function course_is_adler_course($module_ids) {
        return static::mock_this_function(__FUNCTION__, func_get_args());
    }

    public static function get_course_from_course_id($course_id) {
        return static::mock_this_function(__FUNCTION__, func_get_args());
    }
}

class dsl_score_mock extends dsl_score {
    use static_mock_utilities_trait;

    protected static $helpers = helpers_mock::class;

    public static function get_adler_score_record(int $module_id): stdClass {
        return static::mock_this_function(__FUNCTION__, func_get_args());
    }

    public function test_get_score_item() {
        return $this->score_item;
    }
}


class dsl_score_test extends local_adler_testcase {
    public function setUp(): void {
        parent::setUp();

        // create user
        $this->user = $this->getDataGenerator()->create_user();

        // create course
        $this->course = $this->getDataGenerator()->create_course();

        // create module
        $this->module = $this->getDataGenerator()->create_module('url', ['course' => $this->course->id, 'completion' => 1]);

    }

    public function provide_test_construct_data() {
        // double array for each case because phpunit otherwise splits the object into individual params
        return [
            'default case' => [[
                'enrolled' => true,
                'user_param' => null,
                'set_user_object' => true,
                'course_module_param' => 'correct',
                'is_adler_course' => true,
                'is_adler_cm' => true,
                'expect_exception' => false,
                'expect_exception_message' => null,

            ]],
            'with user id param' => [[
                'enrolled' => true,
                'user_param' => 'id',
                'set_user_object' => true,
                'course_module_param' => 'correct',
                'is_adler_course' => true,
                'is_adler_cm' => true,
                'expect_exception' => false,
                'expect_exception_message' => null,

            ]],
            'not enrolled' => [[
                'enrolled' => false,
                'user_param' => null,
                'set_user_object' => true,
                'course_module_param' => 'correct',
                'is_adler_course' => true,
                'is_adler_cm' => true,
                'expect_exception' => moodle_exception::class,
                'expect_exception_message' => "user_not_enrolled",
            ]],
            'invalid module format' => [[
                'enrolled' => true,
                'user_param' => null,
                'set_user_object' => true,
                'course_module_param' => 'incorrect',
                'is_adler_course' => true,
                'is_adler_cm' => true,
                'expect_exception' => coding_exception::class,
                'expect_exception_message' => 'course_module_format_not_valid',
            ]],
            'not adler course' => [[
                'enrolled' => true,
                'user_param' => null,
                'set_user_object' => true,
                'course_module_param' => 'correct',
                'is_adler_course' => false,
                'is_adler_cm' => true,
                'expect_exception' => moodle_exception::class,
                'expect_exception_message' => 'not_an_adler_course',
            ]],
        ];
    }

    /**
     * @dataProvider provide_test_construct_data
     */
    public function test_construct($test) {
        // reset
        helpers_mock::reset_data();
        dsl_score_mock::reset_data();

        $module_format_correct = get_fast_modinfo($this->course->id, 0, false)->get_cm($this->module->cmid);

        if ($test['enrolled']) {
            $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id);
        }

        helpers_mock::set_returns('course_is_adler_course', [$test['is_adler_course']]);

        if ($test['is_adler_cm']) {
            dsl_score_mock::set_returns('get_adler_score_record', [(object)['id' => 1, 'moduleid' => $module_format_correct->id, 'score' => 17]]);
        } else {
            dsl_score_mock::set_returns('get_adler_score_record', [null]);
            dsl_score_mock::set_exceptions('get_adler_score_record', [new moodle_exception('not_an_adler_cm', 'test')]);
        }

        if ($test['set_user_object']) {
            $this->setUser($this->user);
        }

        if ($test['course_module_param'] === 'correct') {
            $test['course_module_param'] = $module_format_correct;
        } else if ($test['course_module_param'] === 'incorrect') {
            $test['course_module_param'] = $this->module;
        }

        if ($test['user_param'] === 'id') {
            $test['user_param'] = $this->user->id;
        }


        // call method
        try {
            $result = new dsl_score_mock($test['course_module_param'], $test['user_param']);
        } catch (Throwable $e) {
            $this->assertEquals($test['expect_exception'], get_class($e));
            if ($test['expect_exception_message'] !== null) {
                $this->assertStringContainsString($test['expect_exception_message'], $e->getMessage());
            }
            return;
        }
        if ($test['expect_exception'] !== false) {
            $this->fail('Exception expected');
        }

        // No exception thrown and no exception expected -> check result
        // test score
        $this->assertEquals(17, $result->test_get_score_item()->score);
        $this->assertEquals($module_format_correct->id, $result->get_cmid());
    }

    public function provide_test_get_primitive_score_data() {
        return [
            'complete' => [[
                'completion_enabled' => true,
                'completion_state' => COMPLETION_COMPLETE,
                'expect_exception' => false,
                'expect_exception_message' => null,
                'expect_score' => 1,
            ]],
            'incomplete' => [[
                'completion_enabled' => true,
                'completion_state' => COMPLETION_INCOMPLETE,
                'expect_exception' => false,
                'expect_exception_message' => null,
                'expect_score' => 0,
            ]],
            'completion_disabled' => [[
                'completion_enabled' => false,
                'completion_state' => COMPLETION_INCOMPLETE,
                'expect_exception' => moodle_exception::class,
                'expect_exception_message' => "completion_not_enabled",
                'expect_score' => 0,
            ]],
        ];
    }

    /**
     * @dataProvider provide_test_get_primitive_score_data
     */
    public function test_get_primitive_score($data) {
        // create primitive activity
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_url');
        $cm = $generator->create_instance(array(
            'course' => $this->course->id,
        ));
        $cm_other_format = get_fast_modinfo($this->course->id, 0, false)->get_cm($cm->cmid);

        // Create score (dsl) item.
        $score_item = $this->getDataGenerator()
            ->get_plugin_generator('local_adler')
            ->create_dsl_score_item($cm_other_format->id, [], false);


        // create dsl_score object and set private properties
        $reflection = new ReflectionClass(dsl_score::class);
        // create dsl_score without constructor
        $dsl_score = $reflection->newInstanceWithoutConstructor();
        // set private properties of dsl_score
        $property = $reflection->getProperty('score_item');
        $property->setAccessible(true);
        $property->setValue($dsl_score, $score_item);
        $property = $reflection->getProperty('course_module');
        $property->setAccessible(true);
        $property->setValue($dsl_score, $cm_other_format);
        $property = $reflection->getProperty('user_id');
        $property->setAccessible(true);
        $property->setValue($dsl_score, $this->user->id);

        // set completion_info mock
        $property = $reflection->getProperty('completion_info');
        $property->setAccessible(true);
        $property->setValue($dsl_score, completion_info_mock::class);

        // set parameters for completion_info mock
        completion_info_mock::reset_data();
        completion_info_mock::set_returns('is_enabled', [$data['completion_enabled']]);
        completion_info_mock::set_returns('get_data', [(object)['completionstate' => $data['completion_state']]]);


        // call method
        try {
            $result = $dsl_score->get_score();
        } catch (Throwable $e) {
            $this->assertEquals($data['expect_exception'], get_class($e));
            if ($data['expect_exception_message'] !== null) {
                $this->assertStringContainsString($data['expect_exception_message'], $e->getMessage());
            }
            return;
        }

        $this->assertEquals($data['expect_score'] == 1 ? $score_item->score_max : 0, $result);

    }

//    public function setUp(): void {
//        parent::setUp();
//
//        // cleanup after every test
//        $this->resetAfterTest(true);
//
//        // Create a course.
//        $this->course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
//        $this->course_without_dsl_data = $this->getDataGenerator()->create_course();
//        $this->adler_course = $this->getDataGenerator()->get_plugin_generator('local_adler')->create_adler_course_object($this->course->id);
//
//        // Create user.
//        $this->user = $this->getDataGenerator()->create_user();
//        // Set current user. Required for h5p generator and completion->update_state (as default value).
//        $this->setUser($this->user);
//
//        // Create primitive modules.
//        $this->module_db_format = $this->getDataGenerator()->create_module('url', ['course' => $this->course->id, 'completion' => 1]);
//        $this->module = get_fast_modinfo($this->course->id, 0, false)->get_cm($this->module_db_format->cmid);
//
//        $this->module_without_dsl_data_db_format = $this->getDataGenerator()->create_module('url', ['course' => $this->course_without_dsl_data->id]);
//        $this->module_without_dsl_data = get_fast_modinfo($this->course_without_dsl_data->id, 0, false)->get_cm($this->module_without_dsl_data_db_format->cmid);
//
//        // Create score (dsl) item for primitive module.
//        $this->score_item_primitive = $this->getDataGenerator()->get_plugin_generator('local_adler')->create_dsl_score_item($this->module->id);
//    }
//
//    /** runs after every test */
//    public function tearDown(): void {
//        parent::tearDown();
//        // Moodle thinks debugging messages should be tested (check for debugging messages in unit tests).
//        // Imho this is very bad practice, because devs should be encouraged to provide additional Information
//        // for debugging. Checking for log messages in tests provides huge additional effort (eg tests will fail because
//        // a message was changed / an additional one was added / ...). Because logging should NEVER affect the
//        // functionality of the code, this is completely unnecessary. Where this leads can be perfectly seen in all
//        // moodle code: Things work or do not work and there is no feedback on that. Often things return null if successfully
//        // and if nothing happened (also categorized as successful), but no feedback is available which of both cases happened.
//        // Users and devs very often can't know why something does not work.
//        // If something went wrong either the code should handle the problem or it should throw an exception.
//        $this->resetDebugging();
//    }
//
//    public function test_create_dsl_score_with_wrong_course_format() {
//        $this->expectException('coding_exception');
//        $this->expectExceptionMessage('course_module_format_not_valid');
//
//        new dsl_score($this->module_db_format, $this->user->id);
//    }
//
//    public function test_user_not_enrolled_in_course() {
//        $this->expectException('moodle_exception');
//        $this->expectExceptionMessage('local_adler/user_not_enrolled');
//
//        new dsl_score($this->module, $this->user->id);
//    }
//
//    public function test_get_score_for_primitive_learning_element() {
//        // enroll user in course
//        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
//
//        $dsl_score = new dsl_score($this->module, $this->user->id);
//        $completion = new completion_info($this->course);
//
//        // test empty submission
//        $this->assertEquals(0, $dsl_score->get_score());
//
//        // test completion entry exists with entry: false
//        $completion->update_state($this->module, COMPLETION_INCOMPLETE);
//        $this->assertEquals(0, $dsl_score->get_score());
//
//        // test completion entry exists with entry: true
//        $completion->update_state($this->module, COMPLETION_COMPLETE);
//        $this->assertEquals($this->score_item_primitive->score_max, $dsl_score->get_score());
//    }
//
//    public function test_get_score_for_primitive_learning_element_fail() {
//        // enroll user in course
//        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
//
//        // create module with completion disabled
//        $module = $this->getDataGenerator()->create_module('url', ['course' => $this->course->id, 'completion' =>0]);
//        $module = get_fast_modinfo($this->course->id, 0, false)->get_cm($module->cmid);
//
//        // Create score (dsl) item.
//        $score_module = $this->getDataGenerator()->get_plugin_generator('local_adler')->create_dsl_score_item($module->id);
//
//
//        // prepare dsl_score object
//        $dsl_score = new dsl_score($module, $this->user->id);
//
//        // expect exception
//        $this->expectException('moodle_exception');
//        $this->expectExceptionMessage('local_adler/completion_not_enabled');
//
//        // call CUD
//        $dsl_score->get_score();
//    }
//
//    public function test_get_score_for_primitive_learning_element_with_global_USER_obejct() {
//        // enroll user in course
//        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
//
//        // initialize user object
//        global $USER, $DB;
//        $USER = $DB->get_record('user', ['id' => $this->user->id]);
//        // Don't be strict about output for this test. completionlib is using deprecated functions. I can not change this.
//        $this->expectOutputRegex('/.*/');
//
//        $dsl_score = new dsl_score($this->module);
//
//        // test empty submission
//        $this->assertEquals(0, $dsl_score->get_score());
//    }
//
//    public function test_get_score_for_primitive_learning_element_no_dsl_metadata() {
//        $this->getDataGenerator()->enrol_user($this->user->id, $this->course_without_dsl_data->id, 'student');
//
//        $this->expectException('moodle_exception');
//        $this->expectExceptionMessage('local_adler/not_an_adler_course');
//
//        $dsl_score = new dsl_score($this->module_without_dsl_data, $this->user->id);
//    }
//
    /** h5p attempt generator is not calculating the scaled attribute.
     * When accessing h5pactivity_attempts it's not using the rawscore field,
     * but instead calculates the scaled value (maxscore * scaled), making this field required for tests.
     * This method works around this issue by calculating the redundant "scaled" field for all existing attempts.
     *
     * Note that this method does not set/update gradebook entries.
     */
    private function fix_scaled_attribute_of_h5pactivity_attempts() {
        global $DB;

        $attempts = $DB->get_records('h5pactivity_attempts');
        foreach ($attempts as $attempt) {
            $attempt->scaled = $attempt->rawscore / $attempt->maxscore;
            $DB->update_record('h5pactivity_attempts', $attempt);
        }
    }

    /**
     * @medium
     */
    public function test_get_score_for_h5p_learning_element() {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');


        // set current user (required by h5p generator)
        $this->setUser($this->user);


        // create h5p activity
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_h5pactivity');
        $cm = $generator->create_instance(array(
            'course' => $this->course->id,
        ));
        $cm_other_format = get_fast_modinfo($this->course->id, 0, false)->get_cm($cm->cmid);

        // Create score (dsl) item.
        $score_item_h5p = $this->getDataGenerator()
            ->get_plugin_generator('local_adler')
            ->create_dsl_score_item($cm_other_format->id, [], false);


        // create dsl_score object and set private properties
        $reflection = new ReflectionClass(dsl_score::class);
        // create dsl_score without constructor
        $dsl_score = $reflection->newInstanceWithoutConstructor();
        // set private properties of dsl_score
        $property = $reflection->getProperty('score_item');
        $property->setAccessible(true);
        $property->setValue($dsl_score, $score_item_h5p);
        $property = $reflection->getProperty('course_module');
        $property->setAccessible(true);
        $property->setValue($dsl_score, $cm_other_format);
        $property = $reflection->getProperty('user_id');
        $property->setAccessible(true);
        $property->setValue($dsl_score, $this->user->id);


        // test no attempt
        // call method
        $result = $dsl_score->get_score();
        $this->assertEquals($result, 0);


        // Test with attempts.

        // create grader
        $grader = new grader($cm);

        // array with test data for attempts with different maxscores and rawscores
        $test_data = [
            ['maxscore' => 100, 'rawscore' => 0, 'expected_score' => 0],
            ['maxscore' => 100, 'rawscore' => 100, 'expected_score' => 100],
            ['maxscore' => 100, 'rawscore' => 50, 'expected_score' => 50],
            ['maxscore' => 50, 'rawscore' => 0, 'expected_score' => 0],
            ['maxscore' => 50, 'rawscore' => 50, 'expected_score' => 100],
            ['maxscore' => 50, 'rawscore' => 25, 'expected_score' => 50],
            ['maxscore' => 200, 'rawscore' => 0, 'expected_score' => 0],
            ['maxscore' => 200, 'rawscore' => 200, 'expected_score' => 100],
            ['maxscore' => 200, 'rawscore' => 100, 'expected_score' => 50],
        ];

        // test attempts with different maxscores and rawscores
        foreach ($test_data as $data) {
            // Create h5p attempt
            $params = [
                'h5pactivityid' => $cm->id,
                'userid' => $this->user->id,
                'rawscore' => $data['rawscore'],
                'maxscore' => $data['maxscore']
            ];
            $generator->create_attempt($params);
            $this->fix_scaled_attribute_of_h5pactivity_attempts();

            // Create grade entry (grade_grades)
            $grader->update_grades();

            // check result
            $this->assertEquals(round($data['expected_score'], 3), round($dsl_score->get_score(), 3));
        }


        // test invalid rawscore
        $params = [[
            'h5pactivityid' => $cm->id,
            'userid' => $this->user->id,
            'rawscore' => -1,
            'maxscore' => 100
        ], [
            'h5pactivityid' => $cm->id,
            'userid' => $this->user->id,
            'rawscore' => 101,
            'maxscore' => 100
        ]];
        // use indexed loop
        for ($i = 0; $i < count($params); $i++) {
            $generator->create_attempt($params[$i]);
            $this->fix_scaled_attribute_of_h5pactivity_attempts();

            // Create grade entry (grade_grades)
            $grader->update_grades();

            // check result
            $this->assertEquals($i == 0 ? 0 : $params[$i]['maxscore'], $dsl_score->get_score());
        }
    }

    public function test_calculate_percentage_achieved() {
        // test setup
        // create dsl_score object without constructor call
        $dsl_score = $this->getMockBuilder(dsl_score::class)
            ->disableOriginalConstructor()
            ->getMock();

        // make calculate_percentage_achieved public
        $reflection = new ReflectionClass(dsl_score::class);
        $method = $reflection->getMethod('calculate_percentage_achieved');
        $method->setAccessible(true);

        // enroll user
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');

        // test data
        $test_data = [
            ['min' => 0, 'max' => 100, 'value' => 0, 'expected' => 0],
            ['min' => 0, 'max' => 100, 'value' => 50, 'expected' => .5],
            ['min' => 0, 'max' => 100, 'value' => 100, 'expected' => 1],
            ['min' => 10, 'max' => 20, 'value' => 10, 'expected' => 0],
            ['min' => 10, 'max' => 20, 'value' => 15, 'expected' => .5],
            ['min' => 10, 'max' => 20, 'value' => 20, 'expected' => 1],
            ['min' => 0, 'max' => 100, 'value' => -1, 'expected' => 0],
            ['min' => 0, 'max' => 100, 'value' => 101, 'expected' => 1],
        ];

        // test
        foreach ($test_data as $data) {
            $result = $method->invokeArgs($dsl_score, [$data['value'], $data['max'], $data['min']]);
            $this->assertEquals($data['expected'], $result);
        }
    }
//
//    private function generate_test_data_list_access_default() {
//        ////  create h5p test activities with attempts and dsl score entries
//        // Create 3 h5p activities
//        $generator = $this->getDataGenerator()->get_plugin_generator('mod_h5pactivity');
//        $cms = [
//            $generator->create_instance(['course' => $this->course->id]),
//            $generator->create_instance(['course' => $this->course->id]),
//            $generator->create_instance(['course' => $this->course->id])
//        ];
//
//        // create attempts for each h5p activity
//        $attempts = [
//            $generator->create_attempt([
//                'h5pactivityid' => $cms[0]->id,
//                'userid' => $this->user->id,
//                'rawscore' => 100,
//                'maxscore' => 100
//            ]),
//            $generator->create_attempt([
//                'h5pactivityid' => $cms[1]->id,
//                'userid' => $this->user->id,
//                'rawscore' => 50,
//                'maxscore' => 100
//            ]),
//            $generator->create_attempt([
//                'h5pactivityid' => $cms[2]->id,
//                'userid' => $this->user->id,
//                'rawscore' => 0,
//                'maxscore' => 100
//            ]),
//        ];
//        $this->fix_scaled_attribute_of_h5pactivity_attempts();
//        // Create grade entry (grade_grades)
//        foreach ($cms as $cm) {
//            $grader = new grader($cm);
//            $grader->update_grades();
//        }
//
//        // create dsl score entries
//        $dsl_score_entries = [
//            $this->getDataGenerator()->get_plugin_generator('local_adler')->create_dsl_score_item($cms[0]->cmid, ['score_max' => 10]),
//            $this->getDataGenerator()->get_plugin_generator('local_adler')->create_dsl_score_item($cms[1]->cmid, ['score_max' => 10]),
//            $this->getDataGenerator()->get_plugin_generator('local_adler')->create_dsl_score_item($cms[2]->cmid, ['score_max' => 10])
//        ];
//
//
//        //// add primitive activity to test data
//        $cms[] = $this->module_db_format;
//        // add dsl_score_entry
//        $dsl_score_entries[] = $this->score_item_primitive;
//        // set $this->module to success
//        $completion = new completion_info($this->course);
//        $completion->update_state($this->module, COMPLETION_COMPLETE);
//
//
//        //// expected result
//        $expected = [
//            $cms[0]->cmid => 10.,
//            $cms[1]->cmid => 5.,
//            $cms[2]->cmid => 0.
//        ];
//        $expected[$this->module_db_format->cmid] = 100;
//
//
//        //// prepare CUT call: list with cmids
//        $cmids = array_map(function ($cm) {
//            return $cm->cmid;
//        }, $cms);
//
//        return [$cmids, $expected];
//    }
//
//    }
//
//    public function test_get_achieved_scores() {
//        // enroll user
//        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
//
//
//        //// test data
//        list($cmids, $expected) = $this->generate_test_data_list_access_default();
//
//
//        //// call CUT
//        $result = dsl_score::get_achieved_scores($cmids);
//
//
//        //// check result
//        $this->assertEquals(count($cmids), count($result));
//        for ($i = 0; $i < count($cmids); $i++) {
//            $this->assertEquals($expected[$cmids[$i]], $result[$cmids[$i]]);
//        }
//    }
//
//    private function generate_test_data_list_access_not_enrolled() {
//        // testcase user not enrolled (and without dsl)
//        $cmids[] = $this->module_without_dsl_data_db_format->cmid;
//        $expected[$this->module_without_dsl_data_db_format->cmid] = false;
//
//        return [$cmids, $expected];
//    }
//
//    public function test_get_dsl_score_objects_exception() {
//        // enroll user
//        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
//
//        // test setup
//        list($cmids, $expected) = $this->generate_test_data_list_access_not_enrolled();
//
//        // exception expected
//        $this->expectException('moodle_exception');
//
//        // test
//        dsl_score::get_dsl_score_objects([$cmids[0]]);  // only testcase user not enrolled
//    }
//
//
////    public function test_get_achieved_scores_with_false_return() {
////        // enroll user in course
////        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
////
////        // case: completion not enabled
////        // create module with completion disabled
////        $module = $this->getDataGenerator()->create_module('url', ['course' => $this->course->id, 'completion' =>0]);
////        $module = get_fast_modinfo($this->course->id, 0, false)->get_cm($module->cmid);
////
////        // Create score (dsl) item.
////        $score_module = $this->getDataGenerator()->get_plugin_generator('local_adler')->create_dsl_score_item($module->id);
////
////        $cmids[] = $module->id;
////
////
////        // case: testcase without dsl
////        $module_no_dsl = $this->getDataGenerator()->create_module('url', ['course' => $this->course->id]);
////        $cmids[] = $module_no_dsl->cmid;
////
////
////        // call CUT
////        $result = dsl_score::get_achieved_scores($cmids);
////
////        // check result
////        $this->assertEquals(count($cmids), count($result));
////        for ($i = 0; $i < count($cmids); $i++) {
////            $this->assertFalse($result[$cmids[$i]]);
////        }
////    }
//
//    public function test_get_achieved_scores_exception_not_enrolled() {
//        // prepare test data
//        list($cmids, $expected) = $this->generate_test_data_list_access_not_enrolled();
//
//        // exception expected
//        $this->expectException('moodle_exception');
//        $this->expectExceptionMessage('user_not_enrolled');
//
//        // call CUT
//        dsl_score::get_achieved_scores($cmids);
//    }
//
//    /**
//     * This test is currently not really testable, because this can only happen if $DB->get_record() inside get_score
//     * throws an exception. Most cases where this could happen are prevented by other checks. One possible case could
//     * be database is down. But this is not testable.
//     * TODO: should be easier once DB queries are abstracted into helpers.
//     */
////    public function test_get_achieved_scores_exception() {
////        // enroll user in course
////        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
////
////        // create module with completion disabled
////        $module = $this->getDataGenerator()->create_module('url', ['course' => $this->course->id, 'completion' =>0]);
////        $module = get_fast_modinfo($this->course->id, 0, false)->get_cm($module->cmid);
////
////        // Create score (dsl) item.
////        $score_module = $this->getDataGenerator()->get_plugin_generator('local_adler')->create_dsl_score_item($module->id);
////
////        // exception expected
////        $this->expectException('moodle_exception');
////
////        // test
////        dsl_score::get_achieved_scores([$module->id]);  // only testcase user not enrolled
////    }
}
