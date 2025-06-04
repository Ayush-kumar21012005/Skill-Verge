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

$message = '';
$error = '';

// Handle resume upload and parsing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume'])) {
    $file = $_FILES['resume'];
    
    // Validate file
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!in_array($file['type'], $allowed_types)) {
        $error = 'Please upload a PDF or Word document.';
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        $error = 'File size must be less than 5MB.';
    } else {
        // Upload file
        $upload_dir = '../uploads/resumes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filename = uniqid() . '_' . time() . '_' . $file['name'];
        $file_path = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Parse resume
            $parsed_data = parseResume($file_path, $file['type']);
            
            // Save analysis to database
            $insert_analysis = "INSERT INTO resume_analysis 
                               (candidate_id, resume_url, extracted_text, parsed_data, 
                                skills_extracted, experience_extracted, education_extracted, 
                                contact_info, analysis_score) 
                               VALUES (:candidate_id, :resume_url, :extracted_text, :parsed_data, 
                                       :skills, :experience, :education, :contact, :score)";
            $analysis_stmt = $db->prepare($insert_analysis);
            $analysis_stmt->bindParam(':candidate_id', $candidate['id']);
            $analysis_stmt->bindParam(':resume_url', $file_path);
            $analysis_stmt->bindParam(':extracted_text', $parsed_data['text']);
            $analysis_stmt->bindParam(':parsed_data', json_encode($parsed_data));
            $analysis_stmt->bindParam(':skills', json_encode($parsed_data['skills']));
            $analysis_stmt->bindParam(':experience', json_encode($parsed_data['experience']));
            $analysis_stmt->bindParam(':education', json_encode($parsed_data['education']));
            $analysis_stmt->bindParam(':contact', json_encode($parsed_data['contact']));
            $analysis_stmt->bindParam(':score', $parsed_data['score']);
            
            if ($analysis_stmt->execute()) {
                // Update candidate profile with extracted data
                $update_candidate = "UPDATE candidates SET 
                                   resume_url = :resume_url, 
                                   skills = :skills,
                                   experience_years = :experience_years
                                   WHERE id = :candidate_id";
                $update_stmt = $db->prepare($update_candidate);
                $update_stmt->bindParam(':resume_url', $file_path);
                $update_stmt->bindParam(':skills', implode(', ', $parsed_data['skills']));
                $update_stmt->bindParam(':experience_years', $parsed_data['experience_years']);
                $update_stmt->bindParam(':candidate_id', $candidate['id']);
                $update_stmt->execute();
                
                $message = 'Resume uploaded and analyzed successfully!';
            } else {
                $error = 'Failed to save resume analysis.';
            }
        } else {
            $error = 'Failed to upload resume.';
        }
    }
}

// Get existing resume analyses
$analyses_query = "SELECT * FROM resume_analysis WHERE candidate_id = :candidate_id ORDER BY created_at DESC";
$analyses_stmt = $db->prepare($analyses_query);
$analyses_stmt->bindParam(':candidate_id', $candidate['id']);
$analyses_stmt->execute();
$analyses = $analyses_stmt->fetchAll(PDO::FETCH_ASSOC);

function parseResume($file_path, $mime_type) {
    $extracted_text = '';
    
    // Extract text based on file type
    if ($mime_type === 'application/pdf') {
        $extracted_text = extractTextFromPDF($file_path);
    } elseif (strpos($mime_type, 'word') !== false) {
        $extracted_text = extractTextFromWord($file_path);
    }
    
    // Parse extracted text
    $skills = extractSkills($extracted_text);
    $experience = extractExperience($extracted_text);
    $education = extractEducation($extracted_text);
    $contact = extractContactInfo($extracted_text);
    $experience_years = calculateExperienceYears($experience);
    
    // Calculate analysis score
    $score = calculateResumeScore($skills, $experience, $education, $contact, $extracted_text);
    
    return [
        'text' => $extracted_text,
        'skills' => $skills,
        'experience' => $experience,
        'education' => $education,
        'contact' => $contact,
        'experience_years' => $experience_years,
        'score' => $score
    ];
}

