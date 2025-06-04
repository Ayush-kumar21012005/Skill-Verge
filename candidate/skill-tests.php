<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['candidate']);

// Get candidate data
$candidate_query = "SELECT * FROM candidates WHERE user_id = :user_id";
$candidate_stmt = $db->prepare($candidate_query);
$candidate_stmt->bindParam(':user_id', $_SESSION['user_id']);
$candidate_stmt->execute();
$candidate = $candidate_stmt->fetch(PDO::FETCH_ASSOC);

// Get available skill tests
$tests_query = "SELECT st.*, 
                (SELECT COUNT(*) FROM skill_test_attempts sta WHERE sta.test_id = st.id AND sta.candidate_id = :candidate_id) as attempt_count,
                (SELECT MAX(score) FROM skill_test_attempts sta WHERE sta.test_id = st.id AND sta.candidate_id = :candidate_id AND sta.status = 'completed') as best_score
                FROM skill_tests st 
                WHERE st.is_active = 1 
                ORDER BY st.skill_category, st.difficulty";
$tests_stmt = $db->prepare($tests_query);
$tests_stmt->bindParam(':candidate_id', $candidate['id']);
$tests_stmt->execute();
$tests = $tests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group tests by category
$grouped_tests = [];
foreach ($tests as $test) {
    $grouped_tests[$test['skill_category']][] = $test;
}

// Get recent attempts
$attempts_query = "SELECT sta.*, st.name as test_name, st.skill_category 
                  FROM skill_test_attempts sta 
                  JOIN skill_tests st ON sta.test_id = st.id 
                  WHERE sta.candidate_id = :candidate_id 
                  ORDER BY sta.started_at DESC 
                  LIMIT 10";
