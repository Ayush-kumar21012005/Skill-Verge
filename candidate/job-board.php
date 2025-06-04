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

// Handle job application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_job'])) {
    $job_id = $_POST['job_id'];
    $cover_letter = trim($_POST['cover_letter']);
    
    // Check if already applied
    $check_application = "SELECT id FROM job_applications WHERE job_id = :job_id AND candidate_id = :candidate_id";
    $check_stmt = $db->prepare($check_application);
    $check_stmt->bindParam(':job_id', $job_id);
    $check_stmt->bindParam(':candidate_id', $candidate['id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        $apply_query = "INSERT INTO job_applications (job_id, candidate_id, cover_letter, status) 
                       VALUES (:job_id, :candidate_id, :cover_letter, 'applied')";
        $apply_stmt = $db->prepare($apply_query);
        $apply_stmt->bindParam(':job_id', $job_id);
        $apply_stmt->bindParam(':candidate_id', $candidate['id']);
        $apply_stmt->bindParam(':cover_letter', $cover_letter);
        
        if ($apply_stmt->execute()) {
            $success_message = 'Application submitted successfully!';
        } else {
            $error_message = 'Failed to submit application. Please try again.';
        }
    } else {
        $error_message = 'You have already applied for this job.';
    }
}

// Get filters
$location_filter = $_GET['location'] ?? '';
$job_type_filter = $_GET['job_type'] ?? '';
$experience_filter = $_GET['experience'] ?? '';
$search_query = $_GET['search'] ?? '';

// Build query with filters - fix SQL injection
$where_conditions = ['jp.is_active = 1'];
$params = [':candidate_id' => $candidate['id']];

if (!empty($location_filter)) {
    $where_conditions[] = 'jp.location LIKE :location';
    $params[':location'] = '%' . $location_filter . '%';
}

if (!empty($job_type_filter)) {
    $where_conditions[] = 'jp.job_type = :job_type';
    $params[':job_type'] = $job_type_filter;
}

