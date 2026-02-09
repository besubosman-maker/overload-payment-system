<?php
// department_head/course_assignment.php
include("../config/session.php");
include("../config/db.php");

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SESSION['role'] != 'department_head') {
    header("Location: ../auth/login.php");
    exit();
}

$success_msg = '';
$error_msg = '';
$user_id = $_SESSION['user_id'];

// Get department from database
$dept_sql = "SELECT department FROM users WHERE user_id = ?";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->bind_param("i", $user_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();

if ($dept_result->num_rows > 0) {
    $user_data = $dept_result->fetch_assoc();
    $department = $user_data['department'];
    $_SESSION['department'] = $department;
} else {
    $_SESSION['error'] = "Department information not found. Please contact administrator.";
    header("Location: ../auth/logout.php");
    exit();
}

// DIRECT TEST: Check courses in database
$test_sql = "SELECT * FROM courses";
$test_result = $conn->query($test_sql);
$all_courses = [];
if ($test_result) {
    while ($row = $test_result->fetch_assoc()) {
        $all_courses[] = $row;
    }
}

// DIRECT TEST: Check courses in Engineering department specifically
$test_dept_sql = "SELECT * FROM courses WHERE department = 'Engineering'";
$test_dept_result = $conn->query($test_dept_sql);
$engineering_courses = [];
if ($test_dept_result) {
    while ($row = $test_dept_result->fetch_assoc()) {
        $engineering_courses[] = $row;
    }
}

// Store teachers and courses results for later use
$teachers_list = [];
$courses_list = [];
$assignments_list = [];

// Get teachers in same department
$teachers_sql = "SELECT t.teacher_id, u.full_name, u.email 
                FROM teachers t 
                JOIN users u ON t.user_id = u.user_id 
                WHERE u.department = ? 
                AND u.role = 'teacher' 
                ORDER BY u.full_name";
$teachers_stmt = $conn->prepare($teachers_sql);
if ($teachers_stmt) {
    $teachers_stmt->bind_param("s", $department);
    $teachers_stmt->execute();
    $teachers_result = $teachers_stmt->get_result();

    if ($teachers_result) {
        while ($teacher = $teachers_result->fetch_assoc()) {
            $teachers_list[] = $teacher;
        }
    }
}

// Get courses in same department - using direct comparison
$courses_sql = "SELECT course_id, course_code, course_name, credit_hours 
                FROM courses 
                WHERE department = ? 
                ORDER BY course_code";
$courses_stmt = $conn->prepare($courses_sql);
if ($courses_stmt) {
    $courses_stmt->bind_param("s", $department);
    $courses_stmt->execute();
    $courses_result = $courses_stmt->get_result();

    if ($courses_result) {
        while ($course = $courses_result->fetch_assoc()) {
            $courses_list[] = $course;
        }
    }
} else {
    $error_msg = "Courses prepare failed: " . $conn->error;
}

// Handle course assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_course'])) {
    $teacher_id = intval($_POST['teacher_id']);
    $course_id = intval($_POST['course_id']);
    $semester = $_POST['semester'];
    $academic_year = $_POST['academic_year'];
    $credit_hours = intval($_POST['credit_hours']);
    $notes = $_POST['notes'] ?? '';
    $assigned_by = $user_id;

    // Verify teacher exists in department
    $teacher_exists = false;
    foreach ($teachers_list as $teacher) {
        if ($teacher['teacher_id'] == $teacher_id) {
            $teacher_exists = true;
            break;
        }
    }

    if (!$teacher_exists) {
        $error_msg = "Selected teacher is not in your department.";
    } else {
        // Check if already assigned
        $check_sql = "SELECT * FROM course_assignments 
                      WHERE teacher_id = ? AND course_id = ? 
                      AND semester = ? AND academic_year = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iiss", $teacher_id, $course_id, $semester, $academic_year);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_msg = "This course is already assigned to this teacher for the selected semester.";
        } else {
            $insert_sql = "INSERT INTO course_assignments 
                          (teacher_id, course_id, semester, academic_year, credit_hours, assigned_by, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iissiis", $teacher_id, $course_id, $semester, $academic_year, $credit_hours, $assigned_by, $notes);
            
            if ($insert_stmt->execute()) {
                $success_msg = "Course assigned successfully!";
                header("Location: course_assignment.php?success=1");
                exit();
            } else {
                $error_msg = "Error assigning course: " . $conn->error;
            }
        }
    }
}

