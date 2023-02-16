<?php

namespace local_adler;

use core\event\course_content_deleted;
use core\event\course_deleted;
use core\event\course_module_deleted;
use local_adler\lib\local_adler_testcase;


global $CFG;
require_once($CFG->dirroot . '/local/adler/tests/lib/adler_testcase.php');

class observer_test extends local_adler_testcase {

    public function test_course_content_deleted() {#
        global $DB;

        $generator = $this->getDataGenerator();
        $adler_generator = $this->getDataGenerator()->get_plugin_generator('local_adler');

        // create course
        $course = $this->getDataGenerator()->create_course();
        // make courses adler courses
        $adler_generator->create_adler_course_object($course->id);



        // create cms in course
        $modules = [];
        for ($i = 0; $i < 10; $i++) {
            $module = $generator->create_module('url', ['course' => $course->id]);
            // create adler score record
            $adler_generator->create_dsl_score_item($module->cmid);
            $modules[] = $module;
        }


        // create adler scores without cms
        $adler_score_tb_deleted = [];
        for ($i = 0; $i < 10; $i++) {
            $adler_score_tb_deleted[] = $adler_generator->create_dsl_score_item($modules[count($modules) - 1]->cmid + 1 + $i);
        }


        // call function
        // create course_content_deleted mock object
        $event = $this->getMockBuilder(course_content_deleted::class)
            ->disableOriginalConstructor()
            ->getMock();
        observer::course_content_deleted($event);

        // check if all adler score records without cms were deleted
        foreach ($adler_score_tb_deleted as $adler_score) {
            $this->assertEquals(0, count($DB->get_records('local_adler_scores_items', ['cmid' => $adler_score->cmid])));
        }
        // check if other adler score records and cms were not deleted
        foreach ($modules as $module) {
            $this->assertEquals(1, count($DB->get_records('local_adler_scores_items', ['cmid' => $module->cmid])));
            $this->assertEquals(1, count($DB->get_records('course_modules', ['id' => $module->cmid])));
        }
    }


    public function test_course_deleted() {
        global $DB;

        $adler_generator = $this->getDataGenerator()->get_plugin_generator('local_adler');

        // create adler courses
        $adler_generator->create_adler_course_object(7);

        // create mock course_deleted
        $event = $this->getMockBuilder(course_deleted::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->method('__get')->with('objectid')->willReturn(7);


        // call function
        observer::course_deleted($event);

        // check result
        $this->assertEquals(0, count($DB->get_records('local_adler_course', ['course_id' => 7])));
    }

    public function provide_test_course_deleted_data() {
        return [
            'default' => [['case' => 'default']],
            'no adler course' => [['case' => 'no_adler_course']],
            'not adler cms' => [['case' => 'not_adler_cm']],
        ];
    }

    /**
     * @dataProvider provide_test_course_deleted_data
     */
    public function test_course_module_deleted($data) {
        global $DB;

        $generator = $this->getDataGenerator();
        $adler_generator = $this->getDataGenerator()->get_plugin_generator('local_adler');

        // create course
        $course = $this->getDataGenerator()->create_course();

        // create 2 cms in course
        $module1 = $generator->create_module('url', ['course' => $course->id]);
        $module2 = $generator->create_module('url', ['course' => $course->id]);

        if ($data['case'] == 'default' || $data['case'] == 'not_adler_cm') {
            // make course adler course
            $adler_generator->create_adler_course_object($course->id);
        }

        if ($data['case'] == 'default') {
            // create adler score record
            $adler_generator->create_dsl_score_item($module1->cmid);
            $adler_generator->create_dsl_score_item($module2->cmid);
        }

        // create mock course_module_deleted
        $event = $this->getMockBuilder(course_module_deleted::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->method('__get')
            ->withConsecutive(['objectid'], ['courseid'])
            ->willReturnOnConsecutiveCalls($module1->cmid, $course->id);

        // call function
        observer::course_module_deleted($event);

        // check result
        if ($data['case'] == 'default') {
            $this->assertEquals(0, count($DB->get_records('local_adler_scores_items', ['cmid' => $module1->cmid])));
            $this->assertEquals(1, count($DB->get_records('local_adler_scores_items', ['cmid' => $module2->cmid])));
        }
    }


    public function test_delete_non_existent_adler_cms_perf() {
        $this->markTestSkipped('Test performance of the implementation -> no point in running it during regular unit tests execution');

        $generator = $this->getDataGenerator();
        $adler_generator = $this->getDataGenerator()->get_plugin_generator('local_adler');

        // create course
        $course = $this->getDataGenerator()->create_course();
        // make course adler course
        $adler_generator->create_adler_course_object($course->id);



        // create cms in course
        $modules = [];
        $count = 100000;
        for ($i = 0; $i < $count; $i++) {
            // log progress
            if ($i % 100 === 0) {
                fwrite(STDERR, 'setup test data: ' . $i . '/' . $count . PHP_EOL);
            }
            $module = $generator->create_module('url', ['course' => $course->id]);
            // create adler score record
            $adler_generator->create_dsl_score_item($module->cmid);
            $modules[] = $module;
        }


        // create 100 adler scores without cms
        for ($i = 0; $i < 100; $i++) {
            $adler_generator->create_dsl_score_item($modules[count($modules) - 1]->cmid + 1 + $i);
        }

        // call function
        $start = microtime(true);
        observer::delete_non_existent_adler_cms();
        $end = microtime(true);

        // check result
        $this->assertTrue($end - $start < 10);

        // output duration
        fwrite(STDERR, 'duration in s: ' . ($end - $start) . PHP_EOL);
    }


    // Call delete_course() which will trigger the course_deleted event and the course_content_deleted
    // event. This function prints out data to the screen, which we do not want during a PHPUnit test,
    // so use ob_start and ob_end_clean to prevent this.
//ob_start();
//delete_course($course);
}