if (!empty($experience_filter)) {
    $where_conditions[] = 'jp.experience_level = :experience';
    $params[':experience'] = $experience_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = '(jp.title LIKE :search OR jp.description LIKE :search OR c.company_name LIKE :search)';
    $params[':search'] = '%' . $search_query . '%';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Add pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$jobs_query = "SELECT jp.*, c.company_name, c.company_size, c.industry,
               (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = jp.id) as application_count,
               (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = jp.id AND ja.candidate_id = :candidate_id) as user_applied
               FROM job_postings jp 
               JOIN companies c ON jp.company_id = c.id 
               $where_clause
               ORDER BY jp.created_at DESC 
               LIMIT $limit OFFSET $offset";

$jobs_stmt = $db->prepare($jobs_query);
foreach ($params as $key => $value) {
    $jobs_stmt->bindValue($key, $value);
}
$jobs_stmt->execute();
$jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get my applications
$my_applications_query = "SELECT ja.*, jp.title, jp.location, c.company_name 
                         FROM job_applications ja 
                         JOIN job_postings jp ON ja.job_id = jp.id 
                         JOIN companies c ON jp.company_id = c.id 
                         WHERE ja.candidate_id = :candidate_id 
                         ORDER BY ja.applied_at DESC 
                         LIMIT 5";
$my_apps_stmt = $db->prepare($my_applications_query);
$my_apps_stmt->bindParam(':candidate_id', $candidate['id']);
$my_apps_stmt->execute();
$my_applications = $my_apps_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Board - SkillVerge</title>
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
            <small class="text-muted">Job Board</small>
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
                    <a class="nav-link" href="expert-interviews.php">
                        <i class="fas fa-users"></i>Expert Interviews
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="job-board.php">
                        <i class="fas fa-briefcase"></i>Job Board
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my-applications.php">
                        <i class="fas fa-file-alt"></i>My Applications
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
                    <h2 class="fw-bold mb-1">Job Board</h2>
                    <p class="text-muted mb-0">Discover and apply for exciting job opportunities</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="my-applications.php" class="btn btn-outline-primary">
                        <i class="fas fa-file-alt me-2"></i>My Applications
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#jobAlertsModal">
                        <i class="fas fa-bell me-2"></i>Job Alerts
                    </button>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search Jobs</label>
                                <input type="text" class="form-control" name="search" placeholder="Job title, company, or keywords" value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" name="location" placeholder="City or remote" value="<?php echo htmlspecialchars($location_filter); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Job Type</label>
                                <select class="form-control" name="job_type">
                                    <option value="">All Types</option>
                                    <option value="full-time" <?php echo $job_type_filter === 'full-time' ? 'selected' : ''; ?>>Full-time</option>
                                    <option value="part-time" <?php echo $job_type_filter === 'part-time' ? 'selected' : ''; ?>>Part-time</option>
                                    <option value="contract" <?php echo $job_type_filter === 'contract' ? 'selected' : ''; ?>>Contract</option>
                                    <option value="internship" <?php echo $job_type_filter === 'internship' ? 'selected' : ''; ?>>Internship</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Experience</label>
                                <select class="form-control" name="experience">
                                    <option value="">All Levels</option>
                                    <option value="entry" <?php echo $experience_filter === 'entry' ? 'selected' : ''; ?>>Entry Level</option>
                                    <option value="mid" <?php echo $experience_filter === 'mid' ? 'selected' : ''; ?>>Mid Level</option>
                                    <option value="senior" <?php echo $experience_filter === 'senior' ? 'selected' : ''; ?>>Senior Level</option>
                                    <option value="executive" <?php echo $experience_filter === 'executive' ? 'selected' : ''; ?>>Executive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Search
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Job Listings -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Available Jobs (<?php echo count($jobs); ?>)</h5>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle btn-sm" data-bs-toggle="dropdown">
                                <i class="fas fa-sort me-2"></i>Sort by
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'newest'])); ?>">Newest First</a></li>
                                <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'salary_high'])); ?>">Salary: High to Low</a></li>
                                <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'salary_low'])); ?>">Salary: Low to High</a></li>
                                <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'company'])); ?>">Company Name</a></li>
                            </ul>
                        </div>
                    </div>

                    <?php if (empty($jobs)): ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-search text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 text-muted">No jobs found</h4>
                                <p class="text-muted">Try adjusting your search criteria or check back later for new opportunities.</p>
                                <a href="job-board.php" class="btn btn-primary">Clear Filters</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($jobs as $job): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-start">
                                                <div class="company-logo me-3">
                                                    <div class="bg-primary rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                        <i class="fas fa-building text-white"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1">
                                                        <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#jobModal<?php echo $job['id']; ?>">
                                                            <?php echo htmlspecialchars($job['title']); ?>
                                                        </a>
                                                    </h5>
                                                    <h6 class="text-primary mb-2"><?php echo htmlspecialchars($job['company_name']); ?></h6>
                                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                                        <span class="badge bg-light text-dark">
                                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location']); ?>
                                                        </span>
                                                        <span class="badge bg-light text-dark">
                                                            <i class="fas fa-briefcase me-1"></i><?php echo ucfirst(str_replace('-', ' ', $job['job_type'])); ?>
                                                        </span>
                                                        <span class="badge bg-light text-dark">
                                                            <i class="fas fa-layer-group me-1"></i><?php echo ucfirst($job['experience_level']); ?>
                                                        </span>
                                                        <?php if ($job['salary_min'] && $job['salary_max']): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-rupee-sign me-1"></i>
                                                                <?php echo number_format($job['salary_min']); ?> - <?php echo number_format($job['salary_max']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-muted mb-2"><?php echo substr(htmlspecialchars($job['description']), 0, 150); ?>...</p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>Posted <?php echo date('M j, Y', strtotime($job['created_at'])); ?>
                                                        <span class="ms-3">
                                                            <i class="fas fa-users me-1"></i><?php echo $job['application_count']; ?> applicants
                                                        </span>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="d-flex flex-column gap-2">
                                                <?php if ($job['user_applied'] > 0): ?>
                                                    <button class="btn btn-success" disabled>
                                                        <i class="fas fa-check me-2"></i>Applied
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyModal<?php echo $job['id']; ?>">
                                                        <i class="fas fa-paper-plane me-2"></i>Apply Now
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-secondary btn-sm" onclick="saveJob(<?php echo $job['id']; ?>)">
                                                    <i class="fas fa-bookmark me-2"></i>Save
                                                </button>
                                                <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#jobModal<?php echo $job['id']; ?>">
                                                    <i class="fas fa-eye me-2"></i>View Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Job Details Modal -->
                            <div class="modal fade" id="jobModal<?php echo $job['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?php echo htmlspecialchars($job['title']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <h6 class="text-primary"><?php echo htmlspecialchars($job['company_name']); ?></h6>
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($job['location']); ?>
                                                    </p>
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-industry me-2"></i><?php echo htmlspecialchars($job['industry']); ?>
                                                    </p>
                                                    <p class="text-muted">
                                                        <i class="fas fa-users me-2"></i><?php echo htmlspecialchars($job['company_size']); ?> employees
                                                    </p>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <span class="badge bg-primary"><?php echo ucfirst(str_replace('-', ' ', $job['job_type'])); ?></span>
                                                        <span class="badge bg-secondary"><?php echo ucfirst($job['experience_level']); ?></span>
                                                        <?php if ($job['salary_min'] && $job['salary_max']): ?>
                                                            <span class="badge bg-success">
                                                                ₹<?php echo number_format($job['salary_min']); ?> - ₹<?php echo number_format($job['salary_max']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <h6 class="fw-bold">Job Description</h6>
                                            <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                                            
                                            <?php if ($job['requirements']): ?>
                                                <h6 class="fw-bold mt-4">Requirements</h6>
                                                <p><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if ($job['skills_required']): ?>
                                                <h6 class="fw-bold mt-4">Required Skills</h6>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php 
                                                    $skills = json_decode($job['skills_required'], true);
                                                    if ($skills) {
                                                        foreach ($skills as $skill) {
                                                            echo '<span class="badge bg-light text-dark">' . htmlspecialchars($skill) . '</span>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <?php if ($job['user_applied'] == 0): ?>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyModal<?php echo $job['id']; ?>" data-bs-dismiss="modal">
                                                    <i class="fas fa-paper-plane me-2"></i>Apply for this Job
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Apply Modal -->
                            <?php if ($job['user_applied'] == 0): ?>
                                <div class="modal fade" id="applyModal<?php echo $job['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Apply for <?php echo htmlspecialchars($job['title']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                    
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Your profile information and resume will be automatically included with this application.
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="cover_letter<?php echo $job['id']; ?>" class="form-label">Cover Letter</label>
                                                        <textarea class="form-control" id="cover_letter<?php echo $job['id']; ?>" name="cover_letter" rows="6" 
                                                                  placeholder="Tell the employer why you're interested in this position and what makes you a great fit..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="apply_job" class="btn btn-primary">
                                                        <i class="fas fa-paper-plane me-2"></i>Submit Application
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- My Recent Applications -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-file-alt me-2"></i>Recent Applications
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($my_applications)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-file-alt text-muted" style="font-size: 2rem;"></i>
                                    <p class="text-muted mt-2 mb-0">No applications yet</p>
                                    <small class="text-muted">Start applying to see your applications here</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($my_applications as $application): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($application['title']); ?></h6>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($application['company_name']); ?></small>
                                        <div class="d-flex justify-content-between align-items-center mt-1">
                                            <span class="badge bg-<?php 
                                                echo $application['status'] === 'applied' ? 'primary' : 
                                                    ($application['status'] === 'interview' ? 'warning' : 
                                                    ($application['status'] === 'hired' ? 'success' : 'secondary')); 
                                            ?> small">
                                                <?php echo ucfirst($application['status']); ?>
                                            </span>
                                            <small class="text-muted"><?php echo date('M j', strtotime($application['applied_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="text-center mt-3">
                                    <a href="my-applications.php" class="btn btn-sm btn-outline-primary">View All Applications</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Job Search Tips -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-lightbulb me-2"></i>Job Search Tips
                            </h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <small>Tailor your cover letter for each application</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <small>Practice with AI interviews before applying</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <small>Keep your profile and skills updated</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <small>Follow up on applications professionally</small>
                                </li>
                                <li class="mb-0">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <small>Network with industry professionals</small>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Recommended Jobs -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-star me-2"></i>Recommended for You
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Based on your skills and preferences</p>
                            <!-- This would be populated with AI-recommended jobs -->
                            <div class="text-center py-3">
                                <i class="fas fa-robot text-muted" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2 mb-0">AI Recommendations</p>
                                <small class="text-muted">Complete your profile to get personalized job recommendations</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Alerts Modal -->
    <div class="modal fade" id="jobAlertsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Job Alerts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Get notified when new jobs matching your criteria are posted.</p>
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Keywords</label>
                            <input type="text" class="form-control" placeholder="e.g., Software Developer, Data Scientist">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" placeholder="e.g., Mumbai, Remote">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Job Type</label>
                            <select class="form-control">
                                <option value="">All Types</option>
                                <option value="full-time">Full-time</option>
                                <option value="part-time">Part-time</option>
                                <option value="contract">Contract</option>
                                <option value="internship">Internship</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Frequency</label>
                            <select class="form-control">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">Create Alert</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function saveJob(jobId) {
            // Save job functionality
            fetch('save-job.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ job_id: jobId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Job saved successfully!', 'success');
                } else {
                    showToast('Error saving job: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to save job', 'error');
            });
        }

        function showToast(message, type) {
            // Create toast notification
            const toast = document.createElement('div');
            toast.className = 'toast position-fixed top-0 end-0 m-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="toast-header">
                    <i class="fas fa-briefcase text-primary me-2"></i>
                    <strong class="me-auto">SkillVerge Jobs</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;
            
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }
    </script>
</body>
</html>
