<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['candidate']);

// Get candidate data
$candidate_query = "SELECT c.*, u.full_name, u.email FROM candidates c 
                   JOIN users u ON c.user_id = u.id 
                   WHERE c.user_id = :user_id";
$candidate_stmt = $db->prepare($candidate_query);
$candidate_stmt->bindParam(':user_id', $_SESSION['user_id']);
$candidate_stmt->execute();
$candidate = $candidate_stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$error = '';

// Handle coupon redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_coupon'])) {
    $coupon_code = trim($_POST['coupon_code']);
    
    if (empty($coupon_code)) {
        $error = 'Please enter a coupon code.';
    } else {
        $coupon_query = "SELECT * FROM coupon_codes WHERE code = :code AND is_used = 0";
        $coupon_stmt = $db->prepare($coupon_query);
        $coupon_stmt->bindParam(':code', $coupon_code);
        $coupon_stmt->execute();
        $coupon = $coupon_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coupon) {
            // Mark coupon as used
            $update_coupon = "UPDATE coupon_codes SET is_used = 1, used_by = :user_id, used_at = NOW() WHERE id = :coupon_id";
            $update_stmt = $db->prepare($update_coupon);
            $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $update_stmt->bindParam(':coupon_id', $coupon['id']);
            
            // Activate premium
            $update_premium = "UPDATE candidates SET is_premium = 1, premium_expires_at = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE id = :candidate_id";
            $premium_stmt = $db->prepare($update_premium);
            $premium_stmt->bindParam(':candidate_id', $candidate['id']);
            
            if ($update_stmt->execute() && $premium_stmt->execute()) {
                $message = 'Coupon redeemed successfully! You now have premium access for 1 year.';
                // Refresh candidate data
                $candidate_stmt->execute();
                $candidate = $candidate_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to redeem coupon. Please try again.';
            }
        } else {
            $error = 'Invalid or already used coupon code.';
        }
    }
}

// Get payment history
$payment_history_query = "SELECT s.*, COALESCE(s.razorpay_payment_id, 'N/A') as payment_id 
                         FROM subscriptions s 
                         WHERE s.user_id = :user_id 
                         ORDER BY s.created_at DESC";
$payment_stmt = $db->prepare($payment_history_query);
$payment_stmt->bindParam(':user_id', $_SESSION['user_id']);
$payment_stmt->execute();
$payment_history = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);

