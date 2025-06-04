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

// Get interview statistics
$stats_query = "SELECT 
    COUNT(*) as total_interviews,
    AVG(overall_score) as avg_score,
    MAX(overall_score) as best_score
    FROM ai_interviews WHERE candidate_id = :candidate_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':candidate_id', $candidate['id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent interviews
$recent_query = "SELECT * FROM ai_interviews 
                WHERE candidate_id = :candidate_id 
                ORDER BY completed_at DESC LIMIT 5";
$recent_stmt = $db->prepare($recent_query);
$recent_stmt->bindParam(':candidate_id', $candidate['id']);
$recent_stmt->execute();
$recent_interviews = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming expert interviews
$expert_query = "SELECT * FROM expert_interviews 
                WHERE candidate_id = :candidate_id AND status = 'scheduled'
                ORDER BY scheduled_at ASC LIMIT 3";
$expert_stmt = $db->prepare($expert_query);
$expert_stmt->bindParam(':candidate_id', $candidate['id']);
$expert_stmt->execute();
$expert_interviews = $expert_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Dashboard - SkillVerge</title>
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
            <small class="text-muted">Candidate Portal</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
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
                    <a class="nav-link" href="recordings.php">
                        <i class="fas fa-video"></i>Mock Recordings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-line"></i>Performance Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="subscription.php">
                        <i class="fas fa-crown"></i>Subscription
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i>Profile
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
                <h2 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars($candidate['full_name']); ?>!</h2>
                <p class="text-muted mb-0">Ready to ace your next interview?</p>
            </div>
            <div class="d-flex gap-2">
                <?php if (!$candidate['is_premium']): ?>
                    <a href="subscription.php" class="btn btn-warning">
                        <i class="fas fa-crown me-2"></i>Upgrade to Premium
                    </a>
                <?php endif; ?>
                <button class="btn btn-primary" onclick="startQuickInterview()">
                    <i class="fas fa-play me-2"></i>Quick Interview
                </button>
            </div>
        </div>

        <!-- Premium Status Alert -->
        <?php if (!$candidate['is_premium'] && $candidate['trial_interviews_used'] >= 2): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                You've used all your free trial interviews. <a href="subscription.php" class="fw-bold">Upgrade to Premium</a> for unlimited access!
            </div>
        <?php elseif (!$candidate['is_premium']): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                You have <?php echo 2 - $candidate['trial_interviews_used']; ?> free trial interview(s) remaining.
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <i class="fas fa-microphone"></i>
                    </div>
                    <div class="stats-value"><?php echo $stats['total_interviews'] ?? 0; ?></div>
                    <div class="stats-label">Total Interviews</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon success">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stats-value"><?php echo number_format($stats['avg_score'] ?? 0, 1); ?></div>
                    <div class="stats-label">Average Score</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon warning">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stats-value"><?php echo number_format($stats['best_score'] ?? 0, 1); ?></div>
                    <div class="stats-label">Best Score</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon <?php echo $candidate['is_premium'] ? 'success' : 'danger'; ?>">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="stats-value"><?php echo $candidate['is_premium'] ? 'Premium' : 'Free'; ?></div>
                    <div class="stats-label">Account Status</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-robot text-primary me-2"></i>AI Mock Interview
                        </h5>
                        <p class="card-text">Practice with our AI interviewer and get instant feedback on your performance.</p>
                        <a href="ai-interview.php" class="btn btn-primary">Start AI Interview</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-users text-success me-2"></i>Expert Interview
                        </h5>
                        <p class="card-text">Book a session with industry experts for personalized guidance.</p>
                        <a href="expert-interviews.php" class="btn btn-success">Book Expert Session</a>
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
                            <i class="fas fa-history me-2"></i>Recent Interviews
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_interviews)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-microphone-slash text-muted" style="font-size: 3rem;"></i>
                                <h6 class="text-muted mt-3">No interviews yet</h6>
                                <p class="text-muted">Start your first AI mock interview to see your progress here.</p>
                                <a href="ai-interview.php" class="btn btn-primary">Start Interview</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Domain</th>
                                            <th>Score</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_interviews as $interview): ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-code text-primary me-2"></i>
                                                    <?php echo htmlspecialchars($interview['domain']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $interview['overall_score'] >= 7 ? 'success' : ($interview['overall_score'] >= 5 ? 'warning' : 'danger'); ?>">
                                                        <?php echo number_format($interview['overall_score'], 1); ?>/10
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($interview['completed_at'])); ?></td>
                                                <td>
                                                    <a href="interview-details.php?id=<?php echo $interview['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        View Details
                                                    </a>
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
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar text-success me-2"></i>Upcoming Sessions
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($expert_interviews)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-calendar-plus text-muted" style="font-size: 2rem;"></i>
                                <h6 class="text-muted mt-2">No upcoming sessions</h6>
                                <p class="text-muted small">Book an expert interview session.</p>
                                <a href="expert-interviews.php" class="btn btn-sm btn-success">Book Session</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($expert_interviews as $session): ?>
                                <div class="border rounded p-3 mb-3">
                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($session['expert_name']); ?></h6>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($session['scheduled_at'])); ?>
                                    </p>
                                    <a href="<?php echo $session['meeting_link']; ?>" class="btn btn-sm btn-success" target="_blank">
                                        Join Meeting
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function startQuickInterview() {
            window.location.href = 'ai-interview.php';
        }

        // Auto-refresh upcoming sessions every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
