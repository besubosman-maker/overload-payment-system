<?php
// teacher/dashboard.php
include("../config/session.php");
include("../config/db.php");

// Debug mode - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SESSION['role'] != 'teacher') {
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];

// Get teacher details
$teacher_sql = "SELECT t.*, u.* FROM teachers t 
                JOIN users u ON t.user_id = u.user_id 
                WHERE u.user_id = ?";
$teacher_stmt = $conn->prepare($teacher_sql);

if (!$teacher_stmt) {
    die("Prepare failed: " . $conn->error);
}

$teacher_stmt->bind_param("i", $user_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();

// Get statistics
$stats = [];

// Total requests
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM overload_requests WHERE teacher_id = " . ($teacher['teacher_id'] ?? 0))->fetch_assoc();

// Pending requests
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM overload_requests WHERE teacher_id = " . ($teacher['teacher_id'] ?? 0) . " AND status = 'pending'")->fetch_assoc();

// Approved requests
$stats['approved'] = $conn->query("SELECT COUNT(*) as count FROM overload_requests WHERE teacher_id = " . ($teacher['teacher_id'] ?? 0) . " AND status = 'approved'")->fetch_assoc();

// Paid requests
$stats['paid'] = $conn->query("SELECT COUNT(*) as count FROM overload_requests o 
                               LEFT JOIN payments p ON o.request_id = p.request_id 
                               WHERE o.teacher_id = " . ($teacher['teacher_id'] ?? 0) . " 
                               AND p.payment_status = 'paid'")->fetch_assoc();

// Total earnings
$earnings_result = $conn->query("SELECT SUM(p.amount) as total FROM payments p 
                                JOIN overload_requests o ON p.request_id = o.request_id 
                                WHERE o.teacher_id = " . ($teacher['teacher_id'] ?? 0) . " 
                                AND p.payment_status = 'paid'");
$stats['earnings'] = $earnings_result->fetch_assoc();

// Recent requests
$recent_sql = "SELECT o.*, p.payment_status, p.amount 
               FROM overload_requests o 
               LEFT JOIN payments p ON o.request_id = p.request_id 
               WHERE o.teacher_id = ? 
               ORDER BY o.submitted_at DESC 
               LIMIT 5";
$recent_stmt = $conn->prepare($recent_sql);
if ($recent_stmt) {
    $recent_stmt->bind_param("i", $teacher['teacher_id']);
    $recent_stmt->execute();
    $recent_result = $recent_stmt->get_result();
    $recent_requests = $recent_result ? $recent_result->fetch_all(MYSQLI_ASSOC) : [];
} else {
    $recent_requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | Woldiya University</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard Specific CSS -->
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
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border-top: 4px solid var(--primary-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card.pending {
            border-top-color: var(--warning-color);
        }
        
        .stat-card.approved {
            border-top-color: var(--success-color);
        }
        
        .stat-card.paid {
            border-top-color: var(--info-color);
        }
        
        .stat-card.earnings {
            border-top-color: #28a745;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f5e9 100%);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.8rem;
            background: var(--light-color);
            color: var(--primary-color);
        }
        
        .stat-card.pending .stat-icon {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .stat-card.approved .stat-icon {
            background-color: #d4edda;
            color: #155724;
        }
        
        .stat-card.paid .stat-icon {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .stat-card.earnings .stat-icon {
            background-color: #d4edda;
            color: #155724;
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 8px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .stat-card.pending .stat-number {
            color: var(--warning-color);
        }
        
        .stat-card.approved .stat-number {
            color: var(--success-color);
        }
        
        .stat-card.paid .stat-number {
            color: var(--info-color);
        }
        
        .stat-card.earnings .stat-number {
            color: #28a745;
            text-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
        }
        
        .stat-label {
            font-size: 0.95rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        
        .quick-action-btn {
            background: white;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 20px 15px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }
        
        .quick-action-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 64, 128, 0.2);
        }
        
        .quick-action-btn i {
            font-size: 2.2rem;
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #004080 0%, #0066cc 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .welcome-content {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .welcome-text h2 {
            color: white;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .welcome-text p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 1.1rem;
        }
        
        .welcome-icon {
            font-size: 4rem;
            opacity: 0.2;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-paid {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        /* Recent Requests Table */
        .recent-requests {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-top: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-top: 15px;
        }
        
        .recent-requests table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .recent-requests th {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
        }
        
        .recent-requests td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            color: #212529;
        }
        
        .recent-requests tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 30px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
            border: 2px dashed #dee2e6;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #6c757d;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto;
        }
        
        /* Teacher Info Card */
        .teacher-info-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 25px;
        }
        
        .teacher-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #004080, #0066cc);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            flex-shrink: 0;
        }
        
        .teacher-details h3 {
            margin: 0 0 10px 0;
            color: var(--primary-color);
        }
        
        .teacher-details p {
            margin: 5px 0;
            color: #6c757d;
        }
        
        .teacher-rank {
            display: inline-block;
            background: #e9ecef;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            color: #495057;
            margin-top: 5px;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .welcome-content {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .teacher-info-card {
                flex-direction: column;
                text-align: center;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .recent-requests {
                padding: 15px;
            }
            
            .recent-requests th,
            .recent-requests td {
                padding: 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Teacher Portal</h3>
                <div class="user-info">
                    <div class="teacher-avatar">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($teacher['full_name'] ?? 'Teacher'); ?></h4>
                        <p><?php echo htmlspecialchars($teacher['academic_rank'] ?? 'Teacher'); ?></p>
                        <p class="teacher-rank"><?php echo htmlspecialchars($teacher['department'] ?? 'Department'); ?></p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="my_courses.php"><i class="fas fa-book"></i> My Courses</a></li>
                <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="submit_overload.php"><i class="fas fa-paper-plane"></i> Submit Overload</a></li>
                <li><a href="view_status.php"><i class="fas fa-clock"></i> View Status</a></li>
                <li><a href="payment_history.php"><i class="fas fa-history"></i> Payment History</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner fade-in">
                <div class="welcome-content">
                    <div class="welcome-text">
                        <h2>Welcome, <?php echo htmlspecialchars($teacher['full_name'] ?? 'Teacher'); ?>!</h2>
                        <p>Track your overload requests and payments in one place.</p>
                    </div>
                    <div class="welcome-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                </div>
            </div>
            
            <!-- Teacher Info Card -->
            <div class="teacher-info-card fade-in">
                <div class="teacher-avatar">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="teacher-details">
                    <h3><?php echo htmlspecialchars($teacher['full_name']); ?></h3>
                    <p><i class="fas fa-award"></i> <?php echo htmlspecialchars($teacher['academic_rank'] ?? 'Teacher'); ?></p>
                    <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($teacher['department']); ?></p>
                    <p><i class="fas fa-calendar-alt"></i> Member since: <?php echo date('F Y', strtotime($teacher['created_at'])); ?></p>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card fade-in">
                    <div class="stat-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total']['count'] ?? 0; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                
                <div class="stat-card pending fade-in" style="animation-delay: 0.1s;">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['pending']['count'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                
                <div class="stat-card approved fade-in" style="animation-delay: 0.2s;">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['approved']['count'] ?? 0; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                
                <div class="stat-card paid fade-in" style="animation-delay: 0.3s;">
                    <div class="stat-icon">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['paid']['count'] ?? 0; ?></div>
                    <div class="stat-label">Paid</div>
                </div>
                
                <div class="stat-card earnings fade-in" style="animation-delay: 0.4s;">
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-number">ETB <?php echo number_format($stats['earnings']['total'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Earnings</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="submit_overload.php" class="quick-action-btn fade-in">
                    <i class="fas fa-plus-circle pulse"></i>
                    <span>Submit New Request</span>
                </a>
                <a href="view_status.php" class="quick-action-btn fade-in" style="animation-delay: 0.1s;">
                    <i class="fas fa-search"></i>
                    <span>Check Status</span>
                </a>
                <a href="payment_history.php" class="quick-action-btn fade-in" style="animation-delay: 0.2s;">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Payment History</span>
                </a>
                <a href="profile.php" class="quick-action-btn fade-in" style="animation-delay: 0.3s;">
                    <i class="fas fa-user-edit"></i>
                    <span>Update Profile</span>
                </a>
            </div>
            
            <!-- Recent Requests -->
            <div class="recent-requests fade-in">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Overload Requests</h3>
                    <a href="view_status.php" class="btn-primary btn-sm">View All</a>
                </div>
                
                <?php if (empty($recent_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>No Requests Yet</h4>
                        <p>You haven't submitted any overload requests yet. Start by submitting your first request!</p>
                        <a href="submit_overload.php" class="btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-plus"></i> Submit First Request
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Credits</th>
                                    <th>Semester</th>
                                    <th>Year</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_requests as $request): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($request['course_name']); ?></strong></td>
                                    <td><?php echo $request['credit_hour']; ?></td>
                                    <td><?php echo htmlspecialchars($request['semester']); ?></td>
                                    <td><?php echo htmlspecialchars($request['academic_year']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($request['payment_status'] == 'paid'): ?>
                                            <span class="status-badge status-paid">
                                                <i class="fas fa-check"></i> Paid
                                            </span>
                                        <?php elseif ($request['status'] == 'approved'): ?>
                                            <span class="text-warning">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($request['submitted_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Tips -->
            <div class="card fade-in" style="margin-top: 25px;">
                <div class="card-header">
                    <h3><i class="fas fa-lightbulb"></i> Quick Tips</h3>
                </div>
                <div class="quick-tips">
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>Submit overload requests at least 2 weeks before the semester starts</li>
                        <li>Check your request status regularly in the "View Status" section</li>
                        <li>Ensure all information is accurate before submitting requests</li>
                        <li>Contact your department head if a request is pending for more than 5 days</li>
                        <li>Payments are processed within 5 working days after approval</li>
                        <li>Update your profile information to ensure accurate payment processing</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../assets/js/main.js"></script>
    
    <!-- Dashboard Specific JavaScript -->
    <script>
        // Wait for DOM to load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Teacher Dashboard loaded');
            
            // Initialize tooltips
            initTooltips();
            
            // Initialize animations
            initAnimations();
            
            // Initialize auto-refresh
            initAutoRefresh();
            
            // Initialize stats counter animation
            initStatsCounters();
            
            // Initialize notifications
            checkNotifications();
        });
        
        function initTooltips() {
            // Add tooltips to action buttons
            const tooltips = {
                'submit-overload': 'Submit new overload teaching request',
                'check-status': 'Check status of your submitted requests',
                'payment-history': 'View your payment history and details',
                'update-profile': 'Update your personal information'
            };
            
            // Add title attributes for tooltips
            document.querySelectorAll('.quick-action-btn').forEach(btn => {
                const text = btn.querySelector('span').textContent.toLowerCase();
                for (const [key, tip] of Object.entries(tooltips)) {
                    if (text.includes(key.replace('-', ' '))) {
                        btn.setAttribute('title', tip);
                        break;
                    }
                }
            });
        }
        
        function initAnimations() {
            // Add hover effects to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Add click animation to buttons
            const buttons = document.querySelectorAll('.btn-primary, .quick-action-btn');
            buttons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    // Create ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.7);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    // Remove ripple after animation
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            
            // Add CSS for ripple animation
            if (!document.getElementById('ripple-styles')) {
                const style = document.createElement('style');
                style.id = 'ripple-styles';
                style.textContent = `
                    @keyframes ripple {
                        to {
                            transform: scale(4);
                            opacity: 0;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        function initAutoRefresh() {
            // Auto-refresh dashboard every 2 minutes
            let refreshInterval;
            let isVisible = true;
            
            // Check if tab is visible
            document.addEventListener('visibilitychange', function() {
                isVisible = !document.hidden;
                if (isVisible) {
                    startAutoRefresh();
                } else {
                    clearInterval(refreshInterval);
                }
            });
            
            function startAutoRefresh() {
                refreshInterval = setInterval(() => {
                    if (isVisible) {
                        refreshDashboard();
                    }
                }, 120000); // 2 minutes
            }
            
            startAutoRefresh();
        }
        
        function refreshDashboard() {
            // Show loading indicator
            const loadingIndicator = document.createElement('div');
            loadingIndicator.id = 'refresh-loading';
            loadingIndicator.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #004080;
                color: white;
                padding: 10px 15px;
                border-radius: 5px;
                z-index: 1000;
                display: flex;
                align-items: center;
                gap: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            `;
            loadingIndicator.innerHTML = `
                <i class="fas fa-sync-alt fa-spin"></i>
                <span>Refreshing dashboard...</span>
            `;
            
            document.body.appendChild(loadingIndicator);
            
            // Simulate refresh (in real app, this would be an AJAX call)
            setTimeout(() => {
                // Remove loading indicator
                loadingIndicator.remove();
                
                // Show success message
                showNotification('Dashboard refreshed successfully!', 'success');
                
                // Actually reload the page for demo
                // location.reload();
            }, 1000);
        }
        
        function initStatsCounters() {
            // Animate stat numbers counting up
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalValue = parseFloat(stat.textContent.replace(/[^0-9.]/g, ''));
                const prefix = stat.textContent.replace(/[0-9.,]/g, '');
                
                if (!isNaN(finalValue) && finalValue > 0) {
                    let startValue = 0;
                    const duration = 2000; // 2 seconds
                    const increment = finalValue / (duration / 16); // 60fps
                    
                    const timer = setInterval(() => {
                        startValue += increment;
                        if (startValue >= finalValue) {
                            stat.textContent = prefix + finalValue.toLocaleString();
                            clearInterval(timer);
                        } else {
                            stat.textContent = prefix + Math.floor(startValue).toLocaleString();
                        }
                    }, 16);
                }
            });
        }
        
        function checkNotifications() {
            // Check for new notifications (simulated)
            const hasNotifications = Math.random() > 0.5;
            
            if (hasNotifications) {
                // Add notification badge to sidebar
                const notificationBadge = document.createElement('span');
                notificationBadge.className = 'notification-badge';
                notificationBadge.style.cssText = `
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    background: #dc3545;
                    color: white;
                    border-radius: 50%;
                    width: 8px;
                    height: 8px;
                    animation: pulse 2s infinite;
                `;
                
                const dashboardLink = document.querySelector('.sidebar-menu li a[href="dashboard.php"]');
                if (dashboardLink) {
                    dashboardLink.style.position = 'relative';
                    dashboardLink.appendChild(notificationBadge);
                }
            }
        }
        
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#004080'};
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                justify-content: space-between;
                min-width: 300px;
                max-width: 400px;
                z-index: 1000;
                animation: slideIn 0.3s ease;
            `;
            
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                    <span>${message}</span>
                </div>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
            
            // Add animations if not exists
            if (!document.getElementById('notification-animations')) {
                const style = document.createElement('style');
                style.id = 'notification-animations';
                style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOut {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        // Export function for external use
        window.teacherDashboard = {
            refresh: refreshDashboard,
            showNotification: showNotification,
            getStats: function() {
                return {
                    total: <?php echo $stats['total']['count'] ?? 0; ?>,
                    pending: <?php echo $stats['pending']['count'] ?? 0; ?>,
                    approved: <?php echo $stats['approved']['count'] ?? 0; ?>,
                    paid: <?php echo $stats['paid']['count'] ?? 0; ?>,
                    earnings: <?php echo $stats['earnings']['total'] ?? 0; ?>
                };
            }
        };
        
        // Print dashboard
        function printDashboard() {
            window.print();
        }
        
        // Export dashboard data
        function exportDashboardData() {
            const data = {
                teacher: "<?php echo htmlspecialchars($teacher['full_name']); ?>",
                department: "<?php echo htmlspecialchars($teacher['department']); ?>",
                stats: window.teacherDashboard.getStats(),
                timestamp: new Date().toISOString()
            };
            
            const dataStr = JSON.stringify(data, null, 2);
            const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
            
            const exportFileDefaultName = `teacher-dashboard-<?php echo $teacher['username']; ?>-${new Date().toISOString().split('T')[0]}.json`;
            
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', exportFileDefaultName);
            linkElement.click();
            
            showNotification('Dashboard data exported successfully!', 'success');
        }
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + R to refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshDashboard();
            }
            
            // Ctrl + P to print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printDashboard();
            }
            
            // Ctrl + E to export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportDashboardData();
            }
        });
        
        // Add help modal
        function showHelp() {
            const helpModal = document.createElement('div');
            helpModal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 2000;
            `;
            
            helpModal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%;">
                    <h3 style="margin-top: 0; color: #004080;">
                        <i class="fas fa-question-circle"></i> Teacher Dashboard Help
                    </h3>
                    <div style="margin: 20px 0;">
                        <h4>Keyboard Shortcuts:</h4>
                        <ul>
                            <li><strong>Ctrl + R:</strong> Refresh dashboard</li>
                            <li><strong>Ctrl + P:</strong> Print dashboard</li>
                            <li><strong>Ctrl + E:</strong> Export dashboard data</li>
                        </ul>
                        
                        <h4>Features:</h4>
                        <ul>
                            <li>View your overload request statistics</li>
                            <li>Submit new overload requests</li>
                            <li>Track request status and payments</li>
                            <li>Update your profile information</li>
                            <li>Auto-refresh every 2 minutes</li>
                        </ul>
                    </div>
                    <div style="text-align: right;">
                        <button onclick="this.closest('[style]').remove()" class="btn-primary">
                            Close
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(helpModal);
        }
        
        // Add help button
        const helpButton = document.createElement('button');
        helpButton.innerHTML = '<i class="fas fa-question-circle"></i>';
        helpButton.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #004080;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(0,64,128,0.3);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        helpButton.onclick = showHelp;
        document.body.appendChild(helpButton);
    </script>
</body>
</html>