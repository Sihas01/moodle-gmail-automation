<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback'  => '\local_automation\observer::user_enrolled',
    ],
    [
        'eventname' => '\core\event\course_completed',
        'callback'  => '\local_automation\observer::course_completed',
    ],
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback'  => '\local_automation\observer::module_completed',
    ],
];
