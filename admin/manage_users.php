<?php
// admin/manage_users.php
include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'admin') {
    die("Access Denied");
}

$success_msg = '';
$error_msg = '';

// Create new user
if (isset($_POST['create'])) {
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    $role     = $_POST['role'];
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $department = $_POST['department'] ?? '';

    // Check if username already exists
    $check_sql = "SELECT * FROM users WHERE username = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_msg = "Username already exists. Please choose a different username.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO users (username, password, role, full_name, email, phone, department, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->bind_param("sssssss", $username, $password, $role, $full_name, $email, $phone, $department);
        
        if ($stmt->execute()) {
            $success_msg = "User created successfully.";
            
            // If teacher, also create teacher record
            if ($role == 'teacher') {
                $user_id = $stmt->insert_id;
                $teacher_sql = "INSERT INTO teachers (user_id, academic_rank) VALUES (?, 'Assistant Lecturer')";
                $teacher_stmt = $conn->prepare($teacher_sql);
                $teacher_stmt->bind_param("i", $user_id);
                $teacher_stmt->execute();
            }
        } else {
            $error_msg = "Error creating user: " . $conn->error;
        }
    }
}

// Delete user
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    
    // Don't allow deleting yourself
    if ($user_id == $_SESSION['user_id']) {
        $error_msg = "You cannot delete your own account.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // First delete from teachers table if exists
            $delete_teacher_sql = "DELETE FROM teachers WHERE user_id = ?";
            $delete_teacher_stmt = $conn->prepare($delete_teacher_sql);
            $delete_teacher_stmt->bind_param("i", $user_id);
            $delete_teacher_stmt->execute();
            
            // Then delete from users table
            $delete_sql = "DELETE FROM users WHERE user_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $user_id);
            
            if ($delete_stmt->execute()) {
                $conn->commit();
                $success_msg = "User deleted successfully.";
            } else {
                throw new Exception("Error deleting user: " . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = $e->getMessage();
        }
    }
}

// Update user status
if (isset($_GET['toggle_status'])) {
    $user_id = intval($_GET['toggle_status']);
    $current_status = $_GET['current_status'];
    $new_status = ($current_status == 'active') ? 'inactive' : 'active';
    
    // Don't allow deactivating yourself
    if ($user_id == $_SESSION['user_id'] && $new_status == 'inactive') {
        $error_msg = "You cannot deactivate your own account.";
    } else {
        $update_sql = "UPDATE users SET status = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $user_id);
        
        if ($update_stmt->execute()) {
            $success_msg = "User status updated to " . $new_status . ".";
        } else {
            $error_msg = "Error updating user status: " . $conn->error;
        }
    }
}

// Fetch users with search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : 'all';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';

$sql = "SELECT user_id, username, full_name, email, phone, role, department, status, created_at, last_login 
        FROM users WHERE 1=1";

if ($search) {
    $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
}

if ($role_filter != 'all') {
    $sql .= " AND role = ?";
}

