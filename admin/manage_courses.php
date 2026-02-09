<?php
// admin/manage_courses.php
include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle add new course
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course'])) {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $credit_hours = intval($_POST['credit_hours']);
    $department = $_POST['department'];
    $description = trim($_POST['description'] ?? '');
    
    // Validate inputs
    if (empty($course_code) || empty($course_name) || empty($department)) {
        $error_msg = "Please fill in all required fields.";
    } elseif ($credit_hours < 1 || $credit_hours > 6) {
        $error_msg = "Credit hours must be between 1 and 6.";
    } else {
        // Check if course code already exists
        $check_sql = "SELECT * FROM courses WHERE course_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $course_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_msg = "Course code already exists. Please use a different code.";
        } else {
            $insert_sql = "INSERT INTO courses (course_code, course_name, credit_hours, department, description) 
                          VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssiss", $course_code, $course_name, $credit_hours, $department, $description);
            
            if ($insert_stmt->execute()) {
                $success_msg = "Course added successfully!";
            } else {
                $error_msg = "Error adding course: " . $conn->error;
            }
        }
    }
}

// Handle edit course
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_course'])) {
    $course_id = intval($_POST['course_id']);
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $credit_hours = intval($_POST['credit_hours']);
    $department = $_POST['department'];
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'];
    
    // Check if course code already exists (excluding current course)
    $check_sql = "SELECT * FROM courses WHERE course_code = ? AND course_id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $course_code, $course_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_msg = "Course code already exists. Please use a different code.";
    } else {
        $update_sql = "UPDATE courses SET 
                      course_code = ?, 
                      course_name = ?, 
                      credit_hours = ?, 
                      department = ?, 
                      description = ?, 
                      status = ? 
                      WHERE course_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssisssi", $course_code, $course_name, $credit_hours, $department, $description, $status, $course_id);
        
        if ($update_stmt->execute()) {
            $success_msg = "Course updated successfully!";
        } else {
            $error_msg = "Error updating course: " . $conn->error;
        }
    }
}

// Handle delete course
if (isset($_GET['delete_course'])) {
    $course_id = intval($_GET['delete_course']);
    
    // Check if course has assignments
    $check_sql = "SELECT * FROM course_assignments WHERE course_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $course_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_msg = "Cannot delete course. It has existing assignments. Deactivate it instead.";
    } else {
        $delete_sql = "DELETE FROM courses WHERE course_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $course_id);
        
        if ($delete_stmt->execute()) {
            $success_msg = "Course deleted successfully!";
        } else {
            $error_msg = "Error deleting course: " . $conn->error;
        }
    }
}

// Handle toggle status
if (isset($_GET['toggle_status'])) {
    $course_id = intval($_GET['toggle_status']);
    
    // Get current status - make sure the status column exists
    $current_sql = "SHOW COLUMNS FROM courses LIKE 'status'";
    $current_result = $conn->query($current_sql);
    
    if ($current_result->num_rows > 0) {
        // Status column exists
        $current_sql = "SELECT status FROM courses WHERE course_id = ?";
        $current_stmt = $conn->prepare($current_sql);
        $current_stmt->bind_param("i", $course_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        $current = $current_result->fetch_assoc();
        
        $new_status = ($current['status'] == 'active') ? 'inactive' : 'active';
        
        $update_sql = "UPDATE courses SET status = ? WHERE course_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $course_id);
    } else {
        // Status column doesn't exist, update is_active instead
        $current_sql = "SELECT is_active FROM courses WHERE course_id = ?";
        $current_stmt = $conn->prepare($current_sql);
        $current_stmt->bind_param("i", $course_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        $current = $current_result->fetch_assoc();
        
        $new_status = ($current['is_active'] == 1) ? 0 : 1;
        
        $update_sql = "UPDATE courses SET is_active = ? WHERE course_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $new_status, $course_id);
    }
    
    if ($update_stmt->execute()) {
        $status_text = ($new_status == 1 || $new_status == 'active') ? 'active' : 'inactive';
        $success_msg = "Course status updated to " . $status_text . ".";
    } else {
        $error_msg = "Error updating course status: " . $conn->error;
    }
}

// Get all courses with search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department_filter = isset($_GET['department_filter']) ? $_GET['department_filter'] : 'all';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';

// Check if status column exists
$check_status_column = $conn->query("SHOW COLUMNS FROM courses LIKE 'status'");
$has_status_column = $check_status_column->num_rows > 0;

// Build SQL query based on available columns
if ($has_status_column) {
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM course_assignments WHERE course_id = c.course_id AND status = 'active') as active_assignments,
            (SELECT COUNT(*) FROM course_assignments WHERE course_id = c.course_id) as total_assignments
            FROM courses c WHERE 1=1";
    
    if ($status_filter != 'all') {
        $sql .= " AND c.status = ?";
    }
} else {
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM course_assignments WHERE course_id = c.course_id AND status = 'active') as active_assignments,
            (SELECT COUNT(*) FROM course_assignments WHERE course_id = c.course_id) as total_assignments
            FROM courses c WHERE 1=1";
    
    if ($status_filter != 'all') {
        if ($status_filter == 'active') {
            $sql .= " AND c.is_active = 1";
        } else {
            $sql .= " AND c.is_active = 0";
        }
    }
}

