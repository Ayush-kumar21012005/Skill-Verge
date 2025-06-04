<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['admin']);

// Get overall statistics
$stats = [];

// Total users by type
$users_query = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$user_stats = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($user_stats as $stat) {
    $stats[$stat['user_type'] . '_count'] = $stat['count'];
}

// Total interviews
$interviews_query = "SELECT COUNT(*) as total_ai_interviews FROM ai_interviews";
$interviews_stmt = $db->prepare($interviews_query);
$interviews_stmt->execute();
$stats['total_ai_interviews'] = $interviews_stmt->fetchColumn();

// Total revenue
$revenue_query = "SELECT SUM(amount) as total_revenue FROM subscriptions WHERE payment_status = 'completed'";
$revenue_stmt = $db->prepare($revenue_query);
$revenue_stmt->execute();
$stats['total_revenue'] = $revenue_stmt->fetchColumn() ?? 0;

// Premium users
$premium_query = "SELECT COUNT(*) as premium_users FROM candidates WHERE is_premium = 1";
$premium_stmt = $db->prepare($premium_query);
$premium_stmt->execute();
$stats['premium_users'] = $premium_stmt->fetchColumn();

// Recent activities
$recent_users_query = "SELECT u.*, c.company_name FROM users u 
                      LEFT JOIN companies c ON u.id = c.user_id 
                      ORDER BY u.created_at DESC LIMIT 10";
$recent_users_stmt = $db->prepare($recent_users_query);
$recent_users_stmt->execute();
$recent_users = $recent_users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly growth data for charts
$growth_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as new_users
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month";
$growth_stmt = $db->prepare($growth_query);
$growth_stmt->execute();
$growth_data = $growth_stmt->fetchAll(PDO::FETCH_ASSOC);

// Revenue data
$revenue_monthly_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    SUM(amount) as revenue
    FROM subscriptions 
    WHERE payment_status = 'completed' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month";
$revenue_monthly_stmt = $db->prepare($revenue_monthly_query);
$revenue_monthly_stmt->execute();
$revenue_data = $revenue_monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SkillVerge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="p-3 border-bottom">
            <h5 class="fw-bold text-danger mb-0">
                <i class="fas fa-shield-alt me-2"></i>SkillVerge
            </h5>
            <small class="text-muted">Admin Panel</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i>User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="companies.php">
                        <i class="fas fa-building"></i>Companies
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card"></i>Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-line"></i>Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="coupons.php">
                        <i class="fas fa-ticket-alt"></i>Coupon Codes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>Settings
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Admin Dashboard</h2>
                <p class="text-muted mb-0">Monitor and manage SkillVerge platform</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="exportData()">
                    <i class="fas fa-download me-2"></i>Export Data
                </button>
                <button class="btn btn-danger" onclick="generateReport()">
                    <i class="fas fa-file-pdf me-2"></i>Generate Report
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-value"><?php echo ($stats['candidate_count'] ?? 0) + ($stats['company_count'] ?? 0); ?></div>
                    <div class="stats-label">Total Users</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon success">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="stats-value"><?php echo $stats['premium_users'] ?? 0; ?></div>
                    <div class="stats-label">Premium Users</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon warning">
                        <i class="fas fa-microphone"></i>
                    </div>
                    <div class="stats-value"><?php echo $stats['total_ai_interviews'] ?? 0; ?></div>
                    <div class="stats-label">AI Interviews</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon danger">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stats-value">₹<?php echo number_format($stats['total_revenue'], 0); ?></div>
                    <div class="stats-label">Total Revenue</div>
                </div>
            </div>
        </div>

        <!-- User Distribution -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-pie me-2"></i>User Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="userDistributionChart" width="300" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line me-2"></i>User Growth (Last 12 Months)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="userGrowthChart" width="600" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-area me-2"></i>Revenue Trends (Last 12 Months)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" width="800" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>Recent User Registrations
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Email</th>
                                        <th>Registered</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-<?php echo $user['user_type'] === 'candidate' ? 'primary' : 'success'; ?> rounded-circle d-flex align-items-center justify-content-center me-2">
                                                        <i class="fas fa-<?php echo $user['user_type'] === 'candidate' ? 'user' : 'building'; ?> text-white"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                        <?php if ($user['company_name']): ?>
                                                            <small class="text-muted"><?php echo htmlspecialchars($user['company_name']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['user_type'] === 'candidate' ? 'primary' : 'success'; ?>">
                                                    <?php echo ucfirst($user['user_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['is_verified'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $user['is_verified'] ? 'Verified' : 'Pending'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="user-details.php?id=<?php echo $user['id']; ?>">View Details</a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="toggleUserStatus(<?php echo $user['id']; ?>)">
                                                            <?php echo $user['is_verified'] ? 'Suspend' : 'Activate'; ?>
                                                        </a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?php echo $user['id']; ?>)">Delete</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>System Alerts
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-server me-2"></i>
                            <strong>Server Load:</strong> 78% - Monitor closely
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-database me-2"></i>
                            <strong>Database:</strong> All systems operational
                        </div>
                        <div class="alert alert-success">
                            <i class="fas fa-shield-alt me-2"></i>
                            <strong>Security:</strong> No threats detected
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tasks text-primary me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="sendBulkEmail()">
                                <i class="fas fa-envelope me-2"></i>Send Bulk Email
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="generateCoupons()">
                                <i class="fas fa-ticket-alt me-2"></i>Generate Coupons
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="backupDatabase()">
                                <i class="fas fa-database me-2"></i>Backup Database
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="clearCache()">
                                <i class="fas fa-broom me-2"></i>Clear Cache
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User Distribution Chart
        const userDistCtx = document.getElementById('userDistributionChart').getContext('2d');
        new Chart(userDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Candidates', 'Companies', 'Admins'],
                datasets: [{
                    data: [
                        <?php echo $stats['candidate_count'] ?? 0; ?>,
                        <?php echo $stats['company_count'] ?? 0; ?>,
                        <?php echo $stats['admin_count'] ?? 0; ?>
                    ],
                    backgroundColor: ['#2563eb', '#059669', '#dc2626'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // User Growth Chart
        const growthCtx = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($growth_data, 'month')) . "'"; ?>],
                datasets: [{
                    label: 'New Users',
                    data: [<?php echo implode(',', array_column($growth_data, 'new_users')); ?>],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($revenue_data, 'month')) . "'"; ?>],
                datasets: [{
                    label: 'Revenue (₹)',
                    data: [<?php echo implode(',', array_column($revenue_data, 'revenue')); ?>],
                    backgroundColor: 'rgba(5, 150, 105, 0.8)',
                    borderColor: '#059669',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Admin Functions
        function exportData() {
            if (confirm('Export all platform data? This may take a few minutes.')) {
                window.location.href = 'export-data.php';
            }
        }

        function generateReport() {
            window.open('generate-report.php', '_blank');
        }

        function toggleUserStatus(userId) {
            if (confirm('Are you sure you want to change this user\'s status?')) {
                fetch('toggle-user-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: userId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                fetch('delete-user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: userId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function sendBulkEmail() {
            window.location.href = 'bulk-email.php';
        }

        function generateCoupons() {
            window.location.href = 'coupons.php?action=generate';
        }

        function backupDatabase() {
            if (confirm('Start database backup? This may take several minutes.')) {
                fetch('backup-database.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                });
            }
        }

        function clearCache() {
            if (confirm('Clear all cached data?')) {
                fetch('clear-cache.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                });
            }
        }

        // Auto-refresh dashboard every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
