<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['expert', 'admin']);

try {
    $candidate_id = $_POST['candidate_id'] ?? null;
    $expert_id = $_POST['expert_id'] ?? null;
    
    if (!$candidate_id) {
        throw new Exception('Candidate ID is required');
    }
    
    // Get candidate basic info
    $candidate_query = "SELECT c.*, u.full_name, u.email, u.phone 
                       FROM candidates c 
                       JOIN users u ON c.user_id = u.id 
                       WHERE c.id = :candidate_id";
    $candidate_stmt = $db->prepare($candidate_query);
    $candidate_stmt->bindParam(':candidate_id', $candidate_id);
    $candidate_stmt->execute();
    $candidate = $candidate_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidate) {
        throw new Exception('Candidate not found');
    }
    
    // Get AI interview history and performance
    $ai_interviews_query = "SELECT * FROM ai_interviews 
                           WHERE candidate_id = :candidate_id 
                           ORDER BY completed_at DESC";
    $ai_stmt = $db->prepare($ai_interviews_query);
    $ai_stmt->bindParam(':candidate_id', $candidate_id);
    $ai_stmt->execute();
    $ai_interviews = $ai_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get skill test results
    $skill_tests_query = "SELECT st.*, sts.score, sts.completed_at as test_completed_at
                         FROM skill_test_submissions sts
                         JOIN skill_tests st ON sts.test_id = st.id
                         WHERE sts.candidate_id = :candidate_id
                         ORDER BY sts.completed_at DESC";
    $skill_stmt = $db->prepare($skill_tests_query);
    $skill_stmt->bindParam(':candidate_id', $candidate_id);
    $skill_stmt->execute();
    $skill_tests = $skill_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get job applications and preferences
    $applications_query = "SELECT ja.*, jp.title as job_title, jp.required_skills, c.company_name
                          FROM job_applications ja
                          JOIN job_postings jp ON ja.job_id = jp.id
                          JOIN companies c ON jp.company_id = c.id
                          WHERE ja.candidate_id = :candidate_id
                          ORDER BY ja.applied_at DESC LIMIT 5";
    $app_stmt = $db->prepare($applications_query);
    $app_stmt->bindParam(':candidate_id', $candidate_id);
    $app_stmt->execute();
    $applications = $app_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate AI-powered candidate brief
    $brief = generateCandidateBrief($candidate, $ai_interviews, $skill_tests, $applications);
    
    // Save the brief for expert access
    $save_brief_query = "INSERT INTO candidate_briefs (candidate_id, expert_id, brief_data, generated_at)
                        VALUES (:candidate_id, :expert_id, :brief_data, NOW())
                        ON DUPLICATE KEY UPDATE 
                        brief_data = VALUES(brief_data), 
                        generated_at = NOW()";
    $save_stmt = $db->prepare($save_brief_query);
    $save_stmt->bindParam(':candidate_id', $candidate_id);
    $save_stmt->bindParam(':expert_id', $expert_id);
    $save_stmt->bindParam(':brief_data', json_encode($brief));
    $save_stmt->execute();
    
    echo json_encode([
        'success' => true,
        'brief' => $brief
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function generateCandidateBrief($candidate, $ai_interviews, $skill_tests, $applications) {
    // Calculate performance metrics
    $total_interviews = count($ai_interviews);
    $avg_overall_score = $total_interviews > 0 ? array_sum(array_column($ai_interviews, 'overall_score')) / $total_interviews : 0;
    $avg_technical_score = $total_interviews > 0 ? array_sum(array_column($ai_interviews, 'technical_score')) / $total_interviews : 0;
    $avg_communication_score = $total_interviews > 0 ? array_sum(array_column($ai_interviews, 'communication_score')) / $total_interviews : 0;
    $avg_confidence_score = $total_interviews > 0 ? array_sum(array_column($ai_interviews, 'confidence_score')) / $total_interviews : 0;
    
    // Analyze domain expertise
    $domain_performance = [];
    foreach ($ai_interviews as $interview) {
        $domain = $interview['domain'];
        if (!isset($domain_performance[$domain])) {
            $domain_performance[$domain] = ['count' => 0, 'total_score' => 0];
        }
        $domain_performance[$domain]['count']++;
        $domain_performance[$domain]['total_score'] += $interview['overall_score'];
    }
    
    foreach ($domain_performance as $domain => &$perf) {
        $perf['avg_score'] = $perf['total_score'] / $perf['count'];
    }
    
    // Identify strengths and weaknesses
    $strengths = [];
    $weaknesses = [];
    $recommendations = [];
    
    if ($avg_technical_score >= 7) {
        $strengths[] = "Strong technical knowledge and problem-solving skills";
    } elseif ($avg_technical_score < 5) {
        $weaknesses[] = "Technical knowledge needs improvement";
        $recommendations[] = "Focus on strengthening core technical concepts";
    }
    
    if ($avg_communication_score >= 7) {
        $strengths[] = "Excellent communication and articulation skills";
    } elseif ($avg_communication_score < 5) {
        $weaknesses[] = "Communication clarity needs work";
        $recommendations[] = "Practice explaining technical concepts clearly";
    }
    
    if ($avg_confidence_score >= 7) {
        $strengths[] = "Confident and composed during interviews";
    } elseif ($avg_confidence_score < 5) {
        $weaknesses[] = "Lacks confidence in responses";
        $recommendations[] = "Build confidence through more practice sessions";
    }
    
    // Analyze skill test performance
    $skill_summary = [];
    foreach ($skill_tests as $test) {
        $skill_summary[] = [
            'skill' => $test['skill_name'],
            'score' => $test['score'],
            'level' => $test['score'] >= 80 ? 'Advanced' : ($test['score'] >= 60 ? 'Intermediate' : 'Beginner'),
            'completed_at' => $test['test_completed_at']
        ];
    }
    
    // Career interests analysis
    $career_interests = [];
    $applied_roles = array_unique(array_column($applications, 'job_title'));
    $required_skills = [];
    foreach ($applications as $app) {
        $skills = explode(',', $app['required_skills']);
        $required_skills = array_merge($required_skills, array_map('trim', $skills));
    }
    $top_skills = array_count_values($required_skills);
    arsort($top_skills);
    $top_skills = array_slice($top_skills, 0, 5, true);
    
    // Generate AI insights
    $ai_insights = generateAIInsights($candidate, $avg_overall_score, $domain_performance, $strengths, $weaknesses);
    
    // Interview focus areas
    $focus_areas = [];
    if ($avg_technical_score < $avg_communication_score) {
        $focus_areas[] = "Technical depth and problem-solving approach";
    }
    if ($avg_communication_score < 6) {
        $focus_areas[] = "Communication clarity and structure";
    }
    if ($avg_confidence_score < 6) {
        $focus_areas[] = "Building confidence and reducing nervousness";
    }
    if (empty($focus_areas)) {
        $focus_areas[] = "Advanced technical scenarios and leadership questions";
    }
    
    return [
        'candidate_info' => [
            'name' => $candidate['full_name'],
            'email' => $candidate['email'],
            'phone' => $candidate['phone'],
            'experience_level' => $candidate['experience_level'],
            'preferred_role' => $candidate['preferred_role'],
            'is_premium' => $candidate['is_premium'],
            'total_interviews' => $candidate['trial_interviews_used']
        ],
        'performance_summary' => [
            'total_ai_interviews' => $total_interviews,
            'average_scores' => [
                'overall' => round($avg_overall_score, 1),
                'technical' => round($avg_technical_score, 1),
                'communication' => round($avg_communication_score, 1),
                'confidence' => round($avg_confidence_score, 1)
            ],
            'performance_trend' => calculatePerformanceTrend($ai_interviews),
            'best_domain' => !empty($domain_performance) ? array_keys($domain_performance, max($domain_performance))[0] : null
        ],
        'domain_expertise' => $domain_performance,
        'skill_assessments' => $skill_summary,
        'strengths' => $strengths,
        'weaknesses' => $weaknesses,
        'recommendations' => $recommendations,
        'career_interests' => [
            'applied_roles' => $applied_roles,
            'top_skills' => array_keys($top_skills)
        ],
        'ai_insights' => $ai_insights,
        'interview_focus_areas' => $focus_areas,
        'suggested_questions' => generateSuggestedQuestions($candidate, $domain_performance, $weaknesses),
        'preparation_tips' => generatePreparationTips($weaknesses, $recommendations),
        'generated_at' => date('Y-m-d H:i:s')
    ];
}

function calculatePerformanceTrend($interviews) {
    if (count($interviews) < 2) return 'insufficient_data';
    
    $recent = array_slice($interviews, 0, 3);
    $older = array_slice($interviews, 3, 3);
    
    if (empty($older)) return 'improving';
    
    $recent_avg = array_sum(array_column($recent, 'overall_score')) / count($recent);
    $older_avg = array_sum(array_column($older, 'overall_score')) / count($older);
    
    $diff = $recent_avg - $older_avg;
    
    if ($diff > 0.5) return 'improving';
    if ($diff < -0.5) return 'declining';
    return 'stable';
}

function generateAIInsights($candidate, $avg_score, $domain_performance, $strengths, $weaknesses) {
    $insights = [];
    
    if ($avg_score >= 8) {
        $insights[] = "High-performing candidate with strong overall interview skills";
    } elseif ($avg_score >= 6) {
        $insights[] = "Solid candidate with good potential, some areas for improvement";
    } else {
        $insights[] = "Candidate needs significant preparation before real interviews";
    }
    
    if (!empty($domain_performance)) {
        $best_domain = array_keys($domain_performance, max($domain_performance))[0];
        $insights[] = "Shows strongest performance in {$best_domain}";
    }
    
    if (count($strengths) > count($weaknesses)) {
        $insights[] = "More strengths than weaknesses - focus on leveraging existing skills";
    } else {
        $insights[] = "Multiple improvement areas identified - structured preparation needed";
    }
    
    return $insights;
}

function generateSuggestedQuestions($candidate, $domain_performance, $weaknesses) {
    $questions = [];
    
    // Based on experience level
    if ($candidate['experience_level'] === 'entry') {
        $questions[] = "Tell me about a challenging project you worked on during your studies";
        $questions[] = "How do you approach learning new technologies?";
    } elseif ($candidate['experience_level'] === 'mid') {
        $questions[] = "Describe a time when you had to debug a complex issue";
        $questions[] = "How do you handle conflicting requirements from stakeholders?";
    } else {
        $questions[] = "How do you mentor junior developers?";
        $questions[] = "Describe your approach to system architecture decisions";
    }
    
    // Based on weaknesses
    if (in_array("Technical knowledge needs improvement", $weaknesses)) {
        $questions[] = "Walk me through your problem-solving process for technical challenges";
        $questions[] = "How do you stay updated with latest technology trends?";
    }
    
    if (in_array("Communication clarity needs work", $weaknesses)) {
        $questions[] = "Explain a complex technical concept to a non-technical person";
        $questions[] = "How do you handle disagreements in technical discussions?";
    }
    
    return $questions;
}

function generatePreparationTips($weaknesses, $recommendations) {
    $tips = [];
    
    foreach ($recommendations as $rec) {
        switch ($rec) {
            case "Focus on strengthening core technical concepts":
                $tips[] = "Review fundamental concepts in data structures and algorithms";
                $tips[] = "Practice coding problems on platforms like LeetCode or HackerRank";
                break;
            case "Practice explaining technical concepts clearly":
                $tips[] = "Practice the 'rubber duck' method - explain concepts out loud";
                $tips[] = "Record yourself explaining technical topics and review for clarity";
                break;
            case "Build confidence through more practice sessions":
                $tips[] = "Take more AI mock interviews to build familiarity";
                $tips[] = "Practice positive self-talk and visualization techniques";
                break;
        }
    }
    
    return array_unique($tips);
}
?>
