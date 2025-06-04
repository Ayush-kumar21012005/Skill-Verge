<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class MobileAPI {
    private $db;
    private $user_id;
    private $api_key;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->authenticateAPI();
    }
    
    private function authenticateAPI() {
        $headers = getallheaders();
        $api_key = $headers['X-API-Key'] ?? $_GET['api_key'] ?? null;
        
        if (!$api_key) {
            $this->sendError('API key required', 401);
        }
        
        $key_query = "SELECT ak.*, u.id as user_id FROM api_keys ak 
                     JOIN users u ON ak.user_id = u.id 
                     WHERE ak.api_key = :api_key AND ak.is_active = 1 
                     AND (ak.expires_at IS NULL OR ak.expires_at > NOW())";
        $key_stmt = $this->db->prepare($key_query);
        $key_stmt->bindParam(':api_key', $api_key);
        $key_stmt->execute();
        $key_data = $key_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$key_data) {
            $this->sendError('Invalid API key', 401);
        }
        
        $this->user_id = $key_data['user_id'];
        $this->api_key = $key_data;
        
        // Update usage
        $update_usage = "UPDATE api_keys SET usage_count = usage_count + 1, last_used = NOW() WHERE id = :id";
        $update_stmt = $this->db->prepare($update_usage);
        $update_stmt->bindParam(':id', $key_data['id']);
        $update_stmt->execute();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path_parts = explode('/', trim($path, '/'));
        
        // Remove 'api' and 'mobile-api.php' from path
        $endpoint = $path_parts[count($path_parts) - 1] ?? '';
        
        switch ($endpoint) {
            case 'profile':
                $this->handleProfile($method);
                break;
            case 'interviews':
                $this->handleInterviews($method);
                break;
            case 'notifications':
                $this->handleNotifications($method);
                break;
            case 'jobs':
                $this->handleJobs($method);
                break;
            case 'analytics':
                $this->handleAnalytics($method);
                break;
            case 'upload':
                $this->handleUpload($method);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }
    
    private function handleProfile($method) {
        switch ($method) {
            case 'GET':
                $profile_query = "SELECT u.*, c.skills, c.experience_years, c.preferred_domain, c.is_premium 
                                 FROM users u 
                                 LEFT JOIN candidates c ON u.id = c.user_id 
                                 WHERE u.id = :user_id";
                $profile_stmt = $this->db->prepare($profile_query);
                $profile_stmt->bindParam(':user_id', $this->user_id);
                $profile_stmt->execute();
                $profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);
                
                unset($profile['password']); // Remove sensitive data
                $this->sendSuccess($profile);
                break;
                
            case 'PUT':
                $input = json_decode(file_get_contents('php://input'), true);
                
                $update_user = "UPDATE users SET full_name = :name, phone = :phone WHERE id = :user_id";
                $update_stmt = $this->db->prepare($update_user);
                $update_stmt->bindParam(':name', $input['full_name']);
                $update_stmt->bindParam(':phone', $input['phone']);
                $update_stmt->bindParam(':user_id', $this->user_id);
                $update_stmt->execute();
                
                if (isset($input['skills'])) {
                    $update_candidate = "UPDATE candidates SET skills = :skills, preferred_domain = :domain WHERE user_id = :user_id";
                    $candidate_stmt = $this->db->prepare($update_candidate);
                    $candidate_stmt->bindParam(':skills', $input['skills']);
                    $candidate_stmt->bindParam(':domain', $input['preferred_domain']);
                    $candidate_stmt->bindParam(':user_id', $this->user_id);
                    $candidate_stmt->execute();
                }
                
                $this->sendSuccess(['message' => 'Profile updated successfully']);
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    private function handleInterviews($method) {
        switch ($method) {
            case 'GET':
                $interviews_query = "SELECT ai.*, id.name as domain_name 
                                   FROM ai_interviews ai 
                                   JOIN interview_domains id ON ai.domain = id.name 
                                   JOIN candidates c ON ai.candidate_id = c.id 
                                   WHERE c.user_id = :user_id 
                                   ORDER BY ai.completed_at DESC 
                                   LIMIT 20";
                $interviews_stmt = $this->db->prepare($interviews_query);
                $interviews_stmt->bindParam(':user_id', $this->user_id);
                $interviews_stmt->execute();
                $interviews = $interviews_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $this->sendSuccess($interviews);
                break;
                
            case 'POST':
                $input = json_decode(file_get_contents('php://input'), true);
                
                // Get candidate ID
                $candidate_query = "SELECT id FROM candidates WHERE user_id = :user_id";
                $candidate_stmt = $this->db->prepare($candidate_query);
                $candidate_stmt->bindParam(':user_id', $this->user_id);
                $candidate_stmt->execute();
                $candidate_id = $candidate_stmt->fetchColumn();
                
                // Create new interview
                $insert_interview = "INSERT INTO ai_interviews 
                                   (candidate_id, domain, questions, responses, ai_evaluation, 
                                    overall_score, technical_score, communication_score, confidence_score) 
                                   VALUES (:candidate_id, :domain, :questions, :responses, :evaluation, 
                                           :overall, :technical, :communication, :confidence)";
                $insert_stmt = $this->db->prepare($insert_interview);
                $insert_stmt->bindParam(':candidate_id', $candidate_id);
                $insert_stmt->bindParam(':domain', $input['domain']);
                $insert_stmt->bindParam(':questions', json_encode($input['questions']));
                $insert_stmt->bindParam(':responses', json_encode($input['responses']));
                $insert_stmt->bindParam(':evaluation', json_encode($input['evaluation']));
                $insert_stmt->bindParam(':overall', $input['scores']['overall']);
                $insert_stmt->bindParam(':technical', $input['scores']['technical']);
                $insert_stmt->bindParam(':communication', $input['scores']['communication']);
                $insert_stmt->bindParam(':confidence', $input['scores']['confidence']);
                
                if ($insert_stmt->execute()) {
                    $interview_id = $this->db->lastInsertId();
                    $this->sendSuccess(['interview_id' => $interview_id, 'message' => 'Interview saved successfully']);
                } else {
                    $this->sendError('Failed to save interview', 500);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    private function handleNotifications($method) {
        switch ($method) {
            case 'GET':
                $notifications_query = "SELECT * FROM notifications 
                                       WHERE user_id = :user_id 
                                       ORDER BY created_at DESC 
                                       LIMIT 50";
                $notifications_stmt = $this->db->prepare($notifications_query);
                $notifications_stmt->bindParam(':user_id', $this->user_id);
                $notifications_stmt->execute();
                $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $this->sendSuccess($notifications);
                break;
                
            case 'PUT':
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (isset($input['mark_read'])) {
                    $update_query = "UPDATE notifications SET is_read = 1 
                                   WHERE id = :id AND user_id = :user_id";
                    $update_stmt = $this->db->prepare($update_query);
                    $update_stmt->bindParam(':id', $input['notification_id']);
                    $update_stmt->bindParam(':user_id', $this->user_id);
                    $update_stmt->execute();
                    
                    $this->sendSuccess(['message' => 'Notification marked as read']);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    private function handleJobs($method) {
        switch ($method) {
            case 'GET':
                $search = $_GET['search'] ?? '';
                $location = $_GET['location'] ?? '';
                $job_type = $_GET['job_type'] ?? '';
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = min(50, max(10, intval($_GET['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                
                $where_conditions = ['jp.is_active = 1'];
                $params = [];
                
                if ($search) {
                    $where_conditions[] = '(jp.title LIKE :search OR jp.description LIKE :search)';
                    $params[':search'] = '%' . $search . '%';
                }
                
                if ($location) {
                    $where_conditions[] = 'jp.location LIKE :location';
                    $params[':location'] = '%' . $location . '%';
                }
                
                if ($job_type) {
                    $where_conditions[] = 'jp.job_type = :job_type';
                    $params[':job_type'] = $job_type;
                }
                
                $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
                
                $jobs_query = "SELECT jp.*, c.company_name, c.industry 
                             FROM job_postings jp 
                             JOIN companies c ON jp.company_id = c.id 
                             $where_clause 
                             ORDER BY jp.created_at DESC 
                             LIMIT :limit OFFSET :offset";
                
                $jobs_stmt = $this->db->prepare($jobs_query);
                foreach ($params as $key => $value) {
                    $jobs_stmt->bindValue($key, $value);
                }
                $jobs_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $jobs_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $jobs_stmt->execute();
                $jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $this->sendSuccess([
                    'jobs' => $jobs,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'has_more' => count($jobs) === $limit
                    ]
                ]);
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    private function handleAnalytics($method) {
        if ($method !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }
        
        // Get candidate ID
        $candidate_query = "SELECT id FROM candidates WHERE user_id = :user_id";
        $candidate_stmt = $this->db->prepare($candidate_query);
        $candidate_stmt->bindParam(':user_id', $this->user_id);
        $candidate_stmt->execute();
        $candidate_id = $candidate_stmt->fetchColumn();
        
        // Get interview statistics
        $stats_query = "SELECT 
                          COUNT(*) as total_interviews,
                          AVG(overall_score) as avg_overall_score,
                          AVG(technical_score) as avg_technical_score,
                          AVG(communication_score) as avg_communication_score,
                          AVG(confidence_score) as avg_confidence_score,
                          MAX(overall_score) as best_score,
                          MIN(overall_score) as lowest_score
                        FROM ai_interviews 
                        WHERE candidate_id = :candidate_id";
        $stats_stmt = $this->db->prepare($stats_query);
        $stats_stmt->bindParam(':candidate_id', $candidate_id);
        $stats_stmt->execute();
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get recent performance trend
        $trend_query = "SELECT DATE(completed_at) as date, AVG(overall_score) as score 
                       FROM ai_interviews 
                       WHERE candidate_id = :candidate_id 
                       AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                       GROUP BY DATE(completed_at) 
                       ORDER BY date";
        $trend_stmt = $this->db->prepare($trend_query);
        $trend_stmt->bindParam(':candidate_id', $candidate_id);
        $trend_stmt->execute();
        $trend = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->sendSuccess([
            'statistics' => $stats,
            'performance_trend' => $trend
        ]);
    }
    
    private function handleUpload($method) {
        if ($method !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        if (!isset($_FILES['file'])) {
            $this->sendError('No file uploaded', 400);
        }
        
        $file = $_FILES['file'];
        $upload_purpose = $_POST['purpose'] ?? 'document';
        
        // Validate file
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            $this->sendError('File too large', 400);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'audio/wav', 'audio/mp3'];
        if (!in_array($file['type'], $allowed_types)) {
            $this->sendError('File type not allowed', 400);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $stored_name = uniqid() . '_' . time() . '.' . $extension;
        $upload_dir = '../uploads/' . $upload_purpose . '/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_path = $upload_dir . $stored_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Save to database
            $insert_file = "INSERT INTO file_uploads 
                           (user_id, original_name, stored_name, file_path, file_type, 
                            file_size_bytes, mime_type, upload_purpose) 
                           VALUES (:user_id, :original_name, :stored_name, :file_path, 
                                   :file_type, :file_size, :mime_type, :purpose)";
            $insert_stmt = $this->db->prepare($insert_file);
            $insert_stmt->bindParam(':user_id', $this->user_id);
            $insert_stmt->bindParam(':original_name', $file['name']);
            $insert_stmt->bindParam(':stored_name', $stored_name);
            $insert_stmt->bindParam(':file_path', $file_path);
            $insert_stmt->bindParam(':file_type', $extension);
            $insert_stmt->bindParam(':file_size', $file['size']);
            $insert_stmt->bindParam(':mime_type', $file['type']);
            $insert_stmt->bindParam(':purpose', $upload_purpose);
            
            if ($insert_stmt->execute()) {
                $file_id = $this->db->lastInsertId();
                $this->sendSuccess([
                    'file_id' => $file_id,
                    'filename' => $stored_name,
                    'url' => '/uploads/' . $upload_purpose . '/' . $stored_name
                ]);
            } else {
                $this->sendError('Failed to save file record', 500);
            }
        } else {
            $this->sendError('Failed to upload file', 500);
        }
    }
    
    private function sendSuccess($data) {
        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
        exit();
    }
}

// Initialize and handle request
try {
    $api = new MobileAPI();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>
