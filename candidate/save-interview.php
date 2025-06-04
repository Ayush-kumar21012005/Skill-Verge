<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['candidate']);

try {
    // Get candidate data
    $candidate_query = "SELECT * FROM candidates WHERE user_id = :user_id";
    $candidate_stmt = $db->prepare($candidate_query);
    $candidate_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $candidate_stmt->execute();
    $candidate = $candidate_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidate) {
        throw new Exception('Candidate not found');
    }
    
    // Get form data
    $domain = $_POST['domain'];
    $difficulty = $_POST['difficulty'];
    $duration = intval($_POST['duration']);
    $questions = json_decode($_POST['questions'], true);
    $scores = json_decode($_POST['scores'], true);
    
    // Create responses array with audio file paths
    $responses = [];
    $recording_urls = [];
    
    // Handle audio file uploads
    $upload_dir = '../uploads/interviews/' . $candidate['id'] . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    foreach ($_FILES as $key => $file) {
        if (strpos($key, 'audio_') === 0) {
            $question_index = str_replace('audio_', '', $key);
            $filename = 'interview_' . time() . '_q' . $question_index . '.wav';
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $responses[$question_index] = [
                    'question' => $questions[$question_index],
                    'audio_file' => $filename
                ];
                $recording_urls[] = $filepath;
            }
        }
    }
    
    // Generate AI evaluation (mock data for now)
    $ai_evaluation = [
        'technical_feedback' => 'Good understanding of core concepts. Consider providing more specific examples.',
        'communication_feedback' => 'Clear and articulate communication throughout the interview.',
        'confidence_feedback' => 'Confident delivery with good pace and tone.',
        'overall_feedback' => 'Strong performance overall. Focus on providing more detailed examples in future interviews.',
        'strengths' => [
            'Clear communication',
            'Good technical knowledge',
            'Confident delivery'
        ],
        'improvements' => [
            'Provide more specific examples',
            'Practice explaining complex concepts simply'
        ]
    ];
    
    // Insert interview record
    $insert_query = "INSERT INTO ai_interviews 
                    (candidate_id, domain, questions, responses, ai_evaluation, 
                     overall_score, technical_score, communication_score, confidence_score, 
                     recording_url, duration_minutes) 
                    VALUES 
                    (:candidate_id, :domain, :questions, :responses, :ai_evaluation,
                     :overall_score, :technical_score, :communication_score, :confidence_score,
                     :recording_url, :duration_minutes)";
    
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':candidate_id', $candidate['id']);
    $insert_stmt->bindParam(':domain', $domain);
    $insert_stmt->bindParam(':questions', json_encode($questions));
    $insert_stmt->bindParam(':responses', json_encode($responses));
    $insert_stmt->bindParam(':ai_evaluation', json_encode($ai_evaluation));
    $insert_stmt->bindParam(':overall_score', $scores['overall']);
    $insert_stmt->bindParam(':technical_score', $scores['technical']);
    $insert_stmt->bindParam(':communication_score', $scores['communication']);
    $insert_stmt->bindParam(':confidence_score', $scores['confidence']);
    $insert_stmt->bindParam(':recording_url', json_encode($recording_urls));
    $insert_stmt->bindParam(':duration_minutes', $duration);
    
    if ($insert_stmt->execute()) {
        // Update trial usage if not premium
        if (!$candidate['is_premium']) {
            $update_trial = "UPDATE candidates SET trial_interviews_used = trial_interviews_used + 1 WHERE id = :candidate_id";
            $trial_stmt = $db->prepare($update_trial);
            $trial_stmt->bindParam(':candidate_id', $candidate['id']);
            $trial_stmt->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Interview saved successfully']);
    } else {
        throw new Exception('Failed to save interview');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
