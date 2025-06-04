<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['expert']);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $interview_id = $input['interview_id'];
    $notes = $input['notes'];
    $scores = $input['scores'];
    $tags = $input['tags'];
    
    // Get expert ID
    $expert_query = "SELECT id FROM experts WHERE user_id = :user_id";
    $expert_stmt = $db->prepare($expert_query);
    $expert_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $expert_stmt->execute();
    $expert = $expert_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expert) {
        throw new Exception('Expert not found');
    }
    
    // Get candidate ID from interview
    $interview_query = "SELECT candidate_id FROM expert_interviews WHERE id = :interview_id AND expert_id = :expert_id";
    $interview_stmt = $db->prepare($interview_query);
    $interview_stmt->bindParam(':interview_id', $interview_id);
    $interview_stmt->bindParam(':expert_id', $expert['id']);
    $interview_stmt->execute();
    $interview = $interview_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$interview) {
        throw new Exception('Interview not found');
    }
    
    // Calculate overall impression based on scores
    $avg_score = array_sum($scores) / count($scores);
    $overall_impression = '';
    if ($avg_score >= 8) {
        $overall_impression = 'strong_hire';
    } elseif ($avg_score >= 6.5) {
        $overall_impression = 'hire';
    } elseif ($avg_score >= 5) {
        $overall_impression = 'no_hire';
    } else {
        $overall_impression = 'strong_no_hire';
    }
    
    // Save evaluation using stored procedure
    $save_query = "CALL UpdateExpertEvaluation(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $save_stmt = $db->prepare($save_query);
    $save_stmt->execute([
        $interview_id,
        $expert['id'],
        $interview['candidate_id'],
        $scores['technical'],
        $scores['communication'],
        $scores['problemSolving'],
        $scores['culturalFit'],
        $overall_impression,
        $notes,
        json_encode($tags)
    ]);
    
    // Log collaboration data
    $collab_query = "INSERT INTO interview_collaboration_data (interview_id, data_type, data_content, created_by)
                    VALUES (:interview_id, 'evaluation_update', :data_content, :created_by)";
    $collab_stmt = $db->prepare($collab_query);
    $collab_stmt->bindParam(':interview_id', $interview_id);
    $collab_stmt->bindParam(':data_content', json_encode(['scores' => $scores, 'tags' => $tags]));
    $collab_stmt->bindParam(':created_by', $_SESSION['user_id']);
    $collab_stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Evaluation saved successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
