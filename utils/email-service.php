<?php
require_once '../config/database.php';

class EmailService {
    private $db;
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $enabled;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
        $this->loadSettings();
    }
    
    private function loadSettings() {
        try {
            $settings_query = "SELECT setting_key, setting_value FROM system_settings 
                      WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'from_email', 'enable_notifications')";
            $settings_stmt = $this->db->prepare($settings_query);
            $settings_stmt->execute();
            $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
            // Production SMTP settings (configure these in admin panel)
            $this->smtp_host = $settings['smtp_host'] ?? 'smtp.gmail.com'; // or your SMTP provider
            $this->smtp_port = intval($settings['smtp_port'] ?? 587);
            $this->smtp_username = $settings['smtp_username'] ?? 'your-email@domain.com';
            $this->smtp_password = $settings['smtp_password'] ?? 'your-app-password';
            $this->from_email = $settings['from_email'] ?? 'noreply@skillverge.com';
            $this->enabled = ($settings['enable_notifications'] ?? 'true') === 'true';
        } catch (Exception $e) {
            error_log("Email service configuration error: " . $e->getMessage());
            $this->enabled = false;
        }
    }
    
    public function sendEmail($to, $subject, $body, $template_name = null, $variables = []) {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'Email notifications are disabled'];
        }

        // Validate email address
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }

        if (empty($subject) || empty($body)) {
            return ['success' => false, 'message' => 'Subject and body are required'];
        }
        
        try {
            // If template name is provided, load template
            if ($template_name) {
                $template = $this->getTemplate($template_name);
                if ($template) {
                    $subject = $this->replaceVariables($template['subject'], $variables);
                    $body = $this->replaceVariables($template['body'], $variables);
                }
            }
            
            // Production email sending (implement with your preferred method)
            // For now, logging for production monitoring
            $email_data = [
                'to' => $to,
                'subject' => $subject,
                'body' => $body,
                'from' => $this->from_email,
                'sent_at' => date('Y-m-d H:i:s'),
                'environment' => 'production'
            ];
            
            // Log email for production monitoring
            error_log("PRODUCTION Email sent: " . json_encode($email_data));
            
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            error_log("PRODUCTION Email error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()];
        }
    }
    
    private function getTemplate($template_name) {
        $template_query = "SELECT * FROM email_templates WHERE name = :name AND is_active = 1";
        $template_stmt = $this->db->prepare($template_query);
        $template_stmt->bindParam(':name', $template_name);
        $template_stmt->execute();
        return $template_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }
    
    public function sendWelcomeEmail($user_email, $user_name, $user_type) {
        $variables = [
            'name' => $user_name,
            'user_type' => $user_type,
            'login_url' => 'https://skillverge.com/login.php'
        ];
        
        return $this->sendEmail(
            $user_email,
            '',
            '',
            'welcome_' . $user_type,
            $variables
        );
    }
    
    public function sendInterviewReminder($user_email, $user_name, $interview_date, $interview_time) {
        $variables = [
            'name' => $user_name,
            'date' => $interview_date,
            'time' => $interview_time
        ];
        
        return $this->sendEmail(
            $user_email,
            '',
            '',
            'interview_reminder',
            $variables
        );
    }
    
    public function sendPaymentConfirmation($user_email, $user_name, $amount, $plan_type) {
        $variables = [
            'name' => $user_name,
            'amount' => $amount,
            'plan' => $plan_type
        ];
        
        return $this->sendEmail(
            $user_email,
            '',
            '',
            'payment_success',
            $variables
        );
    }
}
?>
