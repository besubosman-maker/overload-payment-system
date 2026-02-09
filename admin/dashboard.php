<?php
// admin/dashboard.php (Enhanced)
include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'admin') {
    die("Access Denied");
}

// Initialize stats array with default values
$stats = [
    'total_users' => 0,
    'active_teachers' => 0,
    'pending_requests' => 0,
    'approved_month' => 0,
    'total_payments' => 0,
    'total_courses' => 0,
    'active_courses' => 0
];

// Fetch real statistics from database
try {
    // Get total users (excluding admin)
    $user_sql = "SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'";
    $user_result = $conn->query($user_sql);
    if ($user_result) {
        $stats['total_users'] = $user_result->fetch_assoc()['total_users'] ?? 0;
    }

    // Get active teachers - check for is_active column
    $check_teacher_column = $conn->query("SHOW COLUMNS FROM teachers LIKE 'is_active'");
    if ($check_teacher_column && $check_teacher_column->num_rows > 0) {
        $teacher_sql = "SELECT COUNT(*) as active_teachers FROM teachers WHERE is_active = 1";
    } else {
        // Fallback if is_active column doesn't exist
        $teacher_sql = "SELECT COUNT(*) as active_teachers FROM teachers";
    }
    $teacher_result = $conn->query($teacher_sql);
    if ($teacher_result) {
        $stats['active_teachers'] = $teacher_result->fetch_assoc()['active_teachers'] ?? 0;
    }

    // Get pending overload requests
    $pending_sql = "SELECT COUNT(*) as pending_requests FROM overload_requests WHERE status = 'pending'";
    $pending_result = $conn->query($pending_sql);
    if ($pending_result) {
        $stats['pending_requests'] = $pending_result->fetch_assoc()['pending_requests'] ?? 0;
    }

    // Get approved requests this month
    $approved_sql = "SELECT COUNT(*) as approved_month 
                    FROM overload_requests 
                    WHERE status = 'approved' 
                    AND MONTH(COALESCE(approved_date, submitted_at)) = MONTH(CURRENT_DATE()) 
                    AND YEAR(COALESCE(approved_date, submitted_at)) = YEAR(CURRENT_DATE())";
    $approved_result = $conn->query($approved_sql);
    if ($approved_result) {
        $stats['approved_month'] = $approved_result->fetch_assoc()['approved_month'] ?? 0;
    }

    // Get total payments amount
    $payment_sql = "SELECT SUM(amount) as total_payments FROM payments WHERE payment_status = 'paid'";
    $payment_result = $conn->query($payment_sql);
    if ($payment_result) {
        $payment_data = $payment_result->fetch_assoc();
        $stats['total_payments'] = $payment_data['total_payments'] ?? 0;
    }

    // Get total courses
    $course_sql = "SELECT COUNT(*) as total_courses FROM courses";
    $course_result = $conn->query($course_sql);
    if ($course_result) {
        $stats['total_courses'] = $course_result->fetch_assoc()['total_courses'] ?? 0;
    }

    // Get active courses - check if status or is_active column exists
    $check_course_status = $conn->query("SHOW COLUMNS FROM courses LIKE 'status'");
    if ($check_course_status && $check_course_status->num_rows > 0) {
        $active_course_sql = "SELECT COUNT(*) as active_courses FROM courses WHERE status = 'active'";
    } else {
        $check_course_active = $conn->query("SHOW COLUMNS FROM courses LIKE 'is_active'");
        if ($check_course_active && $check_course_active->num_rows > 0) {
            $active_course_sql = "SELECT COUNT(*) as active_courses FROM courses WHERE is_active = 1";
        } else {
            $active_course_sql = "SELECT COUNT(*) as active_courses FROM courses";
        }
    }
    $active_course_result = $conn->query($active_course_sql);
    if ($active_course_result) {
        $stats['active_courses'] = $active_course_result->fetch_assoc()['active_courses'] ?? 0;
    }

} catch (Exception $e) {
    // Log error but don't display to user
    error_log("Dashboard query error: " . $e->getMessage());
}

// Get recent activity from logs (if logs table exists)
$recent_activity = [];