function extractTextFromPDF($file_path) {
    // For demo purposes, return sample text
    // In production, use libraries like pdf2text or pdftotext
    return "Sample extracted text from PDF resume. This would contain the actual resume content including skills like PHP, JavaScript, Python, experience at various companies, education details, etc.";
}

function extractTextFromWord($file_path) {
    // For demo purposes, return sample text
    // In production, use libraries like PHPWord or antiword
    return "Sample extracted text from Word resume. This would contain the actual resume content.";
}

function extractSkills($text) {
    $skill_keywords = [
        'Programming' => ['PHP', 'JavaScript', 'Python', 'Java', 'C++', 'C#', 'Ruby', 'Go', 'Rust', 'Swift'],
        'Web Technologies' => ['HTML', 'CSS', 'React', 'Angular', 'Vue.js', 'Node.js', 'Express', 'Laravel', 'Django'],
        'Databases' => ['MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'SQLite', 'Oracle', 'SQL Server'],
        'Cloud' => ['AWS', 'Azure', 'Google Cloud', 'Docker', 'Kubernetes', 'Terraform'],
        'Tools' => ['Git', 'Jenkins', 'JIRA', 'Slack', 'Photoshop', 'Figma']
    ];
    
    $found_skills = [];
    $text_lower = strtolower($text);
    
    foreach ($skill_keywords as $category => $skills) {
        foreach ($skills as $skill) {
            if (strpos($text_lower, strtolower($skill)) !== false) {
                $found_skills[] = $skill;
            }
        }
    }
    
    return array_unique($found_skills);
}

function extractExperience($text) {
    // Simple regex patterns to find experience
    $experience = [];
    
    // Look for company names and positions
    $patterns = [
        '/(?:worked at|employed at|experience at)\s+([A-Za-z\s&]+)/i',
        '/(?:software engineer|developer|analyst|manager)\s+at\s+([A-Za-z\s&]+)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $company) {
                $experience[] = [
                    'company' => trim($company),
                    'position' => 'Software Developer', // Simplified
                    'duration' => '2 years' // Simplified
                ];
            }
        }
    }
    
    return $experience;
}

function extractEducation($text) {
    $education = [];
    
    // Look for degree patterns
    $degree_patterns = [
        '/(?:bachelor|b\.?tech|b\.?e\.?|b\.?sc|b\.?a\.?)\s+(?:of|in)?\s*([A-Za-z\s]+)/i',
        '/(?:master|m\.?tech|m\.?e\.?|m\.?sc|m\.?a\.?|mba)\s+(?:of|in)?\s*([A-Za-z\s]+)/i'
    ];
    
    foreach ($degree_patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[0] as $degree) {
                $education[] = [
                    'degree' => trim($degree),
                    'institution' => 'University Name', // Simplified
                    'year' => '2020' // Simplified
                ];
            }
        }
    }
    
    return $education;
}

function extractContactInfo($text) {
    $contact = [];
    
    // Extract email
    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $email_match)) {
        $contact['email'] = $email_match[0];
    }
    
    // Extract phone
    if (preg_match('/(?:\+91|91)?[\s-]?[6-9]\d{9}/', $text, $phone_match)) {
        $contact['phone'] = $phone_match[0];
    }
    
    // Extract LinkedIn
    if (preg_match('/linkedin\.com\/in\/([A-Za-z0-9-]+)/', $text, $linkedin_match)) {
        $contact['linkedin'] = $linkedin_match[0];
    }
    
    return $contact;
}

function calculateExperienceYears($experience) {
    // Simplified calculation
    return count($experience) * 2; // Assume 2 years per job
}

