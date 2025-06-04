<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['admin']);

// Get date range from query params
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Key Metrics
$metrics = [];

// Total Users
$users_query = "SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN user_type = 'candidate' THEN 1 END) as candidates,
    COUNT(CASE WHEN user_type = 'company' THEN 1 END) as companies,
    COUNT(CASE WHEN user_type = 'expert' THEN 1 END) as experts,
    COUNT(CASE WHEN created_at >= :start_date AND created_at <= :end_date THEN 1 END) as new_users
    FROM users";
$users_stmt = $db->prepare($users_query);
$users_stmt->bindParam(':start_date', $start_date);
$users_stmt->bindParam(':end_date', $end_date);
$users_stmt->execute();
$metrics['users'] = $users_stmt->fetch(PDO::FETCH_ASSOC);

// Interview Statistics
$interviews_query = "SELECT 
    COUNT(*) as total_interviews,
    AVG(overall_score) as avg_score,
    COUNT(CASE WHEN overall_score >= 7 THEN 1 END) as high_performers,
    COUNT(CASE WHEN completed_at >= :start_date AND completed_at <= :end_date THEN 1 END) as recent_interviews
    FROM ai_interviews 
    WHERE completed_at IS NOT NULL";
$interviews_stmt = $db->prepare($interviews_query);
$interviews_stmt->bindParam(':start_date', $start_date);
$interviews_stmt->bindParam(':end_date', $end_date);
$interviews_stmt->execute();
$metrics['interviews'] = $interviews_stmt->fetch(PDO::FETCH_ASSOC);

// Revenue Statistics
$revenue_query = "SELECT 
    COUNT(*) as total_payments,
    SUM(amount) as total_revenue,
    AVG(amount) as avg_payment,
    COUNT(CASE WHEN created_at >= :start_date AND created_at <= :end_date THEN 1 END) as recent_payments,
    SUM(CASE WHEN created_at >= :start_date AND created_at <= :end_date THEN amount ELSE 0 END) as recent_revenue
    FROM payments 
    WHERE status = 'completed'";
$revenue_stmt = $db->prepare($revenue_query);
$revenue_stmt->bindParam(':start_date', $start_date);
$revenue_stmt->bindParam(':end_date', $end_date);
$revenue_stmt->execute();
$metrics['revenue'] = $revenue_stmt->fetch(PDO::FETCH_ASSOC);

// Job Statistics
$jobs_query = "SELECT 
    COUNT(*) as total_jobs,
    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_jobs,
    COUNT(CASE WHEN created_at >= :start_date AND created_at <= :end_date THEN 1 END) as new_jobs
    FROM job_postings";
$jobs_stmt = $db->prepare($jobs_query);
$jobs_stmt->bindParam(':start_date', $start_date);
$jobs_stmt->bindParam(':end_date', $end_date);
$jobs_stmt->execute();
$metrics['jobs'] = $jobs_stmt->fetch(PDO::FETCH_ASSOC);

// Daily Analytics for Charts
$daily_analytics = [];

// Daily User Registrations
$daily_users_query = "SELECT DATE(created_at) as date, COUNT(*) as count 
                     FROM users 
                     WHERE created_at >= :start_date AND created_at <= :end_date 
                     GROUP BY DATE(created_at) 
                     ORDER BY date";
$daily_users_stmt = $db->prepare($daily_users_query);
$daily_users_stmt->bindParam(':start_date', $start_date);
$daily_users_stmt->bindParam(':end_date', $end_date);
$daily_users_stmt->execute();
$daily_analytics['users'] = $daily_users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Daily Interviews
$daily_interviews_query = "SELECT DATE(completed_at) as date, COUNT(*) as count, AVG(overall_score) as avg_score
                          FROM ai_interviews 
                          WHERE completed_at >= :start_date AND completed_at <= :end_date 
                          GROUP BY DATE(completed_at) 
                          ORDER BY date";
$daily_interviews_stmt = $db->prepare($daily_interviews_query);
$daily_interviews_stmt->bindParam(':start_date', $start_date);
$daily_interviews_stmt->bindParam(':end_date', $end_date);
$daily_interviews_stmt->execute();
$daily_analytics['interviews'] = $daily_interviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Daily Revenue
$daily_revenue_query = "SELECT DATE(created_at) as date, SUM(amount) as revenue, COUNT(*) as transactions
                       FROM payments 
                       WHERE created_at >= :start_date AND created_at <= :end_date AND status = 'completed'
                       GROUP BY DATE(created_at) 
                       ORDER BY date";
$daily_revenue_stmt = $db->prepare($daily_revenue_query);
$daily_revenue_stmt->bindParam(':start_date', $start_date);
$daily_revenue_stmt->bindParam(':end_date', $end_date);
$daily_revenue_stmt->execute();
$daily_analytics['revenue'] = $daily_revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Performing Domains
$domains_query = "SELECT domain, COUNT(*) as interview_count, AVG(overall_score) as avg_score
                 FROM ai_interviews 
                 WHERE completed_at >= :start_date AND completed_at <= :end_date
                 GROUP BY domain 
                 ORDER BY interview_count DESC 
                 LIMIT 10";
