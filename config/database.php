<?php
class Database {
    private $host = "your-production-host"; // e.g., "localhost" or your hosting provider's DB host
    private $db_name = "your_production_db_name"; // Your live database name
    private $username = "your_production_username"; // Your live DB username
    private $password = "your_production_password"; // Your live DB password
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // Production connection with SSL and optimizations
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                $this->username, 
                $this->password,
                $options
            );
        } catch(PDOException $exception) {
            error_log("Production DB Connection error: " . $exception->getMessage());
            die("Database connection failed. Please contact support.");
        }
        return $this->conn;
    }

    // Method to execute SQL file for initial setup
    public function executeSQLFile($filepath) {
        try {
            if (!file_exists($filepath)) {
                throw new Exception("Database SQL file not found: " . $filepath);
            }

            $sql = file_get_contents($filepath);
            
            // Split SQL file into individual statements
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($statement) {
                    return !empty($statement) && !preg_match('/^\s*--/', $statement);
                }
            );

            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    $this->conn->exec($statement);
                }
            }
            
            return true;
        } catch(Exception $e) {
            error_log("Database setup error: " . $e->getMessage());
            return false;
        }
    }

    // Method to check if database is properly set up
    public function isDatabaseSetup() {
        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'users'");
            return $stmt->rowCount() > 0;
        } catch(Exception $e) {
            return false;
        }
    }

    // Method to get database version and info
    public function getDatabaseInfo() {
        try {
            $stmt = $this->conn->query("SELECT VERSION() as version");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $tables_stmt = $this->conn->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = '{$this->db_name}'");
            $tables_result = $tables_stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'mysql_version' => $result['version'],
                'database_name' => $this->db_name,
                'table_count' => $tables_result['table_count'],
                'connection_status' => 'Connected'
            ];
        } catch(Exception $e) {
            return [
                'error' => $e->getMessage(),
                'connection_status' => 'Failed'
            ];
        }
    }

    // Quick setup method for development
    public function quickSetup() {
        try {
            $sqlFile = dirname(__DIR__) . '/scripts/skillverge.sql';
            
            if (!file_exists($sqlFile)) {
                return [
                    'success' => false,
                    'message' => 'SQL file not found: ' . $sqlFile
                ];
            }

            $result = $this->executeSQLFile($sqlFile);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Database setup completed successfully!',
                    'info' => $this->getDatabaseInfo()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to execute SQL file'
                ];
            }
        } catch(Exception $e) {
            return [
                'success' => false,
                'message' => 'Setup failed: ' . $e->getMessage()
            ];
        }
    }
}
?>