// Get assignments for department
$assignments_sql = "SELECT ca.*, 
                    u.full_name as teacher_name, 
                    c.course_code, c.course_name,
                    au.full_name as assigned_by_name
                   FROM course_assignments ca
                   JOIN teachers t ON ca.teacher_id = t.teacher_id
                   JOIN users u ON t.user_id = u.user_id
                   JOIN courses c ON ca.course_id = c.course_id
                   JOIN users au ON ca.assigned_by = au.user_id
                   WHERE u.department = ?
                   ORDER BY ca.academic_year DESC, 
                   FIELD(ca.semester, 'Fall', 'Spring', 'Summer')";
$assignments_stmt = $conn->prepare($assignments_sql);
if ($assignments_stmt) {
    $assignments_stmt->bind_param("s", $department);
    $assignments_stmt->execute();
    $assignments_result = $assignments_stmt->get_result();

    if ($assignments_result) {
        while ($assignment = $assignments_result->fetch_assoc()) {
            $assignments_list[] = $assignment;
        }
    }
}

// Check for success message
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_msg = "Course assigned successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Assignment | Department Head | Woldiya University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Same CSS styles as before */
        .dashboard-container { display: flex; min-height: 100vh; background: #f5f7fa; }
        .sidebar { width: 260px; background: linear-gradient(180deg, #004080, #00264d); color: white; position: fixed; height: 100%; overflow-y: auto; }
        .main-content { flex: 1; margin-left: 260px; padding: 30px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e0e6ef; }
        .btn-primary { background: #004080; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .notification { padding: 16px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; }
        .notification-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .notification-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 30px; }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; padding: 16px 20px; text-align: left; font-weight: 600; }
        td { padding: 16px 20px; border-bottom: 1px solid #eef2f7; }
        .empty-state { text-align: center; padding: 60px 40px; color: #6c757d; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; }
        .form-group { margin-bottom: 22px; }
        select, input, textarea { width: 100%; padding: 12px 16px; border: 1px solid #d1d9e6; border-radius: 6px; }
        
        .department-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-top: 4px solid #004080;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #004080;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .debug-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 12px;
        }
        .database-test {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-view { background: #e7f3ff; color: #004080; }
        .btn-edit { background: #fff3cd; color: #856404; }
        .status-active { 
            background: #d1f7c4; 
            color: #155724; 
            padding: 5px 12px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 600; 
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Dept. Head Panel</h3>
                <div class="user-info">
                    <i class="fas fa-user-tie"></i>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Dept Head'); ?></h4>
                        <p><?php echo htmlspecialchars($department); ?> Department</p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="approve_overload.php"><i class="fas fa-check-circle"></i> Review Requests</a></li>
                <li><a href="approval_history.php"><i class="fas fa-history"></i> Approval History</a></li>
                <li><a href="course_assignment.php" class="active"><i class="fas fa-book"></i> Assign Courses</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-book"></i> Course Assignment - <?php echo htmlspecialchars($department); ?> Department</h2>
                <div class="header-actions">
                    <button class="btn-primary" onclick="toggleAssignmentForm()">
                        <i class="fas fa-plus-circle"></i> Assign New Course
                    </button>
                    <button class="btn-warning" onclick="toggleDebug()">
                        <i class="fas fa-bug"></i> Debug
                    </button>
                </div>
            </div>
            
            <!-- Database Test Results -->
            <div class="database-test">
                <h4><i class="fas fa-database"></i> Database Test Results</h4>
                <p><strong>Your Department:</strong> <?php echo htmlspecialchars($department); ?></p>
                <p><strong>All Courses in Database:</strong> <?php echo count($all_courses); ?></p>
                <p><strong>Courses in 'Engineering' Department:</strong> <?php echo count($engineering_courses); ?></p>
                
                <?php if (!empty($all_courses)): ?>
                <p><strong>All Course Departments:</strong></p>
                <ul>
                    <?php 
                    $departments = [];
                    foreach ($all_courses as $course) {
                        $dept = $course['department'] ?? 'No Department';
                        if (!in_array($dept, $departments)) {
                            $departments[] = $dept;
                        }
                    }
                    foreach ($departments as $dept): ?>
                    <li><?php echo htmlspecialchars($dept); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                
                <?php if (!empty($engineering_courses)): ?>
                <p><strong>Engineering Courses Found:</strong></p>
                <ul>
                    <?php foreach ($engineering_courses as $course): ?>
                    <li><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p style="color: #dc3545;"><strong>No courses found in 'Engineering' department!</strong></p>
                <?php endif; ?>
            </div>
            
            <!-- Debug Panel -->
            <div class="debug-panel" id="debugPanel" style="display: none;">
                <h4><i class="fas fa-bug"></i> Debug Information</h4>
                <p><strong>Department:</strong> <?php echo htmlspecialchars($department); ?></p>
                <p><strong>Teachers Found:</strong> <?php echo count($teachers_list); ?></p>
                <p><strong>Courses Found (using prepared statement):</strong> <?php echo count($courses_list); ?></p>
                <p><strong>Assignments Found:</strong> <?php echo count($assignments_list); ?></p>
                
                <?php if (!empty($courses_list)): ?>
                <p><strong>Courses List (from prepared statement):</strong></p>
                <ul>
                    <?php foreach ($courses_list as $course): ?>
                    <li><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name'] . ' (' . $course['credit_hours'] . ' credits)'); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            
            <!-- Department Info -->
            <div class="department-info">
                <i class="fas fa-university"></i>
                <div>
                    <h3 style="margin: 0 0 10px 0;"><?php echo htmlspecialchars($department); ?> Department</h3>
                    <p style="margin: 0; opacity: 0.9;">Manage course assignments for teachers in your department</p>
                </div>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="notification notification-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                    <button class="notification-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="notification notification-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                    <button class="notification-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($teachers_list); ?></div>
                    <div class="stat-label">Teachers in Department</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($courses_list); ?></div>
                    <div class="stat-label">Available Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($assignments_list); ?></div>
                    <div class="stat-label">Active Assignments</div>
                </div>
            </div>
            
            <!-- Assignment Form -->
            <div class="card" id="assignmentForm" style="display: none;">
                <div class="card-header">
                    <h3><i class="fas fa-plus-circle"></i> Assign New Course</h3>
                    <button class="btn-warning btn-sm" onclick="toggleAssignmentForm()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
                <div class="card-body">
                    <div style="background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #17a2b8;">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Note:</strong> You can only assign courses to teachers in the <?php echo htmlspecialchars($department); ?> department.
                    </div>
                    
                    <?php if (empty($teachers_list) || empty($courses_list)): ?>
                        <div style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 40px; text-align: center; margin: 30px 0;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 64px; color: #6c757d; opacity: 0.4; margin-bottom: 20px;"></i>
                            <h3>Setup Required</h3>
                            <p>
                                <?php if (empty($teachers_list)): ?>
                                    <strong>No teachers found</strong> in your department.<br>
                                <?php endif; ?>
                                <?php if (empty($courses_list)): ?>
                                    <?php if (empty($teachers_list)) echo "<br>"; ?>
                                    <strong>No courses found</strong> in your department.<br>
                                    <small>Check the Database Test Results above.</small>
                                <?php endif; ?>
                            </p>
                            <div style="margin-top: 20px;">
                                <button class="btn-primary" onclick="toggleDebug()">
                                    <i class="fas fa-bug"></i> Show Debug Info
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-chalkboard-teacher"></i> Select Teacher *</label>
                                <select name="teacher_id" required>
                                    <option value="">Select a Teacher</option>
                                    <?php foreach ($teachers_list as $teacher): ?>
                                    <option value="<?php echo $teacher['teacher_id']; ?>">
                                        <?php echo htmlspecialchars($teacher['full_name']); ?> 
                                        <?php if (!empty($teacher['email'])): ?>
                                        (<?php echo htmlspecialchars($teacher['email']); ?>)
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-book"></i> Select Course *</label>
                                <select name="course_id" required id="courseSelect">
                                    <option value="">Select a Course</option>
                                    <?php foreach ($courses_list as $course): ?>
                                    <option value="<?php echo $course['course_id']; ?>" data-hours="<?php echo $course['credit_hours']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        (<?php echo $course['credit_hours']; ?> credits)
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
                                <input type="text" name="academic_year" required 
                                       pattern="\d{4}/\d{4}" 
                                       placeholder="YYYY/YYYY (e.g., 2024/2025)"
                                       title="Format: YYYY/YYYY (e.g., 2024/2025)"
                                       value="<?php echo date('Y') . '/' . (date('Y') + 1); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Credit Hours *</label>
                            <input type="number" name="credit_hours" id="creditHours" min="1" max="10" value="3" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> Notes (Optional)</label>
                            <textarea name="notes" placeholder="Any additional notes or instructions for this assignment..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="assign_course" class="btn-primary" style="padding: 12px 30px;">
                                <i class="fas fa-check-circle"></i> Assign Course
                            </button>
                            <button type="reset" class="btn-warning" style="padding: 12px 30px; margin-left: 10px;">
                                <i class="fas fa-redo"></i> Reset Form
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Current Assignments -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-list-alt"></i> Current Course Assignments</h3>
                    <div class="user-count">
                        <span style="color: #6c757d;">
                            <i class="fas fa-filter"></i> 
                            Showing: <?php echo count($assignments_list); ?> assignment(s)
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($assignments_list)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>Academic Year</th>
                            <th>Credit Hours</th>
                            <th>Assigned By</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments_list as $assignment): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($assignment['teacher_name']); ?></strong>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($assignment['course_code']); ?></strong><br>
                                <small><?php echo htmlspecialchars($assignment['course_name']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($assignment['semester']); ?></td>
                            <td><?php echo htmlspecialchars($assignment['academic_year']); ?></td>
                            <td><?php echo htmlspecialchars($assignment['credit_hours']); ?></td>
                            <td><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></td>
                            <td>
                                <span class="status-active">Active</span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn btn-view" onclick="viewAssignment(<?php echo $assignment['assignment_id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="action-btn btn-edit" onclick="editAssignment(<?php echo $assignment['assignment_id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No Course Assignments Found</h3>
                    <p>There are no course assignments in the <?php echo htmlspecialchars($department); ?> department yet.</p>
                    <?php if (empty($teachers_list) || empty($courses_list)): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; max-width: 600px; margin-left: auto; margin-right: auto;">
                        <p><strong>Requirements:</strong></p>
                        <ul style="text-align: left;">
                            <?php if (empty($teachers_list)): ?>
                            <li>Add teachers to the <?php echo htmlspecialchars($department); ?> department</li>
                            <?php endif; ?>
                            <?php if (empty($courses_list)): ?>
                            <li>Add courses to the <?php echo htmlspecialchars($department); ?> department</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php else: ?>
                    <p>Click "Assign New Course" to create your first assignment.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        function toggleAssignmentForm() {
            const form = document.getElementById('assignmentForm');
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
                window.scrollTo({ top: form.offsetTop - 20, behavior: 'smooth' });
            } else {
                form.style.display = 'none';
            }
        }
        
        function toggleDebug() {
            const debugPanel = document.getElementById('debugPanel');
            if (debugPanel.style.display === 'none' || debugPanel.style.display === '') {
                debugPanel.style.display = 'block';
            } else {
                debugPanel.style.display = 'none';
            }
        }
        
        // Auto-update credit hours
        const courseSelect = document.getElementById('courseSelect');
        const creditHoursInput = document.getElementById('creditHours');
        
        if (courseSelect && creditHoursInput) {
            courseSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const creditHours = selectedOption.getAttribute('data-hours');
                if (creditHours) {
                    creditHoursInput.value = creditHours;
                }
            });
        }
        
        // Auto-show form if no assignments exist
        document.addEventListener('DOMContentLoaded', function() {
            const assignmentsCount = <?php echo count($assignments_list); ?>;
            if (assignmentsCount === 0) {
                const teachersCount = <?php echo count($teachers_list); ?>;
                const coursesCount = <?php echo count($courses_list); ?>;
                if (teachersCount > 0 && coursesCount > 0) {
                    setTimeout(() => {
                        toggleAssignmentForm();
                    }, 1000);
                }
            }
        });
    </script>
</body>
</html>