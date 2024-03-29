<?php

namespace local_adler;


use coding_exception;
use completion_info;
use context_course;
use dml_exception;
use local_adler\local\exceptions\user_not_enrolled_exception;
use local_logging\logger;
use moodle_exception;
use stdClass;

/**
 * Managing adler score system for one course module
 */
class adler_score {
    private logger $logger;
    private object $course_module;

    private int $user_id;
    protected stdClass $score_item;

    protected static string $helpers = helpers::class;

    protected static string $completion_info = completion_info::class;

    protected static string $adler_score_helpers = adler_score_helpers::class;

    /**
     * @param object $course_module
     * @param int|null $user_id If null, the current user will be used
     * @throws user_not_enrolled_exception
     * @throws moodle_exception course_module_format_not_valid, not_an_adler_cm, course_not_adler_course
     */
    public function __construct(object $course_module, int $user_id = null) {
        $this->logger = new logger('local_adler', 'adler_score');

        $this->course_module = $course_module;

        if ($user_id === null) {
            global $USER;
            $this->user_id = $USER->id;
        } else {
            $this->user_id = $user_id;
        }

        // validate correct course_module format
        if (!isset($this->course_module->modname)) {
            $this->logger->debug('Moodle hast different course_module formats. ' .
                'The DB-Format and the one returned by get_coursemodule_from_id().' .
                ' They are incompatible and only the last one is currently supported by this method.');
            $this->logger->debug('Support for DB format can be implemented if required,' .
                ' the required fields are existing there with different names.');
            $this->logger->error('course_module_format_not_valid');
            throw new coding_exception('course_module_format_not_valid', 'local_adler');
        }

        // validate user is enrolled in course
        $course_context = context_course::instance($this->course_module->course);
        if (!is_enrolled($course_context, $this->user_id)) {
            throw new user_not_enrolled_exception();
        }

        // validate course is adler course
        if (!static::$helpers::course_is_adler_course($this->course_module->course)) {
            throw new moodle_exception('not_an_adler_course', 'local_adler');
        }

        // get adler score metadata object
        // throws not_an_adler_cm exception if not adler course module
        $this->score_item = static::$adler_score_helpers::get_adler_score_record($this->course_module->id);
    }

    /** Calculates the score based on the percentage the user has achieved
     * @param float $max_score The maximum score that can be achieved.
     * @param float $percentage_achieved As float value between 0 and 1
     */
    private static function calculate_score(float $max_score, float $percentage_achieved): float {
        if ($percentage_achieved === 1.) {
            return $max_score;
        }
        return 0.;
    }

    /** Calculate percentage achieved between $min and $max
     * @param float $min
     * @param float $max
     * @param float $value
     * @return float as float value between 0 and 1
     */
    private static function calculate_percentage_achieved(float $value, float $max, float $min = 0): float {
        // This approach is also used by gradebook.
        if ($value > $max) {
            $value = $max;
        }
        if ($value < $min) {
            $value = $min;
        }
        return ($value - $min) / ($max - $min);
    }

    /** Get course_module id
     * @return int
     */
    public function get_cmid(): int {
        return $this->course_module->id;
    }


    /** Calculates the achieved score for the course module if it is of type h5p.
     * There is no type checking, calling this method for a course module that
     * is not of type h5p will result in an error.
     * @return float
     */
    private function get_h5p_score(): float {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        // get h5p result


        $grading_info = grade_get_grades($this->course_module->course, 'mod', 'h5pactivity', $this->course_module->instance, $this->user_id);
        $grading_info = $grading_info->items[0];

        if ($grading_info->grades[$this->user_id]->grade === null) {
            $this->logger->debug('h5p grade not found, probably the user has not submitted the h5p activity yet -> assuming 0%');
            $relative_grade = 0;
        } else {
            $relative_grade = static::calculate_percentage_achieved(
                $grading_info->grades[$this->user_id]->grade,
                $grading_info->grademax,
                $grading_info->grademin
            );
        }
        return self::calculate_score($this->score_item->score_max, $relative_grade);
    }

    /** Calculates the achieved score for a primitive course module. There is no type checking, calling this method
     * for a course module that is not a primitive cm will result in a wrong score or an error.
     * @return float
     * @throws moodle_exception
     */
    private function get_primitive_score(): float {
        // get completion object
        $course = static::$helpers::get_course_from_course_id($this->course_module->course);
        $completion = new static::$completion_info($course);

        // check if completion is enabled for this course_module
        if (!$completion->is_enabled($this->course_module)) {
            throw new moodle_exception('completion_not_enabled', 'local_adler');
        }

        // get completion status
        $completion_status = (float)$completion->get_data($this->course_module, false, $this->user_id)->completionstate;

        // completionstate has multiple options, not just COMPLETE and INCOMPLETE
        $is_completed_successfully = $completion_status == COMPLETION_COMPLETE || $completion_status == COMPLETION_COMPLETE_PASS;

        return self::calculate_score($this->score_item->score_max, $is_completed_successfully);
    }

    /** Get the score for the course module.
     * Gets the completion status and for h5p activities the achieved grade and calculates the adler score with the values from
     * local_adler_course_modules.
     * @throws dml_exception|moodle_exception
     */
    public function get_score(): float {
        // if course_module is a h5p activity, get achieved grade
        if ($this->course_module->modname == 'h5pactivity') {
            return $this->get_h5p_score();
        }

        // if course_module is not a h5p activity, get completion status
        $this->logger->debug('course_module is either a primitive or an unsupported complex activity');

        return $this->get_primitive_score();
    }
}
