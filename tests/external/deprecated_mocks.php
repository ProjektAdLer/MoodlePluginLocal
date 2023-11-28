<?php

/** This file contains mocks from old mocking approaches of static stuff.
 * Don't use them anymore in new tests. Use Mockery instead.
 */

namespace local_adler\external;

use local_adler\adler_score;
use local_adler\adler_score_helpers_mock;

require_once(__DIR__ . '/generic_mocks.php');

/**
 * @deprecated use Mockery instead
 */
class score_get_element_scores_mock extends score_get_element_scores {
    use external_api_validate_context_trait;

    protected static string $adler_score_helpers = adler_score_helpers_mock::class;
    protected static string $context_module = context_module_mock::class;
}

/**
 * @deprecated use Mockery instead
 */
class mock_score_primitive_learning_element extends score_primitive_learning_element {
    private static $index = 0;
    private static $data = array();

    public static function set_data(array $data) {
        self::$data = $data;
        self::$index = 0;
    }

    protected static function create_adler_score_instance($course_module): adler_score {
        self::$index += 1;
        return self::$data[self::$index - 1];
    }

    public static function call_create_adler_score_instance($course_module): adler_score {
        // call protected function
        return parent::create_adler_score_instance($course_module);
    }
}
