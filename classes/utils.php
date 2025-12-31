<?php
namespace local_automation;

defined('MOODLE_INTERNAL') || die();

class utils {

    /**
     * Send an email via the external SMTP service.
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     * @return bool
     */
    public static function send_email($to, $subject, $message) {
        $url = 'http://127.0.0.1:5000/send-email';
        $apiKey = 'f9d8s7f9s8df7sdf9s8df7sdf';

        $data = json_encode([
            'to' => $to,
            'subject' => $subject,
            'message' => $message
        ]);

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nX-API-Key: $apiKey\r\n",
                'content' => $data,
                'timeout' => 5
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            error_log("Automation plugin: Failed to send email to {$to}.");
            return false;
        }

        return true;
    }

    /**
     * Get recommended courses based on learning path custom fields.
     */
    public static function get_next_recommended_courses($courseid, $userid) {
        global $DB;

        // Get custom field data using Moodle API.
        $learning_path = null;
        $path_order = null;

        try {
            $handler = \core_customfield\handler::get_handler('core_course', 'course');
            if ($handler) {
                $instance_data = $handler->get_instance_data($courseid);
                foreach ($instance_data as $data) {
                    $field = $data->get_field();
                    $shortname = $field->get('shortname');
                    
                    if ($shortname === 'learning_path') {
                        $learning_path = $data->get_value();
                    } else if ($shortname === 'path_order') {
                        $path_order = $data->get_value();
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Automation plugin: Error using Custom Field API: " . $e->getMessage());
            // Fallback to manual SQL if API fails for some reason
            $sql = "SELECT f.shortname, d.value, d.intvalue, d.charvalue
                      FROM {customfield_field} f
                      JOIN {customfield_data} d ON d.fieldid = f.id
                     WHERE d.instanceid = :courseid
                       AND f.shortname IN ('learning_path', 'path_order')";
            $results = $DB->get_records_sql($sql, ['courseid' => $courseid]);
            foreach ($results as $res) {
                if ($res->shortname === 'learning_path') {
                    $learning_path = $res->value ?: $res->charvalue;
                } else if ($res->shortname === 'path_order') {
                    $path_order = ($res->intvalue !== null && $res->intvalue !== '') ? $res->intvalue : $res->value;
                }
            }
        }

        $learning_path = trim($learning_path);
        $path_order = (int)$path_order;

        error_log("Automation plugin: Course {$courseid} - Path: [{$learning_path}], Order: [{$path_order}]");

        if (empty($learning_path) || $path_order <= 0) {
            error_log("Automation plugin: Insufficient path data for course {$courseid}");
            return [];
        }

        // Find courses with same learning_path.
        // Priority:
        // Same order (excluding current course)
        // Next order (path_order + 1)
        
        $recommendations_sql = "
            SELECT c.id, c.fullname
              FROM {course} c
             WHERE c.visible = 1
               AND c.id <> :currentcourseid
               AND c.id IN (
                   SELECT instanceid FROM {customfield_data} d
                   JOIN {customfield_field} f ON f.id = d.fieldid
                   WHERE f.shortname = 'learning_path' AND d.value = :learning_path
               )
               AND c.id IN (
                   SELECT instanceid FROM {customfield_data} d
                   JOIN {customfield_field} f ON f.id = d.fieldid
                   WHERE f.shortname = 'path_order' AND (d.intvalue = :target_order OR d.value = :target_order_str)
               )
               AND NOT EXISTS (
                   SELECT 1 FROM {enrol} e 
                   JOIN {user_enrolments} ue ON ue.enrolid = e.id
                   WHERE e.courseid = c.id AND ue.userid = :userid
               )
        ";

        // Try SAME order.
        $params = [
            'learning_path' => $learning_path,
            'target_order' => $path_order,
            'target_order_str' => (string)$path_order,
            'userid' => $userid,
            'currentcourseid' => $courseid
        ];

        try {
            $results = $DB->get_records_sql($recommendations_sql, $params);
            
            // If no same-order courses found, try NEXT order.
            if (empty($results)) {
                $next_order = $path_order + 1;
                $params['target_order'] = $next_order;
                $params['target_order_str'] = (string)$next_order;
                $results = $DB->get_records_sql($recommendations_sql, $params);
                error_log("Automation plugin: Found " . count($results) . " recommended courses for User {$userid} in NEXT order ({$next_order})");
            } else {
                error_log("Automation plugin: Found " . count($results) . " recommended courses for User {$userid} in SAME order ({$path_order})");
            }
            
            return $results;
        } catch (\Exception $e) {
            error_log("Automation plugin: SQL Error in recommendation query: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Send recommendation email to student.
     */
    public static function send_recommendation_email($user, $courses) {
        global $CFG;

        if (empty($courses)) {
            return;
        }

        $course_list = "";
        foreach ($courses as $course) {
            $courseurl = "{$CFG->wwwroot}/course/view.php?id={$course->id}";
            $course_list .= "- {$course->fullname}: {$courseurl}\n";
        }

        $subject = 'Recommended next course for you';
        $message = "Hi {$user->firstname},\n\nBased on your progress, we recommend the following next course(s) for you:\n\n{$course_list}";

        self::send_email($user->email, $subject, $message);
    }
}
