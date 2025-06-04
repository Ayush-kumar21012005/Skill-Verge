<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['candidate']);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $interview_id = $input['interview_id'];
    $domain = $input['domain'];
    $questions = $input['questions'];
    $audio_files = $input['audio_files'] ?? [];
    
    // Prepare data for Python AI engine
    $analysis_data = [
        'domain' => $domain,
        'questions' => $questions,
        'audio_files' => $audio_files,
        'interview_id' => $interview_id
    ];
    
    // Call Python AI analysis script
    $python_script = __DIR__ . '/../ai_engine/interview_analyzer.py';
    $data_file = tempnam(sys_get_temp_dir(), 'interview_data_');
    file_put_contents($data_file, json_encode($analysis_data));
    
    $command = "python3 $python_script $data_file 2>&1";
    $output = shell_exec($command);
    
    // Clean up temp file
    unlink($data_file);
    
    if ($output) {
        $analysis_result = json_decode($output, true);
        
        if ($analysis_result) {
            echo json_encode([
                'success' => true,
                'analysis' => $analysis_result
            ]);
        } else {
            throw new Exception('Failed to parse AI analysis results');
        }
    } else {
        throw new Exception('AI analysis script failed to execute');
    }
    
} catch (Exception $e) {
    // Fallback to mock analysis if AI engine fails
    $mock_analysis = [
        'scores' => [
            'technical' => rand(60, 95) / 10,
            'communication' => rand(65, 95) / 10,
            'confidence' => rand(60, 90) / 10,
            'overall' => rand(65, 90) / 10
        ],
        'feedback' => [
            'overall_feedback' => 'Good performance with room for improvement in some areas.',
            'technical_feedback' => 'Solid technical understanding. Consider providing more specific examples.',
            'communication_feedback' => 'Clear communication throughout the interview.',
            'confidence_feedback' => 'Confident delivery with good pace.',
            'strengths' => ['Clear communication', 'Good technical knowledge', 'Confident delivery'],
            'improvements' => ['Provide more specific examples', 'Practice explaining complex concepts']
        ],
        'analysis_timestamp' => date('c')
    ];
    
    echo json_encode([
        'success' => true,
        'analysis' => $mock_analysis,
        'note' => 'Using fallback analysis due to: ' . $e->getMessage()
    ]);
}
?>
