<?php
// admin/course_assignment.php
include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle course assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_course'])) {
    $teacher_id = intval($_POST['teacher_id']);
    $course_id = intval($_POST['course_id']);
    $semester = $_POST['semester'];
    $academic_year = $_POST['academic_year'];
    $credit_hours = intval($_POST['credit_hours']);
    $notes = $_POST['notes'] ?? '';
    $assigned_by = $_SESSION['user_id'];

    // Check if already assigned in same semester
    $check_sql = "SELECT * FROM course_assignments 
                  WHERE teacher_id = ? AND course_id = ? 
                  AND semester = ? AND academic_year = ? 
                  AND status = 'active'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iiss", $teacher_id, $course_id, $semester, $academic_year);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_msg = "This course is already assigned to this teacher for the selected semester and year.";
    } else {
        $insert_sql = "INSERT INTO course_assignments 
                      (teacher_id, course_id, semester, academic_year, credit_hours, assigned_by, notes) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iissiis", $teacher_id, $course_id, $semester, $academic_year, $credit_hours, $assigned_by, $notes);
        
        if ($insert_stmt->execute()) {
            $success_msg = "Course assigned successfully!";
        } else {
            $error_msg = "Error assigning course: " . $conn->error;
        }
    }
}

// Handle remove assignment
if (isset($_GET['remove_assignment'])) {
    $assignment_id = intval($_GET['remove_assignment']);
    
    $delete_sql = "DELETE FROM course_assignments WHERE assignment_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $assignment_id);
    
    if ($delete_stmt->execute()) {
        $success_msg = "Course assignment removed successfully.";
    } else {
        $error_msg = "Error removing assignment: " . $conn->error;
    }
}

// Handle update assignment status
if (isset($_GET['update_status'])) {
    $assignment_id = intval($_GET['update_status']);
    $new_status = $_GET['status'];
    
    $update_sql = "UPDATE course_assignments SET status = ? WHERE assignment_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $assignment_id);
    
    if ($update_stmt->execute()) {
        $success_msg = "Assignment status updated to " . $new_status . ".";
    } else {
        $error_msg = "Error updating status: " . $conn->error;
    }
}

// Get all teachers and store in array
$teachers_sql = "SELECT t.teacher_id, u.user_id, u.full_name, u.username, u.department 
                FROM teachers t 
                JOIN users u ON t.user_id = u.user_id 
                WHERE u.role = 'teacher' 
                ORDER BY u.department, u.full_name";
$teachers_result = $conn->query($teachers_sql);
$teachers = [];
if ($teachers_result) {
    while ($teacher = $teachers_result->fetch_assoc()) {
        $teachers[] = $teacher;
    }
} else {
    $error_msg .= " Error fetching teachers: " . $conn->error;
}

// Get all courses and store in array - REMOVED status filter
$courses_sql = "SELECT * FROM courses ORDER BY department, course_code";
$courses_result = $conn->query($courses_sql);
$courses = [];
if ($courses_result) {
    while ($course = $courses_result->fetch_assoc()) {
        $courses[] = $course;
    }
} else {
    $error_msg .= " Error fetching courses: " . $conn->error;
}

// Build filter conditions
$filter_conditions = [];
$params = [];
$param_types = "";

if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
    $filter_conditions[] = "ca.status = ?";
    $params[] = $_GET['status_filter'];
    $param_types .= "s";
}

if (isset($_GET['semester_filter']) && !empty($_GET['semester_filter'])) {
    $filter_conditions[] = "ca.semester = ?";
    $params[] = $_GET['semester_filter'];
    $param_types .= "s";
}

if (isset($_GET['year_filter']) && !empty($_GET['year_filter'])) {
    $filter_conditions[] = "ca.academic_year = ?";
    $params[] = $_GET['year_filter'];
    $param_types .= "s";
}

if (isset($_GET['teacher_filter']) && !empty($_GET['teacher_filter'])) {
    $filter_conditions[] = "ca.teacher_id = ?";
    $params[] = intval($_GET['teacher_filter']);
    $param_types .= "i";
}

// Get all assignments with details
$assignments_sql = "SELECT ca.*, 
                    u.full_name as teacher_name, 
                    u.department as teacher_dept,
                    u.email as teacher_email,
                    c.course_code, c.course_name, c.department as course_dept, c.credit_hours as course_credit_hours,
                    au.full_name as assigned_by_name,
                    au.username as assigned_by_username
                   FROM course_assignments ca
                   JOIN teachers t ON ca.teacher_id = t.teacher_id
                   JOIN users u ON t.user_id = u.user_id
                   JOIN courses c ON ca.course_id = c.course_id
                   JOIN users au ON ca.assigned_by = au.user_id";
                   
