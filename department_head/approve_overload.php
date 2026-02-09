<?php
include("../config/session.php");
include("../config/db.php");

// Debug mode - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SESSION['role'] != 'department_head') {
    die("Access Denied");
}

$dept_head_id = $_SESSION['user_id'];
$user_id = $_SESSION['user_id'];

// First, get the department of the department head
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

// Handle approval or rejection
if (isset($_POST['action'])) {
    $request_id = intval($_POST['request_id']);
    $decision   = $_POST['action']; // 'approved' or 'rejected'
    $comment    = trim($_POST['comment']);
    
    // Validate comment
    if (empty($comment)) {
        $error_message = "Please enter comments explaining your decision.";
    } else {
        // Update overload request status
        $update_sql = "UPDATE overload_requests SET status = ? WHERE request_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        
        if (!$update_stmt) {
            die("Prepare failed for update: " . $conn->error);
        }
        
        $update_stmt->bind_param("si", $decision, $request_id);
        
        if ($update_stmt->execute()) {
            // Insert approval record
            $approval_sql = "INSERT INTO approvals (request_id, department_head_id, decision, comment) 
                             VALUES (?, ?, ?, ?)";
            $approval_stmt = $conn->prepare($approval_sql);
            
            if (!$approval_stmt) {
                die("Prepare failed for approval insert: " . $conn->error);
            }
            
            $approval_stmt->bind_param("iiss", $request_id, $dept_head_id, $decision, $comment);
            
            if ($approval_stmt->execute()) {
                $success_message = "Decision saved successfully!";
                // Refresh page to show updated list
                header("Refresh: 2; url=approve_overload.php");
            } else {
                $error_message = "Error saving approval record: " . $approval_stmt->error;
            }
        } else {
            $error_message = "Error updating request status: " . $update_stmt->error;
        }
    }
}

// DEBUG: Show what department we're filtering by
echo "<!-- DEBUG: Department Head Department: $head_department -->";

// Get pending requests for this department head's department
$sql = "SELECT o.request_id, 
               u.full_name, 
               o.course_name, 
               o.credit_hour, 
               o.semester, 
               o.academic_year,
               u.department,
               o.submitted_at,
               o.notes
        FROM overload_requests o
        INNER JOIN teachers t ON o.teacher_id = t.teacher_id
        INNER JOIN users u ON t.user_id = u.user_id
        WHERE o.status = 'pending'";

// Add department filter if head has a department
if (!empty($head_department)) {
    $sql .= " AND u.department = ?";
} else {
    // If department head has no department assigned, show all
    $sql .= " AND 1=1";
}

$sql .= " ORDER BY o.submitted_at ASC";

// DEBUG: Show the SQL query
echo "<!-- DEBUG: SQL Query: " . htmlspecialchars($sql) . " -->";

// Prepare the statement
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed for main query: " . $conn->error . "<br>SQL: " . $sql);
}

// Bind parameter if needed
if (!empty($head_department)) {
    $stmt->bind_param("s", $head_department);
}

// Execute query
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

// Get result
$result = $stmt->get_result();

if (!$result) {
    die("Getting result failed: " . $stmt->error);
}

// DEBUG: Show number of results
echo "<!-- DEBUG: Found " . $result->num_rows . " pending requests -->";

$has_pending_requests = $result->num_rows > 0;

// Debug: Also show all pending requests regardless of department
$debug_sql = "SELECT o.request_id, u.full_name, u.department 
              FROM overload_requests o 
              INNER JOIN teachers t ON o.teacher_id = t.teacher_id 
              INNER JOIN users u ON t.user_id = u.user_id 
              WHERE o.status = 'pending'";
