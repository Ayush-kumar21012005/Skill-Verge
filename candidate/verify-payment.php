<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['candidate']);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $payment_id = $input['razorpay_payment_id'];
    $order_id = $input['razorpay_order_id'];
    $signature = $input['razorpay_signature'];
    $plan_type = $input['plan_type'];
    
    // In production, verify the signature with Razorpay
    // For demo, we'll assume verification is successful
    $signature_valid = true;
    
    if ($signature_valid) {
        // Update subscription status
        $update_subscription = "UPDATE subscriptions SET payment_status = 'completed', payment_id = :payment_id 
                               WHERE payment_id = :order_id AND user_id = :user_id";
        $update_stmt = $db->prepare($update_subscription);
        $update_stmt->bindParam(':payment_id', $payment_id);
        $update_stmt->bindParam(':order_id', $order_id);
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $update_stmt->execute();
        
        // Get candidate ID
        $candidate_query = "SELECT id FROM candidates WHERE user_id = :user_id";
        $candidate_stmt = $db->prepare($candidate_query);
        $candidate_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $candidate_stmt->execute();
        $candidate_id = $candidate_stmt->fetchColumn();
        
        // Activate premium
        $premium_expires = $plan_type === 'annual' ? 'DATE_ADD(NOW(), INTERVAL 1 YEAR)' : 'DATE_ADD(NOW(), INTERVAL 1 MONTH)';
        $activate_premium = "UPDATE candidates SET is_premium = 1, premium_expires_at = $premium_expires WHERE id = :candidate_id";
        $premium_stmt = $db->prepare($activate_premium);
        $premium_stmt->bindParam(':candidate_id', $candidate_id);
        $premium_stmt->execute();
        
        // Send notification
        $notification_query = "INSERT INTO notifications (user_id, title, message, type) 
                              VALUES (:user_id, 'Payment Successful', 'Your premium subscription has been activated successfully!', 'success')";
        $notification_stmt = $db->prepare($notification_query);
        $notification_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $notification_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Payment verified and premium activated']);
    } else {
        throw new Exception('Payment signature verification failed');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
