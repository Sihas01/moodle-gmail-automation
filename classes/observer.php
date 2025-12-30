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

}
