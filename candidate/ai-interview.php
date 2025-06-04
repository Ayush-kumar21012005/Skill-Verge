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

// Validate candidate exists
if (!$candidate) {
    header('Location: ../login.php?error=invalid_session');
    exit();
}

// Check database connection
if (!$db) {
    die('Database connection failed');
}

// Check if user can take interview
$can_take_interview = $candidate['is_premium'] || ($candidate['trial_interviews_used'] < 2);

// Get available domains
$domains_query = "SELECT * FROM interview_domains WHERE is_active = 1";
$domains_stmt = $db->prepare($domains_query);
$domains_stmt->execute();
$domains = $domains_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Mock Interview - SkillVerge</title>
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
            <small class="text-muted">AI Interview</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="ai-interview.php">
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
            <?php if (!$can_take_interview): ?>
                <!-- Upgrade Required -->
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card text-center">
                            <div class="card-body py-5">
                                <i class="fas fa-crown text-warning" style="font-size: 4rem;"></i>
                                <h3 class="mt-4 mb-3">Upgrade to Premium Required</h3>
                                <p class="text-muted mb-4">You've used all your free trial interviews. Upgrade to Premium for unlimited AI mock interviews and expert sessions.</p>
                                <a href="subscription.php" class="btn btn-warning btn-lg">
                                    <i class="fas fa-crown me-2"></i>Upgrade Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Interview Setup -->
                <div id="interview-setup" class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="interview-container">
                            <div class="interview-header">
                                <h2 class="mb-0">
                                    <i class="fas fa-robot me-3"></i>AI Mock Interview
                                </h2>
                                <p class="mb-0 mt-2">Practice with our AI interviewer and get instant feedback</p>
                            </div>
                            
                            <div class="interview-content">
                                <form id="interview-form">
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label for="domain" class="form-label fw-bold">Select Interview Domain</label>
                                            <select class="form-control" id="domain" name="domain" required>
                                                <option value="">Choose your domain</option>
                                                <?php foreach ($domains as $domain): ?>
                                                    <option value="<?php echo $domain['name']; ?>" 
                                                            data-description="<?php echo htmlspecialchars($domain['description']); ?>">
                                                        <?php echo $domain['name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div id="domain-description" class="text-muted small mt-2"></div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="difficulty" class="form-label fw-bold">Difficulty Level</label>
                                            <select class="form-control" id="difficulty" name="difficulty" required>
                                                <option value="">Select difficulty</option>
                                                <option value="beginner">Beginner (0-2 years)</option>
                                                <option value="intermediate">Intermediate (2-5 years)</option>
                                                <option value="advanced">Advanced (5+ years)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label for="duration" class="form-label fw-bold">Interview Duration</label>
                                            <select class="form-control" id="duration" name="duration" required>
                                                <option value="15">15 minutes (5 questions)</option>
                                                <option value="30" selected>30 minutes (10 questions)</option>
                                                <option value="45">45 minutes (15 questions)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <h6><i class="fas fa-info-circle me-2"></i>Before you start:</h6>
                                                <ul class="mb-0">
                                                    <li>Ensure you have a stable internet connection</li>
                                                    <li>Allow microphone access when prompted</li>
                                                    <li>Find a quiet environment for the interview</li>
                                                    <li>Speak clearly and at a moderate pace</li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 text-center">
                                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                                <i class="fas fa-play me-2"></i>Start Interview
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interview Interface (Hidden initially) -->
                <div id="interview-interface" class="d-none">
                    <div class="row">
                        <div class="col-lg-8 mx-auto">
                            <div class="interview-container">
                                <div class="interview-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="mb-0" id="interview-title">Software Development Interview</h4>
                                            <small id="interview-progress">Question 1 of 10</small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold" id="timer">30:00</div>
                                            <small class="text-muted">Time Remaining</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="interview-content">
                                    <!-- Current Question -->
                                    <div class="question-card">
                                        <div class="question-number" id="question-number">1</div>
                                        <h5 class="mb-3" id="current-question">Loading question...</h5>
                                        <div class="text-muted small" id="question-hint"></div>
                                    </div>
                                    
                                    <!-- Recording Controls -->
                                    <div class="recording-controls">
                                        <button class="record-btn start" id="start-recording" onclick="startRecording()">
                                            <i class="fas fa-microphone"></i>
                                        </button>
                                        <button class="record-btn stop d-none" id="stop-recording" onclick="stopRecording()">
                                            <i class="fas fa-stop"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="text-center">
                                        <div id="recording-status" class="text-muted mb-3">Click the microphone to start recording your answer</div>
                                        <div id="audio-visualizer" class="d-none">
                                            <div class="spinner"></div>
                                            <span class="ms-2">Recording...</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Navigation -->
                                    <div class="d-flex justify-content-between mt-4">
                                        <button class="btn btn-outline-secondary" id="prev-question" onclick="previousQuestion()" disabled>
                                            <i class="fas fa-chevron-left me-2"></i>Previous
                                        </button>
                                        <button class="btn btn-primary" id="next-question" onclick="nextQuestion()" disabled>
                                            Next<i class="fas fa-chevron-right ms-2"></i>
                                        </button>
                                        <button class="btn btn-success d-none" id="finish-interview" onclick="finishInterview()">
                                            <i class="fas fa-check me-2"></i>Finish Interview
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Interface (Hidden initially) -->
                <div id="interview-results" class="d-none">
                    <div class="row">
                        <div class="col-lg-10 mx-auto">
                            <div class="interview-container">
                                <div class="interview-header text-center">
                                    <i class="fas fa-trophy text-warning" style="font-size: 3rem;"></i>
                                    <h2 class="mt-3 mb-0">Interview Completed!</h2>
                                    <p class="mb-0 mt-2">Here's your detailed performance analysis</p>
                                </div>
                                
                                <div class="interview-content">
                                    <div class="row g-4">
                                        <!-- Overall Score -->
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <div class="display-4 fw-bold text-primary" id="overall-score">8.5</div>
                                                <h5 class="text-muted">Overall Score</h5>
                                            </div>
                                        </div>
                                        
                                        <!-- Score Breakdown -->
                                        <div class="col-md-8">
                                            <h6 class="fw-bold mb-3">Score Breakdown</h6>
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Technical Accuracy</span>
                                                    <span id="technical-score">8.0/10</span>
                                                </div>
                                                <div class="progress">
                                                    <div class="progress-bar bg-primary" id="technical-progress" style="width: 80%"></div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Communication Clarity</span>
                                                    <span id="communication-score">9.0/10</span>
                                                </div>
                                                <div class="progress">
                                                    <div class="progress-bar bg-success" id="communication-progress" style="width: 90%"></div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Confidence Level</span>
                                                    <span id="confidence-score">8.5/10</span>
                                                </div>
                                                <div class="progress">
                                                    <div class="progress-bar bg-warning" id="confidence-progress" style="width: 85%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- AI Feedback -->
                                    <div class="mt-4">
                                        <h6 class="fw-bold mb-3">AI Feedback & Recommendations</h6>
                                        <div class="alert alert-light border">
                                            <div id="ai-feedback">
                                                <p><strong>Strengths:</strong></p>
                                                <ul>
                                                    <li>Clear and articulate communication</li>
                                                    <li>Good understanding of core concepts</li>
                                                    <li>Confident delivery</li>
                                                </ul>
                                                <p><strong>Areas for Improvement:</strong></p>
                                                <ul>
                                                    <li>Consider providing more specific examples</li>
                                                    <li>Practice explaining complex concepts more simply</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="text-center mt-4">
                                        <a href="dashboard.php" class="btn btn-outline-primary me-3">
                                            <i class="fas fa-home me-2"></i>Back to Dashboard
                                        </a>
                                        <button class="btn btn-success me-3" onclick="bookExpertSession()">
                                            <i class="fas fa-users me-2"></i>Book Expert Session
                                        </button>
                                        <button class="btn btn-primary" onclick="startNewInterview()">
                                            <i class="fas fa-redo me-2"></i>Take Another Interview
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/ai-interview.js"></script>
</body>
</html>