// Add filters if any
if (!empty($filter_conditions)) {
    $assignments_sql .= " WHERE " . implode(" AND ", $filter_conditions);
}

$assignments_sql .= " ORDER BY ca.academic_year DESC, 
                   FIELD(ca.semester, 'Fall', 'Spring', 'Summer'), 
                   ca.assigned_date DESC";

// Prepare and execute with filters
$assignments_stmt = $conn->prepare($assignments_sql);
if (!$assignments_stmt) {
    $error_msg .= " Error preparing assignments query: " . $conn->error;
} else {
    if (!empty($params)) {
        $assignments_stmt->bind_param($param_types, ...$params);
    }
    
    if (!$assignments_stmt->execute()) {
        $error_msg .= " Error executing assignments query: " . $assignments_stmt->error;
    }
    
    $assignments_result = $assignments_stmt->get_result();
    if (!$assignments_result) {
        $error_msg .= " Error getting assignments result: " . $assignments_stmt->error;
    }
}

// Get statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT teacher_id) as total_teachers,
    COUNT(DISTINCT course_id) as total_courses,
    COUNT(*) as total_assignments,
    SUM(credit_hours) as total_credit_hours,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_assignments,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_assignments
    FROM course_assignments";
    
// Add filters to stats if any
if (!empty($filter_conditions)) {
    $stats_sql .= " WHERE " . str_replace("ca.", "", implode(" AND ", $filter_conditions));
}

$stats_stmt = $conn->prepare($stats_sql);
if ($stats_stmt) {
    if (!empty($params)) {
        $stats_stmt->bind_param($param_types, ...$params);
    }
    
    if ($stats_stmt->execute()) {
        $stats_result = $stats_stmt->get_result();
        if ($stats_result) {
            $stats = $stats_result->fetch_assoc();
        }
    }
}

