<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['company']);

// Get company data
$company_query = "SELECT * FROM companies WHERE user_id = :user_id";
$company_stmt = $db->prepare($company_query);
$company_stmt->bindParam(':user_id', $_SESSION['user_id']);
$company_stmt->execute();
$company = $company_stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    $location = trim($_POST['location']);
    $job_type = $_POST['job_type'];
    $experience_level = $_POST['experience_level'];
    $salary_min = !empty($_POST['salary_min']) ? floatval($_POST['salary_min']) : null;
    $salary_max = !empty($_POST['salary_max']) ? floatval($_POST['salary_max']) : null;
    $currency = $_POST['currency'];
    $skills_required = array_filter(array_map('trim', explode(',', $_POST['skills_required'])));
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    // Validation
    $errors = [];
    
    if (empty($title)) $errors[] = 'Job title is required';
    if (empty($description)) $errors[] = 'Job description is required';
    if (empty($location)) $errors[] = 'Location is required';
    if (empty($job_type)) $errors[] = 'Job type is required';
    if (empty($experience_level)) $errors[] = 'Experience level is required';
    if ($salary_min && $salary_max && $salary_min > $salary_max) {
        $errors[] = 'Minimum salary cannot be greater than maximum salary';
    }
    if ($expires_at && strtotime($expires_at) < time()) {
        $errors[] = 'Expiry date cannot be in the past';
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        $insert_query = "INSERT INTO job_postings 
                        (company_id, title, description, requirements, location, job_type, 
                         experience_level, salary_min, salary_max, currency, skills_required, expires_at) 
                        VALUES 
                        (:company_id, :title, :description, :requirements, :location, :job_type,
                         :experience_level, :salary_min, :salary_max, :currency, :skills_required, :expires_at)";
        
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':company_id', $company['id']);
        $insert_stmt->bindParam(':title', $title);
        $insert_stmt->bindParam(':description', $description);
        $insert_stmt->bindParam(':requirements', $requirements);
        $insert_stmt->bindParam(':location', $location);
        $insert_stmt->bindParam(':job_type', $job_type);
        $insert_stmt->bindParam(':experience_level', $experience_level);
        $insert_stmt->bindParam(':salary_min', $salary_min);
        $insert_stmt->bindParam(':salary_max', $salary_max);
        $insert_stmt->bindParam(':currency', $currency);
        $insert_stmt->bindParam(':skills_required', json_encode($skills_required));
        $insert_stmt->bindParam(':expires_at', $expires_at);
        
        if ($insert_stmt->execute()) {
            $message = 'Job posting created successfully!';
            // Clear form data
            $_POST = [];
        } else {
            $error = 'Failed to create job posting. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Job Posting - SkillVerge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="p-3 border-bottom">
            <h5 class="fw-bold text-success mb-0">
                <i class="fas fa-building me-2"></i>SkillVerge
            </h5>
            <small class="text-muted">Create Job</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="create-interview.php">
                        <i class="fas fa-plus-circle"></i>Create Interview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="create-job.php">
                        <i class="fas fa-briefcase"></i>Create Job
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage-jobs.php">
                        <i class="fas fa-list"></i>Manage Jobs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="candidates.php">
                        <i class="fas fa-users"></i>Candidates
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
                    <h2 class="fw-bold mb-1">Create Job Posting</h2>
                    <p class="text-muted mb-0">Post a new job opportunity to attract top talent</p>
                </div>
                <a href="manage-jobs.php" class="btn btn-outline-primary">
                    <i class="fas fa-list me-2"></i>View All Jobs
                </a>
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

            <!-- Job Creation Form -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-plus-circle me-2"></i>Job Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row g-3">
                                    <!-- Job Title -->
                                    <div class="col-12">
                                        <label for="title" class="form-label">Job Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" required 
                                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                                               placeholder="e.g., Senior Software Developer">
                                    </div>

                                    <!-- Location -->
                                    <div class="col-md-6">
                                        <label for="location" class="form-label">Location *</label>
                                        <input type="text" class="form-control" id="location" name="location" required 
                                               value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
                                               placeholder="e.g., Mumbai, Remote, Hybrid">
                                    </div>

                                    <!-- Job Type -->
                                    <div class="col-md-6">
                                        <label for="job_type" class="form-label">Job Type *</label>
                                        <select class="form-control" id="job_type" name="job_type" required>
                                            <option value="">Select job type</option>
                                            <option value="full-time" <?php echo (isset($_POST['job_type']) && $_POST['job_type'] === 'full-time') ? 'selected' : ''; ?>>Full-time</option>
                                            <option value="part-time" <?php echo (isset($_POST['job_type']) && $_POST['job_type'] === 'part-time') ? 'selected' : ''; ?>>Part-time</option>
                                            <option value="contract" <?php echo (isset($_POST['job_type']) && $_POST['job_type'] === 'contract') ? 'selected' : ''; ?>>Contract</option>
                                            <option value="internship" <?php echo (isset($_POST['job_type']) && $_POST['job_type'] === 'internship') ? 'selected' : ''; ?>>Internship</option>
                                        </select>
                                    </div>

                                    <!-- Experience Level -->
                                    <div class="col-md-6">
                                        <label for="experience_level" class="form-label">Experience Level *</label>
                                        <select class="form-control" id="experience_level" name="experience_level" required>
                                            <option value="">Select experience level</option>
                                            <option value="entry" <?php echo (isset($_POST['experience_level']) && $_POST['experience_level'] === 'entry') ? 'selected' : ''; ?>>Entry Level (0-2 years)</option>
                                            <option value="mid" <?php echo (isset($_POST['experience_level']) && $_POST['experience_level'] === 'mid') ? 'selected' : ''; ?>>Mid Level (2-5 years)</option>
                                            <option value="senior" <?php echo (isset($_POST['experience_level']) && $_POST['experience_level'] === 'senior') ? 'selected' : ''; ?>>Senior Level (5+ years)</option>
                                            <option value="executive" <?php echo (isset($_POST['experience_level']) && $_POST['experience_level'] === 'executive') ? 'selected' : ''; ?>>Executive Level</option>
                                        </select>
                                    </div>

                                    <!-- Currency -->
                                    <div class="col-md-6">
                                        <label for="currency" class="form-label">Currency</label>
                                        <select class="form-control" id="currency" name="currency">
                                            <option value="INR" <?php echo (isset($_POST['currency']) && $_POST['currency'] === 'INR') ? 'selected' : ''; ?>>INR (₹)</option>
                                            <option value="USD" <?php echo (isset($_POST['currency']) && $_POST['currency'] === 'USD') ? 'selected' : ''; ?>>USD ($)</option>
                                        </select>
                                    </div>

                                    <!-- Salary Range -->
                                    <div class="col-md-6">
                                        <label for="salary_min" class="form-label">Minimum Salary</label>
                                        <input type="number" class="form-control" id="salary_min" name="salary_min" 
                                               value="<?php echo isset($_POST['salary_min']) ? $_POST['salary_min'] : ''; ?>"
                                               placeholder="e.g., 500000">
                                    </div>

                                    <div class="col-md-6">
                                        <label for="salary_max" class="form-label">Maximum Salary</label>
                                        <input type="number" class="form-control" id="salary_max" name="salary_max" 
                                               value="<?php echo isset($_POST['salary_max']) ? $_POST['salary_max'] : ''; ?>"
                                               placeholder="e.g., 800000">
                                    </div>

                                    <!-- Skills Required -->
                                    <div class="col-12">
                                        <label for="skills_required" class="form-label">Required Skills</label>
                                        <input type="text" class="form-control" id="skills_required" name="skills_required" 
                                               value="<?php echo isset($_POST['skills_required']) ? htmlspecialchars($_POST['skills_required']) : ''; ?>"
                                               placeholder="e.g., JavaScript, React, Node.js, MongoDB (comma separated)">
                                        <small class="text-muted">Separate skills with commas</small>
                                    </div>

                                    <!-- Job Description -->
                                    <div class="col-12">
                                        <label for="description" class="form-label">Job Description *</label>
                                        <textarea class="form-control" id="description" name="description" rows="6" required 
                                                  placeholder="Describe the role, responsibilities, and what you're looking for in a candidate..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                    </div>

                                    <!-- Requirements -->
                                    <div class="col-12">
                                        <label for="requirements" class="form-label">Requirements</label>
                                        <textarea class="form-control" id="requirements" name="requirements" rows="4" 
                                                  placeholder="List the specific requirements, qualifications, and experience needed..."><?php echo isset($_POST['requirements']) ? htmlspecialchars($_POST['requirements']) : ''; ?></textarea>
                                    </div>

                                    <!-- Expiry Date -->
                                    <div class="col-md-6">
                                        <label for="expires_at" class="form-label">Application Deadline</label>
                                        <input type="date" class="form-control" id="expires_at" name="expires_at" 
                                               value="<?php echo isset($_POST['expires_at']) ? $_POST['expires_at'] : ''; ?>"
                                               min="<?php echo date('Y-m-d'); ?>">
                                        <small class="text-muted">Leave blank for no deadline</small>
                                    </div>

                                    <!-- Submit Buttons -->
                                    <div class="col-12">
                                        <hr class="my-4">
                                        <div class="d-flex gap-3">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-plus me-2"></i>Create Job Posting
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="saveDraft()">
                                                <i class="fas fa-save me-2"></i>Save as Draft
                                            </button>
                                            <a href="dashboard.php" class="btn btn-outline-danger">
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function saveDraft() {
            // Save form data as draft
            const formData = new FormData(document.querySelector('form'));
            formData.append('save_draft', '1');
            
            fetch('save-job-draft.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Draft saved successfully!');
                } else {
                    alert('Error saving draft: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to save draft');
            });
        }

        // Auto-save draft every 2 minutes
        setInterval(function() {
            const form = document.querySelector('form');
            const formData = new FormData(form);
            
            // Only auto-save if there's content
            if (formData.get('title') || formData.get('description')) {
                formData.append('auto_save', '1');
                
                fetch('save-job-draft.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Auto-saved draft');
                    }
                })
                .catch(error => console.error('Auto-save error:', error));
            }
        }, 120000); // 2 minutes
    </script>
</body>
</html>
