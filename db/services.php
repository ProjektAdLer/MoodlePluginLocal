<?php
defined('MOODLE_INTERNAL') || die();

$services = array(
    'adler_services' => array(                                                // the name of the web service
        'functions' => array(
            'core_course_get_contents',
            'core_course_search_courses',
            'core_xapi_statement_post',# (Wird aber bald von deinem plugin ersetzt bitte trotzdem rein machen)
            'mod_h5pactivity_get_attempts',# (Wird aber bald von deinem plugin ersetzt bitte trotzdem rein machen)
            'format_tiles_update_activity_completion_status_manually',# (Wird aber bald von deinem plugin ersetzt bitte trotzdem rein machen)
            'core_webservice_get_site_info',
            'core_user_get_users_by_field',
            'core_course_delete_courses',
            'local_adler_get_moodle_ids_by_uuids',
            'local_adler_upload_course', # temporär, da dieser service aktuell noch nicht im main branch ist, aber trotzdem durch einen merge in den feature branch auf die whitelist soll
        ), // web service functions of this service
        'requiredcapability' => '',                // if set, the web service user need this capability to access
        // any function of this service. For example: 'some/capability:specified'
        'restrictedusers' => 0,                                             // if enabled, the Moodle administrator must link some user to this service
        // into the administration
        'enabled' => 1,                                                       // if enabled, the service can be reachable on a default installation
        'shortname' => 'adler_services',       // optional – but needed if restrictedusers is set so as to allow logins.
        'downloadfiles' => 0,    // allow file downloads.
        'uploadfiles' => 0,      // allow file uploads.
        'loginrequired' => true
    )
);

$functions = array(
    'local_adler_score_primitive_learning_element' => array(         //web service function name
        'classname' => 'local_adler\external\score_primitive_learning_element',  //class containing the external function OR namespaced class in classes/external/XXXX.php
        'description' => 'Submit result for primitive learning elements (completed/not completed)',    //human readable description of the web service function
        'type' => 'write',                  //database rights of the web service function (read, write)
        'ajax' => false,        // is the service available to 'internal' ajax calls.
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'adler_services'),   // Optional, only available for Moodle 3.1 onwards. List of built-in services (by shortname) where the function will be included.  Services created manually via the Moodle interface are not supported.
        'capabilities' => '', // comma separated list of capabilities used by the function.
        'loginrequired' => true
    ),
    'local_adler_score_h5p_learning_element' => array(
        'classname' => 'local_adler\external\score_h5p_learning_element',
        'description' => 'Submit result for h5p. This is just a proxy function and forwards its payload to {"wsfunction", "core_xapi_statement_post"}, {"component", "mod_h5pactivity"}, {"requestjson", "[" + statement + "]"}',    //human readable description of the web service function
        'type' => 'write',
        'ajax' => false,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'adler_services'),
        'capabilities' => '',
        'loginrequired' => true
    ),
    'local_adler_score_get_element_scores' => array(
        'classname' => 'local_adler\external\score_get_element_scores',  //class containing the external function OR namespaced class in classes/external/XXXX.php
        'description' => 'Get scores (adler) for learning elements with given ids',
        'type' => 'read',                  //database rights of the web service function (read, write)
        'ajax' => false,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'adler_services'),
        'capabilities' => '', // comma separated list of capabilities used by the function.
        'loginrequired' => true
    ),
    'local_adler_score_get_course_scores' => array(
        'classname' => 'local_adler\external\score_get_course_scores',  //class containing the external function OR namespaced class in classes/external/XXXX.php
        'description' => 'Get scores (adler) for all elements inside course with given course id',    //human readable description of the web service function
        'type' => 'read',                  //database rights of the web service function (read, write)
        'ajax' => false,        // is the service available to 'internal' ajax calls.
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'adler_services'),   // Optional, only available for Moodle 3.1 onwards. List of built-in services (by shortname) where the function will be included.  Services created manually via the Moodle interface are not supported.
        'capabilities' => '', // comma separated list of capabilities used by the function.
        'loginrequired' => true
    ),
    'local_adler_upload_course' => array(
        'classname' => 'local_adler\external\upload_course',  //class containing the external function OR namespaced class in classes/external/XXXX.php
        'description' => 'Upload adler course (as mbz file)',    //human readable description of the web service function
        'type' => 'write',                  //database rights of the web service function (read, write)
        'ajax' => false,        // is the service available to 'internal' ajax calls.
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),   // Optional, only available for Moodle 3.1 onwards. List of built-in services (by shortname) where the function will be included.  Services created manually via the Moodle interface are not supported.
        'capabilities' => '', // comma separated list of capabilities used by the function.
        'loginrequired' => true
    ),
    'local_adler_get_element_ids_by_uuids' => array(
        'classname' => 'local_adler\external\get_element_ids_by_uuids',  //class containing the external function OR namespaced class in classes/external/XXXX.php
        'description' => 'Returns context and database ids for sections / course modules with given UUIDs and course ids',    //human readable description of the web service function
        'type' => 'read',                  //database rights of the web service function (read, write)
        'ajax' => false,        // is the service available to 'internal' ajax calls.
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),   // Optional, only available for Moodle 3.1 onwards. List of built-in services (by shortname) where the function will be included.  Services created manually via the Moodle interface are not supported.
        'capabilities' => '', // comma separated list of capabilities used by the function.
        'loginrequired' => true
    ),
);
