// If stats is not set, initialize empty array
if (!isset($stats)) {
    $stats = [
        'total_teachers' => 0,
        'total_courses' => 0,
        'total_assignments' => 0,
        'total_credit_hours' => 0,
        'active_assignments' => 0,
        'completed_assignments' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Assignment | Admin Panel | Woldiya University</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #004080;
            --secondary-color: #0066cc;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --border-radius: 12px;
            --box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
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
            transition: all 0.3s;
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
            color: #343a40;
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
            color: #343a40;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 64, 128, 0.1);
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
            transition: all 0.3s;
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
            transition: all 0.3s;
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
            transition: all 0.3s;
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
            transition: all 0.3s;
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
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.85rem;
        }

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
            color: #343a40;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1rem;
            color: #6c757d;
            font-weight: 600;
        }

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

        /* Status badges */
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

        .status-completed {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }

        .status-cancelled {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        /* Semester badges */
        .semester-badge {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
        }

        .semester-fall {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .semester-spring {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .semester-summer {
            background: linear-gradient(135deg, #f39c12, #d35400);
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
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

        .btn-update {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .btn-update:hover {
            background: rgba(255, 193, 7, 0.2);
            transform: translateY(-1px);
        }

        /* Filter section */
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #495057;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        /* Notes popup */
        .notes-popup {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            max-width: 300px;
            display: none;
        }

        .notes-popup.show {
            display: block;
        }

        /* Responsive */
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
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .page-header h2 {
                font-size: 1.6rem;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 20px;
            }
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
                <li><a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="course_assignment.php" class="active"><i class="fas fa-book"></i> Course Assignment</a></li>
                <li><a href="payment_rate.php"><i class="fas fa-money-check-alt"></i> Payment Rates</a></li>
                <li><a href="system_logs.php"><i class="fas fa-clipboard-list"></i> System Logs</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-book"></i> Course Assignment</h2>
                <div class="header-actions">
                    <button class="btn-primary" onclick="toggleAssignmentForm()">
                        <i class="fas fa-plus-circle"></i> Assign New Course
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
            
            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <div class="stat-number"><?php echo $stats['total_teachers'] ?? 0; ?></div>
                    <div class="stat-label">Teachers Assigned</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-book"></i>
                    <div class="stat-number"><?php echo $stats['total_courses'] ?? 0; ?></div>
                    <div class="stat-label">Courses Assigned</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-tasks"></i>
                    <div class="stat-number"><?php echo $stats['total_assignments'] ?? 0; ?></div>
                    <div class="stat-label">Total Assignments</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <div class="stat-number"><?php echo $stats['total_credit_hours'] ?? 0; ?></div>
                    <div class="stat-label">Total Credit Hours</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-number"><?php echo $stats['active_assignments'] ?? 0; ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-double"></i>
                    <div class="stat-number"><?php echo $stats['completed_assignments'] ?? 0; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <h3 style="margin-bottom: 15px; color: var(--primary-color);">
                    <i class="fas fa-filter"></i> Filter Assignments
                </h3>
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status_filter">
                                <option value="">All Status</option>
                                <option value="active" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="completed" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Semester</label>
                            <select name="semester_filter">
                                <option value="">All Semesters</option>
                                <option value="Fall" <?php echo (isset($_GET['semester_filter']) && $_GET['semester_filter'] == 'Fall') ? 'selected' : ''; ?>>Fall</option>
                                <option value="Spring" <?php echo (isset($_GET['semester_filter']) && $_GET['semester_filter'] == 'Spring') ? 'selected' : ''; ?>>Spring</option>
                                <option value="Summer" <?php echo (isset($_GET['semester_filter']) && $_GET['semester_filter'] == 'Summer') ? 'selected' : ''; ?>>Summer</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Academic Year</label>
                            <input type="text" name="year_filter" placeholder="e.g., 2024/2025" 
                                   value="<?php echo htmlspecialchars($_GET['year_filter'] ?? ''); ?>">
                        </div>
                        <div class="filter-group">
                            <label>Teacher</label>
                            <select name="teacher_filter">
                                <option value="">All Teachers</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['teacher_id']; ?>" 
                                    <?php echo (isset($_GET['teacher_filter']) && $_GET['teacher_filter'] == $teacher['teacher_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary btn-sm">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="course_assignment.php" class="btn-warning btn-sm">
                            <i class="fas fa-redo"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Assignment Form (Initially Hidden) -->
            <div class="card" id="assignmentForm" style="display: none;">
                <div class="card-header">
                    <h3><i class="fas fa-plus-circle"></i> Assign New Course</h3>
                    <button class="btn-warning btn-sm" onclick="toggleAssignmentForm()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-chalkboard-teacher"></i> Select Teacher *</label>
                                <select name="teacher_id" required>
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['teacher_id']; ?>">
                                        <?php echo htmlspecialchars($teacher['full_name'] . ' (' . $teacher['department'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-book"></i> Select Course *</label>
                                <select name="course_id" required id="courseSelect">
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['course_id']; ?>" data-hours="<?php echo $course['credit_hours']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name'] . ' (' . $course['department'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Semester *</label>
                                <select name="semester" required>
                                    <option value="">Select Semester</option>
                                    <option value="Fall">Fall Semester</option>
                                    <option value="Spring">Spring Semester</option>
                                    <option value="Summer">Summer Semester</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Academic Year *</label>
                                <select name="academic_year" required>
                                    <option value="">Select Year</option>
                                    <?php for ($year = date('Y'); $year <= date('Y') + 5; $year++): ?>
                                    <?php $academic_year = ($year - 1) . '/' . $year; ?>
                                    <option value="<?php echo $academic_year; ?>"><?php echo $academic_year; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Credit Hours *</label>
                                <input type="number" name="credit_hours" min="1" max="6" value="3" required id="creditHours">
                                <small class="text-muted" style="display: block; margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> Default is 3 credit hours
                                </small>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-sticky-note"></i> Notes (Optional)</label>
                                <textarea name="notes" rows="3" placeholder="Add any additional notes..."></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="assign_course" class="btn-success">
                                <i class="fas fa-save"></i> Assign Course
                            </button>
                            <button type="reset" class="btn-warning">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Current Assignments -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-list-alt"></i> Course Assignments</h3>
                    <div class="user-count">
                        <span class="text-muted">
                            <i class="fas fa-filter"></i> 
                            Showing: 
                            <?php 
                            if (isset($assignments_result) && $assignments_result) {
                                echo $assignments_result->num_rows;
                            } else {
                                echo '0';
                            }
                            ?> assignment(s)
                        </span>
                    </div>
                </div>
                
                <?php if (isset($assignments_result) && $assignments_result && $assignments_result->num_rows > 0): ?>
                    <div class="table-wrapper">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag"></i> ID</th>
                                    <th><i class="fas fa-user"></i> Teacher Details</th>
                                    <th><i class="fas fa-book"></i> Course Details</th>
                                    <th><i class="fas fa-calendar"></i> Semester/Year</th>
                                    <th><i class="fas fa-clock"></i> Credit Hours</th>
                                    <th><i class="fas fa-user-check"></i> Assignment Info</th>
                                    <th><i class="fas fa-toggle-on"></i> Status</th>
                                    <th><i class="fas fa-cog"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($assignment = $assignments_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary-color);">
                                            #<?php echo $assignment['assignment_id']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($assignment['teacher_name']); ?></div>
                                        <div style="font-size: 0.85rem; color: #666;">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($assignment['teacher_dept']); ?>
                                        </div>
                                        <?php if (!empty($assignment['teacher_email'])): ?>
                                        <div style="font-size: 0.8rem; color: #888;">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($assignment['teacher_email']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($assignment['course_code']); ?></div>
                                        <div style="font-size: 0.85rem; color: #666;">
                                            <?php echo htmlspecialchars($assignment['course_name']); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #888;">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($assignment['course_dept']); ?>
                                        </div>
                                        <?php if (isset($assignment['course_credit_hours'])): ?>
                                        <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">
                                            <i class="fas fa-info-circle"></i> Course Credits: <?php echo $assignment['course_credit_hours']; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="semester-badge semester-<?php echo strtolower($assignment['semester']); ?>">
                                            <?php echo $assignment['semester']; ?>
                                        </span>
                                        <div style="margin-top: 5px; font-weight: 600;">
                                            <?php echo $assignment['academic_year']; ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #888;">
                                            <i class="fas fa-calendar-day"></i> Assigned: <?php echo date('M d, Y', strtotime($assignment['assigned_date'])); ?>
                                        </div>
                                        <?php if (!empty($assignment['updated_date']) && $assignment['updated_date'] != $assignment['assigned_date']): ?>
                                        <div style="font-size: 0.8rem; color: #888;">
                                            <i class="fas fa-sync-alt"></i> Updated: <?php echo date('M d, Y', strtotime($assignment['updated_date'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary-color);">
                                            <?php echo $assignment['credit_hours']; ?> hrs
                                        </div>
                                        <?php if (!empty($assignment['notes'])): ?>
                                        <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">
                                            <a href="#" onclick="showNotes('<?php echo htmlspecialchars(addslashes($assignment['notes'])); ?>', event)" 
                                               style="color: var(--info-color); text-decoration: none;">
                                                <i class="fas fa-sticky-note"></i> View Notes
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></div>
                                        <div style="font-size: 0.8rem; color: #888;">
                                            @<?php echo htmlspecialchars($assignment['assigned_by_username']); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #888;">
                                            <i class="fas fa-calendar-day"></i> <?php echo date('M d, Y', strtotime($assignment['assigned_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $assignment['status']; ?>">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                            <?php echo ucfirst($assignment['status']); ?>
                                        </span>
                                        <?php if ($assignment['status'] == 'active'): ?>
                                        <div style="font-size: 0.8rem; color: var(--success-color); margin-top: 5px;">
                                            <i class="fas fa-play-circle"></i> Currently Active
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($assignment['status'] == 'active'): ?>
                                            <a href="?update_status=<?php echo $assignment['assignment_id']; ?>&status=completed" 
                                               class="btn-action btn-update"
                                               onclick="return confirm('Mark this assignment as completed?')">
                                                <i class="fas fa-check-circle"></i> Complete
                                            </a>
                                            <a href="?update_status=<?php echo $assignment['assignment_id']; ?>&status=cancelled" 
                                               class="btn-action btn-update"
                                               onclick="return confirm('Cancel this assignment?')">
                                                <i class="fas fa-times-circle"></i> Cancel
                                            </a>
                                            <?php endif; ?>
                                            <a href="#" class="btn-action btn-edit"
                                               onclick="editAssignment(<?php echo $assignment['assignment_id']; ?>, event)">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="?remove_assignment=<?php echo $assignment['assignment_id']; ?>" 
                                               class="btn-action btn-delete"
                                               onclick="return confirm('Are you sure you want to remove this assignment? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i> Remove
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 40px; background: #f8f9fa; border-radius: 10px; margin: 20px 0;">
                        <i class="fas fa-book-open" style="font-size: 80px; color: var(--primary-color); margin-bottom: 25px; opacity: 0.5;"></i>
                        <h3 style="color: #343a40; margin-bottom: 15px; font-size: 1.8rem;">
                            <?php if (isset($_GET['status_filter']) || isset($_GET['semester_filter']) || isset($_GET['year_filter']) || isset($_GET['teacher_filter'])): ?>
                                No Matching Assignments Found
                            <?php else: ?>
                                No Course Assignments Yet
                            <?php endif; ?>
                        </h3>
                        <p style="color: #6c757d; max-width: 500px; margin: 0 auto 25px; font-size: 1.1rem;">
                            <?php if (isset($_GET['status_filter']) || isset($_GET['semester_filter']) || isset($_GET['year_filter']) || isset($_GET['teacher_filter'])): ?>
                                No assignments match your filter criteria. Try adjusting your filters or clear them to see all assignments.
                            <?php else: ?>
                                No course assignments have been made yet. Start by assigning courses to teachers.
                            <?php endif; ?>
                        </p>
                        <?php if (isset($_GET['status_filter']) || isset($_GET['semester_filter']) || isset($_GET['year_filter']) || isset($_GET['teacher_filter'])): ?>
                            <a href="course_assignment.php" class="btn-primary" style="margin-top: 20px; display: inline-flex; width: auto;">
                                <i class="fas fa-redo"></i> Clear Filters
                            </a>
                        <?php else: ?>
                            <button class="btn-primary" onclick="toggleAssignmentForm()" style="margin-top: 20px; display: inline-flex; width: auto;">
                                <i class="fas fa-plus-circle"></i> Assign First Course
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Notes Popup -->
    <div id="notesPopup" class="notes-popup"></div>
    
    <script>
        // Toggle assignment form visibility
        function toggleAssignmentForm() {
            const form = document.getElementById('assignmentForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'block') {
                form.scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        // Auto-fill credit hours when course is selected
        document.getElementById('courseSelect')?.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const creditHours = selectedOption.getAttribute('data-hours');
            if (creditHours) {
                document.getElementById('creditHours').value = creditHours;
            }
        });
        
        // Show notes in popup
        function showNotes(notes, event) {
            event.preventDefault();
            const popup = document.getElementById('notesPopup');
            popup.innerHTML = `<h4 style="margin-bottom: 10px; color: var(--primary-color);">
                                <i class="fas fa-sticky-note"></i> Assignment Notes
                               </h4>
                               <div style="max-height: 200px; overflow-y: auto; padding: 5px;">
                                ${notes.replace(/\n/g, '<br>')}
                               </div>
                               <button onclick="hideNotes()" style="margin-top: 10px; padding: 5px 10px; background: var(--primary-color); color: white; border: none; border-radius: 4px; cursor: pointer;">
                                Close
                               </button>`;
            
            // Position popup near the clicked link
            const rect = event.target.getBoundingClientRect();
            popup.style.left = (rect.left - 320) + 'px';
            popup.style.top = (rect.top + 20) + 'px';
            popup.classList.add('show');
        }
        
        function hideNotes() {
            document.getElementById('notesPopup').classList.remove('show');
        }
        
        // Close notes popup when clicking outside
        document.addEventListener('click', function(event) {
            const popup = document.getElementById('notesPopup');
            if (popup.classList.contains('show') && !popup.contains(event.target) && !event.target.closest('a[onclick*="showNotes"]')) {
                popup.classList.remove('show');
            }
        });
        
        // Edit assignment function
        function editAssignment(assignmentId, event) {
            event.preventDefault();
            alert('Edit functionality for assignment ID: ' + assignmentId + ' will be implemented soon.\n\nYou can update the status or remove the assignment using the available actions.');
            // Future implementation: window.location.href = 'edit_assignment.php?id=' + assignmentId;
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
            const requiredLabels = document.querySelectorAll('input[required], select[required], textarea[required]');
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
            
            // Auto-focus first input in form if it's visible
            const assignmentForm = document.getElementById('assignmentForm');
            if (assignmentForm && assignmentForm.style.display !== 'none') {
                const firstInput = assignmentForm.querySelector('input, select, textarea');
                if (firstInput) firstInput.focus();
            }
        });
    </script>
</body>
</html>