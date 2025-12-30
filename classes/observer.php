<?php
namespace local_automation;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function user_enrolled(\core\event\user_enrolment_created $event) {
        global $DB;

        $userid   = $event->relateduserid;
        $courseid = $event->courseid;

        // Debug log
        error_log("Automation plugin: User {$userid} enrolled in course {$courseid}");

        // Get user info
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        // Call SMTP service
        \local_automation\utils::send_email(
            $user->email,
            'Course enrolment successful',
            "Hi {$user->firstname},\n\nYou have been successfully enrolled in your course."
        );
    }

    public static function course_completed(\core\event\course_completed $event) {
        global $DB;

        $userid   = $event->relateduserid;
        $courseid = $event->courseid;

        error_log("Automation plugin: TRIGGERED course_completed for User {$userid} in Course {$courseid}");

        // Get user info
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Call SMTP service
        \local_automation\utils::send_email(
            $user->email,
            'Congratulations on completing ' . $course->fullname,
            "Hi {$user->firstname},\n\nCongratulations! You have successfully completed the course: {$course->fullname}."
        );
    }

    public static function module_completed(\core\event\course_module_completion_updated $event) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $userid = $event->relateduserid;
        $courseid = $event->courseid;

        error_log("Automation plugin: Activity completed by User {$userid} in Course {$courseid}. Checking course completion...");

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $completion = new \completion_info($course);

        // Check if the course is now complete
        if ($completion->is_course_complete($userid)) {
            error_log("Automation plugin: Course {$courseid} detected as COMPLETE for User {$userid}.");

            // Avoid duplicate emails if the course_completed event also fires later
            
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

            \local_automation\utils::send_email(
                $user->email,
                'Congratulations on completing ' . $course->fullname,
                "Hi {$user->firstname},\n\nCongratulations! You have successfully completed the course: {$course->fullname}."
            );
        }
    }

}
