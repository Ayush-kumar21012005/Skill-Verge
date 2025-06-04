<?php
session_start();

class Auth {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function register($email, $password, $full_name, $user_type, $additional_data = []) {
        try {
            // Check if user already exists
            $check_query = "SELECT id FROM users WHERE email = :email";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(":email", $email);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $query = "INSERT INTO users (email, password, full_name, user_type) VALUES (:email, :password, :full_name, :user_type)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":password", $hashed_password);
            $stmt->bindParam(":full_name", $full_name);
            $stmt->bindParam(":user_type", $user_type);
            
            if ($stmt->execute()) {
                $user_id = $this->conn->lastInsertId();
                
                // Insert additional data based on user type
                if ($user_type === 'candidate') {
                    $candidate_query = "INSERT INTO candidates (user_id, skills, preferred_domain) VALUES (:user_id, :skills, :domain)";
                    $candidate_stmt = $this->conn->prepare($candidate_query);
                    $candidate_stmt->bindParam(":user_id", $user_id);
                    $candidate_stmt->bindParam(":skills", $additional_data['skills'] ?? '');
                    $candidate_stmt->bindParam(":domain", $additional_data['domain'] ?? '');
                    $candidate_stmt->execute();
                } elseif ($user_type === 'company') {
                    $company_query = "INSERT INTO companies (user_id, company_name, industry) VALUES (:user_id, :company_name, :industry)";
                    $company_stmt = $this->conn->prepare($company_query);
                    $company_stmt->bindParam(":user_id", $user_id);
                    $company_stmt->bindParam(":company_name", $additional_data['company_name'] ?? '');
                    $company_stmt->bindParam(":industry", $additional_data['industry'] ?? '');
                    $company_stmt->execute();
                }
                
                return ['success' => true, 'message' => 'Registration successful', 'user_id' => $user_id];
            }
            
            return ['success' => false, 'message' => 'Registration failed'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    public function login($email, $password) {
        try {
            $query = "SELECT u.*, c.id as candidate_id, comp.id as company_id, e.id as expert_id
                     FROM users u 
                     LEFT JOIN candidates c ON u.id = c.user_id 
                     LEFT JOIN companies comp ON u.id = comp.user_id 
                     LEFT JOIN experts e ON u.id = e.user_id
                     WHERE u.email = :email AND u.is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    
                    if ($user['user_type'] === 'candidate') {
                        $_SESSION['candidate_id'] = $user['candidate_id'];
                    } elseif ($user['user_type'] === 'company') {
                        $_SESSION['company_id'] = $user['company_id'];
                    } elseif ($user['user_type'] === 'expert') {
                        $_SESSION['expert_id'] = $user['expert_id'];
                    }
                    
                    return ['success' => true, 'user_type' => $user['user_type']];
                }
            }
            
            return ['success' => false, 'message' => 'Invalid credentials'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    public function logout() {
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function requireAuth($allowed_types = []) {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php');
            exit();
        }
        
        if (!empty($allowed_types) && !in_array($_SESSION['user_type'], $allowed_types)) {
            header('Location: /unauthorized.php');
            exit();
        }
    }
}
?>
