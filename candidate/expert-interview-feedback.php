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

// Get completed expert interviews with feedback
$feedback_query = "SELECT ei.*, e.full_name as expert_name, e.specialization, e.profile_image,
                  ee.technical_score, ee.communication_score, ee.problem_solving_score, 
                  ee.cultural_fit_score, ee.overall_impression, ee.detailed_notes,
                  ef.strengths, ef.areas_for_improvement, ef.specific_recommendations, 
                  ef.next_steps, ef.follow_up_resources, ef.shared_at
                  FROM expert_interviews ei
                  JOIN experts e ON ei.expert_id = e.id
                  JOIN users u ON e.user_id = u.id
                  LEFT JOIN expert_evaluations ee ON ei.id = ee.interview_id
                  LEFT JOIN expert_feedback ef ON ei.id = ef.interview_id AND ef.is_shared_with_candidate = 1
                  WHERE ei.candidate_id = :candidate_id AND ei.status = 'completed'
                  ORDER BY ei.completed_at DESC";
$feedback_stmt = $db->prepare($feedback_query);
$feedback_stmt->bindParam(':candidate_id', $candidate['id']);
$feedback_stmt->execute();
$expert_feedbacks = $feedback_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expert Interview Feedback - SkillVerge</title>
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
            <small class="text-muted">Expert Feedback</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="expert-interviews.php">
                        <i class="fas fa-users"></i>Expert Interviews
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="expert-interview-feedback.php">
                        <i class="fas fa-comments"></i>Expert Feedback
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-line"></i>Performance Analytics
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Expert Interview Feedback</h2>
                    <p class="text-muted mb-0">Detailed feedback from industry experts</p>
                </div>
            </div>

            <?php if (empty($expert_feedbacks)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-comments fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">No Expert Feedback Yet</h4>
                    <p class="text-muted">Complete expert interviews to receive detailed feedback from industry professionals.</p>
                    <a href="expert-interviews.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-2"></i>Book Expert Interview
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($expert_feedbacks as $feedback): ?>
                        <div class="col-12">
                            <div class="card feedback-card">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center">
                                                <div class="expert-avatar me-3">
                                                    <?php if ($feedback['profile_image']): ?>
                                                        <img src="<?php echo htmlspecialchars($feedback['profile_image']); ?>" 
                                                             alt="Expert" class="rounded-circle" width="50" height="50">
                                                    <?php else: ?>
                                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white" 
                                                             style="width: 50px; height: 50px;">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h5 class="mb-0"><?php echo htmlspecialchars($feedback['expert_name']); ?></h5>
                                                    <small class="text-muted"><?php echo htmlspecialchars($feedback['specialization']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="interview-date">
                                                <small class="text-muted">Interview Date</small><br>
                                                <strong><?php echo date('M j, Y', strtotime($feedback['completed_at'])); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <?php if ($feedback['technical_score']): ?>
                                        <!-- Scores Section -->
                                        <div class="row g-3 mb-4">
                                            <div class="col-md-3">
                                                <div class="score-item">
                                                    <div class="score-circle">
                                                        <span class="score-value"><?php echo $feedback['technical_score']; ?></span>
                                                        <span class="score-max">/10</span>
                                                    </div>
                                                    <div class="score-label">Technical</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="score-item">
                                                    <div class="score-circle">
                                                        <span class="score-value"><?php echo $feedback['communication_score']; ?></span>
                                                        <span class="score-max">/10</span>
                                                    </div>
                                                    <div class="score-label">Communication</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="score-item">
                                                    <div class="score-circle">
                                                        <span class="score-value"><?php echo $feedback['problem_solving_score']; ?></span>
                                                        <span class="score-max">/10</span>
                                                    </div>
                                                    <div class="score-label">Problem Solving</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="score-item">
                                                    <div class="score-circle">
                                                        <span class="score-value"><?php echo $feedback['cultural_fit_score']; ?></span>
                                                        <span class="score-max">/10</span>
                                                    </div>
                                                    <div class="score-label">Cultural Fit</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Overall Impression -->
                                        <div class="mb-4">
                                            <h6 class="fw-bold">Overall Impression</h6>
                                            <span class="badge bg-<?php 
                                                echo $feedback['overall_impression'] === 'strong_hire' ? 'success' : 
                                                    ($feedback['overall_impression'] === 'hire' ? 'primary' : 
                                                    ($feedback['overall_impression'] === 'no_hire' ? 'warning' : 'danger')); 
                                            ?> fs-6">
                                                <?php echo ucwords(str_replace('_', ' ', $feedback['overall_impression'])); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($feedback['strengths'] || $feedback['areas_for_improvement']): ?>
                                        <div class="row g-4">
                                            <?php if ($feedback['strengths']): ?>
                                                <div class="col-md-6">
                                                    <div class="feedback-section strengths">
                                                        <h6 class="fw-bold text-success">
                                                            <i class="fas fa-thumbs-up me-2"></i>Strengths
                                                        </h6>
                                                        <p><?php echo nl2br(htmlspecialchars($feedback['strengths'])); ?></p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($feedback['areas_for_improvement']): ?>
                                                <div class="col-md-6">
                                                    <div class="feedback-section improvements">
                                                        <h6 class="fw-bold text-warning">
                                                            <i class="fas fa-arrow-up me-2"></i>Areas for Improvement
                                                        </h6>
                                                        <p><?php echo nl2br(htmlspecialchars($feedback['areas_for_improvement'])); ?></p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($feedback['specific_recommendations']): ?>
                                        <div class="feedback-section recommendations mt-4">
                                            <h6 class="fw-bold text-primary">
                                                <i class="fas fa-lightbulb me-2"></i>Specific Recommendations
                                            </h6>
                                            <p><?php echo nl2br(htmlspecialchars($feedback['specific_recommendations'])); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($feedback['next_steps']): ?>
                                        <div class="feedback-section next-steps mt-4">
                                            <h6 class="fw-bold text-info">
                                                <i class="fas fa-route me-2"></i>Next Steps
                                            </h6>
                                            <p><?php echo nl2br(htmlspecialchars($feedback['next_steps'])); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($feedback['follow_up_resources']): ?>
                                        <?php $resources = json_decode($feedback['follow_up_resources'], true); ?>
                                        <?php if ($resources): ?>
                                            <div class="feedback-section resources mt-4">
                                                <h6 class="fw-bold text-secondary">
                                                    <i class="fas fa-book me-2"></i>Recommended Resources
                                                </h6>
                                                <ul class="list-unstyled">
                                                    <?php foreach ($resources as $resource): ?>
                                                        <li class="mb-2">
                                                            <a href="<?php echo htmlspecialchars($resource['url']); ?>" 
                                                               target="_blank" class="text-decoration-none">
                                                                <i class="fas fa-external-link-alt me-2"></i>
                                                                <?php echo htmlspecialchars($resource['title']); ?>
                                                            </a>
                                                            <?php if (isset($resource['description'])): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($resource['description']); ?></small>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($feedback['detailed_notes']): ?>
                                        <div class="feedback-section notes mt-4">
                                            <h6 class="fw-bold">
                                                <i class="fas fa-sticky-note me-2"></i>Expert Notes
                                            </h6>
                                            <div class="bg-light p-3 rounded">
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($feedback['detailed_notes'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-end mt-4">
                                        <small class="text-muted">
                                            Feedback shared on <?php echo date('M j, Y g:i A', strtotime($feedback['shared_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .feedback-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .score-item {
            text-align: center;
        }

        .score-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: white;
            font-weight: bold;
        }

        .score-value {
            font-size: 1.5rem;
        }

        .score-max {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .score-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }

        .feedback-section {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .feedback-section.strengths {
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
        }

        .feedback-section.improvements {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }

        .feedback-section.recommendations {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }

        .feedback-section.next-steps {
            background-color: #e0f2f1;
            border-left: 4px solid #009688;
        }

        .feedback-section.resources {
            background-color: #f3e5f5;
            border-left: 4px solid #9c27b0;
        }

        .feedback-section.notes {
            background-color: #fff8e1;
            border-left: 4px solid #ff9800;
        }
    </style>
</body>
</html>
