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
}