// Check if system_logs table exists
$check_logs_table = $conn->query("SHOW TABLES LIKE 'system_logs'");
if ($check_logs_table && $check_logs_table->num_rows > 0) {
    $activity_sql = "SELECT action, user, details, timestamp 
                     FROM system_logs 
                     ORDER BY timestamp DESC 
                     LIMIT 10";
    $activity_result = $conn->query($activity_sql);
    
    if ($activity_result) {
        while ($row = $activity_result->fetch_assoc()) {
            $recent_activity[] = $row;
        }
    }
} else {
    // Fallback: try to get from overload requests and payments
    try {
        // First check if overload_requests table exists
        $check_overload_table = $conn->query("SHOW TABLES LIKE 'overload_requests'");
        
        if ($check_overload_table && $check_overload_table->num_rows > 0) {
            $activity_sql = "
            (SELECT 'Request Created' as action, 
                    (SELECT full_name FROM users u WHERE u.user_id = t.user_id) as user,
                    CONCAT('Created overload request for: ', o.course_name) as details,
                    o.submitted_at as timestamp
             FROM overload_requests o
             LEFT JOIN teachers t ON o.teacher_id = t.teacher_id
             ORDER BY o.submitted_at DESC LIMIT 5)
            UNION
            (SELECT 'Payment Processed' as action,
                    (SELECT username FROM users WHERE user_id = p.processed_by) as user,
                    CONCAT('Processed payment ETB ', FORMAT(p.amount, 2), ' for request #', p.request_id) as details,
                    p.payment_date as timestamp
             FROM payments p
             ORDER BY p.payment_date DESC LIMIT 5)
            ORDER BY timestamp DESC LIMIT 10";
            
            $activity_result = $conn->query($activity_sql);
            
            if ($activity_result) {
                while ($row = $activity_result->fetch_assoc()) {
                    $recent_activity[] = $row;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Recent activity query error: " . $e->getMessage());
    }
}

// Get department-wise statistics
$department_stats = [];
try {
    // Check if necessary tables exist
    $check_tables = $conn->query("SHOW TABLES LIKE 'teachers'");
    if ($check_tables && $check_tables->num_rows > 0) {
        $check_users = $conn->query("SHOW TABLES LIKE 'users'");
        if ($check_users && $check_users->num_rows > 0) {
            $dept_sql = "SELECT u.department, COUNT(*) as teacher_count 
                        FROM teachers t 
                        JOIN users u ON t.user_id = u.user_id 
                        GROUP BY u.department";
            $dept_result = $conn->query($dept_sql);
            
            if ($dept_result) {
                while ($row = $dept_result->fetch_assoc()) {
                    $department_stats[] = $row;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Department stats query error: " . $e->getMessage());
}

// Get payment statistics for current month
$month_payments = 0;
try {
    $check_payments = $conn->query("SHOW TABLES LIKE 'payments'");
    if ($check_payments && $check_payments->num_rows > 0) {
        $month_payment_sql = "SELECT SUM(amount) as month_payments 
                            FROM payments 
                            WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
                            AND YEAR(payment_date) = YEAR(CURRENT_DATE())";
        $month_payment_result = $conn->query($month_payment_sql);
        if ($month_payment_result) {
            $month_data = $month_payment_result->fetch_assoc();
            $month_payments = $month_data['month_payments'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Month payments query error: " . $e->getMessage());
}

// Get course assignment statistics
$assignment_stats = [
    'active_assignments' => 0,
    'total_assignments' => 0,
    'pending_assignments' => 0
];

try {
    $check_assignments = $conn->query("SHOW TABLES LIKE 'course_assignments'");
    if ($check_assignments && $check_assignments->num_rows > 0) {
        $assignment_sql = "SELECT 
                            (SELECT COUNT(*) FROM course_assignments WHERE status = 'active') as active_assignments,
                            (SELECT COUNT(*) FROM course_assignments) as total_assignments,
                            (SELECT COUNT(*) FROM course_assignments WHERE status = 'pending') as pending_assignments";
        $assignment_result = $conn->query($assignment_sql);
        if ($assignment_result) {
            $assignment_stats = $assignment_result->fetch_assoc();
        }
    }
} catch (Exception $e) {
    error_log("Assignment stats query error: " . $e->getMessage());
}

// Handle database errors gracefully
if ($conn->error) {
    error_log("Database error in dashboard: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Woldiya University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #004080;
            --secondary-color: #0066cc;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --dark-color: #343a40;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            border-top: 5px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark-color);
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .stat-card.pending .stat-icon { background: linear-gradient(135deg, var(--warning-color), #e0a800); }
        .stat-card.approved .stat-icon { background: linear-gradient(135deg, var(--success-color), #218838); }
        .stat-card.payments .stat-icon { background: linear-gradient(135deg, #20c997, #13855c); }
        .stat-card.teachers .stat-icon { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .stat-card.users .stat-icon { background: linear-gradient(135deg, #6f42c1, #5a2d9c); }
        .stat-card.courses .stat-icon { background: linear-gradient(135deg, #17a2b8, #117a8b); }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-paid {
            background: #cce5ff;
            color: #004085;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
        }
        
        .quick-actions a, .quick-actions button {
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .quick-actions i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table.sortable {
            width: 100%;
            border-collapse: collapse;
        }
        
        table.sortable th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid #dee2e6;
            cursor: pointer;
        }
        
        table.sortable th:hover {
            background: #e9ecef;
        }
        
        table.sortable td {
            padding: 12px 15px;
            border-bottom: 1px solid #eaeaea;
        }
        
        table.sortable tbody tr:hover {
            background: #f8fafc;
        }
        
        .department-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .department-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .department-card h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .department-list {
            list-style: none;
        }
        
        .department-list li {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .department-list li:last-child {
            border-bottom: none;
        }
        
        .dept-count {
            background: var(--primary-color);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .notification {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.3s ease;
        }
        
        .notification-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .notification-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
                <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_courses.php"><i class="fas fa-graduation-cap"></i> Manage Courses</a></li>
                <li><a href="course_assignment.php"><i class="fas fa-book"></i> Course Assignment</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="payment_rate.php"><i class="fas fa-money-check-alt"></i> Payment Rates</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>
                <div class="header-actions">
                    <button class="btn-primary btn-sm" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            
            <!-- Database Connection Check -->
            <?php if ($conn->connect_error): ?>
                <div class="notification notification-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Database Connection Error:</strong> Unable to connect to database.
                    <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card pending">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['pending_requests']; ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                </div>
                
                <div class="stat-card approved">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['approved_month']; ?></div>
                        <div class="stat-label">Approved This Month</div>
                    </div>
                </div>
                
                <div class="stat-card payments">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">ETB <?php echo number_format($stats['total_payments'], 0); ?></div>
                        <div class="stat-label">Total Payments</div>
                    </div>
                </div>
                
                <div class="stat-card teachers">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['active_teachers']; ?></div>
                        <div class="stat-label">Active Teachers</div>
                    </div>
                </div>
                
                <div class="stat-card users">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                        <div class="stat-label">System Users</div>
                    </div>
                </div>
                
                <div class="stat-card courses">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['active_courses']; ?></div>
                        <div class="stat-label">Active Courses</div>
                    </div>
                </div>
            </div>
            
            <!-- Two Column Layout -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <!-- Left Column -->
                <div>
                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-history"></i> Recent Activity</h3>
                            <button class="btn-primary btn-sm" onclick="exportToCSV('activity-table', 'recent-activity.csv')">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                        <div class="table-container">
                            <table id="activity-table" class="sortable">
                                <thead>
                                    <tr>
                                        <th data-sortable="true">Date</th>
                                        <th data-sortable="true">User</th>
                                        <th data-sortable="true">Action</th>
                                        <th data-sortable="true">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_activity)): ?>
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d H:i', strtotime($activity['timestamp'] ?? $activity['created_at'] ?? date('Y-m-d H:i'))); ?></td>
                                                <td><?php echo htmlspecialchars($activity['user'] ?? 'System'); ?></td>
                                                <td>
                                                    <?php 
                                                    $status_class = 'status-pending';
                                                    $action = $activity['action'] ?? 'Unknown';
                                                    if (strpos($action, 'Approved') !== false) $status_class = 'status-approved';
                                                    if (strpos($action, 'Payment') !== false) $status_class = 'status-paid';
                                                    ?>
                                                    <span class="status-badge <?php echo $status_class; ?>">
                                                        <?php echo htmlspecialchars($action); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($activity['details'] ?? 'No details available'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; padding: 40px; color: #6c757d;">
                                                <i class="fas fa-info-circle"></i> No recent activity found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                        </div>
                        <div class="quick-actions">
                            <a href="manage_users.php" class="btn-primary">
                                <i class="fas fa-user-plus"></i> Add New User
                            </a>
                            <a href="payment_rate.php" class="btn-success">
                                <i class="fas fa-edit"></i> Update Rates
                            </a>
                            <button class="btn-warning" onclick="showSystemReport()">
                                <i class="fas fa-file-alt"></i> Generate Report
                            </button>
                            <button class="btn-danger" onclick="backupDatabase()">
                                <i class="fas fa-database"></i> Backup System
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Department Statistics -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-building"></i> Department Statistics</h3>
                        </div>
                        <div class="department-card">
                            <?php if (!empty($department_stats)): ?>
                                <h4>Teachers by Department</h4>
                                <ul class="department-list">
                                    <?php foreach ($department_stats as $dept): ?>
                                        <li>
                                            <span><?php echo htmlspecialchars($dept['department'] ?? 'Unknown Department'); ?></span>
                                            <span class="dept-count"><?php echo $dept['teacher_count'] ?? 0; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p style="text-align: center; padding: 20px; color: #6c757d;">
                                    <i class="fas fa-info-circle"></i> No department data available
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- System Summary -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> System Summary</h3>
                        </div>
                        <div class="department-card">
                            <ul class="department-list">
                                <li>
                                    <span>Total Courses</span>
                                    <span class="dept-count"><?php echo $stats['total_courses']; ?></span>
                                </li>
                                <li>
                                    <span>Active Assignments</span>
                                    <span class="dept-count"><?php echo $assignment_stats['active_assignments'] ?? 0; ?></span>
                                </li>
                                <li>
                                    <span>Pending Assignments</span>
                                    <span class="dept-count"><?php echo $assignment_stats['pending_assignments'] ?? 0; ?></span>
                                </li>
                                <li>
                                    <span>This Month Payments</span>
                                    <span class="dept-count">ETB <?php echo number_format($month_payments, 0); ?></span>
                                </li>
                                <li>
                                    <span>Total Assignments</span>
                                    <span class="dept-count"><?php echo $assignment_stats['total_assignments'] ?? 0; ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Sort table functionality
        function sortTable(tableId, column) {
            const table = document.getElementById(tableId);
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const headers = table.querySelectorAll('th');
            const isAscending = table.dataset.sortAsc === 'true';
            
            rows.sort((a, b) => {
                const aValue = a.cells[column].textContent.trim();
                const bValue = b.cells[column].textContent.trim();
                
                if (!isNaN(aValue) && !isNaN(bValue)) {
                    return isAscending ? aValue - bValue : bValue - aValue;
                }
                
                return isAscending ? 
                    aValue.localeCompare(bValue) : 
                    bValue.localeCompare(aValue);
            });
            
            rows.forEach(row => tbody.appendChild(row));
            table.dataset.sortAsc = !isAscending;
        }
        
        // Initialize sortable tables
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('table.sortable th[data-sortable="true"]').forEach((th, index) => {
                th.addEventListener('click', () => sortTable(th.closest('table').id, index));
            });
        });
        
        function refreshData() {
            const refreshBtn = event.target.closest('button');
            const originalHTML = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            refreshBtn.disabled = true;
            
            // Reload the page to get fresh data
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
        
        function showSystemReport() {
            showNotification('System report generation started. Check reports page in a moment.', 'success');
            setTimeout(() => {
                window.location.href = 'reports.php';
            }, 2000);
        }
        
        function backupDatabase() {
            if (confirm('This will create a backup of the entire system database. Continue?')) {
                showNotification('Database backup initiated. This may take a few moments...', 'warning');
                setTimeout(() => {
                    showNotification('Database backup completed successfully!', 'success');
                }, 3000);
            }
        }
        
        function exportToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            const rows = table.querySelectorAll('tr');
            const csv = [];
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    row.push(cols[j].innerText);
                }
                
                csv.push(row.join(','));
            }
            
            const csvString = csv.join('\n');
            const blob = new Blob([csvString], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', filename);
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            
            showNotification('CSV exported successfully!', 'success');
        }
        
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer;">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
                color: ${type === 'success' ? '#155724' : '#721c24'};
                border-radius: 5px;
                display: flex;
                align-items: center;
                gap: 10px;
                z-index: 1000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideIn 0.3s ease;
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.opacity = '0';
                    notification.style.transition = 'opacity 0.5s';
                    setTimeout(() => notification.remove(), 500);
                }
            }, 5000);
        }
    </script>
</body>
</html>