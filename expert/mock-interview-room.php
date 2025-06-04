<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['expert']);

// Get interview details
$interview_id = $_GET['interview_id'] ?? null;
if (!$interview_id) {
    header('Location: dashboard.php');
    exit();
}

// Get expert data
$expert_query = "SELECT * FROM experts WHERE user_id = :user_id";
$expert_stmt = $db->prepare($expert_query);
$expert_stmt->bindParam(':user_id', $_SESSION['user_id']);
$expert_stmt->execute();
$expert = $expert_stmt->fetch(PDO::FETCH_ASSOC);

// Get interview and candidate details
$interview_query = "SELECT ei.*, c.id as candidate_id, u.full_name as candidate_name, u.email as candidate_email,
                   c.experience_level, c.preferred_role
                   FROM expert_interviews ei
                   JOIN candidates c ON ei.candidate_id = c.id
                   JOIN users u ON c.user_id = u.id
                   WHERE ei.id = :interview_id AND ei.expert_id = :expert_id";
$interview_stmt = $db->prepare($interview_query);
$interview_stmt->bindParam(':interview_id', $interview_id);
$interview_stmt->bindParam(':expert_id', $expert['id']);
$interview_stmt->execute();
$interview = $interview_stmt->fetch(PDO::FETCH_ASSOC);

if (!$interview) {
    header('Location: dashboard.php');
    exit();
}

// Get candidate brief
$brief_query = "SELECT brief_data FROM candidate_briefs 
               WHERE candidate_id = :candidate_id AND expert_id = :expert_id
               ORDER BY generated_at DESC LIMIT 1";
$brief_stmt = $db->prepare($brief_query);
$brief_stmt->bindParam(':candidate_id', $interview['candidate_id']);
$brief_stmt->bindParam(':expert_id', $expert['id']);
$brief_stmt->execute();
$brief_data = $brief_stmt->fetchColumn();
$candidate_brief = $brief_data ? json_decode($brief_data, true) : null;

// Get interview question templates
$questions_query = "SELECT * FROM interview_question_templates 
                   WHERE domain = :specialization OR domain = 'general'
                   ORDER BY difficulty_level, question_order";
