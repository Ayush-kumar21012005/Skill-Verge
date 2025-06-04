<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['candidate']);

// Get interview details
$interview_id = $_GET['interview_id'] ?? null;
if (!$interview_id) {
    header('Location: dashboard.php');
    exit();
}

// Get interview and expert details
$interview_query = "SELECT ei.*, e.full_name as expert_name, e.specialization, e.hourly_rate,
                   u.email as expert_email, c.user_id as candidate_user_id
                   FROM expert_interviews ei
                   JOIN experts e ON ei.expert_id = e.id
                   JOIN users u ON e.user_id = u.id
                   JOIN candidates c ON ei.candidate_id = c.id
                   WHERE ei.id = :interview_id AND c.user_id = :user_id";
$interview_stmt = $db->prepare($interview_query);
$interview_stmt->bindParam(':interview_id', $interview_id);
$interview_stmt->bindParam(':user_id', $_SESSION['user_id']);
$interview_stmt->execute();
$interview = $interview_stmt->fetch(PDO::FETCH_ASSOC);

if (!$interview) {
    header('Location: dashboard.php');
    exit();
}

// Generate room ID for video call
$room_id = 'skillverge_' . $interview_id . '_' . time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Interview - SkillVerge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .video-container {
            position: relative;
            height: 400px;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
        .video-controls {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
        }
        .control-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .control-btn.mute { background: #6c757d; }
        .control-btn.mute.active { background: #dc3545; }
        .control-btn.video { background: #6c757d; }
        .control-btn.video.active { background: #dc3545; }
        .control-btn.end { background: #dc3545; }
        .control-btn.record { background: #28a745; }
        .control-btn.record.active { background: #dc3545; animation: pulse 1s infinite; }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .code-editor {
            height: 400px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        
        .whiteboard {
            height: 400px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: white;
            cursor: crosshair;
        }
        
        .interview-timer {
            font-size: 1.5rem;
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row bg-dark text-white p-3">
            <div class="col-md-6">
                <h5 class="mb-0">
                    <i class="fas fa-video me-2"></i>Interview with <?php echo htmlspecialchars($interview['expert_name']); ?>
                </h5>
                <small><?php echo htmlspecialchars($interview['specialization']); ?></small>
            </div>
            <div class="col-md-6 text-end">
                <div class="interview-timer" id="interviewTimer">00:00:00</div>
                <small>Interview Duration</small>
            </div>
        </div>

        <div class="row g-3 p-3">
            <!-- Video Section -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-video me-2"></i>Video Call
                            </h6>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary" onclick="toggleFullscreen()">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="shareScreen()">
                                    <i class="fas fa-desktop"></i> Share Screen
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- Jitsi Meet Integration -->
                        <div id="jitsi-container" class="video-container">
                            <div class="d-flex align-items-center justify-content-center h-100 text-white">
                                <div class="text-center">
                                    <i class="fas fa-video fa-3x mb-3"></i>
                                    <h5>Connecting to video call...</h5>
                                    <button class="btn btn-primary" onclick="startVideoCall()">
                                        <i class="fas fa-play me-2"></i>Join Video Call
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs for Code Editor and Whiteboard -->
                <div class="card mt-3">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#codeEditor" role="tab">
                                    <i class="fas fa-code me-2"></i>Code Editor
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#whiteboard" role="tab">
                                    <i class="fas fa-chalkboard me-2"></i>Whiteboard
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
                            <!-- Code Editor -->
                            <div class="tab-pane fade show active" id="codeEditor" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex gap-2">
                                        <select class="form-select form-select-sm" id="languageSelect" onchange="changeLanguage()">
                                            <option value="javascript">JavaScript</option>
                                            <option value="python">Python</option>
                                            <option value="java">Java</option>
                                            <option value="cpp">C++</option>
                                            <option value="sql">SQL</option>
                                        </select>
                                        <button class="btn btn-sm btn-success" onclick="runCode()">
                                            <i class="fas fa-play me-1"></i>Run
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary" onclick="saveCode()">
                                            <i class="fas fa-save me-1"></i>Save
                                        </button>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="liveSync" checked>
                                        <label class="form-check-label" for="liveSync">
                                            Live Sync with Expert
                                        </label>
                                    </div>
                                </div>
                                <div id="codeEditorContainer" class="code-editor"></div>
                                <div class="mt-3">
                                    <h6>Output:</h6>
                                    <pre id="codeOutput" class="bg-dark text-light p-3 rounded" style="min-height: 100px;"></pre>
                                </div>
                            </div>

                            <!-- Whiteboard -->
                            <div class="tab-pane fade" id="whiteboard" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-dark" onclick="setDrawingTool('pen')">
                                            <i class="fas fa-pen"></i> Pen
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary" onclick="setDrawingTool('eraser')">
                                            <i class="fas fa-eraser"></i> Eraser
                                        </button>
                                        <input type="color" class="form-control form-control-sm" id="colorPicker" value="#000000" style="width: 50px;">
                                        <input type="range" class="form-range" id="brushSize" min="1" max="20" value="3" style="width: 100px;">
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-warning" onclick="clearWhiteboard()">
                                            <i class="fas fa-trash"></i> Clear
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" onclick="saveWhiteboard()">
                                            <i class="fas fa-save"></i> Save
                                        </button>
                                    </div>
                                </div>
                                <canvas id="whiteboardCanvas" class="whiteboard" width="800" height="400"></canvas>
                            </div>

                            <!-- Notes -->
                            <div class="tab-pane fade" id="notes" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Interview Notes</h6>
                                    <button class="btn btn-sm btn-outline-success" onclick="saveNotes()">
                                        <i class="fas fa-save me-1"></i>Save Notes
                                    </button>
                                </div>
                                <textarea id="interviewNotes" class="form-control" rows="15" 
                                          placeholder="Take notes during the interview..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Interview Info -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Interview Details
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <strong>Expert:</strong><br>
                                <small><?php echo htmlspecialchars($interview['expert_name']); ?></small>
                            </div>
                            <div class="col-6">
                                <strong>Specialization:</strong><br>
                                <small><?php echo htmlspecialchars($interview['specialization']); ?></small>
                            </div>
                            <div class="col-6">
                                <strong>Duration:</strong><br>
                                <small><?php echo $interview['duration_minutes']; ?> minutes</small>
                            </div>
                            <div class="col-6">
                                <strong>Rate:</strong><br>
                                <small>₹<?php echo $interview['hourly_rate']; ?>/hour</small>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Recording Status:</span>
                                <span id="recordingStatus" class="badge bg-secondary">Not Recording</span>
                            </div>
                            <button class="btn btn-outline-danger btn-sm w-100" onclick="toggleRecording()">
                                <i class="fas fa-record-vinyl me-2"></i>
                                <span id="recordingBtn">Start Recording</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Chat -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-comments me-2"></i>Chat
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div id="chatMessages" class="p-3" style="height: 200px; overflow-y: auto;">
                            <div class="text-muted text-center">
                                <small>Chat messages will appear here</small>
                            </div>
                        </div>
                        <div class="border-top p-2">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="chatInput" 
                                       placeholder="Type a message..." onkeypress="handleChatKeyPress(event)">
                                <button class="btn btn-primary" onclick="sendChatMessage()">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
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
                            <button class="btn btn-outline-primary btn-sm" onclick="requestHelp()">
                                <i class="fas fa-hand-paper me-2"></i>Request Help
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="shareFile()">
                                <i class="fas fa-file-upload me-2"></i>Share File
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="reportIssue()">
                                <i class="fas fa-exclamation-triangle me-2"></i>Report Issue
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="endInterview()">
                                <i class="fas fa-phone-slash me-2"></i>End Interview
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Jitsi Meet API -->
    <script src="https://meet.jit.si/external_api.js"></script>
    <!-- Monaco Editor for Code -->
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.34.0/min/vs/loader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let jitsiApi = null;
        let monacoEditor = null;
        let isRecording = false;
        let interviewStartTime = Date.now();
        let whiteboardCanvas = null;
        let whiteboardCtx = null;
        let isDrawing = false;
        let currentTool = 'pen';

        // Initialize interview timer
        function updateTimer() {
            const elapsed = Date.now() - interviewStartTime;
            const hours = Math.floor(elapsed / 3600000);
            const minutes = Math.floor((elapsed % 3600000) / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            
            document.getElementById('interviewTimer').textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
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
                    displayName: '<?php echo $_SESSION['full_name']; ?>',
                    email: '<?php echo $_SESSION['email']; ?>'
                },
                configOverwrite: {
                    startWithAudioMuted: false,
                    startWithVideoMuted: false,
                    enableWelcomePage: false,
                    prejoinPageEnabled: false
                },
                interfaceConfigOverwrite: {
                    TOOLBAR_BUTTONS: [
                        'microphone', 'camera', 'closedcaptions', 'desktop', 'fullscreen',
                        'fodeviceselection', 'hangup', 'profile', 'chat', 'recording',
                        'livestreaming', 'etherpad', 'sharedvideo', 'settings', 'raisehand',
                        'videoquality', 'filmstrip', 'invite', 'feedback', 'stats', 'shortcuts'
                    ]
                }
            };

            jitsiApi = new JitsiMeetExternalAPI(domain, options);

            // Event listeners
            jitsiApi.addEventListener('videoConferenceJoined', () => {
                console.log('Joined video conference');
                // Notify expert that candidate has joined
                notifyExpertJoined();
            });

            jitsiApi.addEventListener('videoConferenceLeft', () => {
                console.log('Left video conference');
            });

            jitsiApi.addEventListener('recordingStatusChanged', (event) => {
                updateRecordingStatus(event.on);
            });
        }

        // Initialize Monaco Editor
        require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.34.0/min/vs' } });
        require(['vs/editor/editor.main'], function () {
            monacoEditor = monaco.editor.create(document.getElementById('codeEditorContainer'), {
                value: '// Write your code here\nfunction solution() {\n    // Your implementation\n}\n',
                language: 'javascript',
                theme: 'vs-dark',
                automaticLayout: true,
                fontSize: 14,
                minimap: { enabled: false }
            });

            // Live sync with expert
            monacoEditor.onDidChangeModelContent(() => {
                if (document.getElementById('liveSync').checked) {
                    syncCodeWithExpert();
                }
            });
        });

        // Initialize Whiteboard
        document.addEventListener('DOMContentLoaded', function() {
            whiteboardCanvas = document.getElementById('whiteboardCanvas');
            whiteboardCtx = whiteboardCanvas.getContext('2d');
            
            // Mouse events
            whiteboardCanvas.addEventListener('mousedown', startDrawing);
            whiteboardCanvas.addEventListener('mousemove', draw);
            whiteboardCanvas.addEventListener('mouseup', stopDrawing);
            whiteboardCanvas.addEventListener('mouseout', stopDrawing);
            
            // Touch events for mobile
            whiteboardCanvas.addEventListener('touchstart', handleTouch);
            whiteboardCanvas.addEventListener('touchmove', handleTouch);
            whiteboardCanvas.addEventListener('touchend', stopDrawing);
        });

        function startDrawing(e) {
            isDrawing = true;
            draw(e);
        }

        function draw(e) {
            if (!isDrawing) return;
            
            const rect = whiteboardCanvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            whiteboardCtx.lineWidth = document.getElementById('brushSize').value;
            whiteboardCtx.lineCap = 'round';
            
            if (currentTool === 'pen') {
                whiteboardCtx.globalCompositeOperation = 'source-over';
                whiteboardCtx.strokeStyle = document.getElementById('colorPicker').value;
            } else if (currentTool === 'eraser') {
                whiteboardCtx.globalCompositeOperation = 'destination-out';
            }
            
            whiteboardCtx.lineTo(x, y);
            whiteboardCtx.stroke();
            whiteboardCtx.beginPath();
            whiteboardCtx.moveTo(x, y);
        }

        function stopDrawing() {
            if (isDrawing) {
                isDrawing = false;
                whiteboardCtx.beginPath();
                // Sync whiteboard with expert
                syncWhiteboardWithExpert();
            }
        }

        function handleTouch(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent(e.type === 'touchstart' ? 'mousedown' : 
                                            e.type === 'touchmove' ? 'mousemove' : 'mouseup', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            whiteboardCanvas.dispatchEvent(mouseEvent);
        }

        function setDrawingTool(tool) {
            currentTool = tool;
            document.querySelectorAll('.btn-outline-dark, .btn-outline-primary').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        function clearWhiteboard() {
            whiteboardCtx.clearRect(0, 0, whiteboardCanvas.width, whiteboardCanvas.height);
            syncWhiteboardWithExpert();
        }

        function changeLanguage() {
            const language = document.getElementById('languageSelect').value;
            const templates = {
                javascript: '// Write your JavaScript code here\nfunction solution() {\n    // Your implementation\n}\n',
                python: '# Write your Python code here\ndef solution():\n    # Your implementation\n    pass\n',
                java: '// Write your Java code here\npublic class Solution {\n    public void solution() {\n        // Your implementation\n    }\n}',
                cpp: '// Write your C++ code here\n#include <iostream>\nusing namespace std;\n\nint main() {\n    // Your implementation\n    return 0;\n}',
                sql: '-- Write your SQL query here\nSELECT * FROM table_name WHERE condition;'
            };
            
            monaco.editor.setModelLanguage(monacoEditor.getModel(), language);
            monacoEditor.setValue(templates[language] || '');
        }

        function runCode() {
            const code = monacoEditor.getValue();
            const language = document.getElementById('languageSelect').value;
            
            // Simulate code execution (in production, use a code execution service)
            document.getElementById('codeOutput').textContent = 'Code execution simulation...\nOutput: Hello World!';
            
            // In production, send to code execution API
            fetch('execute-code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code, language })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('codeOutput').textContent = data.output || 'No output';
            })
            .catch(error => {
                document.getElementById('codeOutput').textContent = 'Error executing code: ' + error.message;
            });
        }

        function saveCode() {
            const code = monacoEditor.getValue();
            const language = document.getElementById('languageSelect').value;
            
            fetch('save-interview-code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    interview_id: <?php echo $interview_id; ?>,
                    code, 
                    language 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Code saved successfully!', 'success');
                }
            });
        }

        function saveWhiteboard() {
            const imageData = whiteboardCanvas.toDataURL();
            
            fetch('save-interview-whiteboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    interview_id: <?php echo $interview_id; ?>,
                    image_data: imageData 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Whiteboard saved successfully!', 'success');
                }
            });
        }

        function saveNotes() {
            const notes = document.getElementById('interviewNotes').value;
            
            fetch('save-interview-notes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    interview_id: <?php echo $interview_id; ?>,
                    notes 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Notes saved successfully!', 'success');
                }
            });
        }

        function toggleRecording() {
            if (jitsiApi) {
                if (isRecording) {
                    jitsiApi.stopRecording();
                } else {
                    jitsiApi.startRecording({
                        mode: 'file'
                    });
                }
            }
        }

        function updateRecordingStatus(recording) {
            isRecording = recording;
            const status = document.getElementById('recordingStatus');
            const btn = document.getElementById('recordingBtn');
            
            if (recording) {
                status.textContent = 'Recording';
                status.className = 'badge bg-danger';
                btn.textContent = 'Stop Recording';
            } else {
                status.textContent = 'Not Recording';
                status.className = 'badge bg-secondary';
                btn.textContent = 'Start Recording';
            }
        }

        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (message) {
                addChatMessage(message, true);
                input.value = '';
                
                // Send to expert via WebSocket or API
                sendMessageToExpert(message);
            }
        }

        function handleChatKeyPress(event) {
            if (event.key === 'Enter') {
                sendChatMessage();
            }
        }

        function addChatMessage(message, isOwn) {
            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `mb-2 ${isOwn ? 'text-end' : ''}`;
            
            const time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            messageDiv.innerHTML = `
                <div class="d-inline-block p-2 rounded ${isOwn ? 'bg-primary text-white' : 'bg-light'}">
                    <div>${message}</div>
                    <small class="opacity-75">${time}</small>
                </div>
            `;
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function endInterview() {
            if (confirm('Are you sure you want to end the interview?')) {
                // Save final state
                saveCode();
                saveWhiteboard();
                saveNotes();
                
                // Leave video call
                if (jitsiApi) {
                    jitsiApi.dispose();
                }
                
                // Redirect to feedback page
                window.location.href = `interview-feedback.php?interview_id=<?php echo $interview_id; ?>`;
            }
        }

        function shareScreen() {
            if (jitsiApi) {
                jitsiApi.executeCommand('toggleShareScreen');
            }
        }

        function toggleFullscreen() {
            if (jitsiApi) {
                jitsiApi.executeCommand('toggleFilmStrip');
            }
        }

        function requestHelp() {
            addChatMessage('🆘 Candidate is requesting help', true);
            sendMessageToExpert('🆘 Candidate is requesting help');
        }

        function shareFile() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.pdf,.doc,.docx,.txt,.png,.jpg';
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Upload file and share with expert
                    uploadAndShareFile(file);
                }
            };
            input.click();
        }

        function reportIssue() {
            const issue = prompt('Please describe the technical issue:');
            if (issue) {
                addChatMessage(`🔧 Technical Issue: ${issue}`, true);
                sendMessageToExpert(`🔧 Technical Issue: ${issue}`);
            }
        }

        // WebSocket or API functions for real-time sync
        function syncCodeWithExpert() {
            // Implementation for real-time code sync
        }

        function syncWhiteboardWithExpert() {
            // Implementation for real-time whiteboard sync
        }

        function sendMessageToExpert(message) {
            // Implementation for sending messages to expert
        }

        function notifyExpertJoined() {
            // Implementation for notifying expert
        }

        function uploadAndShareFile(file) {
            // Implementation for file sharing
        }

        function showToast(message, type) {
            // Implementation for toast notifications
            console.log(`${type}: ${message}`);
        }

        // Auto-save every 30 seconds
        setInterval(() => {
            saveCode();
            saveNotes();
        }, 30000);
    </script>
</body>
</html>
