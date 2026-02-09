<?php
// config/db.php - Enhanced version
class Database {
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $dbname = "woldiya_overload_payment";
    public $conn;
    
    public function __construct() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
        
        if ($this->conn->connect_error) {
            error_log("Database Connection Failed: " . $this->conn->connect_error);
            die("Database connection error. Please contact administrator.");
        }
        
        // Set charset to UTF-8
        $this->conn->set_charset("utf8");
    }
    
    public function getUserProfile($user_id) {
        $sql = "SELECT u.*, t.academic_rank, t.qualification, t.date_of_birth, 
                       t.hire_date, t.bio, t.office_location, t.office_hours,
                       t.teacher_id
                FROM users u 
                LEFT JOIN teachers t ON u.user_id = t.user_id
                WHERE u.user_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    public function updateUserProfile($user_id, $data) {
        $sql = "UPDATE users SET 
                full_name = ?, 
                email = ?, 
                phone = ?, 
                department = ? 
                WHERE user_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssssi", 
            $data['full_name'], 
            $data['email'], 
            $data['phone'], 
            $data['department'], 
            $user_id
        );
        
        return $stmt->execute();
    }
    
    public function logActivity($user_id, $action, $details = null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("issss", $user_id, $action, $details, $ip_address, $user_agent);
        $stmt->execute();
    }
}

// Create global database instance
$db = new Database();
$conn = $db->conn;
?>