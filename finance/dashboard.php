<?php
// finance/dashboard.php
include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'finance') {
    die("Access Denied");
}

// Get statistics
$stats = [];

// Total payments
$result = $conn->query("SELECT COUNT(*) as count, SUM(amount) as total FROM payments");
$stats['payments'] = $result->fetch_assoc();

// Pending approvals
$result = $conn->query("SELECT COUNT(*) as count FROM overload_requests WHERE status = 'approved' 
                       AND NOT EXISTS (SELECT 1 FROM payments WHERE request_id = overload_requests.request_id)");
$stats['pending'] = $result->fetch_assoc();

// Recent payments
$result = $conn->query("SELECT p.*, u.full_name, o.course_name 
                       FROM payments p 
                       JOIN overload_requests o ON p.request_id = o.request_id
                       JOIN teachers t ON o.teacher_id = t.teacher_id
                       JOIN users u ON t.user_id = u.user_id
                       ORDER BY p.payment_date DESC LIMIT 5");
$recent_payments = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard | Woldiya University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar (same as above) -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Finance Officer</h3>
                <div class="user-info">
                    <i class="fas fa-calculator"></i>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Finance Officer'); ?></h4>
                        <p>Finance Department</p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="process_payment.php"><i class="fas fa-money-check-alt"></i> Process Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Payment Reports</a></li>
                <li><a href="pending_requests.php"><i class="fas fa-clock"></i> Pending Approvals</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-tachometer-alt"></i> Finance Dashboard</h2>
                <div class="header-actions">
                    <button onclick="location.reload()" class="btn-primary btn-sm">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #00408020; color: #004080;">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['payments']['count'] ?? 0; ?></h3>
                        <p>Total Payments</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #28a74520; color: #28a745;">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <h3>ETB <?php echo number_format($stats['payments']['total'] ?? 0, 2); ?></h3>
                        <p>Total Amount</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #ffc10720; color: #ffc107;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending']['count'] ?? 0; ?></h3>
                        <p>Pending Payments</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #17a2b820; color: #17a2b8;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php 
                            $teachers = $conn->query("SELECT COUNT(DISTINCT o.teacher_id) as count 
                                                     FROM payments p 
                                                     JOIN overload_requests o ON p.request_id = o.request_id");
                            echo $teachers->fetch_assoc()['count'] ?? 0;
                        ?></h3>
                        <p>Teachers Paid</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="quick-actions" style="display: flex; gap: 15px; flex-wrap: wrap; padding: 20px;">
                    <a href="process_payment.php" class="btn-primary">
                        <i class="fas fa-money-check-alt"></i> Process New Payment
                    </a>
                    <a href="reports.php" class="btn-success">
                        <i class="fas fa-chart-bar"></i> Generate Report
                    </a>
                    <a href="pending_requests.php" class="btn-warning">
                        <i class="fas fa-clock"></i> View Pending
                    </a>
                    <button onclick="window.print()" class="btn-info">
                        <i class="fas fa-print"></i> Print Summary
                    </button>
                </div>
            </div>
            
            <!-- Recent Payments -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Payments</h3>
                    <a href="reports.php" class="btn-primary btn-sm">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Teacher</th>
                                <th>Course</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_payments)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px; color: #6c757d;">
                                        No recent payments found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['course_name']); ?></td>
                                    <td style="font-weight: bold; color: #28a745;">
                                        ETB <?php echo number_format($payment['amount'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $payment['payment_status']; ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>