<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Check if user is authenticated
$auth->requireAuth(['candidate']);

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['transaction_id']) || !isset($data['plan_type']) || !isset($data['amount'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

$transaction_id = $data['transaction_id'];
$plan_type = $data['plan_type'];
$amount = $data['amount'];
$user_id = $_SESSION['user_id'];

try {
    // Begin transaction
    $db->beginTransaction();
    
    // Create subscription record
    $subscription_query = "INSERT INTO subscriptions (user_id, plan_type, amount, currency, payment_method, 
                          payment_status, upi_transaction_id, created_at) 
                          VALUES (:user_id, :plan_type, :amount, 'INR', 'UPI', 'completed', :transaction_id, NOW())";
    $subscription_stmt = $db->prepare($subscription_query);
    $subscription_stmt->bindParam(':user_id', $user_id);
    $subscription_stmt->bindParam(':plan_type', $plan_type);
    $subscription_stmt->bindParam(':amount', $amount);
    $subscription_stmt->bindParam(':transaction_id', $transaction_id);
    $subscription_stmt->execute();
    
    // Get candidate ID
    $candidate_query = "SELECT id FROM candidates WHERE user_id = :user_id";
    $candidate_stmt = $db->prepare($candidate_query);
    $candidate_stmt->bindParam(':user_id', $user_id);
    $candidate_stmt->execute();
    $candidate = $candidate_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidate) {
        throw new Exception("Candidate not found");
    }
    
    // Calculate expiration date based on plan type
    $expiry_period = ($plan_type === 'annual') ? 'INTERVAL 1 YEAR' : 'INTERVAL 1 MONTH';
    
    // Update candidate premium status
    $update_query = "UPDATE candidates SET 
                    is_premium = 1, 
                    premium_expires_at = DATE_ADD(NOW(), $expiry_period) 
                    WHERE id = :candidate_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':candidate_id', $candidate['id']);
    $update_stmt->execute();
    
    // Create notification
    $notification_query = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                          VALUES (:user_id, 'payment', 'Payment Successful', 
                          :message, NOW())";
    $notification_stmt = $db->prepare($notification_query);
    $notification_stmt->bindParam(':user_id', $user_id);
    
    $plan_name = ($plan_type === 'annual') ? 'Annual Premium' : 'Monthly Premium';
    $notification_message = "Your payment of ₹{$amount} for {$plan_name} plan was successful. Your premium access is now active.";
    $notification_stmt->bindParam(':message', $notification_message);
    $notification_stmt->execute();
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    
    echo json_encode([
        'success' => false,
        'message' => 'Payment verification failed: ' . $e->getMessage()
    ]);
}
?>
