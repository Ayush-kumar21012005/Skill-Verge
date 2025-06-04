<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['candidate']);

// Mark notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $mark_read_query = "UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id";
    $mark_stmt = $db->prepare($mark_read_query);
    $mark_stmt->bindParam(':id', $notification_id);
    $mark_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $mark_stmt->execute();
    
    header('Location: notifications.php');
    exit();
}

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    $mark_all_query = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id";
    $mark_all_stmt = $db->prepare($mark_all_query);
    $mark_all_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $mark_all_stmt->execute();
    
    header('Location: notifications.php');
    exit();
}

// Get notifications
$notifications_query = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC";
$notifications_stmt = $db->prepare($notifications_query);
$notifications_stmt->bindParam(':user_id', $_SESSION['user_id']);
$notifications_stmt->execute();
$notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unread_query = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
$unread_stmt = $db->prepare($unread_query);
$unread_stmt->bindParam(':user_id', $_SESSION['user_id']);
$unread_stmt->execute();
$unread_count = $unread_stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - SkillVerge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="p-3 border-bottom">
            <h5 class="fw-bold text-primary mb-0">
                <i class="fas fa-graduation-cap me-2"></i>SkillVerge
            </h5>
            <small class="text-muted">Notifications</small>
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
                    <a class="nav-link active" href="notifications.php">
                        <i class="fas fa-bell"></i>Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger ms-2"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payment.php">
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
                    <h2 class="fw-bold mb-1">Notifications</h2>
                    <p class="text-muted mb-0">Stay updated with your interview activities and platform updates</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($unread_count > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-outline-primary">
                            <i class="fas fa-check-double me-2"></i>Mark All Read
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-primary" onclick="refreshNotifications()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <?php if (empty($notifications)): ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-bell-slash text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 text-muted">No notifications yet</h4>
                                <p class="text-muted">You'll receive notifications about interviews, payments, and platform updates here.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="notifications-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="card mb-3 <?php echo !$notification['is_read'] ? 'border-primary' : ''; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-<?php 
                                                        echo $notification['type'] === 'success' ? 'check-circle text-success' : 
                                                            ($notification['type'] === 'warning' ? 'exclamation-triangle text-warning' : 
                                                            ($notification['type'] === 'error' ? 'exclamation-circle text-danger' : 'info-circle text-info')); 
                                                    ?> me-2"></i>
                                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                    <?php if (!$notification['is_read']): ?>
                                                        <span class="badge bg-primary ms-2">New</span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-muted mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if (!$notification['is_read']): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="?mark_read=<?php echo $notification['id']; ?>">
                                                                <i class="fas fa-check me-2"></i>Mark as Read
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($notification['action_url']): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="<?php echo htmlspecialchars($notification['action_url']); ?>">
                                                                <i class="fas fa-external-link-alt me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <?php if ($notification['action_url']): ?>
                                            <div class="mt-3">
                                                <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-arrow-right me-2"></i>Take Action
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshNotifications() {
            window.location.reload();
        }

        function deleteNotification(notificationId) {
            if (confirm('Are you sure you want to delete this notification?')) {
                fetch('delete-notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ notification_id: notificationId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting notification: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to delete notification');
                });
            }
        }

        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            // Check for new notifications without full page reload
            fetch('check-new-notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.new_count > 0) {
                    // Show toast notification
                    showToast(`You have ${data.new_count} new notification(s)`, 'info');
                }
            })
            .catch(error => console.error('Error checking notifications:', error));
        }, 30000);

        function showToast(message, type) {
            // Create toast notification
            const toast = document.createElement('div');
            toast.className = 'toast position-fixed top-0 end-0 m-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="toast-header">
                    <i class="fas fa-bell text-primary me-2"></i>
                    <strong class="me-auto">SkillVerge</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;
            
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }
    </script>
</body>
</html>