// UPI payment IDs
$upi_ids = [
    '9693939756@ibl',
    '9693939756@ybl',
    '9693939756@axl'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment & Subscription - SkillVerge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        .upi-option {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upi-option:hover {
            border-color: #2563eb;
            background-color: #f0f7ff;
        }
        .upi-option.selected {
            border-color: #2563eb;
            background-color: #f0f7ff;
        }
        .qr-code-container {
            text-align: center;
            margin: 20px 0;
        }
        .qr-code-container img {
            max-width: 200px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
        }
        .payment-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .payment-tabs .nav-link.active {
            color: #2563eb;
            border-bottom: 2px solid #2563eb;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="p-3 border-bottom">
            <h5 class="fw-bold text-primary mb-0">
                <i class="fas fa-graduation-cap me-2"></i>SkillVerge
            </h5>
            <small class="text-muted">Payment Portal</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="ai-interview.php">
                        <i class="fas fa-robot"></i>AI Mock Interview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="expert-interviews.php">
                        <i class="fas fa-users"></i>Expert Interviews
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="payment.php">
                        <i class="fas fa-crown"></i>Subscription
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="dashboard-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Subscription & Payment</h2>
                    <p class="text-muted mb-0">Manage your premium subscription and payment methods</p>
                </div>
                <?php if ($candidate['is_premium']): ?>
                    <span class="badge bg-success fs-6 px-3 py-2">
                        <i class="fas fa-crown me-2"></i>Premium Active
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Current Status -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-user-circle text-primary me-2"></i>Current Plan
                            </h5>
                            <?php if ($candidate['is_premium']): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-crown text-warning me-2" style="font-size: 2rem;"></i>
                                    <div>
                                        <h4 class="mb-0 text-success">Premium Plan</h4>
                                        <small class="text-muted">
                                            Expires: <?php echo $candidate['premium_expires_at'] ? date('M j, Y', strtotime($candidate['premium_expires_at'])) : 'Never'; ?>
                                        </small>
                                    </div>
                                </div>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Unlimited AI Interviews</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Expert Live Sessions</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Advanced Analytics</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Interview Recordings</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Priority Support</li>
                                </ul>
                            <?php else: ?>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-user text-secondary me-2" style="font-size: 2rem;"></i>
                                    <div>
                                        <h4 class="mb-0">Free Plan</h4>
                                        <small class="text-muted">
                                            <?php echo 2 - $candidate['trial_interviews_used']; ?> trial interviews remaining
                                        </small>
                                    </div>
                                </div>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>2 AI Mock Interviews</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>Expert Sessions</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>Advanced Analytics</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>Interview Recordings</li>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-ticket-alt text-warning me-2"></i>Redeem Coupon
                            </h5>
                            <p class="text-muted">Have a coupon code? Redeem it here for instant premium access.</p>
                            <form method="POST" action="">
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" name="coupon_code" placeholder="Enter coupon code" required>
                                    <button class="btn btn-warning" type="submit" name="redeem_coupon">
                                        <i class="fas fa-gift me-2"></i>Redeem
                                    </button>
                                </div>
                            </form>
                            <div class="alert alert-info">
                                <small><i class="fas fa-info-circle me-2"></i>Valid coupon codes provide 1 year of premium access</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$candidate['is_premium']): ?>
            <!-- Pricing Plans -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <h4 class="fw-bold mb-3">Choose Your Premium Plan</h4>
                </div>
                <div class="col-md-6">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <h4>Monthly Premium</h4>
                            <div class="price">₹100<span>/month</span></div>
                            <p>Perfect for short-term preparation</p>
                        </div>
                        <ul class="pricing-features">
                            <li><i class="fas fa-check"></i> Unlimited AI Interviews</li>
                            <li><i class="fas fa-check"></i> Expert Live Sessions</li>
                            <li><i class="fas fa-check"></i> Advanced Analytics</li>
                            <li><i class="fas fa-check"></i> Interview Recordings</li>
                            <li><i class="fas fa-check"></i> Priority Support</li>
                        </ul>
                        <button class="btn btn-primary w-100 mb-2" onclick="showPaymentOptions('monthly', 100)">
                            Subscribe Monthly
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="pricing-card featured">
                        <div class="pricing-header">
                            <h4>Annual Premium</h4>
                            <div class="price">₹1200<span>/year</span></div>
                            <p>Best value - Save 2 months!</p>
                        </div>
                        <ul class="pricing-features">
                            <li><i class="fas fa-check"></i> Everything in Monthly</li>
                            <li><i class="fas fa-check"></i> 2 Months Free</li>
                            <li><i class="fas fa-check"></i> Exclusive Webinars</li>
                            <li><i class="fas fa-check"></i> Career Guidance</li>
                            <li><i class="fas fa-check"></i> Resume Review</li>
                        </ul>
                        <button class="btn btn-success w-100 mb-2" onclick="showPaymentOptions('annual', 1200)">
                            Subscribe Annually
                        </button>
                    </div>
                </div>
            </div>

            <!-- Payment Options Modal -->
            <div class="modal fade" id="paymentOptionsModal" tabindex="-1" aria-labelledby="paymentOptionsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="paymentOptionsModalLabel">Choose Payment Method</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <ul class="nav nav-tabs payment-tabs mb-3" id="paymentTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="upi-tab" data-bs-toggle="tab" data-bs-target="#upi-content" type="button" role="tab" aria-controls="upi-content" aria-selected="true">UPI Payment</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="razorpay-tab" data-bs-toggle="tab" data-bs-target="#razorpay-content" type="button" role="tab" aria-controls="razorpay-content" aria-selected="false">Card/Netbanking</button>
                                </li>
                            </ul>
                            <div class="tab-content" id="paymentTabsContent">
                                <div class="tab-pane fade show active" id="upi-content" role="tabpanel" aria-labelledby="upi-tab">
                                    <div class="qr-code-container">
                                        <img src="../assets/images/payment-qr.png" alt="UPI Payment QR Code" class="img-fluid">
                                        <p class="mt-2 mb-0 fw-bold">Scan to Pay</p>
                                        <p class="text-muted small">Amount: ₹<span id="upi-amount">100</span></p>
                                    </div>
                                    
                                    <p class="text-center mb-3">Or pay using these UPI IDs:</p>
                                    
                                    <div class="upi-options">
                                        <?php foreach ($upi_ids as $upi_id): ?>
                                        <div class="upi-option d-flex align-items-center justify-content-between" onclick="selectUpiOption(this, '<?php echo $upi_id; ?>')">
                                            <div>
                                                <i class="fas fa-mobile-alt text-primary me-2"></i>
                                                <span class="upi-id"><?php echo $upi_id; ?></span>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary copy-btn" onclick="copyUpiId(event, '<?php echo $upi_id; ?>')">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="alert alert-info mt-3">
                                        <small><i class="fas fa-info-circle me-2"></i>After payment, please enter your UPI transaction ID below to verify your payment.</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="upi-transaction-id" class="form-label">UPI Transaction ID</label>
                                        <input type="text" class="form-control" id="upi-transaction-id" placeholder="Enter your UPI transaction ID">
                                    </div>
                                    
                                    <button class="btn btn-primary w-100" id="verify-upi-btn" onclick="verifyUpiPayment()">
                                        Verify Payment
                                    </button>
                                </div>
                                <div class="tab-pane fade" id="razorpay-content" role="tabpanel" aria-labelledby="razorpay-tab">
                                    <p class="mb-3">Pay securely using credit/debit card, netbanking, or other payment methods.</p>
                                    <button class="btn btn-primary w-100" id="razorpay-btn">
                                        <i class="fas fa-lock me-2"></i>Pay with Razorpay
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment History -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-history me-2"></i>Payment History
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($payment_history)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-receipt text-muted" style="font-size: 3rem;"></i>
                                    <h6 class="text-muted mt-3">No payment history</h6>
                                    <p class="text-muted">Your payment transactions will appear here.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Plan</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Payment ID</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payment_history as $payment): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $payment['plan_type'] === 'annual' ? 'success' : 'primary'; ?>">
                                                            <?php echo ucfirst($payment['plan_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo $payment['currency'] === 'USD' ? '$' : '₹'; ?><?php echo number_format($payment['amount'], 2); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $payment['payment_status'] === 'completed' ? 'success' : 
                                                                ($payment['payment_status'] === 'pending' ? 'warning' : 'danger'); 
                                                        ?>">
                                                            <?php echo ucfirst($payment['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <code><?php echo $payment['payment_id'] ?: 'N/A'; ?></code>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="downloadInvoice(<?php echo $payment['id']; ?>)">
                                                            <i class="fas fa-download me-1"></i>Invoice
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPlanType = '';
        let currentAmount = 0;
        
        function showPaymentOptions(planType, amount) {
            currentPlanType = planType;
            currentAmount = amount;
            
            // Update the amount in the UPI tab
            document.getElementById('upi-amount').textContent = amount;
            
            // Set up Razorpay button
            document.getElementById('razorpay-btn').onclick = function() {
                initiatePayment(planType, amount, 'INR');
            };
            
            // Show the modal
            const paymentModal = new bootstrap.Modal(document.getElementById('paymentOptionsModal'));
            paymentModal.show();
        }
        
        function selectUpiOption(element, upiId) {
            // Remove selected class from all options
            document.querySelectorAll('.upi-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
        }
        
        function copyUpiId(event, upiId) {
            event.stopPropagation();
            
            // Copy to clipboard
            navigator.clipboard.writeText(upiId).then(() => {
                // Change button text temporarily
                const button = event.currentTarget;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                }, 2000);
            });
        }
        
        function verifyUpiPayment() {
            const transactionId = document.getElementById('upi-transaction-id').value.trim();
            
            if (!transactionId) {
                alert('Please enter your UPI transaction ID');
                return;
            }
            
            // Show loading
            const button = document.getElementById('verify-upi-btn');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
            button.disabled = true;
            
            // Send verification request
            fetch('verify-upi-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    transaction_id: transactionId,
                    plan_type: currentPlanType,
                    amount: currentAmount
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment verified successfully! Your premium access has been activated.');
                    window.location.reload();
                } else {
                    alert('Payment verification failed: ' + data.message);
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Payment verification failed. Please contact support.');
                button.innerHTML = originalText;
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
        function initiatePayment(planType, amount, currency) {
            // Show loading
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            button.disabled = true;

            // Create order
            fetch('create-payment-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    plan_type: planType,
                    amount: amount,
                    currency: currency
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Initialize Razorpay
                    const options = {
                        key: data.razorpay_key,
                        amount: data.amount,
                        currency: data.currency,
                        name: 'SkillVerge',
                        description: `${planType.charAt(0).toUpperCase() + planType.slice(1)} Premium Plan`,
                        order_id: data.order_id,
                        handler: function(response) {
                            // Verify payment
                            verifyPayment(response, planType);
                        },
                        prefill: {
                            name: '<?php echo htmlspecialchars($candidate['full_name']); ?>',
                            email: '<?php echo htmlspecialchars($candidate['email']); ?>'
                        },
                        theme: {
                            color: '#2563eb'
                        },
                        modal: {
                            ondismiss: function() {
                                button.innerHTML = originalText;
                                button.disabled = false;
                            }
                        }
                    };

                    const rzp = new Razorpay(options);
                    rzp.open();
                } else {
                    alert('Error creating payment order: ' + data.message);
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Payment initialization failed. Please try again.');
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }

        function verifyPayment(response, planType) {
            fetch('verify-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    razorpay_payment_id: response.razorpay_payment_id,
                    razorpay_order_id: response.razorpay_order_id,
                    razorpay_signature: response.razorpay_signature,
                    plan_type: planType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment successful! Your premium access has been activated.');
                    window.location.reload();
                } else {
                    alert('Payment verification failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Payment verification failed. Please contact support.');
            });
        }

        function downloadInvoice(paymentId) {
            window.open(`download-invoice.php?id=${paymentId}`, '_blank');
        }
    </script>
</body>
</html>
