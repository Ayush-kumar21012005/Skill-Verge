<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth(['admin']);

$message = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated_count = 0;
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $setting_key = substr($key, 8); // Remove 'setting_' prefix
            
            // Validate setting value based on type
            if (in_array($setting_key, ['smtp_port', 'free_trial_interviews', 'max_interview_duration'])) {
                $value = intval($value);
            }
            
            $check_query = "SELECT id FROM system_settings WHERE setting_key = :key";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':key', $setting_key);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $update_query = "UPDATE system_settings SET setting_value = :value, updated_by = :user_id, updated_at = NOW() 
                               WHERE setting_key = :key";
            } else {
                $update_query = "INSERT INTO system_settings (setting_key, setting_value, updated_by, created_at, updated_at) 
                               VALUES (:key, :value, :user_id, NOW(), NOW())";
            }
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':value', $value);
            $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $update_stmt->bindParam(':key', $setting_key);
            
            if ($update_stmt->execute()) {
                $updated_count++;
            }
        }
    }
    
    if ($updated_count > 0) {
        $message = "Settings updated successfully! ($updated_count settings changed)";
    } else {
        $error = 'No settings were updated.';
    }
}

// Get all settings
$settings_query = "SELECT * FROM system_settings ORDER BY setting_key";
$settings_stmt = $db->prepare($settings_query);
$settings_stmt->execute();
$settings = $settings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group settings by category
$grouped_settings = [];
foreach ($settings as $setting) {
    $category = explode('_', $setting['setting_key'])[0];
    $grouped_settings[$category][] = $setting;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - SkillVerge Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="p-3 border-bottom">
            <h5 class="fw-bold text-danger mb-0">
                <i class="fas fa-shield-alt me-2"></i>SkillVerge
            </h5>
            <small class="text-muted">System Settings</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i>User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="companies.php">
                        <i class="fas fa-building"></i>Companies
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card"></i>Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="system-settings.php">
                        <i class="fas fa-cog"></i>Settings
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
                    <h2 class="fw-bold mb-1">System Settings</h2>
                    <p class="text-muted mb-0">Configure platform settings and integrations</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="exportSettings()">
                        <i class="fas fa-download me-2"></i>Export Settings
                    </button>
                    <button class="btn btn-warning" onclick="resetToDefaults()">
                        <i class="fas fa-undo me-2"></i>Reset to Defaults
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

            <!-- Settings Form -->
            <form method="POST" action="">
                <div class="row g-4">
                    <!-- General Settings -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-cog me-2"></i>General Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($grouped_settings['site'])): ?>
                                    <?php foreach ($grouped_settings['site'] as $setting): ?>
                                        <div class="mb-3">
                                            <label class="form-label"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                            <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                <select class="form-control" name="setting_<?php echo $setting['setting_key']; ?>">
                                                    <option value="true" <?php echo $setting['setting_value'] === 'true' ? 'selected' : ''; ?>>Enabled</option>
                                                    <option value="false" <?php echo $setting['setting_value'] === 'false' ? 'selected' : ''; ?>>Disabled</option>
                                                </select>
                                            <?php elseif ($setting['setting_type'] === 'number'): ?>
                                                <input type="number" class="form-control" name="setting_<?php echo $setting['setting_key']; ?>" 
                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                            <?php else: ?>
                                                <input type="text" class="form-control" name="setting_<?php echo $setting['setting_key']; ?>" 
                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                            <?php endif; ?>
                                            <?php if ($setting['description']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($setting['description']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- Additional general settings -->
                                <div class="mb-3">
                                    <label class="form-label">Maintenance Mode</label>
                                    <select class="form-control" name="setting_maintenance_mode">
                                        <option value="false" <?php echo (isset($grouped_settings['maintenance']) && $grouped_settings['maintenance'][0]['setting_value'] === 'false') ? 'selected' : ''; ?>>Disabled</option>
                                        <option value="true" <?php echo (isset($grouped_settings['maintenance']) && $grouped_settings['maintenance'][0]['setting_value'] === 'true') ? 'selected' : ''; ?>>Enabled</option>
                                    </select>
                                    <small class="text-muted">Enable maintenance mode to temporarily disable user access</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Free Trial Interviews</label>
                                    <input type="number" class="form-control" name="setting_free_trial_interviews" 
                                           value="<?php echo isset($grouped_settings['free']) ? $grouped_settings['free'][0]['setting_value'] : '2'; ?>" min="0" max="10">
                                    <small class="text-muted">Number of free AI interviews for new users</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Max Interview Duration (minutes)</label>
                                    <input type="number" class="form-control" name="setting_max_interview_duration" 
                                           value="<?php echo isset($grouped_settings['max']) ? $grouped_settings['max'][0]['setting_value'] : '60'; ?>" min="15" max="180">
                                    <small class="text-muted">Maximum duration for AI interviews</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Settings -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-credit-card me-2"></i>Payment Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Razorpay Key ID</label>
                                    <input type="text" class="form-control" name="setting_razorpay_key_id" 
                                           value="<?php echo isset($grouped_settings['razorpay']) ? $grouped_settings['razorpay'][0]['setting_value'] : ''; ?>"
                                           placeholder="rzp_test_xxxxxxxxxx">
                                    <small class="text-muted">Your Razorpay API Key ID</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Razorpay Key Secret</label>
                                    <input type="password" class="form-control" name="setting_razorpay_key_secret" 
                                           value="<?php echo isset($grouped_settings['razorpay']) && count($grouped_settings['razorpay']) > 1 ? $grouped_settings['razorpay'][1]['setting_value'] : ''; ?>"
                                           placeholder="Enter secret key">
                                    <small class="text-muted">Your Razorpay API Secret Key (keep secure)</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Monthly Plan Price (INR)</label>
                                    <input type="number" class="form-control" name="setting_monthly_price_inr" 
                                           value="100" min="1">
                                    <small class="text-muted">Monthly subscription price in Indian Rupees</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Annual Plan Price (INR)</label>
                                    <input type="number" class="form-control" name="setting_annual_price_inr" 
                                           value="1200" min="1">
                                    <small class="text-muted">Annual subscription price in Indian Rupees</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Monthly Plan Price (USD)</label>
                                    <input type="number" class="form-control" name="setting_monthly_price_usd" 
                                           value="2" min="1">
                                    <small class="text-muted">Monthly subscription price in US Dollars</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Annual Plan Price (USD)</label>
                                    <input type="number" class="form-control" name="setting_annual_price_usd" 
                                           value="15" min="1">
                                    <small class="text-muted">Annual subscription price in US Dollars</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-envelope me-2"></i>Email Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" name="setting_smtp_host" 
                                           value="<?php echo isset($grouped_settings['smtp']) ? $grouped_settings['smtp'][0]['setting_value'] : ''; ?>"
                                           placeholder="smtp.gmail.com">
                                    <small class="text-muted">SMTP server hostname</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" name="setting_smtp_port" 
                                           value="<?php echo isset($grouped_settings['smtp']) && count($grouped_settings['smtp']) > 1 ? $grouped_settings['smtp'][1]['setting_value'] : '587'; ?>">
                                    <small class="text-muted">SMTP server port (usually 587 for TLS)</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="email" class="form-control" name="setting_smtp_username" 
                                           value="" placeholder="your-email@gmail.com">
                                    <small class="text-muted">SMTP authentication username</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" name="setting_smtp_password" 
                                           value="" placeholder="Enter SMTP password">
                                    <small class="text-muted">SMTP authentication password</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">From Email</label>
                                    <input type="email" class="form-control" name="setting_from_email" 
                                           value="noreply@skillverge.com" placeholder="noreply@skillverge.com">
                                    <small class="text-muted">Default sender email address</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Enable Email Notifications</label>
                                    <select class="form-control" name="setting_enable_notifications">
                                        <option value="true" <?php echo (isset($grouped_settings['enable']) && $grouped_settings['enable'][0]['setting_value'] === 'true') ? 'selected' : ''; ?>>Enabled</option>
                                        <option value="false" <?php echo (isset($grouped_settings['enable']) && $grouped_settings['enable'][0]['setting_value'] === 'false') ? 'selected' : ''; ?>>Disabled</option>
                                    </select>
                                    <small class="text-muted">Enable/disable email notifications system-wide</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI & Integration Settings -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-robot me-2"></i>AI & Integration Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">AI Analysis Engine</label>
                                    <select class="form-control" name="setting_ai_engine">
                                        <option value="local">Local Python Engine</option>
                                        <option value="openai">OpenAI API</option>
                                        <option value="azure">Azure Cognitive Services</option>
                                        <option value="google">Google Cloud AI</option>
                                    </select>
                                    <small class="text-muted">Choose AI engine for interview analysis</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">OpenAI API Key</label>
                                    <input type="password" class="form-control" name="setting_openai_api_key" 
                                           value="" placeholder="sk-xxxxxxxxxx">
                                    <small class="text-muted">OpenAI API key for advanced AI features</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Video Platform</label>
                                    <select class="form-control" name="setting_video_platform">
                                        <option value="jitsi">Jitsi Meet (Free)</option>
                                        <option value="zoom">Zoom</option>
                                        <option value="teams">Microsoft Teams</option>
                                        <option value="google-meet">Google Meet</option>
                                    </select>
                                    <small class="text-muted">Default video platform for expert interviews</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Zoom API Key</label>
                                    <input type="text" class="form-control" name="setting_zoom_api_key" 
                                           value="" placeholder="Enter Zoom API key">
                                    <small class="text-muted">Required for Zoom integration</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">File Upload Limit (MB)</label>
                                    <input type="number" class="form-control" name="setting_upload_limit" 
                                           value="10" min="1" max="100">
                                    <small class="text-muted">Maximum file size for uploads (resumes, recordings)</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Recording Storage</label>
                                    <select class="form-control" name="setting_recording_storage">
                                        <option value="local">Local Server</option>
                                        <option value="aws-s3">Amazon S3</option>
                                        <option value="google-cloud">Google Cloud Storage</option>
                                        <option value="azure-blob">Azure Blob Storage</option>
                                    </select>
                                    <small class="text-muted">Where to store interview recordings</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-shield-alt me-2"></i>Security Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Session Timeout (minutes)</label>
                                    <input type="number" class="form-control" name="setting_session_timeout" 
                                           value="60" min="15" max="480">
                                    <small class="text-muted">User session timeout duration</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Password Minimum Length</label>
                                    <input type="number" class="form-control" name="setting_password_min_length" 
                                           value="6" min="4" max="20">
                                    <small class="text-muted">Minimum password length requirement</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Enable Two-Factor Authentication</label>
                                    <select class="form-control" name="setting_enable_2fa">
                                        <option value="false">Disabled</option>
                                        <option value="true">Enabled</option>
                                    </select>
                                    <small class="text-muted">Require 2FA for admin accounts</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Login Attempt Limit</label>
                                    <input type="number" class="form-control" name="setting_login_attempt_limit" 
                                           value="5" min="3" max="10">
                                    <small class="text-muted">Maximum failed login attempts before lockout</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Account Lockout Duration (minutes)</label>
                                    <input type="number" class="form-control" name="setting_lockout_duration" 
                                           value="30" min="5" max="1440">
                                    <small class="text-muted">How long to lock account after failed attempts</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Enable Audit Logging</label>
                                    <select class="form-control" name="setting_enable_audit_log">
                                        <option value="true">Enabled</option>
                                        <option value="false">Disabled</option>
                                    </select>
                                    <small class="text-muted">Log all user actions for security auditing</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Settings -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-tachometer-alt me-2"></i>Performance Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Enable Caching</label>
                                    <select class="form-control" name="setting_enable_caching">
                                        <option value="true">Enabled</option>
                                        <option value="false">Disabled</option>
                                    </select>
                                    <small class="text-muted">Enable Redis/Memcached for better performance</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Cache Duration (hours)</label>
                                    <input type="number" class="form-control" name="setting_cache_duration" 
                                           value="24" min="1" max="168">
                                    <small class="text-muted">How long to cache data</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Database Connection Pool Size</label>
                                    <input type="number" class="form-control" name="setting_db_pool_size" 
                                           value="10" min="5" max="50">
                                    <small class="text-muted">Maximum database connections</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Enable Compression</label>
                                    <select class="form-control" name="setting_enable_compression">
                                        <option value="true">Enabled</option>
                                        <option value="false">Disabled</option>
                                    </select>
                                    <small class="text-muted">Enable GZIP compression for faster loading</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">CDN URL</label>
                                    <input type="url" class="form-control" name="setting_cdn_url" 
                                           value="" placeholder="https://cdn.skillverge.com">
                                    <small class="text-muted">CDN URL for static assets (optional)</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Enable Debug Mode</label>
                                    <select class="form-control" name="setting_debug_mode">
                                        <option value="false">Disabled</option>
                                        <option value="true">Enabled</option>
                                    </select>
                                    <small class="text-muted text-warning">⚠️ Only enable for development</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    <i class="fas fa-save me-2"></i>Save All Settings
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-lg px-5 ms-3" onclick="testSettings()">
                                    <i class="fas fa-vial me-2"></i>Test Configuration
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportSettings() {
            window.location.href = 'export-settings.php';
        }

        function resetToDefaults() {
            if (confirm('Are you sure you want to reset all settings to default values? This action cannot be undone.')) {
                fetch('reset-settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Settings reset to defaults successfully!');
                        location.reload();
                    } else {
                        alert('Error resetting settings: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to reset settings');
                });
            }
        }

        function testSettings() {
            const formData = new FormData(document.querySelector('form'));
            
            fetch('test-settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                let message = 'Configuration Test Results:\n\n';
                
                if (data.email_test) {
                    message += '✅ Email Configuration: Working\n';
                } else {
                    message += '❌ Email Configuration: Failed\n';
                }
                
                if (data.payment_test) {
                    message += '✅ Payment Gateway: Connected\n';
                } else {
                    message += '❌ Payment Gateway: Connection Failed\n';
                }
                
                if (data.ai_test) {
                    message += '✅ AI Engine: Operational\n';
                } else {
                    message += '❌ AI Engine: Not Responding\n';
                }
                
                if (data.database_test) {
                    message += '✅ Database: Connected\n';
                } else {
                    message += '❌ Database: Connection Issues\n';
                }
                
                alert(message);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to test configuration');
            });
        }

        // Auto-save draft every 30 seconds
        setInterval(function() {
            const formData = new FormData(document.querySelector('form'));
            formData.append('auto_save', '1');
            
            fetch('auto-save-settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Settings auto-saved');
                }
            })
            .catch(error => console.error('Auto-save error:', error));
        }, 30000);

        // Show/hide dependent fields
        document.addEventListener('change', function(e) {
            if (e.target.name === 'setting_ai_engine') {
                const openaiFields = document.querySelector('input[name="setting_openai_api_key"]').closest('.mb-3');
                if (e.target.value === 'openai') {
                    openaiFields.style.display = 'block';
                } else {
                    openaiFields.style.display = 'none';
                }
            }
            
            if (e.target.name === 'setting_video_platform') {
                const zoomFields = document.querySelector('input[name="setting_zoom_api_key"]').closest('.mb-3');
                if (e.target.value === 'zoom') {
                    zoomFields.style.display = 'block';
                } else {
                    zoomFields.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>
