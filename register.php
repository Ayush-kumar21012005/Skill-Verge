<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$error_message = '';
$success_message = '';
$user_type = isset($_GET['type']) ? $_GET['type'] : 'candidate';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $user_type = $_POST['user_type'];
    
    // Validation
    if (empty($email) || empty($password) || empty($full_name)) {
        $error_message = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long';
    } else {
        $additional_data = [];
        
        if ($user_type === 'candidate') {
            $additional_data['skills'] = trim($_POST['skills'] ?? '');
            $additional_data['domain'] = trim($_POST['domain'] ?? '');
        } elseif ($user_type === 'company') {
            $additional_data['company_name'] = trim($_POST['company_name'] ?? '');
            $additional_data['industry'] = trim($_POST['industry'] ?? '');
        }
        
        $result = $auth->register($email, $password, $full_name, $user_type, $additional_data);
        
        if ($result['success']) {
            $success_message = 'Registration successful! You can now login.';
        } else {
            $error_message = $result['message'];
        }
    }
}

// Get interview domains for candidate registration
$domains = [];
if ($user_type === 'candidate') {
    $domain_query = "SELECT name FROM interview_domains WHERE is_active = 1";
    $domain_stmt = $db->prepare($domain_query);
    $domain_stmt->execute();
    $domains = $domain_stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SkillVerge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100 py-5">
            <div class="col-md-8 col-lg-6">
                <div class="form-container">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary">
                            <i class="fas fa-graduation-cap me-2"></i>SkillVerge
                        </h2>
                        <p class="text-muted">Create your account and start your interview journey</p>
                    </div>

                    <!-- User Type Selection -->
                    <div class="row mb-4">
                        <div class="col-6">
                            <a href="register.php?type=candidate" 
                               class="btn <?php echo $user_type === 'candidate' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                                <i class="fas fa-user me-2"></i>Candidate
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="register.php?type=company" 
                               class="btn <?php echo $user_type === 'company' ? 'btn-success' : 'btn-outline-success'; ?> w-100">
                                <i class="fas fa-building me-2"></i>Company
                            </a>
                        </div>
                    </div>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                            <div class="mt-2">
                                <a href="login.php" class="btn btn-sm btn-success">Login Now</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="user_type" value="<?php echo $user_type; ?>">
                        
                        <div class="form-group">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required 
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <?php if ($user_type === 'candidate'): ?>
                            <div class="form-group">
                                <label for="skills" class="form-label">Skills</label>
                                <input type="text" class="form-control" id="skills" name="skills" 
                                       placeholder="e.g., JavaScript, Python, React"
                                       value="<?php echo isset($_POST['skills']) ? htmlspecialchars($_POST['skills']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="domain" class="form-label">Preferred Domain</label>
                                <select class="form-control" id="domain" name="domain">
                                    <option value="">Select a domain</option>
                                    <?php foreach ($domains as $domain): ?>
                                        <option value="<?php echo $domain; ?>" 
                                                <?php echo (isset($_POST['domain']) && $_POST['domain'] === $domain) ? 'selected' : ''; ?>>
                                            <?php echo $domain; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif ($user_type === 'company'): ?>
                            <div class="form-group">
                                <label for="company_name" class="form-label">Company Name *</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" required 
                                       value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="industry" class="form-label">Industry</label>
                                <select class="form-control" id="industry" name="industry">
                                    <option value="">Select industry</option>
                                    <option value="Technology" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'Technology') ? 'selected' : ''; ?>>Technology</option>
                                    <option value="Finance" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'Finance') ? 'selected' : ''; ?>>Finance</option>
                                    <option value="Healthcare" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'Healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                                    <option value="Education" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'Education') ? 'selected' : ''; ?>>Education</option>
                                    <option value="Retail" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'Retail') ? 'selected' : ''; ?>>Retail</option>
                                    <option value="Manufacturing" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'Manufacturing') ? 'selected' : ''; ?>>Manufacturing</option>
                                    <option value="Other" <?php echo (isset($_POST['industry']) && $_POST['industry'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required 
                                   minlength="6" placeholder="At least 6 characters">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" class="text-decoration-none">Terms of Service</a> and 
                                <a href="#" class="text-decoration-none">Privacy Policy</a>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>

                    <div class="text-center">
                        <p class="text-muted">Already have an account? 
                            <a href="login.php" class="text-decoration-none fw-bold">Sign in here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
