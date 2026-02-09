<?php
include("../config/session.php");
include("../config/db.php");

// Debug mode - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SESSION['role'] != 'department_head') {
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];

// Get department head's department
$dept_sql = "SELECT department FROM users WHERE user_id = ?";
$dept_stmt = $conn->prepare($dept_sql);

if (!$dept_stmt) {
    die("Prepare failed for department query: " . $conn->error);
}

$dept_stmt->bind_param("i", $user_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();

if ($dept_result && $dept_result->num_rows > 0) {
    $dept_row = $dept_result->fetch_assoc();
    $head_department = $dept_row['department'] ?? '';
} else {
    $head_department = '';
}

// Get statistics for dashboard
$stats = [];

// Pending requests count
$pending_sql = "SELECT COUNT(*) as count 
                FROM overload_requests o
                JOIN teachers t ON o.teacher_id = t.teacher_id
                JOIN users u ON t.user_id = u.user_id
                WHERE o.status = 'pending'";
                
if (!empty($head_department)) {
    $pending_sql .= " AND u.department = ?";
    $pending_stmt = $conn->prepare($pending_sql);
    if ($pending_stmt) {
        $pending_stmt->bind_param("s", $head_department);
        $pending_stmt->execute();
        $pending_result = $pending_stmt->get_result();
        $stats['pending'] = $pending_result->fetch_assoc();
    }
} else {
    $pending_result = $conn->query($pending_sql);
    $stats['pending'] = $pending_result ? $pending_result->fetch_assoc() : ['count' => 0];
}

// Approved requests count
$approved_sql = "SELECT COUNT(*) as count 
                 FROM overload_requests o
                 JOIN teachers t ON o.teacher_id = t.teacher_id
                 JOIN users u ON t.user_id = u.user_id
                 WHERE o.status = 'approved'";
                 
if (!empty($head_department)) {
    $approved_sql .= " AND u.department = ?";
    $approved_stmt = $conn->prepare($approved_sql);
    if ($approved_stmt) {
        $approved_stmt->bind_param("s", $head_department);
        $approved_stmt->execute();
        $approved_result = $approved_stmt->get_result();
        $stats['approved'] = $approved_result->fetch_assoc();
    }
} else {
    $approved_result = $conn->query($approved_sql);
    $stats['approved'] = $approved_result ? $approved_result->fetch_assoc() : ['count' => 0];
}

// Rejected requests count
$rejected_sql = "SELECT COUNT(*) as count 
                 FROM overload_requests o
                 JOIN teachers t ON o.teacher_id = t.teacher_id
                 JOIN users u ON t.user_id = u.user_id
                 WHERE o.status = 'rejected'";
                 
if (!empty($head_department)) {
    $rejected_sql .= " AND u.department = ?";
    $rejected_stmt = $conn->prepare($rejected_sql);
    if ($rejected_stmt) {
        $rejected_stmt->bind_param("s", $head_department);
        $rejected_stmt->execute();
        $rejected_result = $rejected_stmt->get_result();
        $stats['rejected'] = $rejected_result->fetch_assoc();
    }
} else {
    $rejected_result = $conn->query($rejected_sql);
    $stats['rejected'] = $rejected_result ? $rejected_result->fetch_assoc() : ['count' => 0];
}

// Total teachers in department
$teachers_sql = "SELECT COUNT(*) as count 
                 FROM users 
                 WHERE role = 'teacher'";
                 
if (!empty($head_department)) {
    $teachers_sql .= " AND department = ?";
    $teachers_stmt = $conn->prepare($teachers_sql);
    if ($teachers_stmt) {
        $teachers_stmt->bind_param("s", $head_department);
        $teachers_stmt->execute();
        $teachers_result = $teachers_stmt->get_result();
        $stats['teachers'] = $teachers_result->fetch_assoc();
    }
} else {
    $teachers_result = $conn->query($teachers_sql);
    $stats['teachers'] = $teachers_result ? $teachers_result->fetch_assoc() : ['count' => 0];
}

// Get recent pending requests
$recent_sql = "SELECT o.request_id, u.full_name, o.course_name, o.credit_hour, 
                      o.semester, o.academic_year, o.submitted_at
               FROM overload_requests o
               JOIN teachers t ON o.teacher_id = t.teacher_id
               JOIN users u ON t.user_id = u.user_id
               WHERE o.status = 'pending'";
               
if (!empty($head_department)) {
    $recent_sql .= " AND u.department = ?";
    $recent_sql .= " ORDER BY o.submitted_at DESC LIMIT 5";
    $recent_stmt = $conn->prepare($recent_sql);
    if ($recent_stmt) {
        $recent_stmt->bind_param("s", $head_department);
        $recent_stmt->execute();
        $recent_result = $recent_stmt->get_result();
        $recent_requests = $recent_result ? $recent_result->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        $recent_requests = [];
    }
} else {
    $recent_sql .= " ORDER BY o.submitted_at DESC LIMIT 5";
    $recent_result = $conn->query($recent_sql);
    $recent_requests = $recent_result ? $recent_result->fetch_all(MYSQLI_ASSOC) : [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Head Dashboard | Woldiya University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-top: 4px solid #004080;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.pending {
            border-top-color: #ffc107;
        }
        .stat-card.approved {
            border-top-color: #28a745;
        }
        .stat-card.rejected {
            border-top-color: #dc3545;
        }
        .stat-card.teachers {
            border-top-color: #17a2b8;
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
        }
        .stat-card.pending .stat-icon {
            background-color: #fff3cd;
            color: #856404;
        }
        .stat-card.approved .stat-icon {
            background-color: #d4edda;
            color: #155724;
        }
        .stat-card.rejected .stat-icon {
            background-color: #f8d7da;
            color: #721c24;
        }
        .stat-card.teachers .stat-icon {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #004080;
            margin-bottom: 5px;
        }
        .stat-card.pending .stat-number {
            color: #ffc107;
        }
        .stat-card.approved .stat-number {
            color: #28a745;
        }
        .stat-card.rejected .stat-number {
            color: #dc3545;
        }
        .stat-card.teachers .stat-number {
            color: #17a2b8;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .department-badge {
            display: inline-block;
            background: linear-gradient(135deg, #004080, #0066cc);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        .quick-action-btn {
            background: white;
            border: 2px solid #004080;
            color: #004080;
            padding: 20px 15px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .quick-action-btn:hover {
            background: #004080;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,64,128,0.2);
        }
        .quick-action-btn i {
            font-size: 2rem;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
            border: 2px dashed #dee2e6;
        }
        .empty-state i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Department Head</h3>
                <div class="user-info">
                    <i class="fas fa-user-tie"></i>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Department Head'); ?></h4>
                        <p><?php echo htmlspecialchars($head_department ?: 'Department Head'); ?></p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="course_assignment.php"><i class="fas fa-book"></i> Assign Courses</a></li>
                <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="approve_overload.php"><i class="fas fa-check-circle"></i> Review Requests</a></li>
                <li><a href="approval_history.php"><i class="fas fa-history"></i> Approval History</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-tachometer-alt"></i> Department Head Dashboard</h2>
                <div class="header-actions">
                    <span class="department-badge">
                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($head_department ?: 'All Departments'); ?>
                    </span>
                    <button onclick="location.reload()" class="btn-primary btn-sm">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            
            <!-- Welcome Message -->
            <div class="card" style="background: linear-gradient(135deg, #004080, #0066cc); color: white;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="color: white; margin-bottom: 10px;">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Department Head'); ?>!</h3>
                        <p style="color: rgba(255,255,255,0.9); margin: 0;">
                            You have <strong><?php echo $stats['pending']['count'] ?? 0; ?></strong> pending overload requests to review.
                        </p>
                    </div>
                    <div style="font-size: 3rem; opacity: 0.3;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card pending">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['pending']['count'] ?? 0; ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
                
                <div class="stat-card approved">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['approved']['count'] ?? 0; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                
                <div class="stat-card rejected">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['rejected']['count'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
                
                <div class="stat-card teachers">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['teachers']['count'] ?? 0; ?></div>
                    <div class="stat-label">Teachers</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    <a href="approve_overload.php" class="quick-action-btn">
                        <i class="fas fa-check-circle"></i>
                        <span>Review Requests</span>
                    </a>
                    <a href="approval_history.php" class="quick-action-btn">
                        <i class="fas fa-history"></i>
                        <span>View History</span>
                    </a>
                    <a href="profile.php" class="quick-action-btn">
                        <i class="fas fa-user-circle"></i>
                        <span>My Profile</span>
                    </a>
                    <button onclick="window.print()" class="quick-action-btn" style="cursor: pointer;">
                        <i class="fas fa-print"></i>
                        <span>Print Report</span>
                    </button>
                </div>
            </div>
            
            <!-- Recent Pending Requests -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Recent Pending Requests</h3>
                    <a href="approve_overload.php" class="btn-primary btn-sm">View All</a>
                </div>
                
                <?php if (empty($recent_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>No Pending Requests</h4>
                        <p>There are currently no overload requests waiting for your approval.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Course</th>
                                    <th>Credits</th>
                                    <th>Semester</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['course_name']); ?></td>
                                    <td><?php echo $request['credit_hour']; ?></td>
                                    <td><?php echo htmlspecialchars($request['semester']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($request['submitted_at'])); ?></td>
                                    <td>
                                        <a href="approve_overload.php" class="btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> Review
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Approval Guidelines -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Quick Guidelines</h3>
                </div>
                <div class="guidelines">
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><strong>Review requests promptly</strong> - Try to respond within 3 working days</li>
                        <li><strong>Check workload balance</strong> - Ensure teachers aren't overloaded</li>
                        <li><strong>Verify course alignment</strong> - Courses should match teacher expertise</li>
                        <li><strong>Provide constructive feedback</strong> - Always add comments to your decisions</li>
                        <li><strong>Maintain consistency</strong> - Apply the same criteria to all requests</li>
                        <li><strong>Contact HR</strong> - For any policy questions or conflicts</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Auto-refresh dashboard every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000); // 5 minutes
        
        // Print dashboard function
        function printDashboard() {
            const printContent = document.querySelector('.main-content').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Department Head Dashboard - Woldiya University</title>
                    <style>
                        body { font-family: Arial; margin: 30px; }
                        h1 { color: #004080; text-align: center; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .stats { display: flex; justify-content: space-around; margin: 30px 0; }
                        .stat-item { text-align: center; }
                        .print-header { text-align: center; margin-bottom: 30px; }
                        .print-date { text-align: right; margin-bottom: 20px; color: #666; }
                        @media print {
                            button { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h1>Woldiya University</h1>
                        <h3>Department Head Dashboard Report</h3>
                    </div>
                    <div class="print-date">
                        Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}<br>
                        Department: <?php echo htmlspecialchars($head_department); ?>
                    </div>
                    ${printContent}
                </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }
        
        // Make print button use custom function
        document.querySelector('button[onclick="window.print()"]')?.addEventListener('click', function(e) {
            e.preventDefault();
            printDashboard();
        });
    </script>
</body>
</html>