$debug_result = $conn->query($debug_sql);
echo "<!-- DEBUG: All pending requests: " . $debug_result->num_rows . " -->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Overload Requests | Woldiya University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Simple, clean CSS */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            background: #004080;
            color: white;
            padding: 20px;
        }
        
        .sidebar-header {
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            margin: 0 0 20px 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info i {
            font-size: 2rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 10px;
        }
        
        .sidebar-menu a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .request-card {
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .teacher-info h4 {
            margin: 0 0 5px 0;
            color: #004080;
        }
        
        .course-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: bold;
        }
        
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            resize: vertical;
            min-height: 80px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-approve, .btn-reject {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-approve {
            background: #28a745;
        }
        
        .btn-approve:hover {
            background: #218838;
        }
        
        .btn-reject {
            background: #dc3545;
        }
        
        .btn-reject:hover {
            background: #c82333;
        }
        
        .notification {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .notification-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .notification-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
        }
        
        .debug-info {
            background: #f8f9fa;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 12px;
            color: #666;
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
                        <p><?php echo htmlspecialchars($head_department ?: 'Department'); ?></p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="approve_overload.php" class="active"><i class="fas fa-check-circle"></i> Review Requests</a></li>
                <li><a href="approval_history.php"><i class="fas fa-history"></i> Approval History</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-tasks"></i> Pending Overload Requests</h2>
                <div>
                    <span style="background: #004080; color: white; padding: 5px 15px; border-radius: 20px;">
                        <?php echo htmlspecialchars($head_department ?: 'All Departments'); ?>
                    </span>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="notification notification-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="notification notification-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Debug information (remove in production) -->
            <div class="debug-info">
                <strong>Debug Info:</strong><br>
                Department Head ID: <?php echo $dept_head_id; ?><br>
                Department: <?php echo $head_department ?: 'Not assigned'; ?><br>
                Found Requests: <?php echo $result->num_rows; ?>
            </div>
            
            <?php if (!$has_pending_requests): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
                    <h3>No Pending Requests</h3>
                    <p>There are currently no overload requests waiting for your approval in your department.</p>
                    
                    <?php 
                    // Show all pending requests for debugging
                    if ($debug_result->num_rows > 0): 
                    ?>
                        <div style="margin-top: 20px; text-align: left;">
                            <h4>All Pending Requests in System:</h4>
                            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                                <thead>
                                    <tr style="background: #f8f9fa;">
                                        <th style="padding: 8px; border: 1px solid #ddd;">Request ID</th>
                                        <th style="padding: 8px; border: 1px solid #ddd;">Teacher</th>
                                        <th style="padding: 8px; border: 1px solid #ddd;">Department</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($debug_row = $debug_result->fetch_assoc()): ?>
                                    <tr>
                                        <td style="padding: 8px; border: 1px solid #ddd;">#<?php echo $debug_row['request_id']; ?></td>
                                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo htmlspecialchars($debug_row['full_name']); ?></td>
                                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo htmlspecialchars($debug_row['department']); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <h3>Requests to Review (<?php echo $result->num_rows; ?>)</h3>
                
                <?php while ($row = $result->fetch_assoc()): 
                    $submitted_date = date('M d, Y h:i A', strtotime($row['submitted_at']));
                ?>
                <div class="request-card">
                    <div class="request-header">
                        <div class="teacher-info">
                            <h4><?php echo htmlspecialchars($row['full_name']); ?></h4>
                            <div style="font-size: 12px; color: #666;">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($row['department']); ?>
                            </div>
                        </div>
                        <div style="font-size: 12px; color: #666;">
                            <i class="fas fa-hashtag"></i> Request #<?php echo $row['request_id']; ?><br>
                            <i class="fas fa-clock"></i> <?php echo $submitted_date; ?>
                        </div>
                    </div>
                    
                    <div class="course-details">
                        <div class="detail-item">
                            <div class="detail-label">Course Name</div>
                            <div class="detail-value"><?php echo htmlspecialchars($row['course_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Credit Hours</div>
                            <div class="detail-value"><?php echo $row['credit_hour']; ?> credits</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Semester</div>
                            <div class="detail-value"><?php echo htmlspecialchars($row['semester']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Academic Year</div>
                            <div class="detail-value"><?php echo htmlspecialchars($row['academic_year']); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($row['notes'])): ?>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                        <div style="font-size: 12px; color: #666; margin-bottom: 5px;">
                            <i class="fas fa-sticky-note"></i> Teacher's Notes:
                        </div>
                        <div><?php echo htmlspecialchars($row['notes']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                        
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                            <i class="fas fa-comment"></i> Your Decision Comments:
                        </label>
                        <textarea name="comment" placeholder="Explain your decision here..." required></textarea>
                        
                        <div class="action-buttons">
                            <button type="submit" name="action" value="approved" class="btn-approve">
                                <i class="fas fa-check"></i> Approve Request
                            </button>
                            <button type="submit" name="action" value="rejected" class="btn-reject">
                                <i class="fas fa-times"></i> Reject Request
                            </button>
                        </div>
                    </form>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Simple form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const comment = this.querySelector('textarea[name="comment"]').value.trim();
                const action = this.querySelector('button[type="submit"]:focus')?.value;
                
                if (!comment) {
                    e.preventDefault();
                    alert('Please enter comments explaining your decision.');
                    this.querySelector('textarea[name="comment"]').focus();
                    return false;
                }
                
                if (action === 'rejected') {
                    if (!confirm('Are you sure you want to REJECT this request?')) {
                        e.preventDefault();
                        return false;
                    }
                } else if (action === 'approved') {
                    if (!confirm('Are you sure you want to APPROVE this request?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });
    </script>
</body>
</html>