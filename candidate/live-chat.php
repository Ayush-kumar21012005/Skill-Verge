<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['candidate']);

// Handle new conversation creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_conversation'])) {
    $subject = trim($_POST['subject']);
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $initial_message = trim($_POST['message']);
    
    if (!empty($subject) && !empty($initial_message)) {
        // Create conversation
        $create_conv = "INSERT INTO support_conversations (user_id, subject, category, priority) 
                       VALUES (:user_id, :subject, :category, :priority)";
        $conv_stmt = $db->prepare($create_conv);
        $conv_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $conv_stmt->bindParam(':subject', $subject);
        $conv_stmt->bindParam(':category', $category);
        $conv_stmt->bindParam(':priority', $priority);
        
        if ($conv_stmt->execute()) {
            $conversation_id = $db->lastInsertId();
            
            // Add initial message
            $add_message = "INSERT INTO support_messages (conversation_id, sender_id, message) 
                           VALUES (:conv_id, :sender_id, :message)";
            $msg_stmt = $db->prepare($add_message);
            $msg_stmt->bindParam(':conv_id', $conversation_id);
            $msg_stmt->bindParam(':sender_id', $_SESSION['user_id']);
            $msg_stmt->bindParam(':message', $initial_message);
            $msg_stmt->execute();
            
            header("Location: live-chat.php?conversation_id=$conversation_id");
            exit();
        }
    }
}

// Get user's conversations
$conversations_query = "SELECT sc.*, 
                       (SELECT COUNT(*) FROM support_messages sm WHERE sm.conversation_id = sc.id AND sm.sender_id != :user_id AND sm.is_read = 0) as unread_count,
                       (SELECT message FROM support_messages sm WHERE sm.conversation_id = sc.id ORDER BY sm.created_at DESC LIMIT 1) as last_message
                       FROM support_conversations sc 
                       WHERE sc.user_id = :user_id 
                       ORDER BY sc.updated_at DESC";