if ($status_filter != 'all') {
    $sql .= " AND status = ?";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$params = [];
$types = '';

if ($search) {
    $search_term = "%$search%";
    $types .= 'ssss';
    array_push($params, $search_term, $search_term, $search_term, $search_term);
}

if ($role_filter != 'all') {
    $types .= 's';
    array_push($params, $role_filter);
}

if ($status_filter != 'all') {
    $types .= 's';
    array_push($params, $status_filter);
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$total_users = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Admin Panel | Woldiya University</title>
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

        /* ====== Card Styles ====== */
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

        /* ====== Form Styles ====== */
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

        /* ====== Search and Filter Section ====== */
        .filters-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .filters-header h3 {
            color: var(--primary-color);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .filter-item {
            flex: 1;
            min-width: 200px;
        }

        .filter-item label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-select,
        .filter-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
            color: var(--dark-color);
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .search-button {
            align-self: flex-end;
            height: 46px;
        }

        /* ====== Users Table ====== */
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-header h3 {
            color: var(--primary-color);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        .users-table th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
        }

        .users-table th i {
            margin-right: 8px;
        }

        .users-table td {
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .users-table tr:hover {
            background: #f8fafc;
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        /* ====== Status Badges ====== */
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
            white-space: nowrap;
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

        /* ====== Role Badges ====== */
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .role-admin {
            background: linear-gradient(135deg, rgba(0, 64, 128, 0.1), rgba(0, 102, 204, 0.1));
            color: var(--primary-color);
            border: 1px solid rgba(0, 64, 128, 0.3);
        }

        .role-teacher {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(30, 126, 52, 0.1));
            color: var(--success-color);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .role-department_head {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(224, 168, 0, 0.1));
            color: var(--warning-color);
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .role-finance {
            background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(19, 132, 150, 0.1));
            color: var(--info-color);
            border: 1px solid rgba(23, 162, 184, 0.3);
        }

        /* ====== User Info ====== */
        .user-info-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 1rem;
        }

        .user-username {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .user-details {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .user-details span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ====== Action Buttons ====== */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            text-decoration: none;
        }

        .btn-edit {
            background: rgba(0, 64, 128, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(0, 64, 128, 0.2);
        }

        .btn-edit:hover {
            background: rgba(0, 64, 128, 0.2);
            transform: translateY(-1px);
        }

        .btn-delete {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .btn-delete:hover {
            background: rgba(220, 53, 69, 0.2);
            transform: translateY(-1px);
        }

        .btn-toggle {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .btn-toggle:hover {
            background: rgba(255, 193, 7, 0.2);
            transform: translateY(-1px);
        }

        .btn-reset {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(23, 162, 184, 0.2);
        }

        .btn-reset:hover {
            background: rgba(23, 162, 184, 0.2);
            transform: translateY(-1px);
        }

        /* ====== Empty State ====== */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: var(--border-radius);
            margin: 20px 0;
            border: 2px dashed #dee2e6;
        }

        .empty-state i {
            font-size: 80px;
            color: var(--primary-color);
            margin-bottom: 25px;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: var(--dark-color);
            margin-bottom: 15px;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .empty-state p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto 25px;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        /* ====== Responsive Design ====== */
        @media (max-width: 992px) {
            .sidebar {
                width: 250px;
            }
            
            .main-content {
                margin-left: 250px;
            }
            
            .form-row {
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .filter-group {
                flex-direction: column;
            }
            
            .filter-item {
                min-width: 100%;
            }
            
            .table-container {
                padding: 20px 15px;
            }
            
            .users-table th,
            .users-table td {
                padding: 14px 16px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .page-header h2 {
                font-size: 1.6rem;
            }
            
            .card-header,
            .card-body {
                padding: 20px;
            }
            
            .notification {
                padding: 12px 15px;
                font-size: 0.9rem;
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

        /* ====== Modal Styles ====== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal {
            background: white;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .modal-body {
            padding: 30px;
            text-align: center;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        /* ====== Password Strength ====== */
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
        
        /* ====== Stats Cards ====== */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            text-align: center;
            border-top: 4px solid var(--primary-color);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1rem;
            color: #6c757d;
            font-weight: 600;
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
            <div class="page-header">
                <h2><i class="fas fa-users-cog"></i> Manage Users</h2>
                <div class="header-actions">
                    <button class="btn-primary" onclick="toggleUserForm()">
                        <i class="fas fa-user-plus"></i> Add New User
                    </button>
                </div>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="notification notification-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="notification notification-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <?php
                // Count active users
                $active_sql = "SELECT COUNT(*) as active_count FROM users WHERE status = 'active'";
                $active_result = $conn->query($active_sql);
                $active_count = $active_result->fetch_assoc()['active_count'];
                ?>
                <div class="stat-card">
                    <i class="fas fa-user-check"></i>
                    <div class="stat-number"><?php echo $active_count; ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
                <?php
                // Count teachers
                $teacher_sql = "SELECT COUNT(*) as teacher_count FROM users WHERE role = 'teacher'";
                $teacher_result = $conn->query($teacher_sql);
                $teacher_count = $teacher_result->fetch_assoc()['teacher_count'];
                ?>
                <div class="stat-card">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <div class="stat-number"><?php echo $teacher_count; ?></div>
                    <div class="stat-label">Teachers</div>
                </div>
                <?php
                // Count department heads
                $dept_sql = "SELECT COUNT(*) as dept_count FROM users WHERE role = 'department_head'";
                $dept_result = $conn->query($dept_sql);
                $dept_count = $dept_result->fetch_assoc()['dept_count'];
                ?>
                <div class="stat-card">
                    <i class="fas fa-user-tie"></i>
                    <div class="stat-number"><?php echo $dept_count; ?></div>
                    <div class="stat-label">Department Heads</div>
                </div>
            </div>
            
            <!-- Add User Form (Initially Hidden) -->
            <div class="card" id="userForm" style="display: none;">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                    <button class="btn-warning btn-sm" onclick="toggleUserForm()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Username *</label>
                                <input type="text" name="username" required placeholder="Enter username" pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores allowed">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Password *</label>
                                <input type="password" name="password" required placeholder="Enter password" minlength="6" id="newPassword">
                                <div id="password-strength" class="password-strength"></div>
                                <small class="text-muted" style="display: block; margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> Minimum 6 characters
                                </small>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-id-card"></i> Full Name</label>
                                <input type="text" name="full_name" placeholder="Enter full name">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email</label>
                                <input type="email" name="email" placeholder="Enter email address">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone</label>
                                <input type="text" name="phone" placeholder="Enter phone number">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-building"></i> Department</label>
                                <select name="department">
                                    <option value="">Select Department</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="Mathematics">Mathematics</option>
                                    <option value="Physics">Physics</option>
                                    <option value="Chemistry">Chemistry</option>
                                    <option value="Biology">Biology</option>
                                    <option value="Engineering">Engineering</option>
                                    <option value="Business">Business</option>
                                    <option value="Economics">Economics</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user-tag"></i> Role *</label>
                                <select name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="teacher">Teacher</option>
                                    <option value="department_head">Department Head</option>
                                    <option value="finance">Finance Officer</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="create" class="btn-success">
                                <i class="fas fa-save"></i> Create User
                            </button>
                            <button type="reset" class="btn-warning">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="filters-container">
                <div class="filters-header">
                    <h3><i class="fas fa-filter"></i> Filter Users</h3>
                </div>
                <form method="GET" action="">
                    <div class="filter-group">
                        <div class="filter-item">
                            <label><i class="fas fa-search"></i> Search</label>
                            <input type="text" class="filter-input" name="search" placeholder="Search by name, username, email or phone" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-item">
                            <label><i class="fas fa-user-tag"></i> Role</label>
                            <select class="filter-select" name="role_filter">
                                <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                                <option value="teacher" <?php echo $role_filter == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                <option value="department_head" <?php echo $role_filter == 'department_head' ? 'selected' : ''; ?>>Department Head</option>
                                <option value="finance" <?php echo $role_filter == 'finance' ? 'selected' : ''; ?>>Finance Officer</option>
                                <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label><i class="fas fa-toggle-on"></i> Status</label>
                            <select class="filter-select" name="status_filter">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <button type="submit" class="btn-primary search-button">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <?php if ($search || $role_filter != 'all' || $status_filter != 'all'): ?>
                            <a href="manage_users.php" class="btn-warning" style="margin-top: 10px; display: inline-block;">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-users"></i> Existing Users</h3>
                    <div class="user-count">
                        <span class="text-muted">
                            <i class="fas fa-user-check"></i> 
                            Showing: <?php echo $total_users; ?> user(s)
                        </span>
                    </div>
                </div>
                
                <?php if ($total_users > 0): ?>
                    <div class="table-wrapper">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user"></i> User</th>
                                    <th><i class="fas fa-user-tag"></i> Role</th>
                                    <th><i class="fas fa-building"></i> Department</th>
                                    <th><i class="fas fa-toggle-on"></i> Status</th>
                                    <th><i class="fas fa-calendar-alt"></i> Created</th>
                                    <th><i class="fas fa-cog"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="user-info-cell">
                                            <div class="user-name"><?php echo htmlspecialchars($row['full_name'] ?: $row['username']); ?></div>
                                            <div class="user-username">
                                                <i class="fas fa-at"></i> <?php echo htmlspecialchars($row['username']); ?>
                                            </div>
                                            <div class="user-details">
                                                <?php if ($row['email']): ?>
                                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['email']); ?></span>
                                                <?php endif; ?>
                                                <?php if ($row['phone']): ?>
                                                <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['phone']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?php echo str_replace('_', '', $row['role']); ?>">
                                            <i class="fas fa-<?php 
                                                echo $row['role'] == 'teacher' ? 'chalkboard-teacher' : 
                                                       ($row['role'] == 'department_head' ? 'user-tie' : 
                                                       ($row['role'] == 'finance' ? 'money-bill-wave' : 'user-shield')); 
                                            ?>"></i>
                                            <?php echo ucwords(str_replace('_', ' ', $row['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($row['department'] ?: 'N/A'); ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $row['status']; ?>">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                                        <?php if ($row['last_login'] && $row['last_login'] != '0000-00-00 00:00:00'): ?>
                                        <div class="text-muted" style="margin-top: 5px; font-size: 0.85rem;">
                                            <i class="fas fa-sign-in-alt"></i> Last login: 
                                            <?php echo date('M d', strtotime($row['last_login'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_user.php?id=<?php echo $row['user_id']; ?>" class="btn-action btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if ($row['user_id'] != $_SESSION['user_id']): ?>
                                            <a href="?toggle_status=<?php echo $row['user_id']; ?>&current_status=<?php echo $row['status']; ?>" 
                                               class="btn-action btn-toggle"
                                               onclick="return confirm('Are you sure you want to <?php echo $row['status'] == 'active' ? 'deactivate' : 'activate'; ?> this user?')">
                                                <i class="fas fa-toggle-<?php echo $row['status'] == 'active' ? 'off' : 'on'; ?>"></i>
                                                <?php echo $row['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </a>
                                            <a href="edit_user.php?id=<?php echo $row['user_id']; ?>&tab=password" class="btn-action btn-reset">
                                                <i class="fas fa-key"></i> Reset Password
                                            </a>
                                            <a href="#" class="btn-action btn-delete"
                                               onclick="return confirmDelete(<?php echo $row['user_id']; ?>, '<?php echo htmlspecialchars($row['username']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.85rem; padding: 8px 12px; background: rgba(0,0,0,0.05); border-radius: 6px;">
                                                <i class="fas fa-info-circle"></i> Current User
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h3>No Users Found</h3>
                        <p>No users match your search criteria. Try adjusting your filters or add new users.</p>
                        <button class="btn-primary" onclick="toggleUserForm()" style="margin-top: 20px; display: inline-flex; width: auto;">
                            <i class="fas fa-user-plus"></i> Add New User
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
            </div>
            <div class="modal-body">
                <i class="fas fa-trash-alt" style="font-size: 4rem; color: var(--danger-color); margin-bottom: 20px;"></i>
                <h4 style="margin-bottom: 15px; color: var(--dark-color);">Are you sure you want to delete this user?</h4>
                <p id="deleteMessage" style="color: #666; margin-bottom: 10px;"></p>
                <p style="color: var(--danger-color); font-weight: 600;">
                    <i class="fas fa-exclamation-circle"></i> This action cannot be undone!
                </p>
                <div class="modal-actions">
                    <button id="confirmDeleteBtn" class="btn-danger">
                        <i class="fas fa-trash"></i> Delete User
                    </button>
                    <button class="btn-warning" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle user form visibility
        function toggleUserForm() {
            const form = document.getElementById('userForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'block') {
                form.scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        // Delete confirmation with modal
        function confirmDelete(userId, username) {
            event.preventDefault();
            
            const modal = document.getElementById('deleteModal');
            const deleteMessage = document.getElementById('deleteMessage');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            
            deleteMessage.textContent = `You are about to delete user: ${username} (ID: ${userId})`;
            
            confirmBtn.onclick = function() {
                window.location.href = `?delete=${userId}`;
            };
            
            modal.style.display = 'flex';
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Auto-hide notifications after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.notification').forEach(notification => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(() => notification.remove(), 500);
            });
        }, 5000);
        
        // Password strength indicator for create form
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
            if (/[A-Z]/.test(password) && /[0-9]/.test(password)) {
                return { text: 'Good', class: 'good' };
            }
            return { text: 'Strong', class: 'strong' };
        }
        
        // Initialize form validation
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
            
            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>
</html>