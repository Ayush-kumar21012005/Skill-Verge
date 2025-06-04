<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../utils/code-executor.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Verify user is authenticated
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['code']) || !isset($input['language'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Code and language are required']);
    exit();
}

$code = $input['code'];
$language = $input['language'];
$test_input = $input['input'] ?? '';

$executor = new CodeExecutor();

// Validate code first
$validation = $executor->validateCode($code, $language);
if (!$validation['valid']) {
    echo json_encode([
        'success' => false,
        'error' => 'Code validation failed: ' . implode(', ', $validation['issues']),
        'output' => ''
    ]);
    exit();
}

// Execute code
$result = $executor->executeCode($code, $language, $test_input);

// Log execution for analytics
$log_query = "INSERT INTO code_executions (user_id, language, code_length, execution_time, success) 
              VALUES (:user_id, :language, :code_length, NOW(), :success)";
$log_stmt = $db->prepare($log_query);
$log_stmt->bindParam(':user_id', $_SESSION['user_id']);
$log_stmt->bindParam(':language', $language);
$log_stmt->bindParam(':code_length', strlen($code));
$log_stmt->bindParam(':success', $result['success'] ? 1 : 0);
$log_stmt->execute();

echo json_encode($result);
?>