$conv_stmt = $db->prepare($conversations_query);
$conv_stmt->bindParam(':user_id', $_SESSION['user_id']);
$conv_stmt->execute();
$conversations = $conv_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current conversation if specified
$current_conversation = null;
$messages = [];
if (isset($_GET['conversation_id'])) {
    $conv_id = $_GET['conversation_id'];
    
    // Get conversation details
    $conv_detail_query = "SELECT sc.*, u.full_name as agent_name 
                         FROM support_conversations sc 
                         LEFT JOIN users u ON sc.agent_id = u.id 
                         WHERE sc.id = :conv_id AND sc.user_id = :user_id";
    $detail_stmt = $db->prepare($conv_detail_query);
    $detail_stmt->bindParam(':conv_id', $conv_id);
    $detail_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $detail_stmt->execute();
    $current_conversation = $detail_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_conversation) {
        // Get messages
        $messages_query = "SELECT sm.*, u.full_name as sender_name, u.user_type 
                          FROM support_messages sm 
                          JOIN users u ON sm.sender_id = u.id 
                          WHERE sm.conversation_id = :conv_id 
                          ORDER BY sm.created_at ASC";
        $msg_stmt = $db->prepare($messages_query);
        $msg_stmt->bindParam(':conv_id', $conv_id);
        $msg_stmt->execute();
        $messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read
        $mark_read = "UPDATE support_messages SET is_read = 1 
                     WHERE conversation_id = :conv_id AND sender_id != :user_id";
        $read_stmt = $db->prepare($mark_read);
        $read_stmt->bindParam(':conv_id', $conv_id);
        $read_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $read_stmt->execute();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat Support - SkillVerge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .chat-container {
            height: 70vh;
            display: flex;
            flex-direction: column;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background-color: #f8f9fa;
        }
        .message {
            margin-bottom: 1rem;
        }
        .message.own {
            text-align: right;
        }
        .message-bubble {
            display: inline-block;
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            word-wrap: break-word;
        }
        .message.own .message-bubble {
            background-color: #007bff;
            color: white;
        }
        .message.other .message-bubble {
            background-color: white;
            border: 1px solid #dee2e6;
        }
        .conversation-item {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .conversation-item:hover {
            background-color: #f8f9fa;
        }
        .conversation-item.active {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="p-3 border-bottom">
            <h5 class="fw-bold text-primary mb-0">
                <i class="fas fa-graduation-cap me-2"></i>SkillVerge
            </h5>
            <small class="text-muted">Live Support</small>
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
                    <a class="nav-link" href="job-board.php">
                        <i class="fas fa-briefcase"></i>Job Board
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="live-chat.php">
                        <i class="fas fa-comments"></i>Live Support
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
                    <h2 class="fw-bold mb-1">Live Chat Support</h2>
                    <p class="text-muted mb-0">Get instant help from our support team</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newChatModal">
                    <i class="fas fa-plus me-2"></i>New Conversation
                </button>
            </div>

            <div class="row g-4">
                <!-- Conversations List -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-list me-2"></i>Conversations
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($conversations)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-comments text-muted" style="font-size: 3rem;"></i>
                                    <h6 class="text-muted mt-3">No conversations yet</h6>
                                    <p class="text-muted">Start a new conversation to get help</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($conversations as $conv): ?>
                                    <div class="conversation-item p-3 border-bottom <?php echo (isset($_GET['conversation_id']) && $_GET['conversation_id'] == $conv['id']) ? 'active' : ''; ?>" 
                                         onclick="openConversation(<?php echo $conv['id']; ?>)">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($conv['subject']); ?></h6>
                                                <p class="text-muted small mb-1"><?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)) . '...'; ?></p>
                                                <div class="d-flex gap-2">
                                                    <span class="badge bg-<?php 
                                                        echo $conv['status'] === 'open' ? 'primary' : 
                                                            ($conv['status'] === 'in_progress' ? 'warning' : 
                                                            ($conv['status'] === 'resolved' ? 'success' : 'secondary')); 
                                                    ?> small">
                                                        <?php echo ucfirst(str_replace('_', ' ', $conv['status'])); ?>
                                                    </span>
                                                    <span class="badge bg-light text-dark small">
                                                        <?php echo ucfirst($conv['category']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted"><?php echo date('M j', strtotime($conv['updated_at'])); ?></small>
                                                <?php if ($conv['unread_count'] > 0): ?>
                                                    <div class="badge bg-danger rounded-pill mt-1"><?php echo $conv['unread_count']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="col-lg-8">
                    <?php if ($current_conversation): ?>
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($current_conversation['subject']); ?></h6>
                                        <small class="text-muted">
                                            <?php if ($current_conversation['agent_name']): ?>
                                                Agent: <?php echo htmlspecialchars($current_conversation['agent_name']); ?>
                                            <?php else: ?>
                                                Waiting for agent assignment...
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-<?php 
                                            echo $current_conversation['status'] === 'open' ? 'primary' : 
                                                ($current_conversation['status'] === 'in_progress' ? 'warning' : 
                                                ($current_conversation['status'] === 'resolved' ? 'success' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $current_conversation['status'])); ?>
                                        </span>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="closeConversation()">
                                                    <i class="fas fa-check me-2"></i>Mark as Resolved
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="downloadTranscript()">
                                                    <i class="fas fa-download me-2"></i>Download Transcript
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="chat-container">
                                <div class="chat-messages" id="chatMessages">
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message <?php echo $message['sender_id'] == $_SESSION['user_id'] ? 'own' : 'other'; ?>">
                                            <div class="message-bubble">
                                                <div class="message-content"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                                <div class="message-time text-muted small mt-1">
                                                    <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                                                    <?php if ($message['sender_id'] != $_SESSION['user_id']): ?>
                                                        - <?php echo htmlspecialchars($message['sender_name']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="chat-input border-top p-3">
                                    <form id="messageForm" onsubmit="sendMessage(event)">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="messageInput" 
                                                   placeholder="Type your message..." required>
                                            <button class="btn btn-primary" type="submit">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-comments text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 text-muted">Select a conversation</h4>
                                <p class="text-muted">Choose a conversation from the list or start a new one</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newChatModal">
                                    <i class="fas fa-plus me-2"></i>Start New Conversation
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- New Chat Modal -->
    <div class="modal fade" id="newChatModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Start New Conversation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required 
                                   placeholder="Brief description of your issue">
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">Select category</option>
                                    <option value="technical">Technical Issue</option>
                                    <option value="billing">Billing & Payment</option>
                                    <option value="general">General Question</option>
                                    <option value="feature_request">Feature Request</option>
                                    <option value="bug_report">Bug Report</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-control" id="priority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="4" required 
                                      placeholder="Describe your issue or question in detail..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_conversation" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Start Conversation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openConversation(conversationId) {
            window.location.href = `live-chat.php?conversation_id=${conversationId}`;
        }

        function sendMessage(event) {
            event.preventDefault();
            
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (!message) return;
            
            const conversationId = <?php echo $current_conversation ? $current_conversation['id'] : 'null'; ?>;
            
            if (!conversationId) {
                alert('No conversation selected');
                return;
            }
            
            // Add message to UI immediately
            addMessageToChat(message, true);
            messageInput.value = '';
            
            // Send to server
            fetch('send-message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    conversation_id: conversationId,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Failed to send message: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send message');
            });
        }

        function addMessageToChat(message, isOwn) {
            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isOwn ? 'own' : 'other'}`;
            
            const now = new Date();
            const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            messageDiv.innerHTML = `
                <div class="message-bubble">
                    <div class="message-content">${message.replace(/\n/g, '<br>')}</div>
                    <div class="message-time text-muted small mt-1">${timeString}</div>
                </div>
            `;
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function closeConversation() {
            if (confirm('Are you sure you want to mark this conversation as resolved?')) {
                const conversationId = <?php echo $current_conversation ? $current_conversation['id'] : 'null'; ?>;
                
                fetch('close-conversation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ conversation_id: conversationId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to close conversation: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to close conversation');
                });
            }
        }

        function downloadTranscript() {
            const conversationId = <?php echo $current_conversation ? $current_conversation['id'] : 'null'; ?>;
            window.open(`download-transcript.php?conversation_id=${conversationId}`, '_blank');
        }

        // Auto-scroll to bottom of chat
        <?php if ($current_conversation): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });

        // Poll for new messages every 3 seconds
        setInterval(function() {
            const conversationId = <?php echo $current_conversation['id']; ?>;
            
            fetch(`get-new-messages.php?conversation_id=${conversationId}&last_check=${Date.now()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(message => {
                        if (message.sender_id != <?php echo $_SESSION['user_id']; ?>) {
                            addMessageToChat(message.message + ' - ' + message.sender_name, false);
                        }
                    });
                }
            })
            .catch(error => console.error('Error polling messages:', error));
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