$questions_stmt = $db->prepare($questions_query);
$questions_stmt->bindParam(':specialization', $expert['specialization']);
$questions_stmt->execute();
$question_templates = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate room ID for video call
$room_id = 'expert_interview_' . $interview_id . '_' . time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expert Mock Interview - SkillVerge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .expert-interface {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .evaluation-panel {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .score-slider {
            margin: 10px 0;
        }
        .question-bank {
            max-height: 400px;
            overflow-y: auto;
        }
        .candidate-brief {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
        }
        .performance-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .performance-excellent { background: #4caf50; }
        .performance-good { background: #ff9800; }
        .performance-needs-work { background: #f44336; }
        .interview-timer {
            font-size: 2rem;
            font-weight: bold;
            color: #fff;
        }
        .notes-area {
            min-height: 200px;
        }
    </style>
</head>
<body>
    <!-- Expert Header -->
    <div class="expert-interface p-3">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h4 class="mb-0">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Expert Mock Interview
                    </h4>
                    <small>Candidate: <?php echo htmlspecialchars($interview['candidate_name']); ?></small>
                </div>
                <div class="col-md-4 text-center">
                    <div class="interview-timer" id="interviewTimer">00:00:00</div>
                    <small>Interview Duration</small>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-light me-2" onclick="toggleCandidateBrief()">
                        <i class="fas fa-user-chart me-1"></i>Candidate Brief
                    </button>
                    <button class="btn btn-warning" onclick="endInterview()">
                        <i class="fas fa-stop me-1"></i>End Interview
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid p-3">
        <div class="row g-3">
            <!-- Video and Main Interface -->
            <div class="col-lg-8">
                <!-- Video Call Section -->
                <div class="card mb-3">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-video me-2"></i>Video Interview
                            </h6>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary" onclick="shareScreen()">
                                    <i class="fas fa-desktop"></i> Share Screen
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="startRecording()">
                                    <i class="fas fa-record-vinyl"></i> Record
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="jitsi-container" style="height: 400px; background: #000;">
                            <div class="d-flex align-items-center justify-content-center h-100 text-white">
                                <div class="text-center">
                                    <i class="fas fa-video fa-3x mb-3"></i>
                                    <h5>Start Video Interview</h5>
                                    <button class="btn btn-primary" onclick="startVideoCall()">
                                        <i class="fas fa-play me-2"></i>Join Video Call
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interview Questions and Evaluation -->
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#questions" role="tab">
                                    <i class="fas fa-question-circle me-2"></i>Questions
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#evaluation" role="tab">
                                    <i class="fas fa-star me-2"></i>Live Evaluation
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#notes" role="tab">
                                    <i class="fas fa-sticky-note me-2"></i>Notes
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Questions Tab -->
                            <div class="tab-pane fade show active" id="questions" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Current Question</h6>
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-primary">Question <span id="currentQuestionNumber">1</span></span>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="#" onclick="markQuestionAsAsked()">Mark as Asked</a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="skipQuestion()">Skip Question</a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="addFollowUp()">Add Follow-up</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <p id="currentQuestionText" class="mb-2">Select a question from the question bank to begin.</p>
                                                <div id="questionHints" class="text-muted small"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <h6>Quick Response Evaluation</h6>
                                            <div class="btn-group w-100" role="group">
                                                <button class="btn btn-outline-success" onclick="quickEvaluate('excellent')">
                                                    <i class="fas fa-thumbs-up"></i> Excellent
                                                </button>
                                                <button class="btn btn-outline-warning" onclick="quickEvaluate('good')">
                                                    <i class="fas fa-hand-paper"></i> Good
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="quickEvaluate('needs_work')">
                                                    <i class="fas fa-thumbs-down"></i> Needs Work
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6>Question Bank</h6>
                                        <div class="question-bank">
                                            <?php foreach ($question_templates as $index => $question): ?>
                                                <div class="card mb-2 question-item" data-question-id="<?php echo $question['id']; ?>">
                                                    <div class="card-body p-2">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1" onclick="selectQuestion(<?php echo $question['id']; ?>)" style="cursor: pointer;">
                                                                <div class="d-flex align-items-center mb-1">
                                                                    <span class="badge bg-<?php echo $question['difficulty_level'] === 'easy' ? 'success' : ($question['difficulty_level'] === 'medium' ? 'warning' : 'danger'); ?> me-2">
                                                                        <?php echo ucfirst($question['difficulty_level']); ?>
                                                                    </span>
                                                                    <small class="text-muted"><?php echo $question['domain']; ?></small>
                                                                </div>
                                                                <p class="mb-1 small"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                                                <?php if ($question['expected_answer']): ?>
                                                                    <small class="text-muted">Expected: <?php echo substr($question['expected_answer'], 0, 50) . '...'; ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="ms-2">
                                                                <span class="question-status" id="status-<?php echo $question['id']; ?>"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Live Evaluation Tab -->
                            <div class="tab-pane fade" id="evaluation" role="tabpanel">
                                <div class="evaluation-panel">
                                    <h6>Real-time Candidate Evaluation</h6>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Technical Knowledge</label>
                                            <div class="score-slider">
                                                <input type="range" class="form-range" id="technicalScore" min="1" max="10" value="5" oninput="updateScore('technical', this.value)">
                                                <div class="d-flex justify-content-between">
                                                    <small>1</small>
                                                    <span id="technicalValue" class="fw-bold">5</span>
                                                    <small>10</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Communication</label>
                                            <div class="score-slider">
                                                <input type="range" class="form-range" id="communicationScore" min="1" max="10" value="5" oninput="updateScore('communication', this.value)">
                                                <div class="d-flex justify-content-between">
                                                    <small>1</small>
                                                    <span id="communicationValue" class="fw-bold">5</span>
                                                    <small>10</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Problem Solving</label>
                                            <div class="score-slider">
                                                <input type="range" class="form-range" id="problemSolvingScore" min="1" max="10" value="5" oninput="updateScore('problemSolving', this.value)">
                                                <div class="d-flex justify-content-between">
                                                    <small>1</small>
                                                    <span id="problemSolvingValue" class="fw-bold">5</span>
                                                    <small>10</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Cultural Fit</label>
                                            <div class="score-slider">
                                                <input type="range" class="form-range" id="culturalFitScore" min="1" max="10" value="5" oninput="updateScore('culturalFit', this.value)">
                                                <div class="d-flex justify-content-between">
                                                    <small>1</small>
                                                    <span id="culturalFitValue" class="fw-bold">5</span>
                                                    <small>10</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <label class="form-label">Overall Impression</label>
                                        <select class="form-select" id="overallImpression">
                                            <option value="">Select overall impression</option>
                                            <option value="strong_hire">Strong Hire</option>
                                            <option value="hire">Hire</option>
                                            <option value="no_hire">No Hire</option>
                                            <option value="strong_no_hire">Strong No Hire</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <label class="form-label">Quick Feedback Tags</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button class="btn btn-sm btn-outline-success feedback-tag" data-tag="clear_communication">Clear Communication</button>
                                            <button class="btn btn-sm btn-outline-success feedback-tag" data-tag="good_technical_depth">Good Technical Depth</button>
                                            <button class="btn btn-sm btn-outline-success feedback-tag" data-tag="structured_thinking">Structured Thinking</button>
                                            <button class="btn btn-sm btn-outline-warning feedback-tag" data-tag="needs_more_examples">Needs More Examples</button>
                                            <button class="btn btn-sm btn-outline-warning feedback-tag" data-tag="unclear_explanations">Unclear Explanations</button>
                                            <button class="btn btn-sm btn-outline-danger feedback-tag" data-tag="lacks_technical_depth">Lacks Technical Depth</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes Tab -->
                            <div class="tab-pane fade" id="notes" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-8">
                                        <label class="form-label">Interview Notes</label>
                                        <textarea id="interviewNotes" class="form-control notes-area" 
                                                placeholder="Take detailed notes during the interview..."></textarea>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-primary" onclick="addTimestamp()">
                                                <i class="fas fa-clock me-1"></i>Add Timestamp
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="saveNotes()">
                                                <i class="fas fa-save me-1"></i>Save Notes
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Quick Notes Templates</label>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="addTemplate('strength')">
                                                + Strength Observed
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="addTemplate('improvement')">
                                                + Area for Improvement
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="addTemplate('follow_up')">
                                                + Follow-up Question
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="addTemplate('red_flag')">
                                                + Red Flag
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Candidate Brief -->
                <?php if ($candidate_brief): ?>
                <div class="card mb-3" id="candidateBriefCard">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-user-chart me-2"></i>AI-Generated Candidate Brief
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="candidate-brief">
                            <h6><?php echo htmlspecialchars($candidate_brief['candidate_info']['name']); ?></h6>
                            <p class="mb-2">
                                <strong>Experience:</strong> <?php echo ucfirst($candidate_brief['candidate_info']['experience_level']); ?><br>
                                <strong>Role:</strong> <?php echo htmlspecialchars($candidate_brief['candidate_info']['preferred_role']); ?>
                            </p>
                            
                            <div class="mb-3">
                                <strong>Performance Summary:</strong>
                                <div class="row g-2 mt-1">
                                    <div class="col-6">
                                        <small>Overall: <span class="fw-bold"><?php echo $candidate_brief['performance_summary']['average_scores']['overall']; ?>/10</span></small>
                                    </div>
                                    <div class="col-6">
                                        <small>Technical: <span class="fw-bold"><?php echo $candidate_brief['performance_summary']['average_scores']['technical']; ?>/10</span></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Key Strengths:</strong>
                                <ul class="mb-0">
                                    <?php foreach (array_slice($candidate_brief['strengths'], 0, 3) as $strength): ?>
                                        <li><small><?php echo htmlspecialchars($strength); ?></small></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Focus Areas:</strong>
                                <ul class="mb-0">
                                    <?php foreach (array_slice($candidate_brief['interview_focus_areas'], 0, 3) as $area): ?>
                                        <li><small><?php echo htmlspecialchars($area); ?></small></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <button class="btn btn-sm btn-primary w-100" onclick="showFullBrief()">
                                View Full Brief
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Interview Progress -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Interview Progress
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Questions Asked</span>
                                <span id="questionsProgress">0/10</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" id="questionsProgressBar" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Current Score</span>
                                <span id="currentScore">5.0/10</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-warning" id="scoreProgressBar" style="width: 50%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <small class="text-muted">Interview Duration: <span id="durationDisplay">0 minutes</span></small>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="sendEncouragement()">
                                <i class="fas fa-thumbs-up me-2"></i>Send Encouragement
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="giveHint()">
                                <i class="fas fa-lightbulb me-2"></i>Give Hint
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="requestClarification()">
                                <i class="fas fa-question me-2"></i>Request Clarification
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="generateReport()">
                                <i class="fas fa-file-alt me-2"></i>Generate Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Full Candidate Brief Modal -->
    <div class="modal fade" id="fullBriefModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Candidate Brief</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($candidate_brief): ?>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6>Performance Analytics</h6>
                                <div class="mb-3">
                                    <strong>AI Interviews:</strong> <?php echo $candidate_brief['performance_summary']['total_ai_interviews']; ?><br>
                                    <strong>Trend:</strong> <?php echo ucfirst(str_replace('_', ' ', $candidate_brief['performance_summary']['performance_trend'])); ?>
                                </div>
                                
                                <h6>Domain Expertise</h6>
                                <?php foreach ($candidate_brief['domain_expertise'] as $domain => $data): ?>
                                    <div class="mb-2">
                                        <strong><?php echo $domain; ?>:</strong> 
                                        <?php echo round($data['avg_score'], 1); ?>/10 
                                        (<?php echo $data['count']; ?> interviews)
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>AI Insights</h6>
                                <ul>
                                    <?php foreach ($candidate_brief['ai_insights'] as $insight): ?>
                                        <li><?php echo htmlspecialchars($insight); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <h6>Suggested Questions</h6>
                                <ul>
                                    <?php foreach (array_slice($candidate_brief['suggested_questions'], 0, 5) as $question): ?>
                                        <li><?php echo htmlspecialchars($question); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://meet.jit.si/external_api.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let jitsiApi = null;
        let interviewStartTime = Date.now();
        let currentQuestionId = null;
        let questionsAsked = 0;
        let totalQuestions = <?php echo count($question_templates); ?>;
        let evaluationScores = {
            technical: 5,
            communication: 5,
            problemSolving: 5,
            culturalFit: 5
        };
        let selectedTags = [];

        // Initialize interview timer
        function updateTimer() {
            const elapsed = Date.now() - interviewStartTime;
            const hours = Math.floor(elapsed / 3600000);
            const minutes = Math.floor((elapsed % 3600000) / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            
            document.getElementById('interviewTimer').textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            document.getElementById('durationDisplay').textContent = `${Math.floor(elapsed / 60000)} minutes`;
        }

        setInterval(updateTimer, 1000);

        // Initialize Jitsi Meet
        function startVideoCall() {
            const domain = 'meet.jit.si';
            const options = {
                roomName: '<?php echo $room_id; ?>',
                width: '100%',
                height: 400,
                parentNode: document.querySelector('#jitsi-container'),
                userInfo: {
                    displayName: 'Expert: <?php echo $_SESSION['full_name']; ?>',
                    email: '<?php echo $_SESSION['email']; ?>'
                },
                configOverwrite: {
                    startWithAudioMuted: false,
                    startWithVideoMuted: false,
                    enableWelcomePage: false,
                    prejoinPageEnabled: false
                }
            };

            jitsiApi = new JitsiMeetExternalAPI(domain, options);

            jitsiApi.addEventListener('videoConferenceJoined', () => {
                console.log('Expert joined video conference');
            });
        }

        // Question management
        function selectQuestion(questionId) {
            currentQuestionId = questionId;
            const questionElement = document.querySelector(`[data-question-id="${questionId}"]`);
            const questionText = questionElement.querySelector('p').textContent;
            
            document.getElementById('currentQuestionText').textContent = questionText;
            document.getElementById('currentQuestionNumber').textContent = questionsAsked + 1;
            
            // Highlight selected question
            document.querySelectorAll('.question-item').forEach(item => {
                item.classList.remove('border-primary');
            });
            questionElement.classList.add('border-primary');
        }

        function markQuestionAsAsked() {
            if (currentQuestionId) {
                questionsAsked++;
                document.getElementById(`status-${currentQuestionId}`).innerHTML = 
                    '<i class="fas fa-check text-success"></i>';
                updateProgress();
            }
        }

        function skipQuestion() {
            if (currentQuestionId) {
                document.getElementById(`status-${currentQuestionId}`).innerHTML = 
                    '<i class="fas fa-times text-warning"></i>';
            }
        }

        function addFollowUp() {
            const followUp = prompt('Enter follow-up question:');
            if (followUp) {
                addToNotes(`Follow-up: ${followUp}`);
            }
        }

        // Evaluation functions
        function updateScore(category, value) {
            evaluationScores[category] = parseInt(value);
            document.getElementById(`${category}Value`).textContent = value;
            
            // Update overall score
            const overall = Object.values(evaluationScores).reduce((a, b) => a + b, 0) / 4;
            document.getElementById('currentScore').textContent = `${overall.toFixed(1)}/10`;
            document.getElementById('scoreProgressBar').style.width = `${overall * 10}%`;
        }

        function quickEvaluate(level) {
            const colors = {
                excellent: 'success',
                good: 'warning', 
                needs_work: 'danger'
            };
            
            const timestamp = new Date().toLocaleTimeString();
            addToNotes(`[${timestamp}] Quick evaluation: ${level.replace('_', ' ')}`);
        }

        // Feedback tags
        document.querySelectorAll('.feedback-tag').forEach(tag => {
            tag.addEventListener('click', function() {
                const tagValue = this.dataset.tag;
                if (selectedTags.includes(tagValue)) {
                    selectedTags = selectedTags.filter(t => t !== tagValue);
                    this.classList.remove('active');
                } else {
                    selectedTags.push(tagValue);
                    this.classList.add('active');
                }
            });
        });

        // Notes functions
        function addTimestamp() {
            const timestamp = new Date().toLocaleTimeString();
            const notes = document.getElementById('interviewNotes');
            notes.value += `\n[${timestamp}] `;
            notes.focus();
        }

        function addTemplate(type) {
            const templates = {
                strength: '\n✓ STRENGTH: ',
                improvement: '\n⚠ IMPROVEMENT AREA: ',
                follow_up: '\n❓ FOLLOW-UP: ',
                red_flag: '\n🚩 RED FLAG: '
            };
            
            const notes = document.getElementById('interviewNotes');
            notes.value += templates[type];
            notes.focus();
        }

        function addToNotes(text) {
            const notes = document.getElementById('interviewNotes');
            notes.value += `\n${text}`;
        }

        function saveNotes() {
            const notes = document.getElementById('interviewNotes').value;
            
            fetch('save-expert-notes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    interview_id: <?php echo $interview_id; ?>,
                    notes: notes,
                    scores: evaluationScores,
                    tags: selectedTags
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Notes saved successfully!', 'success');
                }
            });
        }

        // Progress tracking
        function updateProgress() {
            const progress = (questionsAsked / totalQuestions) * 100;
            document.getElementById('questionsProgress').textContent = `${questionsAsked}/${totalQuestions}`;
            document.getElementById('questionsProgressBar').style.width = `${progress}%`;
        }

        // Quick actions
        function sendEncouragement() {
            if (jitsiApi) {
                jitsiApi.executeCommand('sendChatMessage', '👍 You\'re doing great! Keep it up!');
            }
        }

        function giveHint() {
            const hint = prompt('Enter hint for candidate:');
            if (hint && jitsiApi) {
                jitsiApi.executeCommand('sendChatMessage', `💡 Hint: ${hint}`);
            }
        }

        function requestClarification() {
            if (jitsiApi) {
                jitsiApi.executeCommand('sendChatMessage', '❓ Could you please clarify or provide more details?');
            }
        }

        function generateReport() {
            // Save current state and generate report
            saveNotes();
            
            setTimeout(() => {
                window.open(`interview-report.php?interview_id=<?php echo $interview_id; ?>`, '_blank');
            }, 1000);
        }

        // Modal functions
        function showFullBrief() {
            const modal = new bootstrap.Modal(document.getElementById('fullBriefModal'));
            modal.show();
        }

        function toggleCandidateBrief() {
            const briefCard = document.getElementById('candidateBriefCard');
            briefCard.style.display = briefCard.style.display === 'none' ? 'block' : 'none';
        }

        function endInterview() {
            if (confirm('Are you sure you want to end the interview?')) {
                saveNotes();
                
                // End video call
                if (jitsiApi) {
                    jitsiApi.dispose();
                }
                
                // Redirect to feedback form
                window.location.href = `expert-feedback.php?interview_id=<?php echo $interview_id; ?>`;
            }
        }

        function shareScreen() {
            if (jitsiApi) {
                jitsiApi.executeCommand('toggleShareScreen');
            }
        }

        function startRecording() {
            if (jitsiApi) {
                jitsiApi.startRecording({ mode: 'file' });
            }
        }

        function showToast(message, type) {
            console.log(`${type}: ${message}`);
        }

        // Auto-save every 30 seconds
        setInterval(saveNotes, 30000);

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateProgress();
        });
    </script>
</body>
</html>
