<?php
// pending_requests.php
include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'finance') {
    die("Access Denied");
}

// Get pending approval requests (approved by department head but not yet paid)
$sql = "
SELECT 
    o.request_id,
    u.full_name,
    u.department,
    t.academic_rank,
    o.course_name,
    o.credit_hour,
    o.semester,
    o.academic_year,
    o.submitted_at,
    a.approved_at,
    a.decision,
    a.comment as approval_comment
FROM overload_requests o
JOIN teachers t ON o.teacher_id = t.teacher_id
JOIN users u ON t.user_id = u.user_id
LEFT JOIN approvals a ON o.request_id = a.request_id
WHERE o.status = 'approved'
AND NOT EXISTS (
    SELECT 1 FROM payments p WHERE p.request_id = o.request_id
)
ORDER BY a.approved_at DESC
";

$result = $conn->query($sql);
$has_pending = $result ? $result->num_rows > 0 : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Payment Requests | Woldiya University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: #f8f9fa;
            border-radius: 12px;
            margin: 30px 0;
            border: 2px dashed #dee2e6;
        }
        .empty-state i {
            font-size: 72px;
            color: #6c757d;
            margin-bottom: 25px;
            opacity: 0.4;
        }
        .request-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid #004080;
        }
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .teacher-info h4 {
            margin: 0 0 5px 0;
            color: #004080;
        }
        .department-badge {
            display: inline-block;
            background: #e9ecef;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            color: #495057;
        }
        .rank-badge {
            display: inline-block;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .course-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .info-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #212529;
        }
        .approval-info {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 4px solid #28a745;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        @media (max-width: 768px) {
            .request-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
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
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="process_payment.php"><i class="fas fa-money-check-alt"></i> Process Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Payment Reports</a></li>
                <li><a href="pending_requests.php" class="active"><i class="fas fa-clock"></i> Pending Approvals</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-clock"></i> Pending Payment Requests</h2>
                <div class="header-actions">
                    <button onclick="location.reload()" class="btn-primary btn-sm">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <a href="process_payment.php" class="btn-success btn-sm">
                        <i class="fas fa-money-check-alt"></i> Process Payments
                    </a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list-check"></i> Approved Requests Awaiting Payment</h3>
                    <p class="text-muted">These requests have been approved by department heads and are ready for payment processing.</p>
                </div>
                
                <?php if (!$has_pending): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No Pending Payment Requests</h3>
                        <p>All approved overload requests have been processed for payment.</p>
                        <p>New requests will appear here after they are approved by department heads.</p>
                        <div class="action-buttons" style="margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
                            <a href="dashboard.php" class="btn-primary">
                                <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                            </a>
                            <a href="reports.php" class="btn-success">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="requests-list">
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="request-card">
                            <div class="request-header">
                                <div class="teacher-info">
                                    <h4><?php echo htmlspecialchars($row['full_name']); ?></h4>
                                    <div>
                                        <span class="department-badge">
                                            <?php echo htmlspecialchars($row['department']); ?>
                                        </span>
                                        <span class="rank-badge">
                                            <?php echo htmlspecialchars($row['academic_rank']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="request-id">
                                    <small>Request #<?php echo $row['request_id']; ?></small>
                                    <br>
                                    <small class="text-muted">Approved: <?php echo date('M d, Y', strtotime($row['approved_at'])); ?></small>
                                </div>
                            </div>
                            
                            <div class="course-info">
                                <div class="info-item">
                                    <div class="info-label">Course</div>
                                    <div class="info-value"><?php echo htmlspecialchars($row['course_name']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Credit Hours</div>
                                    <div class="info-value"><?php echo $row['credit_hour']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Semester</div>
                                    <div class="info-value"><?php echo htmlspecialchars($row['semester']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Academic Year</div>
                                    <div class="info-value"><?php echo htmlspecialchars($row['academic_year']); ?></div>
                                </div>
                            </div>
                            
                            <?php if (!empty($row['approval_comment'])): ?>
                            <div class="approval-info">
                                <div class="info-label">Approval Comments</div>
                                <p style="margin: 0; color: #155724;"><?php echo htmlspecialchars($row['approval_comment']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="action-buttons">
                                <a href="process_payment.php" class="btn-primary">
                                    <i class="fas fa-money-check-alt"></i> Process Payment
                                </a>
                                <a href="#" class="btn-warning" onclick="calculatePayment(<?php echo $row['request_id']; ?>, <?php echo $row['credit_hour']; ?>)">
                                    <i class="fas fa-calculator"></i> Calculate Amount
                                </a>
                                <a href="reports.php?teacher_id=<?php echo $row['request_id']; ?>" class="btn-info">
                                    <i class="fas fa-history"></i> View History
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Info Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Payment Processing Information</h3>
                </div>
                <div class="info-content">
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>Payment rates are based on academic rank and credit hours</li>
                        <li>Payments should be processed within 5 working days of approval</li>
                        <li>Always verify the approved amount before processing</li>
                        <li>Contact the department head if there are any discrepancies</li>
                        <li>Record transaction references for audit purposes</li>
                        <li>Teachers will receive automatic notifications when payments are processed</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function calculatePayment(requestId, creditHours) {
            // In a real implementation, this would fetch the rate from the server
            // For now, use a prompt
            const rate = prompt("Enter rate per credit hour (ETB):", "5000");
            if (rate && !isNaN(rate)) {
                const total = creditHours * parseFloat(rate);
                alert(`Payment calculation for Request #${requestId}:\n\n` +
                      `Credit Hours: ${creditHours}\n` +
                      `Rate per Credit: ETB ${parseFloat(rate).toFixed(2)}\n` +
                      `Total Amount: ETB ${total.toFixed(2)}\n\n` +
                      `Click "Process Payment" to proceed with this amount.`);
            }
        }
    </script>
</body>
</html>