<?php
/**
 * Library functions for the local_automation plugin.
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Handle user enrolment event.
 */
function local_automation_user_enrolled(\core\event\user_enrolment_created $event) {
    global $SESSION;

    // Store course id in session
    $SESSION->local_automation_enrolled_course = $event->courseid;
}

/**enrolment
 * Extend navigation for a course.
 */
function local_automation_extend_navigation_course($navigation, $course, $context) {
    global $SESSION;

    if (!empty($SESSION->local_automation_enrolled_course)
        && $SESSION->local_automation_enrolled_course == $course->id) {

        \core\notification::success(
            'You are successfully enrolled in this course!'
        );

        unset($SESSION->local_automation_enrolled_course);
    }
}
