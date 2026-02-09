<?php
// department_head/profile.php
include("../config/session.php");
include("../config/db.php");

// Debug mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SESSION['role'] != 'department_head') {
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];

// Get user profile with department head details
$sql = "SELECT u.* FROM users u WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get department statistics
$dept_stats_sql = "SELECT 
    COUNT(DISTINCT u.user_id) as total_teachers,
    COUNT(CASE WHEN o.status = 'pending' THEN 1 END) as pending_requests,
    COUNT(CASE WHEN o.status = 'approved' THEN 1 END) as approved_requests,
    COUNT(CASE WHEN o.status = 'rejected' THEN 1 END) as rejected_requests
FROM users u
LEFT JOIN teachers t ON u.user_id = t.user_id
LEFT JOIN overload_requests o ON t.teacher_id = o.teacher_id
WHERE u.department = ? AND u.role = 'teacher'";

$dept_stats_stmt = $conn->prepare($dept_stats_sql);
if ($dept_stats_stmt) {
    $dept_stats_stmt->bind_param("s", $user['department']);
    $dept_stats_stmt->execute();
    $dept_stats_result = $dept_stats_stmt->get_result();
    $dept_stats = $dept_stats_result->fetch_assoc();
} else {
    $dept_stats = [
        'total_teachers' => 0,
        'pending_requests' => 0,
        'approved_requests' => 0,
        'rejected_requests' => 0
    ];
}

