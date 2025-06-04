<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['company']);

// Get company data
$company_query = "SELECT c.*, u.full_name, u.email FROM companies c 
                 JOIN users u ON c.user_id = u.id 
                 WHERE c.user_id = :user_id";
$company_stmt = $db->prepare($company_query);
$company_stmt->bindParam(':user_id', $_SESSION['user_id']);
$company_stmt->execute();
$company = $company_stmt->fetch(PDO::FETCH_ASSOC);

// Get interview statistics
$stats_query = "SELECT 
    COUNT(*) as total_interviews,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_interviews,
    COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_interviews,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_interviews
    FROM company_interviews WHERE company_id = :company_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':company_id', $company['id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent interviews
$recent_query = "SELECT ci.*, u.full_name as candidate_name 
                FROM company_interviews ci 
                LEFT JOIN candidates c ON ci.candidate_id = c.id 
                LEFT JOIN users u ON c.user_id = u.id 
                WHERE ci.company_id = :company_id 
                ORDER BY ci.created_at DESC LIMIT 5";
$recent_stmt = $db->prepare($recent_query);
$recent_stmt->bindParam(':company_id', $company['id']);
$recent_stmt->execute();
$recent_interviews = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard - SkillVerge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="p-3 border-bottom">
            <h5 class="fw-bold text-success mb-0">
                <i class="fas fa-building me-2"></i>SkillVerge
            </h5>
            <small class="text-muted">Company Portal</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="create-interview.php">
                        <i class="fas fa-plus-circle"></i>Create Interview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage-interviews.php">
                        <i class="fas fa-list"></i>Manage Interviews
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="candidates.php">
                        <i class="fas fa-users"></i>Candidates
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-bar"></i>Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-building"></i>Company Profile
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
                <h2 class="fw-bold mb-1">Welcome, <?php echo htmlspecialchars($company['company_name']); ?>!</h2>
                <p class="text-muted mb-0">Manage your interviews and find the best candidates</p>
            </div>
            <div class="d-flex gap-2">
                <a href="create-interview.php" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>Create Interview
                </a>
                <a href="candidates.php" class="btn btn-outline-primary">
                    <i class="fas fa-users me-2"></i>View Candidates
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stats-value"><?php echo $stats['total_interviews'] ?? 0; ?></div>
                    <div class="stats-label">Total Interviews</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-value"><?php echo $stats['completed_interviews'] ?? 0; ?></div>
                    <div class="stats-label">Completed</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-value"><?php echo $stats['scheduled_interviews'] ?? 0; ?></div>
                    <div class="stats-label">Scheduled</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon danger">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stats-value"><?php echo $stats['pending_interviews'] ?? 0; ?></div>
                    <div class="stats-label">Pending</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-link text-primary me-2"></i>Share Interview Link
                        </h5>
                        <p class="card-text">Create a shareable interview link that candidates can use to join interviews directly.</p>
                        <button class="btn btn-primary" onclick="generateInterviewLink()">Generate Link</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-calendar-plus text-success me-2"></i>Schedule Interview
                        </h5>
                        <p class="card-text">Set up scheduled interviews with specific candidates at designated times.</p>
                        <a href="create-interview.php?type=scheduled" class="btn btn-success">Schedule Interview</a>
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
                                <i class="fas fa-clipboard-list text-muted" style="font-size: 3rem;"></i>
                                <h6 class="text-muted mt-3">No interviews yet</h6>
                                <p class="text-muted">Create your first interview to start evaluating candidates.</p>
                                <a href="create-interview.php" class="btn btn-success">Create Interview</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Interview Title</th>
                                            <th>Candidate</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_interviews as $interview): ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-briefcase text-primary me-2"></i>
                                                    <?php echo htmlspecialchars($interview['interview_title']); ?>
                                                </td>
                                                <td>
                                                    <?php echo $interview['candidate_name'] ? htmlspecialchars($interview['candidate_name']) : 'Not assigned'; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $interview['status'] === 'completed' ? 'success' : 
                                                            ($interview['status'] === 'scheduled' ? 'warning' : 
                                                            ($interview['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst($interview['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($interview['created_at'])); ?></td>
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
                            <i class="fas fa-chart-pie text-warning me-2"></i>Interview Analytics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Completion Rate</span>
                                <span><?php echo $stats['total_interviews'] > 0 ? round(($stats['completed_interviews'] / $stats['total_interviews']) * 100) : 0; ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?php echo $stats['total_interviews'] > 0 ? round(($stats['completed_interviews'] / $stats['total_interviews']) * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Response Rate</span>
                                <span><?php echo $stats['total_interviews'] > 0 ? round((($stats['completed_interviews'] + $stats['scheduled_interviews']) / $stats['total_interviews']) * 100) : 0; ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" style="width: <?php echo $stats['total_interviews'] > 0 ? round((($stats['completed_interviews'] + $stats['scheduled_interviews']) / $stats['total_interviews']) * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="analytics.php" class="btn btn-outline-primary btn-sm">View Full Analytics</a>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-lightbulb text-warning me-2"></i>Quick Tips
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Use clear interview titles to attract candidates</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Schedule interviews during business hours</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Provide detailed job descriptions</small>
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Follow up with candidates promptly</small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interview Link Modal -->
    <div class="modal fade" id="interviewLinkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Interview Link Generated</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Share this link with candidates to join the interview:</p>
                    <div class="input-group">
                        <input type="text" class="form-control" id="interviewLink" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyLink()">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <small class="text-muted">This link will be valid for 7 days.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="shareLink()">Share Link</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function generateInterviewLink() {
            // Generate a unique interview link
            const linkId = 'iv_' + Math.random().toString(36).substr(2, 9);
            const baseUrl = window.location.origin;
            const interviewLink = `${baseUrl}/interview/${linkId}`;
            
            document.getElementById('interviewLink').value = interviewLink;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('interviewLinkModal'));
            modal.show();
            
            // Save link to database (you would implement this)
            // saveInterviewLink(linkId, interviewLink);
        }
        
        function copyLink() {
            const linkInput = document.getElementById('interviewLink');
            linkInput.select();
            document.execCommand('copy');
            
            // Show feedback
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }
        
        function shareLink() {
            const link = document.getElementById('interviewLink').value;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Interview Link - SkillVerge',
                    text: 'Join the interview using this link:',
                    url: link
                });
            } else {
                // Fallback - copy to clipboard
                copyLink();
            }
        }
    </script>
</body>
</html>
