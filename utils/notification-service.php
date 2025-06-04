<?php
require_once '../config/database.php';
require_once 'email-service.php';

class NotificationService {
    private $db;
    private $emailService;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
        $this->emailService = new EmailService($database);
    }
    
    public function createNotification($user_id, $title, $message, $type = 'info', $action_url = null) {
        try {
            $insert_query = "INSERT INTO notifications (user_id, title, message, type, action_url) 
                           VALUES (:user_id, :title, :message, :type, :action_url)";
            $insert_stmt = $this->db->prepare($insert_query);
            $insert_stmt->bindParam(':user_id', $user_id);
            $insert_stmt->bindParam(':title', $title);
            $insert_stmt->bindParam(':message', $message);
            $insert_stmt->bindParam(':type', $type);
            $insert_stmt->bindParam(':action_url', $action_url);
            
            return $insert_stmt->execute();
        } catch (Exception $e) {
            error_log("Notification creation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function notifyInterviewCompleted($candidate_id, $interview_type, $score) {
        // Get candidate info
        $candidate_query = "SELECT u.email, u.full_name FROM candidates c 
                           JOIN users u ON c.user_id = u.id 
                           WHERE c.id = :candidate_id";
        $candidate_stmt = $this->db->prepare($candidate_query);
        $candidate_stmt->bindParam(':candidate_id', $candidate_id);
        $candidate_stmt->execute();
        $candidate = $candidate_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($candidate) {
            $title = ucfirst($interview_type) . " Interview Completed";
            $message = "Your {$interview_type} interview has been completed. Overall score: {$score}/10. View detailed feedback in your dashboard.";
            
            $this->createNotification(
                $candidate_id,
                $title,
                $message,
                'success',
                'analytics.php'
            );
            
            // Send email notification
            $this->emailService->sendEmail(
                $candidate['email'],
                $title,
                $message . "\n\nLogin to your dashboard to view detailed results: https://skillverge.com/candidate/dashboard.php"
            );
        }
    }
    
    public function notifyPaymentSuccess($user_id, $amount, $plan_type) {
        $user_query = "SELECT email, full_name FROM users WHERE id = :user_id";
        $user_stmt = $this->db->prepare($user_query);
        $user_stmt->bindParam(':user_id', $user_id);
        $user_stmt->execute();
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $title = "Payment Successful";
            $message = "Your payment of ₹{$amount} for {$plan_type} plan has been processed successfully. Premium features are now active.";
            
            $this->createNotification(
                $user_id,
                $title,
                $message,
                'success',
                'payment.php'
            );
            
            $this->emailService->sendPaymentConfirmation(
                $user['email'],
                $user['full_name'],
                $amount,
                $plan_type
            );
        }
    }
    
    public function notifyExpertInterviewScheduled($candidate_id, $expert_name, $scheduled_time) {
        $candidate_query = "SELECT u.email, u.full_name FROM candidates c 
                           JOIN users u ON c.user_id = u.id 
                           WHERE c.id = :candidate_id";
        $candidate_stmt = $this->db->prepare($candidate_query);
        $candidate_stmt->bindParam(':candidate_id', $candidate_id);
        $candidate_stmt->execute();
        $candidate = $candidate_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($candidate) {
            $title = "Expert Interview Scheduled";
            $message = "Your interview with {$expert_name} has been scheduled for " . date('M j, Y g:i A', strtotime($scheduled_time));
            
            $this->createNotification(
                $candidate_id,
                $title,
                $message,
                'info',
                'expert-interviews.php'
            );
            
            $this->emailService->sendInterviewReminder(
                $candidate['email'],
                $candidate['full_name'],
                date('M j, Y', strtotime($scheduled_time)),
                date('g:i A', strtotime($scheduled_time))
            );
        }
    }
    
    public function notifyJobApplication($candidate_id, $job_title, $company_name) {
        $title = "Job Application Submitted";
        $message = "Your application for {$job_title} at {$company_name} has been submitted successfully.";
        
        $this->createNotification(
            $candidate_id,
            $title,
            $message,
            'success',
            'my-applications.php'
        );
    }
    
    public function notifySystemMaintenance($user_type = null) {
        $title = "Scheduled Maintenance";
        $message = "SkillVerge will undergo scheduled maintenance on " . date('M j, Y', strtotime('+1 day')) . " from 2:00 AM to 4:00 AM IST. Some features may be temporarily unavailable.";
        
        // Notify all users or specific user type
        $user_query = "SELECT id FROM users";
        if ($user_type) {
            $user_query .= " WHERE user_type = :user_type";
        }
        
        $user_stmt = $this->db->prepare($user_query);
        if ($user_type) {
            $user_stmt->bindParam(':user_type', $user_type);
        }
        $user_stmt->execute();
        $users = $user_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($users as $user_id) {
            $this->createNotification(
                $user_id,
                $title,
                $message,
                'warning'
            );
        }
    }
    
    public function getUnreadCount($user_id) {
        $count_query = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
        $count_stmt = $this->db->prepare($count_query);
        $count_stmt->bindParam(':user_id', $user_id);
        $count_stmt->execute();
        return $count_stmt->fetchColumn();
    }
    
    public function markAsRead($notification_id, $user_id) {
        $update_query = "UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id";
        $update_stmt = $this->db->prepare($update_query);
        $update_stmt->bindParam(':id', $notification_id);
        $update_stmt->bindParam(':user_id', $user_id);
        return $update_stmt->execute();
    }
    
    public function markAllAsRead($user_id) {
        $update_query = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id";
        $update_stmt = $this->db->prepare($update_query);
        $update_stmt->bindParam(':user_id', $user_id);
        return $update_stmt->execute();
    }
}
?>