// Update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    $update_sql = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    
    if (!$update_stmt) {
        die("Prepare failed for update: " . $conn->error);
    }
    
    $update_stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);
    
    if ($update_stmt->execute()) {
        $success_message = "Profile updated successfully!";
        // Refresh user data
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        // Update session
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
    } else {
        $error_message = "Error updating profile: " . $conn->error;
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = md5($_POST['current_password']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $check_sql = "SELECT password FROM users WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    if ($check_stmt) {
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_user = $check_result->fetch_assoc();
        
        if ($check_user['password'] !== $current_password) {
            $password_error = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $password_error = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $password_error = "Password must be at least 6 characters long.";
        } else {
            $new_password_hash = md5($new_password);
            $update_pass_sql = "UPDATE users SET password = ? WHERE user_id = ?";
            $update_pass_stmt = $conn->prepare($update_pass_sql);
            if ($update_pass_stmt) {
                $update_pass_stmt->bind_param("si", $new_password_hash, $user_id);
                if ($update_pass_stmt->execute()) {
                    $password_success = "Password changed successfully!";
                } else {
                    $password_error = "Error changing password.";
                }
            }
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
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            padding: 20px 0;
        }
        .profile-header {
            background: linear-gradient(135deg, #004080 0%, #0066cc 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .profile-image-section {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .profile-image i {
            font-size: 5rem;
            color: #004080;
        }
        .profile-basic h1 {
            margin-bottom: 10px;
            color: white;
        }
        .profile-title {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 10px;
            font-weight: 600;
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
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-active {
            background-color: #28a745;
            color: white;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-item label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .info-item p {
            color: #333;
            font-size: 1rem;
        }
        .department-stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid #004080;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #004080;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .form-tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        .form-tab {
            padding: 12px 24px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: #6c757d;
            border-bottom: 3px solid transparent;
        }
        .form-tab.active {
            color: #004080;
            border-bottom-color: #004080;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            padding: 5px;
            border-radius: 4px;
            display: inline-block;
        }
        .password-strength.weak {
            color: #dc3545;
            background-color: #f8d7da;
        }
        .password-strength.fair {
            color: #ffc107;
            background-color: #fff3cd;
        }
        .password-strength.good {
            color: #17a2b8;
            background-color: #d1ecf1;
        }
        .password-strength.strong {
            color: #28a745;
            background-color: #d4edda;
        }
        .decision-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .decision-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .decision-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .decision-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        @media (max-width: 768px) {
            .profile-image-section {
                flex-direction: column;
                text-align: center;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .form-tabs {
                flex-direction: column;
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
                        <p>Department Head</p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="approve_overload.php"><i class="fas fa-check-circle"></i> Review Requests</a></li>
                <li><a href="approval_history.php"><i class="fas fa-history"></i> Approval History</a></li>
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
            
            <?php if (isset($success_message)): ?>
                <div class="notification notification-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="notification notification-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($password_success)): ?>
                <div class="notification notification-success">
                    <i class="fas fa-check-circle"></i> <?php echo $password_success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($password_error)): ?>
                <div class="notification notification-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $password_error; ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-container">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-image-section">
                        <div class="profile-image">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="profile-basic">
                            <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                            <p class="profile-title">Department Head</p>
                            <p class="profile-department">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($user['department']); ?>
                            </p>
                            <div class="profile-status">
                                <span class="status-badge status-active">Active</span>
                                <span>Member since: <?php echo date('F Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Department Statistics -->
                <div class="department-stats">
                    <h3><i class="fas fa-chart-bar"></i> Department Overview</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $dept_stats['total_teachers']; ?></div>
                            <div class="stat-label">Total Teachers</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $dept_stats['pending_requests']; ?></div>
                            <div class="stat-label">Pending Requests</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $dept_stats['approved_requests']; ?></div>
                            <div class="stat-label">Approved</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $dept_stats['rejected_requests']; ?></div>
                            <div class="stat-label">Rejected</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-tabs">
                    <button class="form-tab active" onclick="showTab('personalInfo')">Personal Information</button>
                    <button class="form-tab" onclick="showTab('security')">Security</button>
                    <button class="form-tab" onclick="showTab('activity')">Activity</button>
                </div>
                
                <!-- Personal Information Tab -->
                <div id="personalInfo" class="tab-content active">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-id-card"></i> Personal Information</h3>
                            <button class="btn-primary btn-sm" onclick="toggleEdit('personalInfo')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                        <div id="personalInfoContent">
                            <div class="info-grid">
                                <div class="info-item">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-phone"></i> Phone</label>
                                    <p><?php echo htmlspecialchars($user['phone']); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-user-tag"></i> Role</label>
                                    <p>Department Head</p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-user-circle"></i> Username</label>
                                    <p><?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fas fa-building"></i> Department</label>
                                    <p><?php echo htmlspecialchars($user['department']); ?></p>
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
                            <form method="POST">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Full Name *</label>
                                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Email *</label>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Department</label>
                                        <input type="text" value="<?php echo htmlspecialchars($user['department']); ?>" readonly disabled>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="update_profile" class="btn-success">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <button type="button" class="btn-warning" onclick="toggleEdit('personalInfo')">
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
                        <div class="security-section">
                            <form method="POST">
                                <h4>Change Password</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Current Password *</label>
                                        <input type="password" name="current_password" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>New Password *</label>
                                        <input type="password" name="new_password" required minlength="6" id="newPassword">
                                        <small class="text-muted">Minimum 6 characters</small>
                                        <div id="password-strength" class="password-strength" style="display: none;"></div>
                                    </div>
                                    <div class="form-group">
                                        <label>Confirm New Password *</label>
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
                
                <!-- Activity Tab -->
                <div id="activity" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-history"></i> Recent Activity</h3>
                        </div>
                        <div class="activity-list">
                            <?php
                            // Get recent approvals
                            $activity_sql = "SELECT 
                                a.decision, 
                                o.course_name,
                                u.full_name as teacher_name,
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
                                <table style="width: 100%;">
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
                                <p style="text-align: center; padding: 20px; color: #6c757d;">
                                    No recent activity found. Your approvals will appear here.
                                </p>
                            <?php endif; 
                            } else {
                                echo '<p style="text-align: center; padding: 20px; color: #6c757d;">Unable to load activity.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
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
        
        function toggleEdit(tabName) {
            const displaySection = document.getElementById(tabName + 'Content');
            const editSection = document.getElementById(tabName + 'Edit');
            
            if (!displaySection || !editSection) {
                console.error('Could not find edit sections for:', tabName);
                return;
            }
            
            if (displaySection.style.display === 'none') {
                displaySection.style.display = 'block';
                editSection.style.display = 'none';
            } else {
                displaySection.style.display = 'none';
                editSection.style.display = 'block';
            }
        }
        
        // Password strength indicator
        document.getElementById('newPassword')?.addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthIndicator.style.display = 'none';
                return;
            }
            
            strengthIndicator.style.display = 'inline-block';
            const strength = checkPasswordStrength(password);
            strengthIndicator.textContent = 'Strength: ' + strength;
            strengthIndicator.className = 'password-strength ' + strength.toLowerCase();
        });
        
        function checkPasswordStrength(password) {
            if (password.length < 6) return 'Weak';
            if (password.length < 8) return 'Fair';
            if (/[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
                return 'Strong';
            }
            return 'Good';
        }
        
        // Auto-hide notifications after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.notification').forEach(notification => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(() => notification.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>