<?php
include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'admin') {
    die("Access Denied");
}

// Set timezone
date_default_timezone_set('Africa/Addis_Ababa');

// Initialize variables
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';
$date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : null;
$department_filter = isset($_GET['department_filter']) ? $_GET['department_filter'] : 'all';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

// Initialize report data
$report_data = [];
$report_stats = [];
$departments_list = [];

// Test database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

try {
    // Get distinct departments for filter
    $departments_sql = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department";
    $departments_result = $conn->query($departments_sql);
    if ($departments_result) {
        while ($row = $departments_result->fetch_assoc()) {
            $departments_list[] = $row;
        }
        $departments_result->data_seek(0);
    }
    
    // Fetch data based on report type
    switch ($report_type) {
        case 'overview':
            // Get total teachers (no date filter needed)
            $teacher_sql = "SELECT COUNT(*) as total_teachers FROM teachers";
            $result = $conn->query($teacher_sql);
            if ($result) {
                $row = $result->fetch_assoc();
                $report_stats['total_teachers'] = $row['total_teachers'] ?? 0;
            } else {
                $report_stats['total_teachers'] = 0;
            }
            
            // Get request statistics - FIXED: Changed credit_hours to credit_hour
            $request_sql = "SELECT 
                COUNT(*) as total_requests,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) as approved_requests,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_requests,
                COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected_requests,
                COALESCE(SUM(credit_hour), 0) as total_credit_hours
            FROM overload_requests";
            
            if ($date_from && $date_to) {
                $request_sql .= " WHERE DATE(submitted_at) BETWEEN ? AND ?";
                $stmt = $conn->prepare($request_sql);
                if ($stmt) {
                    $stmt->bind_param("ss", $date_from, $date_to);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();
                        $report_stats['total_requests'] = $row['total_requests'] ?? 0;
                        $report_stats['approved_requests'] = $row['approved_requests'] ?? 0;
                        $report_stats['pending_requests'] = $row['pending_requests'] ?? 0;
                        $report_stats['rejected_requests'] = $row['rejected_requests'] ?? 0;
                        $report_stats['total_credit_hours'] = $row['total_credit_hours'] ?? 0;
                    }
                    $stmt->close();
                }
            } else {
                $result = $conn->query($request_sql);
                if ($result) {
                    $row = $result->fetch_assoc();
                    $report_stats['total_requests'] = $row['total_requests'] ?? 0;
                    $report_stats['approved_requests'] = $row['approved_requests'] ?? 0;
                    $report_stats['pending_requests'] = $row['pending_requests'] ?? 0;
                    $report_stats['rejected_requests'] = $row['rejected_requests'] ?? 0;
                    $report_stats['total_credit_hours'] = $row['total_credit_hours'] ?? 0;
                }
            }
            
            // Get payment total - FIXED: Changed created_at to submitted_at
            $payment_sql = "SELECT COALESCE(SUM(p.amount), 0) as total_payments 
                          FROM payments p
                          JOIN overload_requests o ON p.request_id = o.request_id
                          WHERE 1=1";
            
            if ($date_from && $date_to) {
                $payment_sql .= " AND DATE(p.payment_date) BETWEEN ? AND ?";
                $stmt = $conn->prepare($payment_sql);
                if ($stmt) {
                    $stmt->bind_param("ss", $date_from, $date_to);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();
                        $report_stats['total_payments'] = $row['total_payments'] ?? 0;
                    }
                    $stmt->close();
                }
            } else {
                $result = $conn->query($payment_sql);
                if ($result) {
                    $row = $result->fetch_assoc();
                    $report_stats['total_payments'] = $row['total_payments'] ?? 0;
                }
            }
            
            // Monthly trend data - FIXED: Changed credit_hours to credit_hour, created_at to submitted_at
            $trend_sql = "SELECT 
                DATE_FORMAT(submitted_at, '%Y-%m') as month,
                COUNT(*) as request_count,
                COALESCE(SUM(credit_hour), 0) as total_credits
            FROM overload_requests 
            WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
            ORDER BY month ASC";
            
            $trend_result = $conn->query($trend_sql);
            $report_data['trend'] = [];
            if ($trend_result) {
                while ($row = $trend_result->fetch_assoc()) {
                    $report_data['trend'][] = $row;
                }
            }
            break;
            
        case 'payments':
            // Payments query - FIXED: Updated to match actual schema from approval_history.php
            $payment_sql = "SELECT 
                p.payment_id,
                p.request_id,
                u.full_name,
                u.department,
                o.course_name,
                o.credit_hour,
                p.amount,
                p.payment_date,
                p.payment_status,
                u2.full_name as processed_by_name
            FROM payments p
            JOIN overload_requests o ON p.request_id = o.request_id
            JOIN teachers t ON o.teacher_id = t.teacher_id
            JOIN users u ON t.user_id = u.user_id
            LEFT JOIN users u2 ON p.processed_by = u2.user_id
            WHERE 1=1";
            
            $params = [];
            $types = "";
            
            if ($date_from && $date_to) {
                $payment_sql .= " AND DATE(p.payment_date) BETWEEN ? AND ?";
                $params[] = $date_from;
                $params[] = $date_to;
                $types .= "ss";
            }
            
            if ($department_filter != 'all') {
                $payment_sql .= " AND u.department = ?";
                $params[] = $department_filter;
                $types .= "s";
            }
            
            if ($status_filter != 'all') {
                $payment_sql .= " AND p.payment_status = ?";
                $params[] = $status_filter;
                $types .= "s";
            }
            
            $payment_sql .= " ORDER BY p.payment_date DESC";
            
            $stmt = $conn->prepare($payment_sql);
            if ($stmt) {
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $report_data['payments'] = [];
                    while ($row = $result->fetch_assoc()) {
                        $report_data['payments'][] = $row;
                    }
                }
                $stmt->close();
            }
            break;
            
        case 'teachers':
            // Teachers query - FIXED: Updated to match actual schema
            $teacher_sql = "SELECT 
                t.teacher_id,
                u.full_name,
                u.department,
                COUNT(o.request_id) as total_requests,
                COALESCE(SUM(CASE WHEN o.status = 'approved' THEN 1 ELSE 0 END), 0) as approved_requests,
                COALESCE(SUM(CASE WHEN o.status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected_requests,
                COALESCE(SUM(o.credit_hour), 0) as total_credits,
                COALESCE(SUM(p.amount), 0) as total_payments
            FROM teachers t
            JOIN users u ON t.user_id = u.user_id
            LEFT JOIN overload_requests o ON t.teacher_id = o.teacher_id";
            
            // Add date condition to the LEFT JOIN
            if ($date_from && $date_to) {
                $teacher_sql .= " AND DATE(o.submitted_at) BETWEEN ? AND ?";
            }
            
            $teacher_sql .= " LEFT JOIN payments p ON o.request_id = p.request_id
                WHERE 1=1";
            
            if ($department_filter != 'all') {
                $teacher_sql .= " AND u.department = ?";
            }
            
            $teacher_sql .= " GROUP BY t.teacher_id, u.full_name, u.department
                ORDER BY total_payments DESC, total_credits DESC";
            
            $stmt = $conn->prepare($teacher_sql);
            if ($stmt) {
                $param_count = 0;
                $bind_params = [];
                $types = "";
                
                if ($date_from && $date_to) {
                    $types .= "ss";
                    $bind_params[] = $date_from;
                    $bind_params[] = $date_to;
                    $param_count += 2;
                }
                
                if ($department_filter != 'all') {
                    $types .= "s";
                    $bind_params[] = $department_filter;
                    $param_count += 1;
                }
                
                if ($param_count > 0) {
                    $stmt->bind_param($types, ...$bind_params);
                }
                
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $report_data['teachers'] = [];
                    while ($row = $result->fetch_assoc()) {
                        $report_data['teachers'][] = $row;
                    }
                }
                $stmt->close();
            }
            break;
            
        case 'departments':
            // Departments query - COMPLETELY FIXED
            // First, let's get all departments from users table
            $all_depts_sql = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department";
            $all_depts_result = $conn->query($all_depts_sql);
            $all_departments = [];
            if ($all_depts_result) {
                while ($row = $all_depts_result->fetch_assoc()) {
                    $all_departments[] = $row['department'];
                }
            }
            
            $report_data['departments'] = [];
            
            // For each department, calculate statistics - FIXED: Updated column names
            foreach ($all_departments as $dept_name) {
                $dept_stats_sql = "SELECT 
                    ? as department,
                    COUNT(DISTINCT t.teacher_id) as teacher_count,
                    COUNT(o.request_id) as request_count,
                    COALESCE(SUM(CASE WHEN o.status = 'approved' THEN 1 ELSE 0 END), 0) as approved_count,
                    COALESCE(SUM(CASE WHEN o.status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected_count,
                    COALESCE(SUM(o.credit_hour), 0) as total_credits,
                    COALESCE(SUM(p.amount), 0) as total_payments
                FROM users u
                JOIN teachers t ON u.user_id = t.user_id
                LEFT JOIN overload_requests o ON t.teacher_id = o.teacher_id
                LEFT JOIN payments p ON o.request_id = p.request_id
                WHERE u.department = ?";
                
                // Add date filter if provided
                if ($date_from && $date_to) {
                    $dept_stats_sql .= " AND DATE(o.submitted_at) BETWEEN ? AND ?";
                }
                
                $stmt = $conn->prepare($dept_stats_sql);
                if ($stmt) {
                    if ($date_from && $date_to) {
                        $stmt->bind_param("ssss", $dept_name, $dept_name, $date_from, $date_to);
                    } else {
                        $stmt->bind_param("ss", $dept_name, $dept_name);
                    }
                    
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            // Add department even if no requests to show all departments
                            $report_data['departments'][] = $row;
                        }
                    }
                    $stmt->close();
                }
            }
            
            // Sort by total payments
            usort($report_data['departments'], function($a, $b) {
                return $b['total_payments'] <=> $a['total_payments'];
            });
            
            break;
    }
    
} catch (Exception $e) {
    error_log("Report Error: " . $e->getMessage());
    $error_msg = "Error generating report: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Admin Panel | Woldiya University</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Enhanced CSS Styles for Reports Page */
        :root {
            --primary: #004080;
            --primary-light: #2d6bb5;
            --secondary: #0066cc;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --dark: #343a40;
            --light: #f8f9fa;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border: #dee2e6;
            --shadow: rgba(0, 0, 0, 0.1);
            --shadow-hover: rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        /* Dashboard Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--primary) 0%, #003366 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(0, 0, 0, 0.1);
        }

        .sidebar-header h3 {
            font-size: 1.4rem;
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info i {
            font-size: 2.8rem;
            background: rgba(255, 255, 255, 0.15);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .user-details h4 {
            margin: 0 0 5px 0;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .user-details p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
            padding: 0 15px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
            font-size: 1rem;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--warning);
            transform: translateX(5px);
        }

        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: var(--warning);
            font-weight: 600;
        }

        .sidebar-menu a i {
            width: 20px;
            text-align: center;
            font-size: 1.2rem;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            max-width: calc(100% - 260px);
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px var(--shadow);
            border-left: 5px solid var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h2 {
            color: var(--primary);
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 0;
        }

        .page-header h2 i {
            color: var(--primary-light);
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        /* Button Styles */
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 10px rgba(0, 64, 128, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 64, 128, 0.3);
        }

        .btn-secondary {
            background: var(--gray);
            color: white;
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.2);
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2);
        }

        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(40, 167, 69, 0.3);
        }

        /* Report Tabs */
        .report-tabs {
            display: flex;
            background: white;
            border-radius: 12px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 4px 12px var(--shadow);
        }

        .report-tab {
            flex: 1;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 4px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .report-tab::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 64, 128, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .report-tab:hover::before {
            left: 100%;
        }

        .report-tab:hover {
            background: #f8fafc;
            transform: translateY(-2px);
        }

        .report-tab.active {
            background: #f8fafc;
            border-bottom-color: var(--primary);
        }

        .report-tab i {
            font-size: 2.2rem;
            margin-bottom: 15px;
            color: var(--primary-light);
        }

        .report-tab.active i {
            color: var(--primary);
        }

        .report-tab h4 {
            font-size: 1.2rem;
            margin: 0 0 8px 0;
            color: var(--dark);
            font-weight: 600;
        }

        .report-tab p {
            font-size: 0.9rem;
            color: var(--gray);
            margin: 0;
        }

        /* Filter Section */
        .filters-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px var(--shadow);
            border: 1px solid var(--light-gray);
        }

        .filters-container h3 {
            color: var(--primary);
            font-size: 1.4rem;
            margin-bottom: 25px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
        }

        .filter-item label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-select, .filter-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: var(--transition);
            background: white;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(0, 64, 128, 0.1);
        }

        .filter-item small {
            display: block;
            margin-top: 6px;
            color: var(--gray);
            font-size: 0.85rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 4px 12px var(--shadow);
            border-top: 5px solid var(--primary);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px var(--shadow-hover);
        }

        .stat-card.success { border-color: var(--success); }
        .stat-card.warning { border-color: var(--warning); }
        .stat-card.info { border-color: var(--info); }
        .stat-card.danger { border-color: var(--danger); }

        .stat-icon {
            font-size: 2.8rem;
            margin-bottom: 20px;
            color: var(--primary-light);
        }

        .stat-card.success .stat-icon { color: var(--success); }
        .stat-card.warning .stat-icon { color: var(--warning); }
        .stat-card.info .stat-icon { color: var(--info); }
        .stat-card.danger .stat-icon { color: var(--danger); }

        .stat-value { 
            font-size: 2.5rem; 
            font-weight: 800; 
            margin: 10px 0;
            color: var(--primary);
            line-height: 1;
        }
        
        .stat-card.success .stat-value { color: var(--success); }
        .stat-card.warning .stat-value { color: var(--warning); }
        .stat-card.info .stat-value { color: var(--info); }
        .stat-card.danger .stat-value { color: var(--danger); }
        
        .stat-label { 
            color: var(--gray); 
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Tables */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px var(--shadow);
            margin-bottom: 30px;
            border: 1px solid var(--light-gray);
        }

        .table-container h3 {
            color: var(--primary);
            font-size: 1.4rem;
            margin-bottom: 25px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .report-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 10px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .report-table thead {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .report-table th {
            color: white;
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border: none;
        }

        .report-table th:first-child {
            border-top-left-radius: 8px;
        }

        .report-table th:last-child {
            border-top-right-radius: 8px;
        }

        .report-table td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--light-gray);
            vertical-align: middle;
            color: var(--dark);
        }

        .report-table tr:last-child td {
            border-bottom: none;
        }

        .report-table tbody tr {
            transition: var(--transition);
        }

        .report-table tbody tr:hover { 
            background: #f8fafc; 
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-paid {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #b1dfbb;
        }

        .status-pending {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffdf7e;
        }

        /* No Data State */
        .no-data {
            text-align: center;
            padding: 60px 40px;
            color: var(--gray);
        }

        .no-data i {
            font-size: 4rem;
            margin-bottom: 25px;
            opacity: 0.3;
            color: var(--gray);
        }

        .no-data h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: var(--dark);
            font-weight: 600;
        }

        .no-data p {
            font-size: 1.1rem;
            max-width: 500px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }

        /* Alerts and Messages */
        .alert {
            padding: 18px 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 5px solid;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }

        .alert-success {
            background: #d4edda;
            border-left-color: var(--success);
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border-left-color: var(--danger);
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            border-left-color: var(--warning);
            color: #856404;
        }

        .alert-info {
            background: #d1ecf1;
            border-left-color: var(--info);
            color: #0c5460;
        }

        .date-range-info {
            background: #e7f3fe;
            border-left: 5px solid var(--primary-light);
            padding: 18px 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .date-range-info a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .date-range-info a:hover {
            color: var(--secondary);
            gap: 12px;
        }

        /* Print Styles */
        @media print {
            .sidebar, .header-actions, .filters-container, .btn {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                max-width: 100% !important;
                padding: 20px !important;
            }
            
            .page-header {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            
            .table-container {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            
            .stat-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                page-break-inside: avoid;
            }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-content {
                padding: 25px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h3, .user-details, .sidebar-menu a span {
                display: none;
            }
            
            .user-info {
                justify-content: center;
            }
            
            .user-info i {
                font-size: 2.2rem;
                width: 50px;
                height: 50px;
            }
            
            .main-content {
                margin-left: 80px;
                max-width: calc(100% - 80px);
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar-menu a i {
                font-size: 1.4rem;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
                padding: 20px;
            }
            
            .header-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
            }
            
            .report-tabs {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-group {
                grid-template-columns: 1fr;
            }
            
            .report-table {
                display: block;
                overflow-x: auto;
            }
            
            .date-range-info {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-value {
                font-size: 2rem;
            }
        }

        /* Animation for stats */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card, .table-container, .filters-container {
            animation: fadeInUp 0.5s ease forwards;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
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
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="manage_users.php"><i class="fas fa-users-cog"></i> <span>Manage Users</span></a></li>
                <li><a href="payment_rate.php"><i class="fas fa-money-check-alt"></i> <span>Payment Rates</span></a></li>
                <li><a href="system_logs.php"><i class="fas fa-clipboard-list"></i> <span>System Logs</span></a></li>
                <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
                <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <a href="reports.php?report_type=<?php echo $report_type; ?>" class="btn btn-secondary">
                        <i class="fas fa-history"></i> All Time Data
                    </a>
                </div>
            </div>
            
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Error:</strong> <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Show current date range -->
            <?php if ($date_from && $date_to): ?>
                <div class="date-range-info">
                    <div>
                        <i class="fas fa-calendar-alt"></i>
                        Showing data from <strong><?php echo date('F d, Y', strtotime($date_from)); ?></strong> 
                        to <strong><?php echo date('F d, Y', strtotime($date_to)); ?></strong>
                    </div>
                    <a href="reports.php?report_type=<?php echo $report_type; ?>">
                        <i class="fas fa-times"></i> Clear date filter
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Showing All Time Data</strong>
                        <p style="margin: 5px 0 0 0;">No date filter applied. Showing all records from the system.</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Report Tabs -->
            <div class="report-tabs">
                <div class="report-tab <?php echo $report_type == 'overview' ? 'active' : ''; ?>" 
                     onclick="changeReportType('overview')">
                    <i class="fas fa-tachometer-alt"></i>
                    <h4>Overview</h4>
                    <p>System Summary</p>
                </div>
                
                <div class="report-tab <?php echo $report_type == 'payments' ? 'active' : ''; ?>" 
                     onclick="changeReportType('payments')">
                    <i class="fas fa-money-bill-wave"></i>
                    <h4>Payments</h4>
                    <p>Payment Details</p>
                </div>
                
                <div class="report-tab <?php echo $report_type == 'teachers' ? 'active' : ''; ?>" 
                     onclick="changeReportType('teachers')">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h4>Teachers</h4>
                    <p>Performance Report</p>
                </div>
                
                <div class="report-tab <?php echo $report_type == 'departments' ? 'active' : ''; ?>" 
                     onclick="changeReportType('departments')">
                    <i class="fas fa-building"></i>
                    <h4>Departments</h4>
                    <p>Department Analysis</p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-container">
                <h3><i class="fas fa-filter"></i> Report Filters</h3>
                <form method="GET" action="">
                    <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
                    
                    <div class="filter-group">
                        <div class="filter-item">
                            <label><i class="fas fa-calendar-alt"></i> Date From</label>
                            <input type="date" class="filter-input" name="date_from" 
                                   value="<?php echo $date_from ? htmlspecialchars($date_from) : ''; ?>">
                            <small>Leave empty for all data</small>
                        </div>
                        
                        <div class="filter-item">
                            <label><i class="fas fa-calendar-check"></i> Date To</label>
                            <input type="date" class="filter-input" name="date_to" 
                                   value="<?php echo $date_to ? htmlspecialchars($date_to) : ''; ?>">
                            <small>Leave empty for all data</small>
                        </div>
                        
                        <?php if ($report_type == 'payments' || $report_type == 'teachers'): ?>
                        <div class="filter-item">
                            <label><i class="fas fa-building"></i> Department</label>
                            <select class="filter-select" name="department_filter">
                                <option value="all">All Departments</option>
                                <?php 
                                foreach ($departments_list as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                        <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($report_type == 'payments'): ?>
                        <div class="filter-item">
                            <label><i class="fas fa-check-circle"></i> Payment Status</label>
                            <select class="filter-select" name="status_filter">
                                <option value="all">All Status</option>
                                <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="filter-item">
                            <button type="submit" class="btn btn-primary" style="margin-top: 28px;">
                                <i class="fas fa-sync-alt"></i> Apply Filters
                            </button>
                            <?php if ($date_from || $date_to || $department_filter != 'all' || $status_filter != 'all'): ?>
                            <a href="reports.php?report_type=<?php echo $report_type; ?>" class="btn btn-secondary" style="margin-top: 15px; display: block; text-align: center;">
                                <i class="fas fa-times"></i> Clear All Filters
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Report Content -->
            <?php switch ($report_type): 
                case 'overview': ?>
                    
                    <!-- Overview Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value"><?php echo $report_stats['total_teachers'] ?? 0; ?></div>
                            <div class="stat-label">Active Teachers</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-value"><?php echo $report_stats['total_requests'] ?? 0; ?></div>
                            <div class="stat-label">Total Requests</div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-value"><?php echo $report_stats['approved_requests'] ?? 0; ?></div>
                            <div class="stat-label">Approved</div>
                        </div>
                        
                        <div class="stat-card warning">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-value"><?php echo $report_stats['pending_requests'] ?? 0; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        
                        <div class="stat-card danger">
                            <div class="stat-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="stat-value"><?php echo $report_stats['rejected_requests'] ?? 0; ?></div>
                            <div class="stat-label">Rejected</div>
                        </div>
                        
                        <div class="stat-card info">
                            <div class="stat-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="stat-value"><?php echo $report_stats['total_credit_hours'] ?? 0; ?></div>
                            <div class="stat-label">Credit Hours</div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-value">ETB <?php echo number_format($report_stats['total_payments'] ?? 0, 2); ?></div>
                            <div class="stat-label">Total Payments</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($report_data['trend'])): ?>
                    <div class="table-container">
                        <h3><i class="fas fa-chart-line"></i> Monthly Trend (Last 6 Months)</h3>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Requests</th>
                                    <th>Credit Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['trend'] as $trend): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></td>
                                    <td><strong><?php echo $trend['request_count']; ?></strong></td>
                                    <td><strong><?php echo $trend['total_credits']; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <?php break; ?>
                    
                <?php case 'payments': ?>
                    
                    <?php 
                    // Calculate payment statistics
                    $total_payments = 0;
                    $total_amount = 0;
                    $paid_count = 0;
                    $pending_count = 0;
                    
                    if (!empty($report_data['payments'])) {
                        $total_payments = count($report_data['payments']);
                        foreach ($report_data['payments'] as $payment) {
                            $total_amount += $payment['amount'] ?? 0;
                            if (isset($payment['payment_status'])) {
                                if ($payment['payment_status'] == 'paid') {
                                    $paid_count++;
                                } elseif ($payment['payment_status'] == 'pending') {
                                    $pending_count++;
                                }
                            }
                        }
                    }
                    ?>
                    
                    <!-- Payment Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_payments; ?></div>
                            <div class="stat-label">Total Payments</div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-value">ETB <?php echo number_format($total_amount, 2); ?></div>
                            <div class="stat-label">Total Amount</div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-value"><?php echo $paid_count; ?></div>
                            <div class="stat-label">Paid</div>
                        </div>
                        
                        <div class="stat-card warning">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-value"><?php echo $pending_count; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    
                    <!-- Payments Table -->
                    <div class="table-container">
                        <h3><i class="fas fa-list"></i> Payment Details</h3>
                        
                        <?php if (!empty($report_data['payments'])): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Payment ID</th>
                                        <th>Teacher</th>
                                        <th>Department</th>
                                        <th>Course</th>
                                        <th>Credit Hours</th>
                                        <th>Amount</th>
                                        <th>Payment Date</th>
                                        <th>Status</th>
                                        <th>Processed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['payments'] as $payment): ?>
                                    <tr>
                                        <td><strong>#<?php echo str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($payment['full_name'] ?? 'N/A'); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['department'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($payment['course_name'] ?? 'N/A'); ?></td>
                                        <td><strong><?php echo $payment['credit_hour'] ?? 0; ?></strong></td>
                                        <td><strong style="color: var(--success);">ETB <?php echo number_format($payment['amount'] ?? 0, 2); ?></strong></td>
                                        <td><?php echo !empty($payment['payment_date']) ? date('M d, Y', strtotime($payment['payment_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <?php if (isset($payment['payment_status'])): ?>
                                                <span class="status-badge status-<?php echo $payment['payment_status']; ?>">
                                                    <?php echo ucfirst($payment['payment_status']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['processed_by_name'] ?? 'System'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-coins"></i>
                                <h3>No Payment Data Found</h3>
                                <p>No payment records match your current filters.</p>
                                <p style="margin-top: 10px;">
                                    <a href="reports.php?report_type=payments" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View All Payments
                                    </a>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php break; ?>
                    
                <?php case 'teachers': ?>
                    
                    <!-- Teachers Table -->
                    <div class="table-container">
                        <h3><i class="fas fa-chalkboard-teacher"></i> Teacher Performance Report</h3>
                        
                        <?php if (!empty($report_data['teachers'])): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Teacher Name</th>
                                        <th>Department</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Rejected</th>
                                        <th>Credit Hours</th>
                                        <th>Total Payments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['teachers'] as $teacher): ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['department']); ?></td>
                                        <td><strong><?php echo $teacher['total_requests']; ?></strong></td>
                                        <td><span style="color: var(--success); font-weight: 600;"><?php echo $teacher['approved_requests']; ?></span></td>
                                        <td><span style="color: var(--danger); font-weight: 600;"><?php echo $teacher['rejected_requests']; ?></span></td>
                                        <td><strong><?php echo $teacher['total_credits'] ?? 0; ?></strong></td>
                                        <td><strong style="color: var(--success);">ETB <?php echo number_format($teacher['total_payments'] ?? 0, 2); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-users"></i>
                                <h3>No Teacher Data Found</h3>
                                <p>No teacher records match your current filters.</p>
                                <p style="margin-top: 10px;">
                                    <a href="reports.php?report_type=teachers" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View All Teachers
                                    </a>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php break; ?>
                    
                <?php case 'departments': ?>
                    
                    <!-- Departments Table -->
                    <div class="table-container">
                        <h3><i class="fas fa-building"></i> Department Analysis</h3>
                        
                        <?php if (!empty($report_data['departments'])): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Teachers</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Rejected</th>
                                        <th>Credit Hours</th>
                                        <th>Total Payments</th>
                                        <th>Avg Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['departments'] as $dept): 
                                        $avg_payment = ($dept['request_count'] > 0) ? $dept['total_payments'] / $dept['request_count'] : 0;
                                    ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?php echo htmlspecialchars($dept['department']); ?></td>
                                        <td><strong><?php echo $dept['teacher_count']; ?></strong></td>
                                        <td><strong><?php echo $dept['request_count']; ?></strong></td>
                                        <td><span style="color: var(--success); font-weight: 600;"><?php echo $dept['approved_count']; ?></span></td>
                                        <td><span style="color: var(--danger); font-weight: 600;"><?php echo $dept['rejected_count']; ?></span></td>
                                        <td><strong><?php echo $dept['total_credits'] ?? 0; ?></strong></td>
                                        <td><strong style="color: var(--success);">ETB <?php echo number_format($dept['total_payments'] ?? 0, 2); ?></strong></td>
                                        <td>ETB <?php echo number_format($avg_payment, 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- Department Summary Stats -->
                            <?php 
                            $total_dept_teachers = 0;
                            $total_dept_requests = 0;
                            $total_dept_payments = 0;
                            
                            foreach ($report_data['departments'] as $dept) {
                                $total_dept_teachers += $dept['teacher_count'];
                                $total_dept_requests += $dept['request_count'];
                                $total_dept_payments += $dept['total_payments'];
                            }
                            ?>
                            
                            <div class="stats-grid" style="margin-top: 30px;">
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div class="stat-value"><?php echo count($report_data['departments']); ?></div>
                                    <div class="stat-label">Departments</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $total_dept_teachers; ?></div>
                                    <div class="stat-label">Total Teachers</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $total_dept_requests; ?></div>
                                    <div class="stat-label">Total Requests</div>
                                </div>
                                
                                <div class="stat-card success">
                                    <div class="stat-icon">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                    <div class="stat-value">ETB <?php echo number_format($total_dept_payments, 2); ?></div>
                                    <div class="stat-label">Total Payments</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-building"></i>
                                <h3>No Department Data Found</h3>
                                <p>No department records match your current filters.</p>
                                <p style="margin-top: 10px;">
                                    <a href="reports.php?report_type=departments" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View All Departments
                                    </a>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php break; ?>
                    
            <?php endswitch; ?>
        </main>
    </div>
    
    <script>
        // Report type switching
        function changeReportType(type) {
            const url = new URL(window.location.href);
            url.searchParams.set('report_type', type);
            window.location.href = url.toString();
        }
        
        // Set today's date as default for date_to if empty
        document.addEventListener('DOMContentLoaded', function() {
            const dateFrom = document.querySelector('input[name="date_from"]');
            const dateTo = document.querySelector('input[name="date_to"]');
            
            // Set default end date to today if not set
            if (dateTo && !dateTo.value) {
                dateTo.value = new Date().toISOString().split('T')[0];
            }
            
            // Set default start date to 30 days ago if not set
            if (dateFrom && !dateFrom.value) {
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                dateFrom.value = thirtyDaysAgo.toISOString().split('T')[0];
            }
            
            // Add animation to stats cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
        
        // Add confirmation for print
        document.querySelector('.btn-primary[onclick*="print"]')?.addEventListener('click', function(e) {
            if (confirm('Print the current report?')) {
                window.print();
            }
        });
        
        // Add active state to clicked filter buttons
        document.querySelectorAll('.filter-select, .filter-input').forEach(element => {
            element.addEventListener('change', function() {
                this.style.borderColor = 'var(--primary)';
                this.style.boxShadow = '0 0 0 3px rgba(0, 64, 128, 0.1)';
                
                // Remove highlight after 1 second
                setTimeout(() => {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }, 1000);
            });
        });
    </script>
</body>
</html>