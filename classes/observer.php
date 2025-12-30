<?php
namespace local_automation;

defined('MOODLE_INTERNAL') || die();

class observer {
    /** @var array Track sent completion emails to prevent duplicates in the same request */
    protected static $sentnotifications = [];

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

        // Prevent duplicates in same request
        if (!empty(self::$sentnotifications["{$userid}_{$courseid}"])) {
            return;
        }

        error_log("Automation plugin: TRIGGERED course_completed for User {$userid} in Course {$courseid}");

        // Get user info
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Mark as sent
        self::$sentnotifications["{$userid}_{$courseid}"] = true;

        // Call SMTP service
        \local_automation\utils::send_email(
            $user->email,
            'Congratulations on completing ' . $course->fullname,
            "Hi {$user->firstname},\n\nCongratulations! You have successfully completed the course: {$course->fullname}."
        );

        // Notify teachers
        self::notify_teachers($user, $course);
    }

    public static function module_completed(\core\event\course_module_completion_updated $event) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $userid = $event->relateduserid;
        $courseid = $event->courseid;

        // Prevent duplicates in same request
        if (!empty(self::$sentnotifications["{$userid}_{$courseid}"])) {
            return;
        }

        error_log("Automation plugin: Activity completed by User {$userid} in Course {$courseid}. Checking course completion...");

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $completion = new \completion_info($course);

        // Check if the course is now complete
        if ($completion->is_course_complete($userid)) {
            error_log("Automation plugin: Course {$courseid} detected as COMPLETE for User {$userid}.");

            // Mark as sent
            self::$sentnotifications["{$userid}_{$courseid}"] = true;
            
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

            \local_automation\utils::send_email(
                $user->email,
                'Congratulations on completing ' . $course->fullname,
                "Hi {$user->firstname},\n\nCongratulations! You have successfully completed the course: {$course->fullname}."
            );

            // Notify teachers
            self::notify_teachers($user, $course);
        }
    }

    /**
     * Notify all teachers in the course about student completion.
     */
    protected static function notify_teachers($student, $course) {
        global $DB;

        $context = \context_course::instance($course->id);
        
        // Get roles that we consider "Teachers"
        $teacherroles = $DB->get_records_list('role', 'shortname', ['editingteacher', 'teacher'], '', 'id');
        if (empty($teacherroles)) {
            return;
        }
        $roleids = array_keys($teacherroles);

        // Get users with these roles in this course context
        $teachers = get_role_users($roleids, $context);

        if ($teachers) {
            foreach ($teachers as $teacher) {
                \local_automation\utils::send_email(
                    $teacher->email,
                    "Student completion: {$student->firstname} {$student->lastname}",
                    "Hi {$teacher->firstname},\n\nA student ({$student->firstname} {$student->lastname}) has successfully completed your course: {$course->fullname}."
                );
            }
        }
    }

}
