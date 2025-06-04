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
    
    $plan_type = $input['plan_type'];
    $amount = $input['amount'];
    $currency = $input['currency'];
    
    // Get Razorpay credentials from settings
    $razorpay_key_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'razorpay_key_id'";
    $razorpay_stmt = $db->prepare($razorpay_key_query);
    $razorpay_stmt->execute();
    $razorpay_key = $razorpay_stmt->fetchColumn();
    
    $razorpay_secret_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'razorpay_key_secret'";
    $secret_stmt = $db->prepare($razorpay_secret_query);
    $secret_stmt->execute();
    $razorpay_secret = $secret_stmt->fetchColumn();
    
    if (!$razorpay_key || !$razorpay_secret) {
        throw new Exception('Payment gateway not configured');
    }
    
    // Create Razorpay order
    $order_id = 'order_' . time() . '_' . rand(1000, 9999);
    $amount_in_paise = $amount * 100; // Convert to smallest currency unit
    
    // For demo purposes, we'll simulate the order creation
    // In production, you would use the actual Razorpay API
    $order_data = [
        'id' => $order_id,
        'amount' => $amount_in_paise,
        'currency' => $currency,
        'status' => 'created'
    ];
    
    // Store order in database
    $insert_order = "INSERT INTO subscriptions (user_id, plan_type, amount, currency, payment_id, payment_status, starts_at, expires_at) 
                    VALUES (:user_id, :plan_type, :amount, :currency, :order_id, 'pending', NOW(), 
                    DATE_ADD(NOW(), INTERVAL " . ($plan_type === 'annual' ? '1 YEAR' : '1 MONTH') . "))";
    $order_stmt = $db->prepare($insert_order);
    $order_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $order_stmt->bindParam(':plan_type', $plan_type);
    $order_stmt->bindParam(':amount', $amount);
    $order_stmt->bindParam(':currency', $currency);
    $order_stmt->bindParam(':order_id', $order_id);
    $order_stmt->execute();
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'amount' => $amount_in_paise,
        'currency' => $currency,
        'razorpay_key' => $razorpay_key
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