$attempts_stmt = $db->prepare($attempts_query);
$attempts_stmt->bindParam(':candidate_id', $candidate['id']);
$attempts_stmt->execute();
$recent_attempts = $attempts_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill Tests - SkillVerge</title>
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
            <small class="text-muted">Skill Tests</small>
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
                    <a class="nav-link" href="resume-parser.php">
                        <i class="fas fa-file-alt"></i>Resume Parser
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="skill-tests.php">
                        <i class="fas fa-tasks"></i>Skill Tests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="job-board.php">
                        <i class="fas fa-briefcase"></i>Job Board
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
                    <h2 class="fw-bold mb-1">Skill Assessment Tests</h2>
                    <p class="text-muted mb-0">Test your skills and get certified in various technologies</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#leaderboardModal">
                        <i class="fas fa-trophy me-2"></i>Leaderboard
                    </button>
                    <a href="my-certificates.php" class="btn btn-success">
                        <i class="fas fa-certificate me-2"></i>My Certificates
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h3 class="mb-0"><?php echo count($recent_attempts); ?></h3>
                                    <p class="mb-0">Tests Taken</p>
                                </div>
                                <i class="fas fa-tasks fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <?php 
                                    $passed_tests = array_filter($recent_attempts, function($attempt) {
                                        return $attempt['status'] === 'completed' && $attempt['score'] >= 70;
                                    });
                                    ?>
                                    <h3 class="mb-0"><?php echo count($passed_tests); ?></h3>
                                    <p class="mb-0">Tests Passed</p>
                                </div>
                                <i class="fas fa-check-circle fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <?php 
                                    $avg_score = 0;
                                    $completed_tests = array_filter($recent_attempts, function($attempt) {
                                        return $attempt['status'] === 'completed';
                                    });
                                    if (!empty($completed_tests)) {
                                        $avg_score = array_sum(array_column($completed_tests, 'score')) / count($completed_tests);
                                    }
                                    ?>
                                    <h3 class="mb-0"><?php echo number_format($avg_score, 1); ?>%</h3>
                                    <p class="mb-0">Average Score</p>
                                </div>
                                <i class="fas fa-chart-line fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h3 class="mb-0"><?php echo count($grouped_tests); ?></h3>
                                    <p class="mb-0">Categories</p>
                                </div>
                                <i class="fas fa-layer-group fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Categories -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <h4 class="fw-bold mb-3">Available Tests</h4>
                    
                    <?php foreach ($grouped_tests as $category => $category_tests): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-code me-2"></i><?php echo htmlspecialchars($category); ?>
                                    <span class="badge bg-secondary ms-2"><?php echo count($category_tests); ?> tests</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <?php foreach ($category_tests as $test): ?>
                                        <div class="col-md-6">
                                            <div class="test-card border rounded p-3 h-100">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($test['name']); ?></h6>
                                                    <span class="badge bg-<?php 
                                                        echo $test['difficulty'] === 'beginner' ? 'success' : 
                                                            ($test['difficulty'] === 'intermediate' ? 'warning' : 
                                                            ($test['difficulty'] === 'advanced' ? 'danger' : 'dark')); 
                                                    ?>">
                                                        <?php echo ucfirst($test['difficulty']); ?>
                                                    </span>
                                                </div>
                                                
                                                <p class="text-muted small mb-3"><?php echo htmlspecialchars($test['description']); ?></p>
                                                
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="d-flex gap-3">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i><?php echo $test['time_limit_minutes']; ?> min
                                                        </small>
                                                        <small class="text-muted">
                                                            <i class="fas fa-question-circle me-1"></i><?php echo count(json_decode($test['questions'], true)); ?> questions
                                                        </small>
                                                    </div>
                                                    <?php if ($test['best_score']): ?>
                                                        <span class="badge bg-<?php echo $test['best_score'] >= 70 ? 'success' : 'warning'; ?>">
                                                            Best: <?php echo number_format($test['best_score'], 1); ?>%
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="d-flex gap-2">
                                                    <?php if ($test['attempt_count'] > 0): ?>
                                                        <button class="btn btn-outline-primary btn-sm flex-grow-1" onclick="retakeTest(<?php echo $test['id']; ?>)">
                                                            <i class="fas fa-redo me-1"></i>Retake
                                                        </button>
                                                        <button class="btn btn-outline-info btn-sm" onclick="viewResults(<?php echo $test['id']; ?>)">
                                                            <i class="fas fa-chart-bar"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-primary btn-sm flex-grow-1" onclick="startTest(<?php echo $test['id']; ?>)">
                                                            <i class="fas fa-play me-1"></i>Start Test
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-secondary btn-sm" onclick="previewTest(<?php echo $test['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Recent Attempts -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-history me-2"></i>Recent Attempts
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_attempts)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-tasks text-muted" style="font-size: 2rem;"></i>
                                    <p class="text-muted mt-2 mb-0">No tests taken yet</p>
                                    <small class="text-muted">Start your first skill test!</small>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($recent_attempts, 0, 5) as $attempt): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($attempt['test_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($attempt['skill_category']); ?></small>
                                            </div>
                                            <?php if ($attempt['status'] === 'completed'): ?>
                                                <span class="badge bg-<?php echo $attempt['score'] >= 70 ? 'success' : 'warning'; ?>">
                                                    <?php echo number_format($attempt['score'], 1); ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo ucfirst($attempt['status']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($attempt['started_at'])); ?></small>
                                    </div>
                                <?php endforeach; ?>
                                <div class="text-center mt-3">
                                    <a href="test-history.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Skill Progress -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Skill Progress
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="skillProgressChart" width="300" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Achievements -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-trophy me-2"></i>Achievements
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="achievement-item d-flex align-items-center mb-3">
                                <div class="achievement-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">First Test</h6>
                                    <small class="text-muted">Complete your first skill test</small>
                                </div>
                            </div>
                            
                            <div class="achievement-item d-flex align-items-center mb-3">
                                <div class="achievement-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Perfect Score</h6>
                                    <small class="text-muted">Score 100% on any test</small>
                                </div>
                            </div>
                            
                            <div class="achievement-item d-flex align-items-center">
                                <div class="achievement-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-fire"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Test Streak</h6>
                                    <small class="text-muted">Take tests 5 days in a row</small>
                                </div>
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
        function startTest(testId) {
            if (confirm('Are you ready to start this test? Make sure you have enough time to complete it.')) {
                window.location.href = `take-test.php?id=${testId}`;
            }
        }

        function retakeTest(testId) {
            if (confirm('Are you sure you want to retake this test? Your previous score will remain in history.')) {
                window.location.href = `take-test.php?id=${testId}&retake=1`;
            }
        }

        function previewTest(testId) {
            window.open(`preview-test.php?id=${testId}`, '_blank', 'width=800,height=600');
        }

        function viewResults(testId) {
            window.location.href = `test-results.php?test_id=${testId}`;
        }

        // Skill Progress Chart
        const ctx = document.getElementById('skillProgressChart').getContext('2d');
        
        // Sample data - in production, this would come from PHP
        const skillData = {
            labels: ['Programming', 'Web Dev', 'Databases', 'Cloud'],
            datasets: [{
                data: [85, 70, 60, 45],
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0'
                ],
                borderWidth: 0
            }]
        };

        new Chart(ctx, {
            type: 'doughnut',
            data: skillData,
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
    </script>
</body>
</html>