if ($search) {
    $sql .= " AND (c.course_code LIKE ? OR c.course_name LIKE ? OR c.description LIKE ?)";
}

if ($department_filter != 'all') {
    $sql .= " AND c.department = ?";
}

$sql .= " ORDER BY c.department, c.course_code";

$stmt = $conn->prepare($sql);
$params = [];
$types = '';

if ($search) {
    $search_term = "%$search%";
    $types .= 'sss';
    array_push($params, $search_term, $search_term, $search_term);
}

if ($department_filter != 'all') {
    $types .= 's';
    array_push($params, $department_filter);
}

if ($status_filter != 'all' && $has_status_column) {
    $types .= 's';
    array_push($params, $status_filter);
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$total_courses = $result->num_rows;

// Get statistics
if ($has_status_column) {
    $stats_sql = "SELECT 
        COUNT(*) as total_courses,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_courses,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_courses,
        COUNT(DISTINCT department) as total_departments
        FROM courses";
} else {
    $stats_sql = "SELECT 
        COUNT(*) as total_courses,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_courses,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_courses,
        COUNT(DISTINCT department) as total_departments
        FROM courses";
}
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get unique departments for filter
$departments_sql = "SELECT DISTINCT department FROM courses ORDER BY department";
$departments_result = $conn->query($departments_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses | Admin Panel | Woldiya University</title>
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
    --transition: all 0.3s ease;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background-color: #f5f7fb;
    color: #333;
    line-height: 1.6;
}

/* Dashboard Layout */
.dashboard-container {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, var(--primary-color) 0%, #00264d 100%);
    color: white;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
}

.sidebar-header {
    padding: 25px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header h3 {
    font-size: 1.8rem;
    margin-bottom: 25px;
    text-align: center;
    color: white;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}

.user-info i {
    font-size: 2.5rem;
    color: rgba(255, 255, 255, 0.9);
}

.user-details h4 {
    font-size: 1.1rem;
    margin-bottom: 5px;
}

.user-details p {
    font-size: 0.9rem;
    opacity: 0.8;
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
    padding: 15px 25px;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: var(--transition);
    border-left: 4px solid transparent;
}

.sidebar-menu a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: var(--secondary-color);
}

.sidebar-menu a.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-left-color: var(--secondary-color);
    font-weight: 600;
}

.sidebar-menu a i {
    margin-right: 12px;
    width: 20px;
    text-align: center;
}

.logout-link {
    color: #ff6b6b !important;
    margin-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
    padding-top: 20px !important;
}

/* Main Content */
.main-content {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eaeaea;
}

.page-header h2 {
    font-size: 2rem;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-actions {
    display: flex;
    gap: 15px;
}

/* Buttons */
.btn-primary, .btn-success, .btn-warning, .btn-danger, .btn-action {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: var(--transition);
    text-decoration: none;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: var(--secondary-color);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 102, 204, 0.3);
}

.btn-success {
    background: var(--success-color);
    color: white;
}

.btn-warning {
    background: var(--warning-color);
    color: #212529;
}

.btn-danger {
    background: var(--danger-color);
    color: white;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 0.85rem;
}

.btn-action {
    padding: 8px 12px;
    font-size: 0.85rem;
    border-radius: 6px;
}

.btn-edit {
    background: #17a2b8;
    color: white;
}