$domains_stmt = $db->prepare($domains_query);
$domains_stmt->bindParam(':start_date', $start_date);
$domains_stmt->bindParam(':end_date', $end_date);
$domains_stmt->execute();
$top_domains = $domains_stmt->fetchAll(PDO::FETCH_ASSOC);

// System Health Metrics
$system_health = [];

// Database size
$db_size_query = "SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb
    FROM information_schema.tables 
    WHERE table_schema = DATABASE()";
$db_size_stmt = $db->prepare($db_size_query);
$db_size_stmt->execute();
$system_health['db_size'] = $db_size_stmt->fetchColumn();

// Active sessions
$sessions_query = "SELECT COUNT(*) as active_sessions FROM user_sessions WHERE expires_at > NOW()";
$sessions_stmt = $db->prepare($sessions_query);
$sessions_stmt->execute();
$system_health['active_sessions'] = $sessions_stmt->fetchColumn();

// Error logs count (last 24 hours)
$errors_query = "SELECT COUNT(*) as error_count FROM audit_logs WHERE level = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
$errors_stmt = $db->prepare($errors_query);
$errors_stmt->execute();
$system_health['error_count'] = $errors_stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - SkillVerge Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .metric-card {
            transition: transform 0.2s;
        }
        .metric-card:hover {
            transform: translateY(-2px);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .progress-ring {
            transform: rotate(-90deg);
        }
        .progress-ring-circle {
            transition: stroke-dasharray 0.35s;
            transform-origin: 50% 50%;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="p-3 border-bottom">
            <h5 class="fw-bold text-danger mb-0">
                <i class="fas fa-shield-alt me-2"></i>SkillVerge
            </h5>
            <small class="text-muted">Analytics Dashboard</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="analytics-dashboard.php">
                        <i class="fas fa-chart-line"></i>Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i>User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="system-settings.php">
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
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Analytics Dashboard</h2>
                    <p class="text-muted mb-0">Comprehensive platform analytics and insights</p>
                </div>
                <div class="d-flex gap-2">
                    <form method="GET" class="d-flex gap-2">
                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </form>
                    <button class="btn btn-success" onclick="exportReport()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card metric-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h3 class="mb-0"><?php echo number_format($metrics['users']['total_users']); ?></h3>
                                    <p class="mb-0">Total Users</p>
                                    <small class="opacity-75">+<?php echo $metrics['users']['new_users']; ?> this period</small>
                                </div>
                                <i class="fas fa-users fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card metric-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h3 class="mb-0"><?php echo number_format($metrics['interviews']['total_interviews']); ?></h3>
                                    <p class="mb-0">Total Interviews</p>
                                    <small class="opacity-75">Avg Score: <?php echo number_format($metrics['interviews']['avg_score'], 1); ?>/10</small>
                                </div>
                                <i class="fas fa-microphone fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card metric-card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h3 class="mb-0">₹<?php echo number_format($metrics['revenue']['total_revenue']); ?></h3>
                                    <p class="mb-0">Total Revenue</p>
                                    <small class="opacity-75">+₹<?php echo number_format($metrics['revenue']['recent_revenue']); ?> this period</small>
                                </div>
                                <i class="fas fa-rupee-sign fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card metric-card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h3 class="mb-0"><?php echo number_format($metrics['jobs']['active_jobs']); ?></h3>
                                    <p class="mb-0">Active Jobs</p>
                                    <small class="opacity-75">+<?php echo $metrics['jobs']['new_jobs']; ?> new jobs</small>
                                </div>
                                <i class="fas fa-briefcase fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <!-- User Growth Chart -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line me-2"></i>User Growth
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="userGrowthChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interview Performance Chart -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Interview Performance
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="interviewChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue and Domain Analytics -->
            <div class="row g-4 mb-4">
                <!-- Revenue Chart -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-area me-2"></i>Revenue Trends
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Domains -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-trophy me-2"></i>Top Interview Domains
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach (array_slice($top_domains, 0, 5) as $index => $domain): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($domain['domain']); ?></h6>
                                        <small class="text-muted"><?php echo $domain['interview_count']; ?> interviews</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $domain['avg_score'] >= 7 ? 'success' : ($domain['avg_score'] >= 5 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($domain['avg_score'], 1); ?>/10
                                        </span>
                                    </div>
                                </div>
                                <?php if ($index < 4): ?>
                                    <hr class="my-2">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Health and User Distribution -->
            <div class="row g-4 mb-4">
                <!-- System Health -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-heartbeat me-2"></i>System Health
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="text-center">
                                        <div class="progress-ring mx-auto mb-2" style="width: 80px; height: 80px;">
                                            <svg width="80" height="80">
                                                <circle cx="40" cy="40" r="30" stroke="#e9ecef" stroke-width="8" fill="transparent"/>
                                                <circle cx="40" cy="40" r="30" stroke="#28a745" stroke-width="8" fill="transparent"
                                                        stroke-dasharray="<?php echo min(($system_health['active_sessions'] / 100) * 188, 188); ?> 188"
                                                        class="progress-ring-circle"/>
                                            </svg>
                                        </div>
                                        <h6 class="mb-0"><?php echo $system_health['active_sessions']; ?></h6>
                                        <small class="text-muted">Active Sessions</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-center">
                                        <div class="progress-ring mx-auto mb-2" style="width: 80px; height: 80px;">
                                            <svg width="80" height="80">
                                                <circle cx="40" cy="40" r="30" stroke="#e9ecef" stroke-width="8" fill="transparent"/>
                                                <circle cx="40" cy="40" r="30" stroke="#17a2b8" stroke-width="8" fill="transparent"
                                                        stroke-dasharray="<?php echo min(($system_health['db_size'] / 1000) * 188, 188); ?> 188"
                                                        class="progress-ring-circle"/>
                                            </svg>
                                        </div>
                                        <h6 class="mb-0"><?php echo $system_health['db_size']; ?>MB</h6>
                                        <small class="text-muted">Database Size</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Error Rate (24h)</span>
                                    <span class="badge bg-<?php echo $system_health['error_count'] > 10 ? 'danger' : ($system_health['error_count'] > 5 ? 'warning' : 'success'); ?>">
                                        <?php echo $system_health['error_count']; ?> errors
                                    </span>
                                </div>
                                <div class="progress mt-2">
                                    <div class="progress-bar bg-<?php echo $system_health['error_count'] > 10 ? 'danger' : ($system_health['error_count'] > 5 ? 'warning' : 'success'); ?>" 
                                         style="width: <?php echo min(($system_health['error_count'] / 20) * 100, 100); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Distribution -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-pie me-2"></i>User Distribution
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="userDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row g-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clock me-2"></i>Recent Activity
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentActivityTable">
                                        <!-- Activity data will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart.js configurations
        Chart.defaults.font.family = 'Inter, sans-serif';
        Chart.defaults.color = '#6c757d';

        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthData = <?php echo json_encode($daily_analytics['users']); ?>;
        
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: userGrowthData.map(item => new Date(item.date).toLocaleDateString()),
                datasets: [{
                    label: 'New Users',
                    data: userGrowthData.map(item => item.count),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Interview Performance Chart
        const interviewCtx = document.getElementById('interviewChart').getContext('2d');
        const interviewData = <?php echo json_encode($daily_analytics['interviews']); ?>;
        
        new Chart(interviewCtx, {
            type: 'bar',
            data: {
                labels: interviewData.map(item => new Date(item.date).toLocaleDateString()),
                datasets: [{
                    label: 'Interviews',
                    data: interviewData.map(item => item.count),
                    backgroundColor: '#28a745',
                    borderRadius: 4
                }, {
                    label: 'Avg Score',
                    data: interviewData.map(item => item.avg_score),
                    type: 'line',
                    borderColor: '#ffc107',
                    backgroundColor: 'transparent',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left'
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        min: 0,
                        max: 10,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = <?php echo json_encode($daily_analytics['revenue']); ?>;
        
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: revenueData.map(item => new Date(item.date).toLocaleDateString()),
                datasets: [{
                    label: 'Revenue (₹)',
                    data: revenueData.map(item => item.revenue),
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
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

        // User Distribution Chart
        const userDistCtx = document.getElementById('userDistributionChart').getContext('2d');
        const userDistData = {
            candidates: <?php echo $metrics['users']['candidates']; ?>,
            companies: <?php echo $metrics['users']['companies']; ?>,
            experts: <?php echo $metrics['users']['experts']; ?>
        };
        
        new Chart(userDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Candidates', 'Companies', 'Experts'],
                datasets: [{
                    data: [userDistData.candidates, userDistData.companies, userDistData.experts],
                    backgroundColor: ['#007bff', '#28a745', '#ffc107'],
                    borderWidth: 0
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

        // Load recent activity
        function loadRecentActivity() {
            fetch('get-recent-activity.php')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('recentActivityTable');
                tbody.innerHTML = '';
                
                data.activities.forEach(activity => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${new Date(activity.created_at).toLocaleString()}</td>
                        <td>${activity.user_name}</td>
                        <td>${activity.action}</td>
                        <td>${activity.details}</td>
                        <td><span class="badge bg-${activity.status === 'success' ? 'success' : 'warning'}">${activity.status}</span></td>
                    `;
                    tbody.appendChild(row);
                });
            })
            .catch(error => console.error('Error loading activity:', error));
        }

        // Export report
        function exportReport() {
            const startDate = '<?php echo $start_date; ?>';
            const endDate = '<?php echo $end_date; ?>';
            window.open(`export-analytics.php?start_date=${startDate}&end_date=${endDate}&format=pdf`, '_blank');
        }

        // Load recent activity on page load
        document.addEventListener('DOMContentLoaded', loadRecentActivity);

        // Auto-refresh every 30 seconds
        setInterval(loadRecentActivity, 30000);
    </script>
</body>
</html>
