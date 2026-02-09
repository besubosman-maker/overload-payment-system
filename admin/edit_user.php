<?php
// admin/edit_user.php
include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'admin') {
    header("Location: manage_users.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Get user ID from URL - with better error handling
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = intval($_GET['id']);
} else {
    header("Location: manage_users.php?error=Invalid user ID");
    exit();
}

// Fetch user data
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: manage_users.php?error=User not found");
    exit();
}

// Check which tab to show
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Function to check and create teacher record if needed
function checkTeacherRecord($user_id) {
    global $conn;
    
    $check_sql = "SELECT teacher_id FROM teachers WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    if ($check_stmt) {
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            // Create teacher record
            $insert_sql = "INSERT INTO teachers (user_id, academic_rank) VALUES (?, 'Assistant Lecturer')";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("i", $user_id);
            $insert_stmt->execute();
        }
    }
}

// Update user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    // Validate required fields
    if (empty($username) || empty($role) || empty($status)) {
        $error_msg = "Please fill all required fields.";
    } else {
        // Check if username already exists (excluding current user)
        if ($username != $user['username']) {
            $check_sql = "SELECT * FROM users WHERE username = ? AND user_id != ?";
            $check_stmt = $conn->prepare($check_sql);
            if ($check_stmt) {
                $check_stmt->bind_param("si", $username, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error_msg = "Username already exists. Please choose a different username.";
                } else {
                    updateUser();
                }
            } else {
                $error_msg = "Database error: " . $conn->error;
            }
        } else {
            updateUser();
        }
    }
    
    function updateUser() {
        global $conn, $username, $full_name, $email, $phone, $department, $role, $status, $user_id, $success_msg, $error_msg, $user;
        
        $sql = "UPDATE users SET 
                username = ?, 
                full_name = ?, 
                email = ?, 
                phone = ?, 
                department = ?, 
                role = ?, 
                status = ? 
                WHERE user_id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssssssi", $username, $full_name, $email, $phone, $department, $role, $status, $user_id);
            
            if ($stmt->execute()) {
                $success_msg = "User updated successfully.";
                
                // Update the current user data
                $user['username'] = $username;
                $user['full_name'] = $full_name;
                $user['email'] = $email;
                $user['phone'] = $phone;
                $user['department'] = $department;
                $user['role'] = $role;
                $user['status'] = $status;
                
                // If role changed to teacher, ensure teacher record exists
                if ($role == 'teacher') {
                    checkTeacherRecord($user_id);
                }
            } else {
                $error_msg = "Error updating user: " . $conn->error;
            }
        } else {
            $error_msg = "Database error: " . $conn->error;
        }
    }
}

