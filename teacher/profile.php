<?php
// teacher/profile.php OR department_head/profile.php
include("../config/session.php");
include("../config/db.php");

// Check user role
if (!in_array($_SESSION['role'], ['teacher', 'department_head'])) {
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user data
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found");
}

// Get additional info based on role
if ($role == 'teacher') {
    // Get teacher specific data
    $teacher_sql = "SELECT * FROM teachers WHERE user_id = ?";
    $teacher_stmt = $conn->prepare($teacher_sql);
    $teacher_stmt->bind_param("i", $user_id);
    $teacher_stmt->execute();
    $teacher_result = $teacher_stmt->get_result();
    $teacher_data = $teacher_result->fetch_assoc();
    
    // Get teacher statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN credit_hour END), 0) as total_credits
    FROM overload_requests WHERE teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = ?)";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $stats = $stats_result->fetch_assoc();
}

// Update profile if form submitted
$success_msg = '';
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    $update_sql = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);
    
    if ($update_stmt->execute()) {
        $success_msg = "Profile updated successfully!";
        // Refresh user data
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        // Update session
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
    } else {
        $error_msg = "Error updating profile: " . $conn->error;
    }
}

// Change password if form submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = md5($_POST['current_password']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $check_sql = "SELECT password FROM users WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_user = $check_result->fetch_assoc();
    
    if ($check_user['password'] !== $current_password) {
        $error_msg = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_msg = "Password must be at least 6 characters long.";
    } else {
        $new_password_hash = md5($new_password);
        $update_pass_sql = "UPDATE users SET password = ? WHERE user_id = ?";
        $update_pass_stmt = $conn->prepare($update_pass_sql);
        $update_pass_stmt->bind_param("si", $new_password_hash, $user_id);
        if ($update_pass_stmt->execute()) {
            $success_msg = "Password changed successfully!";
        } else {
            $error_msg = "Error changing password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Woldiya University</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ====== CSS Variables ====== */
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

        /* ====== Base Styles ====== */
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

        /* ====== Dashboard Container ====== */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* ====== Sidebar Styles ====== */
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

        /* ====== Main Content ====== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        /* ====== Page Header ====== */
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

        .header-actions {
            display: flex;
            gap: 15px;
        }

        /* ====== Card Styles ====== */
        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
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

        /* ====== Profile Container ====== */
        .profile-container {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ====== Profile Header ====== */
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 40px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .profile-header:before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(100px, -100px);
        }

        .profile-header:after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .profile-image-section {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid white;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-image i {
            font-size: 5rem;
            color: var(--primary-color);
        }

        .profile-basic h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: white;
        }

        .profile-title {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-department {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-status {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: var(--success-color);
            color: white;
        }

        .status-pending {
            background: var(--warning-color);
            color: #212529;
        }

        /* ====== Stats Grid ====== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-item {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: none;
        }

        .stat-item:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.8rem;
            background: linear-gradient(135deg, rgba(0, 64, 128, 0.1), rgba(0, 102, 204, 0.1));
            color: var(--primary-color);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 0.95rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ====== Form Tabs ====== */
        .form-tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .form-tab {
            padding: 15px 30px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: #6c757d;
            border-bottom: 3px solid transparent;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-tab:hover {
            color: var(--primary-color);
        }

        .form-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .tab-content.active {
            display: block;
        }

        /* ====== Info Grid ====== */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .info-item {
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
            transition: var(--transition);
        }

        .info-item:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
        }

        .info-item label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #555;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .info-item label i {
            color: var(--primary-color);
        }

        .info-item p {
            color: var(--dark-color);
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* ====== Form Styles ====== */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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

        /* ====== Button Styles ====== */
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

        .btn-primary:active {
            transform: translateY(0);
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

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.85rem;
        }

        /* ====== Notification Styles ====== */
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

        .notification-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border-left: 4px solid var(--info-color);
        }

        .notification i {
            font-size: 1.2rem;
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

        /* ====== Security Section ====== */
        .security-section {
            padding: 20px;
        }

        .security-section h4 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ====== Activity List ====== */
        .activity-list {
            padding: 20px;
        }

        .activity-list table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-list th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid #dee2e6;
        }

        .activity-list td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .activity-list tr:hover {
            background: #f8f9fa;
        }

        .decision-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .decision-approved {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .decision-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .decision-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        /* ====== Responsive Design ====== */
        @media (max-width: 992px) {
            .sidebar {
                width: 250px;
            }
            
            .main-content {
                margin-left: 250px;
            }
            
            .profile-image-section {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
            
            .profile-header {
                padding: 30px 20px;
            }
            
            .profile-basic h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-tabs {
                flex-direction: column;
            }
            
            .form-tab {
                width: 100%;
                text-align: left;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .page-header h2 {
                font-size: 1.6rem;
            }
            
            .profile-image {
                width: 120px;
                height: 120px;
            }
            
            .profile-image i {
                font-size: 4rem;
            }
            
            .card-header,
            .card-body {
                padding: 20px;
            }
        }

        /* ====== Custom Scrollbar ====== */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* ====== Text Muted ====== */
        .text-muted {
            color: #6c757d !important;
            font-size: 0.85rem;
        }

        /* ====== Password Strength ====== */
        .password-strength {
            margin-top: 5px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
        }

        .password-strength.weak {
            background: #f8d7da;
            color: #721c24;
        }

        .password-strength.fair {
            background: #fff3cd;
            color: #856404;
        }

        .password-strength.good {
            background: #d1ecf1;
            color: #0c5460;
        }

        .password-strength.strong {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><?php echo ucfirst($role); ?> Panel</h3>
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h4>
                        <p><?php echo ucfirst($role); ?></p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <?php if ($role == 'teacher'): ?>
                    <li><a href="submit_overload.php"><i class="fas fa-plus-circle"></i> Submit Overload</a></li>
                    <li><a href="view_status.php"><i class="fas fa-tasks"></i> Request Status</a></li>
                    <li><a href="payment_history.php"><i class="fas fa-money-bill-wave"></i> Payment History</a></li>
                <?php endif; ?>
                <?php if ($role == 'department_head'): ?>
                    <li><a href="approve_overload.php"><i class="fas fa-check-circle"></i> Review Requests</a></li>
                    <li><a href="approval_history.php"><i class="fas fa-history"></i> Approval History</a></li>
                <?php endif; ?>
                <li><a href="profile.php" class="active"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-user-circle"></i> My Profile</h2>
                <div class="header-actions">
                    <button class="btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Profile
                    </button>
                </div>
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
            
            <div class="profile-container">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-image-section">
                        <div class="profile-image">
                            <?php if ($role == 'teacher'): ?>
                                <i class="fas fa-chalkboard-teacher"></i>
                            <?php else: ?>
                                <i class="fas fa-user-tie"></i>
                            <?php endif; ?>
                        </div>
                        <div class="profile-basic">
                            <h1><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h1>
                            <p class="profile-title"><?php echo ucfirst($role); ?></p>
                            <p class="profile-department">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($user['department'] ?? 'Not assigned'); ?>
                            </p>
                            <div class="profile-status">
                                <span class="status-badge status-active">Active</span>
                                <span>Member since: <?php echo date('F Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Section (for teachers) -->
                <?php if ($role == 'teacher' && isset($stats)): ?>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['total_requests'] ?? 0; ?></div>
                        <div class="stat-label">Total Requests</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['approved_requests'] ?? 0; ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['pending_requests'] ?? 0; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['total_credits'] ?? 0; ?></div>
                        <div class="stat-label">Total Credits</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Tabs -->
                <div class="form-tabs">
                    <button class="form-tab active" onclick="showTab('personalInfo')">
                        <i class="fas fa-id-card"></i> Personal Information
                    </button>
                    <button class="form-tab" onclick="showTab('security')">
                        <i class="fas fa-shield-alt"></i> Security
                    </button>
                    <?php if ($role == 'department_head'): ?>
                    <button class="form-tab" onclick="showTab('activity')">
                        <i class="fas fa-history"></i> Activity
                    </button>
                    <?php endif; ?>
                </div>
                
                <!-- Personal Information Tab -->
                <div id="personalInfo" class="tab-content active">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-id-card"></i> Personal Information</h3>
                            <button class="btn-primary btn-sm" onclick="toggleEdit('personalInfoContent')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                        <div id="personalInfoContent">
                            <div class="info-grid">
                                <div class="info-item">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <p><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-phone"></i> Phone</label>
                                    <p><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-user-tag"></i> Role</label>
                                    <p><?php echo ucfirst($user['role']); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-user-circle"></i> Username</label>
                                    <p><?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-building"></i> Department</label>
                                    <p><?php echo htmlspecialchars($user['department'] ?? 'Not assigned'); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-calendar-alt"></i> Account Created</label>
                                    <p><?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-sign-in-alt"></i> Last Login</label>
                                    <p><?php echo $user['last_login'] ? date('F d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Form -->
                        <div id="personalInfoEdit" style="display: none;">
                            <form method="POST" action="">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-user"></i> Full Name</label>
                                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-envelope"></i> Email</label>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-phone"></i> Phone Number</label>
                                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-building"></i> Department</label>
                                        <input type="text" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>" readonly disabled>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="update_profile" class="btn-success">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <button type="button" class="btn-warning" onclick="toggleEdit('personalInfoContent')">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div id="security" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-shield-alt"></i> Account Security</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <h4 style="margin-bottom: 20px; color: var(--primary-color);">
                                    <i class="fas fa-key"></i> Change Password
                                </h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-lock"></i> Current Password</label>
                                        <input type="password" name="current_password" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-lock"></i> New Password</label>
                                        <input type="password" name="new_password" required minlength="6" id="newPassword" oninput="checkPasswordStrength()">
                                        <div id="password-strength" class="password-strength"></div>
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-lock"></i> Confirm New Password</label>
                                        <input type="password" name="confirm_password" required minlength="6">
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="change_password" class="btn-success">
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Tab (for department heads) -->
                <?php if ($role == 'department_head'): ?>
                <div id="activity" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-history"></i> Recent Activity</h3>
                        </div>
                        <div class="card-body">
                            <div class="activity-list">
                                <?php
                                // Get recent approvals for department head
                                $activity_sql = "SELECT 
                                    o.course_name,
                                    u.full_name as teacher_name,
                                    a.decision,
                                    a.approved_at
                                FROM approvals a
                                JOIN overload_requests o ON a.request_id = o.request_id
                                JOIN teachers t ON o.teacher_id = t.teacher_id
                                JOIN users u ON t.user_id = u.user_id
                                WHERE a.department_head_id = ?
                                ORDER BY a.approved_at DESC 
                                LIMIT 10";
                                
                                $activity_stmt = $conn->prepare($activity_sql);
                                if ($activity_stmt) {
                                    $activity_stmt->bind_param("i", $user_id);
                                    $activity_stmt->execute();
                                    $activity_result = $activity_stmt->get_result();
                                    
                                    if ($activity_result->num_rows > 0):
                                ?>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Action</th>
                                                <th>Course</th>
                                                <th>Teacher</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($activity = $activity_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <span class="decision-badge decision-<?php echo $activity['decision']; ?>">
                                                        <?php echo ucfirst($activity['decision']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($activity['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($activity['teacher_name']); ?></td>
                                                <td><?php echo date('M d, H:i', strtotime($activity['approved_at'])); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p style="text-align: center; padding: 40px; color: #6c757d;">
                                        <i class="fas fa-history fa-2x" style="margin-bottom: 15px;"></i><br>
                                        No recent activity found. Your approvals will appear here.
                                    </p>
                                <?php endif; 
                                } else {
                                    echo '<p style="text-align: center; padding: 40px; color: #6c757d;">Unable to load activity.</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
            document.querySelectorAll('.form-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Activate clicked tab
            event.target.classList.add('active');
        }
        
        // Toggle edit mode
        function toggleEdit(sectionId) {
            const displaySection = document.getElementById(sectionId);
            const editSection = document.getElementById(sectionId + 'Edit');
            
            if (displaySection.style.display === 'none') {
                displaySection.style.display = 'block';
                editSection.style.display = 'none';
            } else {
                displaySection.style.display = 'none';
                editSection.style.display = 'block';
            }
        }
        
        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('newPassword').value;
            const strengthIndicator = document.getElementById('password-strength');
            
            if (!strengthIndicator) return;
            
            if (password.length === 0) {
                strengthIndicator.textContent = '';
                strengthIndicator.className = 'password-strength';
                return;
            }
            
            let strength = 'Weak';
            let strengthClass = 'weak';
            
            if (password.length >= 8) {
                strength = 'Fair';
                strengthClass = 'fair';
            }
            
            if (password.length >= 10 && /[A-Z]/.test(password) && /[0-9]/.test(password)) {
                strength = 'Good';
                strengthClass = 'good';
            }
            
            if (password.length >= 12 && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
                strength = 'Strong';
                strengthClass = 'strong';
            }
            
            strengthIndicator.textContent = 'Strength: ' + strength;
            strengthIndicator.className = 'password-strength ' + strengthClass;
        }
        
        // Auto-hide notifications after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.notification').forEach(notification => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(() => notification.remove(), 500);
            });
        }, 5000);
        
        // Initialize password strength on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkPasswordStrength();
        });
    </script>
</body>
</html>