function calculateResumeScore($skills, $experience, $education, $contact, $text) {
    $score = 0;
    
    // Skills score (40%)
    $skills_score = min(count($skills) * 2, 40);
    
    // Experience score (30%)
    $experience_score = min(count($experience) * 10, 30);
    
    // Education score (20%)
    $education_score = min(count($education) * 20, 20);
    
    // Contact info score (10%)
    $contact_score = count($contact) * 2.5;
    
    $total_score = $skills_score + $experience_score + $education_score + $contact_score;
    
    return min($total_score / 10, 10); // Scale to 10
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Parser - SkillVerge</title>
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
            <small class="text-muted">Resume Parser</small>
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
                    <a class="nav-link active" href="resume-parser.php">
                        <i class="fas fa-file-alt"></i>Resume Parser
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="skill-tests.php">
                        <i class="fas fa-tasks"></i>Skill Tests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="job-board.php">
                        <i class="fas fa-briefcase"></i>Job Board
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
                    <h2 class="fw-bold mb-1">AI Resume Parser</h2>
                    <p class="text-muted mb-0">Upload your resume for AI-powered analysis and skill extraction</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#tipsModal">
                        <i class="fas fa-lightbulb me-2"></i>Resume Tips
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Upload Section -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-upload me-2"></i>Upload Resume
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="resumeUploadForm">
                                <div class="upload-area border-2 border-dashed border-primary rounded p-4 text-center mb-3" 
                                     ondrop="dropHandler(event);" ondragover="dragOverHandler(event);" ondragleave="dragLeaveHandler(event);">
                                    <i class="fas fa-cloud-upload-alt text-primary" style="font-size: 3rem;"></i>
                                    <h5 class="mt-3">Drag & Drop your resume here</h5>
                                    <p class="text-muted">or click to browse files</p>
                                    <input type="file" class="form-control d-none" id="resume" name="resume" 
                                           accept=".pdf,.doc,.docx" required onchange="fileSelected(this)">
                                    <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('resume').click()">
                                        <i class="fas fa-folder-open me-2"></i>Browse Files
                                    </button>
                                </div>
                                
                                <div class="file-info d-none" id="fileInfo">
                                    <div class="alert alert-info">
                                        <i class="fas fa-file me-2"></i>
                                        <span id="fileName"></span>
                                        <span class="badge bg-primary ms-2" id="fileSize"></span>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg" id="uploadBtn" disabled>
                                        <i class="fas fa-magic me-2"></i>Parse Resume with AI
                                    </button>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Supported formats: PDF, DOC, DOCX (Max 5MB)
                                    </small>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Features -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-star me-2"></i>AI Parser Features
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="text-center">
                                        <i class="fas fa-cogs text-primary" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Skills Extraction</h6>
                                        <small class="text-muted">Automatically identify technical and soft skills</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <i class="fas fa-briefcase text-success" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Experience Analysis</h6>
                                        <small class="text-muted">Parse work history and calculate experience</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <i class="fas fa-graduation-cap text-warning" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Education Details</h6>
                                        <small class="text-muted">Extract degree and institution information</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <i class="fas fa-chart-line text-info" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Resume Score</h6>
                                        <small class="text-muted">Get an overall resume quality score</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analysis Results -->
                <div class="col-lg-6">
                    <?php if (!empty($analyses)): ?>
                        <?php $latest_analysis = $analyses[0]; ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>Latest Analysis Results
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Resume Score -->
                                <div class="text-center mb-4">
                                    <div class="resume-score-circle">
                                        <canvas id="scoreChart" width="150" height="150"></canvas>
                                        <div class="score-text">
                                            <h3 class="mb-0"><?php echo number_format($latest_analysis['analysis_score'], 1); ?></h3>
                                            <small class="text-muted">Resume Score</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Skills -->
                                <?php 
                                $skills = json_decode($latest_analysis['skills_extracted'], true);
                                if ($skills): 
                                ?>
                                    <div class="mb-4">
                                        <h6 class="fw-bold">
                                            <i class="fas fa-cogs text-primary me-2"></i>Extracted Skills (<?php echo count($skills); ?>)
                                        </h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach (array_slice($skills, 0, 10) as $skill): ?>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($skill); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($skills) > 10): ?>
                                                <span class="badge bg-secondary">+<?php echo count($skills) - 10; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Experience -->
                                <?php 
                                $experience = json_decode($latest_analysis['experience_extracted'], true);
                                if ($experience): 
                                ?>
                                    <div class="mb-4">
                                        <h6 class="fw-bold">
                                            <i class="fas fa-briefcase text-success me-2"></i>Work Experience
                                        </h6>
                                        <?php foreach (array_slice($experience, 0, 3) as $exp): ?>
                                            <div class="border-start border-3 border-success ps-3 mb-2">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($exp['position'] ?? 'Position'); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($exp['company'] ?? 'Company'); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Education -->
                                <?php 
                                $education = json_decode($latest_analysis['education_extracted'], true);
                                if ($education): 
                                ?>
                                    <div class="mb-4">
                                        <h6 class="fw-bold">
                                            <i class="fas fa-graduation-cap text-warning me-2"></i>Education
                                        </h6>
                                        <?php foreach ($education as $edu): ?>
                                            <div class="border-start border-3 border-warning ps-3 mb-2">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($edu['degree'] ?? 'Degree'); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($edu['institution'] ?? 'Institution'); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#fullAnalysisModal">
                                        <i class="fas fa-eye me-2"></i>View Full Analysis
                                    </button>
                                    <button class="btn btn-outline-success" onclick="downloadReport()">
                                        <i class="fas fa-download me-2"></i>Download Report
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-file-alt text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 text-muted">No Resume Analyzed Yet</h4>
                                <p class="text-muted">Upload your resume to get started with AI-powered analysis</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Analysis History -->
            <?php if (count($analyses) > 1): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>Analysis History
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Score</th>
                                                <th>Skills Found</th>
                                                <th>Experience</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($analyses, 1) as $analysis): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y', strtotime($analysis['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $analysis['analysis_score'] >= 7 ? 'success' : ($analysis['analysis_score'] >= 5 ? 'warning' : 'danger'); ?>">
                                                            <?php echo number_format($analysis['analysis_score'], 1); ?>/10
                                                        </span>
                                                    </td>
                                                    <td><?php echo count(json_decode($analysis['skills_extracted'], true) ?: []); ?></td>
                                                    <td><?php echo count(json_decode($analysis['experience_extracted'], true) ?: []); ?> positions</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewAnalysis(<?php echo $analysis['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAnalysis(<?php echo $analysis['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Resume Tips Modal -->
    <div class="modal fade" id="tipsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resume Optimization Tips</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="text-success"><i class="fas fa-check-circle me-2"></i>Do's</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Use clear, readable fonts</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Include relevant keywords</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Quantify achievements</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Keep it concise (1-2 pages)</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Use action verbs</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Include contact information</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-danger"><i class="fas fa-times-circle me-2"></i>Don'ts</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Use fancy graphics or colors</li>
                                <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Include personal photos</li>
                                <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Use generic templates</li>
                                <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Include irrelevant information</li>
                                <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Use passive voice</li>
                                <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Forget to proofread</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // File upload handling
        function dropHandler(ev) {
            ev.preventDefault();
            const files = ev.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('resume').files = files;
                fileSelected(document.getElementById('resume'));
            }
            ev.target.classList.remove('border-success');
        }

        function dragOverHandler(ev) {
            ev.preventDefault();
            ev.target.classList.add('border-success');
        }

        function dragLeaveHandler(ev) {
            ev.target.classList.remove('border-success');
        }

        function fileSelected(input) {
            const file = input.files[0];
            if (file) {
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = formatFileSize(file.size);
                document.getElementById('fileInfo').classList.remove('d-none');
                document.getElementById('uploadBtn').disabled = false;
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Resume score chart
        <?php if (!empty($analyses)): ?>
        const ctx = document.getElementById('scoreChart').getContext('2d');
        const score = <?php echo $latest_analysis['analysis_score']; ?>;
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [score, 10 - score],
                    backgroundColor: [
                        score >= 7 ? '#28a745' : score >= 5 ? '#ffc107' : '#dc3545',
                        '#e9ecef'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>

        function downloadReport() {
            window.open('download-resume-report.php', '_blank');
        }

        function viewAnalysis(analysisId) {
            // Open analysis details in modal or new page
            window.open(`view-analysis.php?id=${analysisId}`, '_blank');
        }

        function deleteAnalysis(analysisId) {
            if (confirm('Are you sure you want to delete this analysis?')) {
                fetch('delete-analysis.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ analysis_id: analysisId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting analysis: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to delete analysis');
                });
            }
        }
    </script>
</body>
</html>