// Reset password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error_msg = "Passwords do not match.";
        $active_tab = 'password';
    } elseif (strlen($new_password) < 6) {
        $error_msg = "Password must be at least 6 characters long.";
        $active_tab = 'password';
    } else {
        $hashed_password = md5($new_password);
        $sql = "UPDATE users SET password = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success_msg = "Password reset successfully.";
                $active_tab = 'password';
            } else {
                $error_msg = "Error resetting password: " . $conn->error;
                $active_tab = 'password';
            }
        } else {
            $error_msg = "Database error: " . $conn->error;
            $active_tab = 'password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | Admin Panel | Woldiya University</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #004080;
            --secondary-color: #0066cc;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-radius: 12px;
            --box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary-color) 0%, #002855 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
        }

        .sidebar-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: white;
            text-align: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info i {
            font-size: 2.5rem;
            background: rgba(255, 255, 255, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-details h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: white;
        }

        .user-details p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--warning-color);
        }

        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: var(--warning-color);
            font-weight: 600;
        }

        .sidebar-menu a i {
            width: 20px;
            text-align: center;
        }

        .logout-link {
            color: #ff6b6b !important;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 20px;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .page-header h2 {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h2 i {
            color: var(--secondary-color);
        }

        .notification {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.5s ease;
        }

        .notification-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid var(--success-color);
        }

        .notification-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid var(--danger-color);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h3 {
            color: var(--primary-color);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 30px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            color: var(--primary-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
            color: var(--dark-color);
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 64, 128, 0.1);
        }

        .form-group input:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 64, 128, 0.2);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
            color: #212529;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info-color), #138496);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.85rem;
        }

        .back-link {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .back-link:hover {
            color: var(--secondary-color);
        }

        .user-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid var(--primary-color);
        }

        .user-summary h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .detail-item {
            font-size: 0.95rem;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value {
            color: var(--dark-color);
            font-weight: 500;
        }

        .tab-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .tab-nav {
            display: flex;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #e2e8f0;
        }

        .tab-btn {
            padding: 15px 30px;
            background: none;
            border: none;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 3px solid transparent;
        }

        .tab-btn:hover {
            color: var(--primary-color);
            background: rgba(0, 64, 128, 0.05);
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: white;
        }

        .tab-content {
            padding: 30px;
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .status-inactive {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h3,
            .user-details,
            .sidebar-menu a span {
                display: none;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar-menu a i {
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .tab-nav {
                flex-direction: column;
            }
            
            .tab-btn {
                text-align: left;
                border-bottom: none;
                border-left: 3px solid transparent;
            }
            
            .tab-btn.active {
                border-left-color: var(--primary-color);
                border-bottom: none;
            }
        }

        .password-strength {
            margin-top: 5px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            display: none;
        }

        .password-strength.weak {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .password-strength.fair {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .password-strength.good {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .password-strength.strong {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
                <div class="user-info">
                    <i class="fas fa-user-shield"></i>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></h4>
                        <p>Administrator</p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_users.php" class="active"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="payment_rate.php"><i class="fas fa-money-check-alt"></i> Payment Rates</a></li>
                <li><a href="system_logs.php"><i class="fas fa-clipboard-list"></i> System Logs</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <a href="manage_users.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to User Management
            </a>
            
            <div class="page-header">
                <h2><i class="fas fa-user-edit"></i> Edit User: <?php echo htmlspecialchars($user['username']); ?></h2>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="notification notification-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="notification notification-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>
            
            <!-- User Summary -->
            <div class="user-summary">
                <h4><i class="fas fa-info-circle"></i> User Information</h4>
                <div class="user-details-grid">
                    <div class="detail-item">
                        <div class="detail-label"><i class="fas fa-user"></i> Username</div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['username']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><i class="fas fa-id-card"></i> Full Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['full_name'] ?: 'Not set'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><i class="fas fa-envelope"></i> Email</div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['email'] ?: 'Not set'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><i class="fas fa-phone"></i> Phone</div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['phone'] ?: 'Not set'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><i class="fas fa-user-tag"></i> Role</div>
                        <div class="detail-value"><?php echo ucwords(str_replace('_', ' ', $user['role'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><i class="fas fa-building"></i> Department</div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['department'] ?: 'Not assigned'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><i class="fas fa-toggle-on"></i> Status</div>
                        <div class="detail-value">
                            <span class="status-badge status-<?php echo $user['status']; ?>">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><i class="fas fa-calendar-alt"></i> Created</div>
                        <div class="detail-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><i class="fas fa-sign-in-alt"></i> Last Login</div>
                        <div class="detail-value">
                            <?php 
                            if ($user['last_login'] && $user['last_login'] != '0000-00-00 00:00:00') {
                                echo date('F d, Y H:i', strtotime($user['last_login']));
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Container -->
            <div class="tab-container">
                <div class="tab-nav">
                    <button class="tab-btn <?php echo $active_tab == 'profile' ? 'active' : ''; ?>" onclick="showTab('profile')">
                        <i class="fas fa-user-cog"></i> Edit Profile
                    </button>
                    <button class="tab-btn <?php echo $active_tab == 'password' ? 'active' : ''; ?>" onclick="showTab('password')">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </div>
                
                <!-- Edit Profile Tab -->
                <div id="profile" class="tab-content <?php echo $active_tab == 'profile' ? 'active' : ''; ?>">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Username *</label>
                                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-id-card"></i> Full Name</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" placeholder="Enter full name">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="Enter email address">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="Enter phone number">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-building"></i> Department</label>
                                <select name="department">
                                    <option value="">Select Department</option>
                                    <option value="Computer Science" <?php echo $user['department'] == 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                                    <option value="Mathematics" <?php echo $user['department'] == 'Mathematics' ? 'selected' : ''; ?>>Mathematics</option>
                                    <option value="Physics" <?php echo $user['department'] == 'Physics' ? 'selected' : ''; ?>>Physics</option>
                                    <option value="Chemistry" <?php echo $user['department'] == 'Chemistry' ? 'selected' : ''; ?>>Chemistry</option>
                                    <option value="Biology" <?php echo $user['department'] == 'Biology' ? 'selected' : ''; ?>>Biology</option>
                                    <option value="Engineering" <?php echo $user['department'] == 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                                    <option value="Business" <?php echo $user['department'] == 'Business' ? 'selected' : ''; ?>>Business</option>
                                    <option value="Economics" <?php echo $user['department'] == 'Economics' ? 'selected' : ''; ?>>Economics</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-user-tag"></i> Role *</label>
                                <select name="role" required>
                                    <option value="teacher" <?php echo $user['role'] == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                    <option value="department_head" <?php echo $user['role'] == 'department_head' ? 'selected' : ''; ?>>Department Head</option>
                                    <option value="finance" <?php echo $user['role'] == 'finance' ? 'selected' : ''; ?>>Finance Officer</option>
                                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-toggle-on"></i> Status *</label>
                                <select name="status" required>
                                    <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Account Created</label>
                                <input type="text" value="<?php echo date('F d, Y', strtotime($user['created_at'])); ?>" disabled>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="update" class="btn-success">
                                <i class="fas fa-save"></i> Update User
                            </button>
                            <a href="manage_users.php" class="btn-warning">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Reset Password Tab -->
                <div id="password" class="tab-content <?php echo $active_tab == 'password' ? 'active' : ''; ?>">
                    <form method="POST" action="">
                        <div class="alert alert-info" style="margin-bottom: 20px;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Note:</strong> This will reset the password for user: <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> New Password *</label>
                                <input type="password" name="new_password" required minlength="6" id="newPassword" autocomplete="new-password">
                                <div id="password-strength" class="password-strength"></div>
                                <small class="text-muted" style="display: block; margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> Password must be at least 6 characters long.
                                </small>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Confirm Password *</label>
                                <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="reset_password" class="btn-info">
                                <i class="fas fa-key"></i> Reset Password
                            </button>
                            <a href="manage_users.php" class="btn-warning">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Tab switching
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Activate clicked tab
            event.target.classList.add('active');
            
            // Update URL without reloading page
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }
        
        // Password strength indicator
        document.getElementById('newPassword')?.addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthIndicator.style.display = 'none';
                return;
            }
            
            strengthIndicator.style.display = 'block';
            const strength = checkPasswordStrength(password);
            strengthIndicator.textContent = 'Strength: ' + strength.text;
            strengthIndicator.className = 'password-strength ' + strength.class;
        });
        
        function checkPasswordStrength(password) {
            if (password.length < 6) return { text: 'Weak', class: 'weak' };
            if (password.length < 8) return { text: 'Fair', class: 'fair' };
            if (/[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
                return { text: 'Strong', class: 'strong' };
            }
            return { text: 'Good', class: 'good' };
        }
        
        // Auto-hide notifications after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.notification').forEach(notification => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(() => notification.remove(), 500);
            });
        }, 5000);
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Add required asterisks
            const requiredLabels = document.querySelectorAll('input[required], select[required]');
            requiredLabels.forEach(input => {
                const label = input.closest('.form-group')?.querySelector('label');
                if (label && !label.querySelector('.required')) {
                    const span = document.createElement('span');
                    span.className = 'required';
                    span.style.color = 'var(--danger-color)';
                    span.textContent = ' *';
                    label.appendChild(span);
                }
            });
            
            // Initialize tab based on URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam && (tabParam === 'profile' || tabParam === 'password')) {
                showTab(tabParam);
            }
        });
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>