.btn-toggle {
    background: #6c757d;
    color: white;
}

.btn-delete {
    background: #dc3545;
    color: white;
}

.btn-action:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

/* Notifications */
.notification {
    padding: 15px 20px;
    margin-bottom: 25px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.notification-success {
    background: #d4edda;
    color: #155724;
    border-left: 5px solid var(--success-color);
}

.notification-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 5px solid var(--danger-color);
}

/* Cards */
.card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 30px;
    overflow: hidden;
}

.card-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-body {
    padding: 25px;
}

/* Statistics Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: var(--border-radius);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    text-align: center;
    transition: var(--transition);
    border-top: 5px solid var(--primary-color);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Forms */
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e1e5eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: var(--transition);
    background: white;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #eaeaea;
}

.text-muted {
    color: #6c757d !important;
    font-size: 0.85rem;
}

/* Filters */
.filters-container {
    background: white;
    border-radius: var(--border-radius);
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.filters-header {
    margin-bottom: 20px;
}

.filters-header h3 {
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-group {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    align-items: end;
}

.filter-input, .filter-select {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #e1e5eb;
    border-radius: 8px;
    font-size: 1rem;
}

.search-button {
    width: 100%;
    justify-content: center;
}

/* Tables */
.table-container {
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--box-shadow);
}

.table-header {
    padding: 20px 25px;
    background: #f8f9fa;
    border-bottom: 1px solid #eaeaea;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h3 {
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-wrapper {
    overflow-x: auto;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
}

.users-table thead {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.users-table th {
    padding: 18px 20px;
    text-align: left;
    font-weight: 600;
    color: var(--dark-color);
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
}

.users-table td {
    padding: 18px 20px;
    border-bottom: 1px solid #eaeaea;
    vertical-align: top;
}

.users-table tbody tr {
    transition: var(--transition);
}

.users-table tbody tr:hover {
    background-color: #f8fafc;
}

/* Badges */
.department-badge {
    display: inline-block;
    padding: 5px 12px;
    background: #e3f2fd;
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.assignment-count {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
}

.assignment-active {
    background: #d4edda;
    color: #155724;
}

.assignment-total {
    background: #e2e3e5;
    color: #383d41;
}

/* Course Description */
.course-description {
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.9rem;
    color: #666;
    margin-top: 5px;
}

.course-description.expanded {
    white-space: normal;
    overflow: visible;
    text-overflow: unset;
}

.view-more {
    color: var(--primary-color);
    cursor: pointer;
    font-size: 0.85rem;
    margin-top: 5px;
    display: inline-block;
    text-decoration: underline;
    font-weight: 600;
}

.view-more:hover {
    color: var(--secondary-color);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 1.8rem;
    margin-bottom: 15px;
    color: #495057;
}

.empty-state p {
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto 30px;
}

/* Modal */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.modal {
    background: white;
    border-radius: var(--border-radius);
    max-width: 500px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    padding: 20px 25px;
    background: var(--danger-color);
    color: white;
}

.modal-header h3 {
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-body {
    padding: 30px;
    text-align: center;
}

.modal-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 25px;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .sidebar {
        width: 250px;
    }
}

@media (max-width: 992px) {
    .dashboard-container {
        flex-direction: column;
    }
    
    .sidebar {
        width: 100%;
        height: auto;
        position: static;
    }
    
    .sidebar-menu {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        padding: 10px;
    }
    
    .sidebar-menu li {
        margin: 5px;
    }
    
    .sidebar-menu a {
        padding: 10px 15px;
        border-left: none;
        border-bottom: 3px solid transparent;
    }
    
    .sidebar-menu a.active {
        border-left: none;
        border-bottom: 3px solid var(--secondary-color);
    }
    
    .user-info {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 20px 15px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .filter-group {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .users-table th,
    .users-table td {
        padding: 12px 15px;
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .modal-actions {
        flex-direction: column;
    }
}
            --primary-color: #004080;
            --secondary-color: #0066cc;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --border-radius: 12px;
            --box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        /* ... All the CSS styles from previous code ... */
        /* (Keep all the CSS styles you had in the previous version) */
        
        /* Just adding a few extra styles for better display */
        .course-description {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .course-description.expanded {
            white-space: normal;
            overflow: visible;
        }
        
        .view-more {
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.85rem;
            margin-top: 5px;
            display: inline-block;
        }
        
        .course-actions {
            min-width: 200px;
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
                <li><a href="manage_courses.php" class="active"><i class="fas fa-graduation-cap"></i> Manage Courses</a></li>
                <li><a href="course_assignment.php"><i class="fas fa-book"></i> Course Assignment</a></li>
                <li><a href="payment_rate.php"><i class="fas fa-money-check-alt"></i> Payment Rates</a></li>
                <li><a href="system_logs.php"><i class="fas fa-clipboard-list"></i> System Logs</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-graduation-cap"></i> Manage Courses</h2>
                <div class="header-actions">
                    <button class="btn-primary" onclick="toggleCourseForm()">
                        <i class="fas fa-plus-circle"></i> Add New Course
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
                    <i class="fas fa-book"></i>
                    <div class="stat-number"><?php echo $stats['total_courses'] ?? 0; ?></div>
                    <div class="stat-label">Total Courses</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-number"><?php echo $stats['active_courses'] ?? 0; ?></div>
                    <div class="stat-label">Active Courses</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-times-circle"></i>
                    <div class="stat-number"><?php echo $stats['inactive_courses'] ?? 0; ?></div>
                    <div class="stat-label">Inactive Courses</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-building"></i>
                    <div class="stat-number"><?php echo $stats['total_departments'] ?? 0; ?></div>
                    <div class="stat-label">Departments</div>
                </div>
            </div>
            
            <!-- Add Course Form (Initially Hidden) -->
            <div class="card" id="courseForm" style="display: none;">
                <div class="card-header">
                    <h3><i class="fas fa-plus-circle"></i> Add New Course</h3>
                    <button class="btn-warning btn-sm" onclick="toggleCourseForm()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="addCourseForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-code"></i> Course Code *</label>
                                <input type="text" name="course_code" required placeholder="e.g., CS101" pattern="[A-Za-z0-9]+" title="Only letters and numbers allowed">
                                <small class="text-muted" style="display: block; margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> Must be unique (e.g., CS101, MATH201)
                                </small>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-book"></i> Course Name *</label>
                                <input type="text" name="course_name" required placeholder="e.g., Introduction to Computer Science">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Credit Hours *</label>
                                <input type="number" name="credit_hours" min="1" max="6" value="3" required>
                                <small class="text-muted" style="display: block; margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> Typically 1-6 credit hours
                                </small>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-building"></i> Department *</label>
                                <select name="department" required>
                                    <option value="">Select Department</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="Mathematics">Mathematics</option>
                                    <option value="Physics">Physics</option>
                                    <option value="Chemistry">Chemistry</option>
                                    <option value="Biology">Biology</option>
                                    <option value="Engineering">Engineering</option>
                                    <option value="Business">Business</option>
                                    <option value="Economics">Economics</option>
                                    <option value="English">English</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label><i class="fas fa-align-left"></i> Course Description</label>
                                <textarea name="description" rows="4" placeholder="Enter course description..."></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="add_course" class="btn-success">
                                <i class="fas fa-save"></i> Add Course
                            </button>
                            <button type="reset" class="btn-warning">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Edit Course Form (Initially Hidden) -->
            <div class="card" id="editCourseForm" style="display: none;">
                <div class="card-header">
                    <h3><i class="fas fa-edit"></i> Edit Course</h3>
                    <button class="btn-warning btn-sm" onclick="closeEditForm()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="editCourseFormData">
                        <input type="hidden" name="course_id" id="edit_course_id">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-code"></i> Course Code *</label>
                                <input type="text" name="course_code" id="edit_course_code" required pattern="[A-Za-z0-9]+">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-book"></i> Course Name *</label>
                                <input type="text" name="course_name" id="edit_course_name" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Credit Hours *</label>
                                <input type="number" name="credit_hours" id="edit_credit_hours" min="1" max="6" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-building"></i> Department *</label>
                                <select name="department" id="edit_department" required>
                                    <option value="">Select Department</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="Mathematics">Mathematics</option>
                                    <option value="Physics">Physics</option>
                                    <option value="Chemistry">Chemistry</option>
                                    <option value="Biology">Biology</option>
                                    <option value="Engineering">Engineering</option>
                                    <option value="Business">Business</option>
                                    <option value="Economics">Economics</option>
                                    <option value="English">English</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-toggle-on"></i> Status *</label>
                                <select name="status" id="edit_status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label><i class="fas fa-align-left"></i> Course Description</label>
                                <textarea name="description" id="edit_description" rows="4"></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="edit_course" class="btn-success">
                                <i class="fas fa-save"></i> Update Course
                            </button>
                            <button type="button" class="btn-warning" onclick="closeEditForm()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="filters-container">
                <div class="filters-header">
                    <h3><i class="fas fa-filter"></i> Filter Courses</h3>
                </div>
                <form method="GET" action="">
                    <div class="filter-group">
                        <div class="filter-item">
                            <label><i class="fas fa-search"></i> Search</label>
                            <input type="text" class="filter-input" name="search" placeholder="Search by course code, name or description" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-item">
                            <label><i class="fas fa-building"></i> Department</label>
                            <select class="filter-select" name="department_filter">
                                <option value="all" <?php echo $department_filter == 'all' ? 'selected' : ''; ?>>All Departments</option>
                                <?php 
                                // Reset pointer and fetch again
                                $departments_result->data_seek(0);
                                while ($dept = $departments_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                                <?php endwhile; ?>
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
                            <?php if ($search || $department_filter != 'all' || $status_filter != 'all'): ?>
                            <a href="manage_courses.php" class="btn-warning" style="margin-top: 10px; display: inline-block;">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Courses Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-list-alt"></i> All Courses</h3>
                    <div class="user-count">
                        <span class="text-muted">
                            <i class="fas fa-filter"></i> 
                            Showing: <?php echo $total_courses; ?> course(s)
                        </span>
                    </div>
                </div>
                
                <?php if ($total_courses > 0): ?>
                    <div class="table-wrapper">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-code"></i> Course Code</th>
                                    <th><i class="fas fa-book"></i> Course Name</th>
                                    <th><i class="fas fa-building"></i> Department</th>
                                    <th><i class="fas fa-clock"></i> Credit Hours</th>
                                    <th><i class="fas fa-tasks"></i> Assignments</th>
                                    <th><i class="fas fa-toggle-on"></i> Status</th>
                                    <th><i class="fas fa-calendar-alt"></i> Created</th>
                                    <th><i class="fas fa-cog"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($course = $result->fetch_assoc()): 
                                    // Determine course status based on available column
                                    if ($has_status_column) {
                                        $course_status = $course['status'];
                                    } else {
                                        $course_status = $course['is_active'] == 1 ? 'active' : 'inactive';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary-color);"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                        <?php if (!empty($course['description'])): ?>
                                        <div class="course-description" id="desc-<?php echo $course['course_id']; ?>">
                                            <?php echo htmlspecialchars(substr($course['description'], 0, 50)); ?>
                                            <?php if (strlen($course['description']) > 50): ?>...<?php endif; ?>
                                        </div>
                                        <?php if (strlen($course['description']) > 50): ?>
                                        <span class="view-more" onclick="toggleDescription(<?php echo $course['course_id']; ?>)">View more</span>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="department-badge"><?php echo htmlspecialchars($course['department']); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary-color);">
                                            <?php echo $course['credit_hours']; ?> hrs
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                            <span class="assignment-count assignment-active">
                                                <i class="fas fa-check-circle"></i> <?php echo $course['active_assignments']; ?> active
                                            </span>
                                            <span class="assignment-count assignment-total">
                                                <i class="fas fa-list"></i> <?php echo $course['total_assignments']; ?> total
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $course_status; ?>">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                            <?php echo ucfirst($course_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo date('M d, Y', strtotime($course['created_at'])); ?></div>
                                    </td>
                                    <td class="course-actions">
                                        <div class="action-buttons">
                                            <button class="btn-action btn-edit" onclick="openEditForm(
                                                <?php echo $course['course_id']; ?>,
                                                '<?php echo addslashes($course['course_code']); ?>',
                                                '<?php echo addslashes($course['course_name']); ?>',
                                                <?php echo $course['credit_hours']; ?>,
                                                '<?php echo addslashes($course['department']); ?>',
                                                '<?php echo addslashes($course['description'] ?? ''); ?>',
                                                '<?php echo $course_status; ?>'
                                            )">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="?toggle_status=<?php echo $course['course_id']; ?>" 
                                               class="btn-action btn-toggle"
                                               onclick="return confirm('Are you sure you want to <?php echo $course_status == 'active' ? 'deactivate' : 'activate'; ?> this course?')">
                                                <i class="fas fa-toggle-<?php echo $course_status == 'active' ? 'off' : 'on'; ?>"></i>
                                                <?php echo $course_status == 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </a>
                                            <a href="#" class="btn-action btn-delete"
                                               onclick="return confirmDelete(<?php echo $course['course_id']; ?>, '<?php echo addslashes($course['course_code']); ?>', <?php echo $course['total_assignments']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>No Courses Found</h3>
                        <p>No courses match your search criteria. Try adjusting your filters or add new courses.</p>
                        <button class="btn-primary" onclick="toggleCourseForm()" style="margin-top: 20px; display: inline-flex; width: auto;">
                            <i class="fas fa-plus-circle"></i> Add First Course
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
                <h4 style="margin-bottom: 15px; color: var(--dark-color);">Are you sure you want to delete this course?</h4>
                <p id="deleteMessage" style="color: #666; margin-bottom: 10px;"></p>
                <div id="assignmentWarning" style="display: none; color: var(--warning-color); font-weight: 600; margin: 15px 0;">
                    <i class="fas fa-exclamation-circle"></i> 
                    <span id="assignmentCount"></span> active assignments exist. Deleting will remove all assignments!
                </div>
                <p style="color: var(--danger-color); font-weight: 600;">
                    <i class="fas fa-exclamation-circle"></i> This action cannot be undone!
                </p>
                <div class="modal-actions">
                    <a id="confirmDeleteBtn" class="btn-danger" href="#">
                        <i class="fas fa-trash"></i> Delete Course
                    </a>
                    <button class="btn-warning" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle add course form visibility
        function toggleCourseForm() {
            const form = document.getElementById('courseForm');
            const editForm = document.getElementById('editCourseForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            editForm.style.display = 'none';
            if (form.style.display === 'block') {
                form.scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        // Open edit form with course data
        function openEditForm(id, code, name, hours, department, description, status) {
            document.getElementById('edit_course_id').value = id;
            document.getElementById('edit_course_code').value = code;
            document.getElementById('edit_course_name').value = name;
            document.getElementById('edit_credit_hours').value = hours;
            document.getElementById('edit_department').value = department;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_status').value = status;
            
            const addForm = document.getElementById('courseForm');
            const editForm = document.getElementById('editCourseForm');
            addForm.style.display = 'none';
            editForm.style.display = 'block';
            editForm.scrollIntoView({ behavior: 'smooth' });
        }
        
        // Close edit form
        function closeEditForm() {
            document.getElementById('editCourseForm').style.display = 'none';
        }
        
        // Toggle description view
        function toggleDescription(courseId) {
            const descElement = document.getElementById('desc-' + courseId);
            if (descElement.classList.contains('expanded')) {
                descElement.classList.remove('expanded');
                descElement.nextElementSibling.textContent = 'View more';
            } else {
                descElement.classList.add('expanded');
                descElement.nextElementSibling.textContent = 'View less';
            }
        }
        
        // Delete confirmation with modal
        function confirmDelete(courseId, courseCode, assignmentCount) {
            event.preventDefault();
            
            const modal = document.getElementById('deleteModal');
            const deleteMessage = document.getElementById('deleteMessage');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const assignmentWarning = document.getElementById('assignmentWarning');
            const assignmentCountSpan = document.getElementById('assignmentCount');
            
            deleteMessage.textContent = `You are about to delete course: ${courseCode} (ID: ${courseId})`;
            
            if (assignmentCount > 0) {
                assignmentWarning.style.display = 'block';
                assignmentCountSpan.textContent = `${assignmentCount}`;
            } else {
                assignmentWarning.style.display = 'none';
            }
            
            confirmBtn.href = `?delete_course=${courseId}`;
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
            
            // Show form if there's an error
            <?php if ($error_msg && isset($_POST['add_course'])): ?>
                toggleCourseForm();
            <?php endif; ?>
            
            <?php if ($error_msg && isset($_POST['edit_course'])): ?>
                document.getElementById('editCourseForm').style.display = 'block';
            <?php endif; ?>
        });
        
        // Auto-capitalize course code
        document.querySelector('input[name="course_code"]')?.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        document.getElementById('edit_course_code')?.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>