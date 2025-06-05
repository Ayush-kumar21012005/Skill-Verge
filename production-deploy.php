<?php
// Production Deployment Script
// Run this script after uploading files to production server

require_once 'config/production.php';

class ProductionDeployment {
    private $db;
    private $deployment_log = [];
    
    public function __construct() {
        echo "<h1>SkillVerge Production Deployment</h1>\n";
        echo "<pre>\n";
        $this->log("Starting production deployment...");
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message";
        $this->deployment_log[] = $log_entry;
        echo "$log_entry\n";
        ProductionLogger::info($message);
    }
    
    public function deploy() {
        try {
            $this->checkEnvironment();
            $this->setupDatabase();
            $this->createDirectories();
            $this->setPermissions();
            $this->configureSettings();
            $this->runTests();
            $this->finalizeDeployment();
            
            $this->log("✅ Production deployment completed successfully!");
            
        } catch (Exception $e) {
            $this->log("❌ Deployment failed: " . $e->getMessage());
            ProductionLogger::error("Deployment failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    private function checkEnvironment() {
        $this->log("🔍 Checking production environment...");
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new Exception("PHP 7.4 or higher required. Current: " . PHP_VERSION);
        }
        $this->log("✅ PHP version: " . PHP_VERSION);
        
        // Check required extensions
        $required_extensions = ['pdo', 'pdo_mysql', 'curl', 'gd', 'mbstring', 'openssl'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new Exception("Required PHP extension missing: $ext");
            }
        }
        $this->log("✅ All required PHP extensions loaded");
        
        // Check write permissions
        $write_dirs = ['/var/log', '/tmp'];
        foreach ($write_dirs as $dir) {
            if (!is_writable($dir)) {
                throw new Exception("Directory not writable: $dir");
            }
        }
        $this->log("✅ Directory permissions verified");
    }
    
