<?php
// teacher/my_courses.php
include("../config/session.php");
include("../config/db.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SESSION['role'] != 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get teacher ID from teachers table
$teacher_sql = "SELECT teacher_id FROM teachers WHERE user_id = ?";
$teacher_stmt = $conn->prepare($teacher_sql);

if (!$teacher_stmt) {
    die("Prepare failed: " . $conn->error);
}

$teacher_stmt->bind_param("i", $user_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();

if ($teacher_result->num_rows === 0) {
    die("Teacher record not found. Please contact administrator.");
}

$teacher_data = $teacher_result->fetch_assoc();
$teacher_id = $teacher_data['teacher_id'];

// CORRECTED QUERY - removed description column
$sql = "SELECT 
        ca.assignment_id,
        ca.teacher_id,
        ca.course_id,
        ca.semester,
        ca.academic_year,
        ca.credit_hours,
        ca.assigned_date,
        ca.notes,
        ca.status,
        ca.assigned_by,
        c.course_code,
        c.course_name,
        u.full_name as assigned_by_name
        FROM course_assignments ca
        JOIN courses c ON ca.course_id = c.course_id
        LEFT JOIN users u ON ca.assigned_by = u.user_id
        WHERE ca.teacher_id = ? 
        AND ca.status = 'active'
        ORDER BY ca.academic_year DESC, 
        FIELD(ca.semester, 'Fall', 'Spring', 'Summer')";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    // Show the SQL error
    die("Prepare failed: " . $conn->error . "<br>SQL: " . $sql);
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

$current_courses = [];
$total_credit_hours = 0;

if ($result->num_rows > 0) {
    while ($course = $result->fetch_assoc()) {
        $current_courses[] = $course;
        $total_credit_hours += $course['credit_hours'];
    }
}

// Get completed courses
$completed_sql = "SELECT 
                 ca.*, 
                 c.course_code, 
                 c.course_name,
                 u.full_name as assigned_by_name
                 FROM course_assignments ca
                 JOIN courses c ON ca.course_id = c.course_id
                 LEFT JOIN users u ON ca.assigned_by = u.user_id
                 WHERE ca.teacher_id = ? 
                 AND ca.status = 'completed'
                 ORDER BY ca.academic_year DESC";
                 
$completed_stmt = $conn->prepare($completed_sql);

if (!$completed_stmt) {
    die("Prepare failed for completed courses: " . $conn->error);
}

$completed_stmt->bind_param("i", $teacher_id);
$completed_stmt->execute();
$completed_result = $completed_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses | Teacher Panel | Woldiya University</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern Dashboard Styles */
        :root {
            --primary-color: #4a6fa5;
            --primary-dark: #3a5680;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --gradient-primary: linear-gradient(135deg, #4a6fa5 0%, #3a5680 100%);
            --gradient-success: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            --gradient-warning: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            --gradient-info: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s ease;
        }
        
        /* Main Content Styles */
        .main-content {
            background: #f5f7fa;
            min-height: 100vh;
            padding: 30px;
        }
        
        .page-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 6px solid var(--primary-color);
        }
        
        .page-header h2 {
            color: var(--dark-color);
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header h2 i {
            color: var(--primary-color);
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn-primary, .btn-warning {
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-warning {
            background: var(--gradient-warning);
            color: #212529;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Statistics Summary - Modern Cards */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }
        
        .stat-item {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border-top: 4px solid var(--primary-color);
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-primary);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-family: 'Segoe UI', system-ui, sans-serif;
            text-shadow: 0 2px 4px rgba(74, 111, 165, 0.1);
        }
        
        .stat-label {
            font-size: 1rem;
            color: var(--secondary-color);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-item:nth-child(2) {
            border-top-color: var(--success-color);
        }
        
        .stat-item:nth-child(2) .stat-number {
            color: var(--success-color);
        }
        
        .stat-item:nth-child(3) {
            border-top-color: var(--info-color);
        }
        
        .stat-item:nth-child(3) .stat-number {
            color: var(--info-color);
        }
        
        .stat-item:nth-child(4) {
            border-top-color: var(--warning-color);
        }
        
        .stat-item:nth-child(4) .stat-number {
            color: var(--warning-color);
        }
        
        /* Course Cards - Modern Design */
        .course-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-md);
            border-left: 6px solid var(--primary-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .course-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, rgba(74, 111, 165, 0.05) 0%, transparent 100%);
            border-radius: 0 0 0 100%;
        }
        
        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f2f5;
        }
        
        .course-title {
            flex: 1;
        }
        
        .course-code {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }
        
        .course-name {
            font-size: 1.3rem;
            color: var(--dark-color);
            font-weight: 600;
            line-height: 1.4;
        }
        
        .credit-hours {
            background: var(--gradient-primary);
            color: white;
            padding: 12px 20px;
            border-radius: var(--radius-md);
            font-size: 1.5rem;
            font-weight: 700;
            min-width: 100px;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        
        .course-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-top: 25px;
            padding: 20px;
            background: #f8fafc;
            border-radius: var(--radius-md);
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-label i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .info-value {
            color: var(--dark-color);
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        /* Semester Tags - Modern Design */
        .semester-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-sm);
        }
        
        .semester-fall { 
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        .semester-spring { 
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .semester-summer { 
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        /* Notes Section - Modern Design */
        .notes-section {
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #fff9db 0%, #fff3cd 100%);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--warning-color);
            animation: fadeIn 0.5s ease;
        }
        
        .notes-section .info-label {
            color: #856404;
        }
        
        .notes-section p {
            margin-top: 10px;
            color: #856404;
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        /* No Courses State - Modern Design */
        .no-courses {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin: 20px 0;
            border: 2px dashed #e9ecef;
            animation: fadeIn 0.5s ease;
        }
        
        .no-courses i {
            font-size: 100px;
            color: var(--primary-color);
            margin-bottom: 30px;
            opacity: 0.2;
        }
        
        .no-courses h3 {
            color: var(--dark-color);
            font-size: 1.8rem;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .no-courses p {
            color: var(--secondary-color);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto 25px;
            line-height: 1.6;
        }
        
        /* Tab Container - Modern Design */
        .tab-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 40px;
        }
        
        .tab-nav {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        
        .tab-btn {
            padding: 20px 35px;
            background: none;
            border: none;
            font-weight: 700;
            color: var(--secondary-color);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: var(--transition);
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .tab-btn:hover {
            background: rgba(255, 255, 255, 0.8);
            color: var(--primary-color);
        }
        
        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: white;
        }
        
        .tab-content {
            padding: 35px;
            display: none;
            animation: fadeIn 0.4s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Completed Courses - Modern Design */
        .course-card.completed {
            border-left-color: var(--secondary-color);
            opacity: 0.9;
        }
        
        .course-card.completed .course-code {
            color: var(--secondary-color);
        }
        
        .course-card.completed .credit-hours {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        .completed-badge {
            background: var(--gradient-success);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        
        .fade-in {
            animation: fadeIn 0.5s ease;
        }
        
        .slide-in {
            animation: slideIn 0.5s ease;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .stats-summary {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .course-header {
                flex-direction: column;
                gap: 20px;
            }
            
            .credit-hours {
                align-self: flex-start;
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
                width: 100%;
                justify-content: center;
            }
            
            .stats-summary {
                grid-template-columns: 1fr;
            }
            
            .tab-nav {
                flex-direction: column;
            }
            
            .tab-btn {
                justify-content: center;
                padding: 18px;
            }
            
            .course-info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .btn-primary, .btn-warning {
                padding: 10px 18px;
                font-size: 0.9rem;
            }
            
            .course-card {
                padding: 20px;
            }
            
            .tab-content {
                padding: 20px;
            }
        }
        
        /* Print Styles */
        @media print {
            .header-actions,
            .tab-nav,
            .btn-primary,
            .btn-warning {
                display: none;
            }
            
            .course-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Teacher Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Teacher Panel</h3>
                <div class="user-info">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Teacher'); ?></h4>
                        <p>Teacher</p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="my_courses.php" class="active"><i class="fas fa-book"></i> My Courses</a></li>
                <li><a href="submit_overload.php"><i class="fas fa-plus-circle"></i> Request Overload</a></li>
                <li><a href="view_status.php"><i class="fas fa-history"></i> Request History</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header fade-in">
                <h2><i class="fas fa-book"></i> My Courses</h2>
                <div class="header-actions">
                    <button class="btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Schedule
                    </button>
                    <button class="btn-warning" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            
            <!-- Statistics Summary -->
            <div class="stats-summary">
                <div class="stat-item fade-in">
                    <div class="stat-number"><?php echo count($current_courses); ?></div>
                    <div class="stat-label">Current Courses</div>
                </div>
                <div class="stat-item fade-in" style="animation-delay: 0.1s;">
                    <div class="stat-number"><?php echo $total_credit_hours; ?></div>
                    <div class="stat-label">Total Credit Hours</div>
                </div>
                <div class="stat-item fade-in" style="animation-delay: 0.2s;">
                    <div class="stat-number"><?php echo $completed_result->num_rows; ?></div>
                    <div class="stat-label">Completed Courses</div>
                </div>
                <div class="stat-item fade-in" style="animation-delay: 0.3s;">
                    <div class="stat-number"><?php echo date('Y'); ?></div>
                    <div class="stat-label">Current Academic Year</div>
                </div>
            </div>
            
            <!-- Tab Container -->
            <div class="tab-container fade-in">
                <div class="tab-nav">
                    <button class="tab-btn active" onclick="showTab('current')">
                        <i class="fas fa-book-open"></i> Current Courses
                        <?php if (count($current_courses) > 0): ?>
                        <span style="background: var(--primary-color); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;">
                            <?php echo count($current_courses); ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <button class="tab-btn" onclick="showTab('completed')">
                        <i class="fas fa-check-circle"></i> Completed Courses
                        <?php if ($completed_result->num_rows > 0): ?>
                        <span style="background: var(--success-color); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;">
                            <?php echo $completed_result->num_rows; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                </div>
                
                <!-- Current Courses Tab -->
                <div id="current" class="tab-content active">
                    <?php if (count($current_courses) > 0): ?>
                        <?php foreach ($current_courses as $course): ?>
                        <div class="course-card slide-in">
                            <div class="course-header">
                                <div class="course-title">
                                    <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                </div>
                                <div class="credit-hours"><?php echo $course['credit_hours']; ?> hrs</div>
                            </div>
                            
                            <div class="course-info-grid">
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-calendar-alt"></i> Semester</div>
                                    <div class="info-value">
                                        <?php if (!empty($course['semester'])): ?>
                                        <span class="semester-tag semester-<?php echo strtolower($course['semester']); ?>">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo htmlspecialchars($course['semester']); ?>
                                        </span>
                                        <?php else: ?>
                                        <em>Not specified</em>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-calendar"></i> Academic Year</div>
                                    <div class="info-value"><?php echo htmlspecialchars($course['academic_year'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-user-check"></i> Assigned By</div>
                                    <div class="info-value"><?php echo htmlspecialchars($course['assigned_by_name'] ?? 'Unknown'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-clock"></i> Assigned Date</div>
                                    <div class="info-value"><?php echo !empty($course['assigned_date']) ? date('F d, Y', strtotime($course['assigned_date'])) : 'N/A'; ?></div>
                                </div>
                            </div>
                            
                            <?php if (!empty($course['notes'])): ?>
                            <div class="notes-section">
                                <div class="info-label"><i class="fas fa-sticky-note"></i> Notes</div>
                                <p><?php echo htmlspecialchars($course['notes']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-courses">
                            <i class="fas fa-book-open"></i>
                            <h3>No Current Courses Assigned</h3>
                            <p>You don't have any active course assignments at the moment.</p>
                            <p>Contact your department head or administrator for course assignments.</p>
                            <button class="btn-primary" onclick="location.reload()" style="margin-top: 20px;">
                                <i class="fas fa-sync-alt"></i> Check Again
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Completed Courses Tab -->
                <div id="completed" class="tab-content">
                    <?php if ($completed_result->num_rows > 0): ?>
                        <?php while ($course = $completed_result->fetch_assoc()): ?>
                        <div class="course-card completed slide-in">
                            <div class="course-header">
                                <div class="course-title">
                                    <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                </div>
                                <div class="credit-hours"><?php echo $course['credit_hours']; ?> hrs</div>
                            </div>
                            
                            <div class="course-info-grid">
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-calendar"></i> Academic Year</div>
                                    <div class="info-value"><?php echo htmlspecialchars($course['academic_year']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-calendar-alt"></i> Semester</div>
                                    <div class="info-value"><?php echo htmlspecialchars($course['semester']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-user-check"></i> Assigned By</div>
                                    <div class="info-value"><?php echo htmlspecialchars($course['assigned_by_name'] ?? 'Unknown'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-clock"></i> Assigned Date</div>
                                    <div class="info-value"><?php echo date('F d, Y', strtotime($course['assigned_date'])); ?></div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <span class="completed-badge">
                                    <i class="fas fa-check-circle"></i> Course Completed
                                </span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-courses">
                            <i class="fas fa-check-circle"></i>
                            <h3>No Completed Courses</h3>
                            <p>You haven't completed any courses yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Summary Footer -->
            <?php if (count($current_courses) > 0): ?>
            <div class="fade-in" style="text-align: center; padding: 25px; background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-md);">
                <h3 style="color: var(--primary-color); margin-bottom: 15px;">
                    <i class="fas fa-chart-bar"></i> Semester Summary
                </h3>
                <p style="color: var(--secondary-color); font-size: 1.1rem; margin-bottom: 10px;">
                    You are currently teaching <strong><?php echo count($current_courses); ?> courses</strong> 
                    with a total of <strong><?php echo $total_credit_hours; ?> credit hours</strong>.
                </p>
                <p style="color: var(--secondary-color); font-size: 0.95rem;">
                    <i class="fas fa-info-circle"></i> 
                    Remember to submit overload requests for additional teaching hours.
                </p>
            </div>
            <?php endif; ?>
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
        }
        
        // Add hover effects to course cards
        document.addEventListener('DOMContentLoaded', function() {
            const courseCards = document.querySelectorAll('.course-card');
            courseCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Add animation to stat numbers
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const value = parseInt(stat.textContent);
                if (value > 0) {
                    let count = 0;
                    const increment = value / 20;
                    const timer = setInterval(() => {
                        count += increment;
                        if (count >= value) {
                            stat.textContent = value;
                            clearInterval(timer);
                        } else {
                            stat.textContent = Math.floor(count);
                        }
                    }, 50);
                }
            });
        });
        
        // Print optimization
        function printSchedule() {
            window.print();
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + P to print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printSchedule();
            }
            
            // Ctrl + 1 for current courses tab
            if (e.ctrlKey && e.key === '1') {
                e.preventDefault();
                showTab('current');
            }
            
            // Ctrl + 2 for completed courses tab
            if (e.ctrlKey && e.key === '2') {
                e.preventDefault();
                showTab('completed');
            }
        });
        
        // Auto-refresh page every 5 minutes
        setTimeout(() => {
            console.log('Auto-refreshing courses page...');
            // location.reload(); // Uncomment if you want auto-refresh
        }, 300000); // 5 minutes
    </script>
</body>
</html>