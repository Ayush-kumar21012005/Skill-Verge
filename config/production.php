<?php
// Production Configuration File
// This file contains production-specific settings

// Error Reporting - Disable in production
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/skillverge_errors.log');

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Production Environment Variables
define('ENVIRONMENT', 'production');
define('DEBUG_MODE', false);
define('SITE_URL', 'https://your-domain.com'); // Update with your actual domain
define('SECURE_COOKIES', true);

// Session Configuration for Production
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Production Database Settings
class ProductionConfig {
    // Payment Gateway Settings
    const RAZORPAY_LIVE_MODE = true;
    const RAZORPAY_KEY_ID = 'rzp_live_your_key_id'; // Replace with live key
    const RAZORPAY_KEY_SECRET = 'your_live_secret_key'; // Replace with live secret
    
    // UPI Payment Settings
    const UPI_MERCHANT_ID = 'your_merchant_id';
    const UPI_MERCHANT_NAME = 'SkillVerge';
    
    // Email Settings
    const SMTP_HOST = 'smtp.gmail.com'; // or your SMTP provider
    const SMTP_PORT = 587;
    const SMTP_SECURE = 'tls';
    const SMTP_USERNAME = 'your-email@domain.com';
    const SMTP_PASSWORD = 'your-app-password';
    const FROM_EMAIL = 'noreply@skillverge.com';
    const FROM_NAME = 'SkillVerge Platform';
    
    // File Upload Settings
    const MAX_UPLOAD_SIZE = 10485760; // 10MB
    const UPLOAD_PATH = '/var/www/html/skillverge/uploads/';
    const ALLOWED_FILE_TYPES = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'mp3', 'wav'];
    
    // Security Settings
    const PASSWORD_MIN_LENGTH = 8;
    const SESSION_TIMEOUT = 3600; // 1 hour
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_DURATION = 900; // 15 minutes
    
    // API Settings
    const API_RATE_LIMIT = 100; // requests per hour
    const API_VERSION = 'v1';
    
    // Backup Settings
    const AUTO_BACKUP = true;
    const BACKUP_RETENTION_DAYS = 30;
    const BACKUP_PATH = '/var/backups/skillverge/';
}

// Production Error Handler
function productionErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_message = date('Y-m-d H:i:s') . " - Error: [$errno] $errstr in $errfile on line $errline\n";
    error_log($error_message, 3, '/var/log/skillverge_errors.log');
    
    // Don't show errors to users in production
    if ($errno === E_ERROR || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR) {
        http_response_code(500);
        include 'error_pages/500.html';
        exit();
    }
    
    return true;
}

set_error_handler('productionErrorHandler');

// Production Exception Handler
function productionExceptionHandler($exception) {
    $error_message = date('Y-m-d H:i:s') . " - Uncaught exception: " . $exception->getMessage() . 
                    " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
    error_log($error_message, 3, '/var/log/skillverge_errors.log');
    
    http_response_code(500);
    include 'error_pages/500.html';
    exit();
}

set_exception_handler('productionExceptionHandler');

// Production Database Connection with Connection Pooling
class ProductionDatabase extends Database {
    private static $instance = null;
    private static $connection_pool = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        $connection_id = 'main_' . getmypid();
        
        if (!isset(self::$connection_pool[$connection_id]) || 
            self::$connection_pool[$connection_id] === null) {
            
            try {
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true, // Connection pooling
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];
                
                self::$connection_pool[$connection_id] = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                    $this->username, 
                    $this->password,
                    $options
                );
                
            } catch(PDOException $exception) {
                error_log("Production DB Connection error: " . $exception->getMessage());
                http_response_code(503);
                include 'error_pages/503.html';
                exit();
            }
        }
        
        return self::$connection_pool[$connection_id];
    }
}

// Production Logging Class
class ProductionLogger {
    private static $log_file = '/var/log/skillverge_app.log';
    
    public static function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $context_str = !empty($context) ? json_encode($context) : '';
        $log_entry = "[$timestamp] [$level] $message $context_str\n";
        
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    public static function critical($message, $context = []) {
        self::log('CRITICAL', $message, $context);
    }
}

// Production Cache Class
class ProductionCache {
    private static $cache_dir = '/tmp/skillverge_cache/';
    
    public static function get($key) {
        $file = self::$cache_dir . md5($key) . '.cache';
        
        if (file_exists($file) && (time() - filemtime($file)) < 3600) { // 1 hour cache
            return unserialize(file_get_contents($file));
        }
        
        return null;
    }
    
    public static function set($key, $value, $ttl = 3600) {
        if (!is_dir(self::$cache_dir)) {
            mkdir(self::$cache_dir, 0755, true);
        }
        
        $file = self::$cache_dir . md5($key) . '.cache';
        file_put_contents($file, serialize($value), LOCK_EX);
    }
    
    public static function delete($key) {
        $file = self::$cache_dir . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    public static function clear() {
        $files = glob(self::$cache_dir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

// Production Security Functions
class ProductionSecurity {
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function rateLimitCheck($identifier, $limit = 100, $window = 3600) {
        $cache_key = "rate_limit_$identifier";
        $current_count = ProductionCache::get($cache_key) ?: 0;
        
        if ($current_count >= $limit) {
            return false;
        }
        
        ProductionCache::set($cache_key, $current_count + 1, $window);
        return true;
    }
}

// Initialize production environment
if (!is_dir('/var/log')) {
    mkdir('/var/log', 0755, true);
}

if (!is_dir('/tmp/skillverge_cache')) {
    mkdir('/tmp/skillverge_cache', 0755, true);
}

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => ProductionConfig::SESSION_TIMEOUT,
        'cookie_secure' => ProductionConfig::SECURE_COOKIES,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}

ProductionLogger::info('Production environment initialized');
?>