    private function setupDatabase() {
        $this->log("🗄️ Setting up production database...");
        
        try {
            $this->db = ProductionDatabase::getInstance();
            $connection = $this->db->getConnection();
            
            // Check if database exists and has tables
            $stmt = $connection->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                $this->log("📥 Importing database schema...");
                $sql_file = __DIR__ . '/scripts/skillverge.sql';
                
                if (!file_exists($sql_file)) {
                    throw new Exception("Database SQL file not found: $sql_file");
                }
                
                $result = $this->db->executeSQLFile($sql_file);
                if (!$result) {
                    throw new Exception("Failed to import database schema");
                }
                
                $this->log("✅ Database schema imported successfully");
            } else {
                $this->log("✅ Database already exists with " . count($tables) . " tables");
            }
            
            // Update system settings for production
            $this->updateSystemSettings();
            
        } catch (Exception $e) {
            throw new Exception("Database setup failed: " . $e->getMessage());
        }
    }
    
    private function updateSystemSettings() {
        $this->log("⚙️ Updating system settings for production...");
        
        $production_settings = [
            'site_url' => SITE_URL,
            'environment' => 'production',
            'debug_mode' => 'false',
            'enable_notifications' => 'true',
            'max_upload_size' => ProductionConfig::MAX_UPLOAD_SIZE,
            'session_timeout' => ProductionConfig::SESSION_TIMEOUT,
            'razorpay_mode' => 'live',
            'smtp_host' => ProductionConfig::SMTP_HOST,
            'smtp_port' => ProductionConfig::SMTP_PORT,
            'from_email' => ProductionConfig::FROM_EMAIL
        ];
        
        $connection = $this->db->getConnection();
        
        foreach ($production_settings as $key => $value) {
            $stmt = $connection->prepare(
                "INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                 VALUES (:key, :value, NOW()) 
                 ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = NOW()"
            );
            $stmt->execute(['key' => $key, 'value' => $value]);
        }
        
        $this->log("✅ System settings updated for production");
    }
    
    private function createDirectories() {
        $this->log("📁 Creating required directories...");
        
        $directories = [
            'uploads/documents',
            'uploads/images',
            'uploads/audio',
            'recordings',
            'logs',
            'cache',
            'backups'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception("Failed to create directory: $dir");
                }
                $this->log("✅ Created directory: $dir");
            }
        }
    }
    
    private function setPermissions() {
        $this->log("🔒 Setting file permissions...");
        
        // Set directory permissions
        $writable_dirs = ['uploads', 'recordings', 'logs', 'cache', 'backups'];
        foreach ($writable_dirs as $dir) {
            if (is_dir($dir)) {
                chmod($dir, 0755);
                $this->log("✅ Set permissions for: $dir");
            }
        }
        
        // Set file permissions
        $config_files = ['config/database.php', 'config/production.php'];
        foreach ($config_files as $file) {
            if (file_exists($file)) {
                chmod($file, 0644);
            }
        }
        
        $this->log("✅ File permissions configured");
    }
    
    private function configureSettings() {
        $this->log("⚙️ Configuring production settings...");
        
        // Create .htaccess for security
        $htaccess_content = "
# SkillVerge Production Security Settings
Options -Indexes
ServerSignature Off

# Prevent access to sensitive files
<Files ~ \"^\.ht\">
    Require all denied
</Files>

<Files ~ \"\.sql$\">
    Require all denied
</Files>

<Files ~ \"\.log$\">
    Require all denied
</Files>

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection \"1; mode=block\"
Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Cache static files
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType text/css \"access plus 1 year\"
    ExpiresByType application/javascript \"access plus 1 year\"
    ExpiresByType image/png \"access plus 1 year\"
    ExpiresByType image/jpg \"access plus 1 year\"
    ExpiresByType image/jpeg \"access plus 1 year\"
</IfModule>
";
        
        file_put_contents('.htaccess', $htaccess_content);
        $this->log("✅ .htaccess security configuration created");
        
        // Create robots.txt
        $robots_content = "User-agent: *
Disallow: /admin/
Disallow: /api/
Disallow: /config/
Disallow: /scripts/
Disallow: /uploads/
Disallow: /logs/
Disallow: /backups/

Sitemap: " . SITE_URL . "/sitemap.xml";
        
        file_put_contents('robots.txt', $robots_content);
        $this->log("✅ robots.txt created");
    }
    
    private function runTests() {
        $this->log("🧪 Running production tests...");
        
        // Test database connection
        try {
            $connection = $this->db->getConnection();
            $stmt = $connection->query("SELECT COUNT(*) FROM users");
            $this->log("✅ Database connection test passed");
        } catch (Exception $e) {
            throw new Exception("Database connection test failed: " . $e->getMessage());
        }
        
        // Test file permissions
        $test_file = 'uploads/test_write.txt';
        if (file_put_contents($test_file, 'test') === false) {
            throw new Exception("File write test failed");
        }
        unlink($test_file);
        $this->log("✅ File write test passed");
        
        // Test cache functionality
        ProductionCache::set('test_key', 'test_value');
        if (ProductionCache::get('test_key') !== 'test_value') {
            throw new Exception("Cache test failed");
        }
        ProductionCache::delete('test_key');
        $this->log("✅ Cache functionality test passed");
    }
    
    private function finalizeDeployment() {
        $this->log("🎯 Finalizing deployment...");
        
        // Clear any existing cache
        ProductionCache::clear();
        $this->log("✅ Cache cleared");
        
        // Log deployment completion
        $deployment_info = [
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => 'production',
            'php_version' => PHP_VERSION,
            'deployment_log' => $this->deployment_log
        ];
        
        file_put_contents('logs/deployment.log', json_encode($deployment_info, JSON_PRETTY_PRINT));
        $this->log("✅ Deployment log saved");
        
        // Create deployment success marker
        file_put_contents('.deployment_success', date('Y-m-d H:i:s'));
        
        ProductionLogger::info('Production deployment completed successfully');
    }
    
    public function getDeploymentSummary() {
        echo "</pre>\n";
        echo "<h2>Deployment Summary</h2>\n";
        echo "<ul>\n";
        echo "<li>✅ Environment verified</li>\n";
        echo "<li>✅ Database configured</li>\n";
        echo "<li>✅ Directories created</li>\n";
        echo "<li>✅ Permissions set</li>\n";
        echo "<li>✅ Security configured</li>\n";
        echo "<li>✅ Tests passed</li>\n";
        echo "</ul>\n";
        
        echo "<h3>Next Steps:</h3>\n";
        echo "<ol>\n";
        echo "<li>Update DNS settings to point to this server</li>\n";
        echo "<li>Configure SSL certificate</li>\n";
        echo "<li>Update Razorpay settings in admin panel</li>\n";
        echo "<li>Configure SMTP settings in admin panel</li>\n";
        echo "<li>Test all functionality</li>\n";
        echo "<li>Set up monitoring and backups</li>\n";
        echo "</ol>\n";
        
        echo "<p><strong>Admin Login:</strong> admin@skillverge.com / password (change immediately!)</p>\n";
        echo "<p><strong>Site URL:</strong> <a href='" . SITE_URL . "'>" . SITE_URL . "</a></p>\n";
    }
}

// Run deployment if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'production-deploy.php') {
    try {
        $deployment = new ProductionDeployment();
        $deployment->deploy();
        $deployment->getDeploymentSummary();
    } catch (Exception $e) {
        echo "<h2 style='color: red;'>Deployment Failed</h2>\n";
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        echo "<p>Please check the logs and try again.</p>\n";
    }
}
?>
