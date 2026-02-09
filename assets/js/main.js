<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'finance') {
    die("Access Denied");
}

$finance_id = $_SESSION['user_id'];

// Handle payment processing
if (isset($_POST['pay'])) {
    error_log("=== PAYMENT PROCESS STARTED ===");
    $request_id = intval($_POST['request_id']);
    $amount     = floatval($_POST['amount']);
    
    // Validate inputs
    if ($request_id <= 0 || $amount <= 0) {
        $_SESSION['error'] = "Invalid payment data provided.";
        header("Location: process_payment.php");
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First, verify the request exists and is approved
        $verify_sql = "SELECT request_id FROM overload_requests 
                      WHERE request_id = ? AND status = 'approved'";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("i", $request_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            throw new Exception("Request not found or not approved.");
        }
        
        // Check if payment already exists
        $check_sql = "SELECT payment_id FROM payments WHERE request_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $request_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception("Payment for this request has already been processed.");
        }
        
        // Generate transaction reference
        $transaction_ref = "PYMNT" . date('YmdHis') . rand(1000, 9999);
        $notes = "Overload teaching payment processed";
        
        // Debug: Log values
        error_log("Request ID: $request_id");
        error_log("Amount: $amount");
        error_log("Finance ID: $finance_id");
        error_log("Transaction Ref: $transaction_ref");
        error_log("Notes: $notes");
        
        // Insert payment record - FIXED bind_param
        $payment_sql = "INSERT INTO payments (request_id, amount, payment_status, processed_by, payment_date, transaction_ref, notes, created_at)
                       VALUES (?, ?, 'paid', ?, NOW(), ?, ?, NOW())";
        $payment_stmt = $conn->prepare($payment_sql);
        
        // CORRECTED: 5 parameters for 5 placeholders
        // i = integer (request_id)
        // d = double/float (amount)
        // i = integer (finance_id)
        // s = string (transaction_ref)
        // s = string (notes)
        $payment_stmt->bind_param("idiss", $request_id, $amount, $finance_id, $transaction_ref, $notes);
        
        if (!$payment_stmt->execute()) {
            throw new Exception("Failed to process payment: " . $payment_stmt->error);
        }
        
        $payment_id = $conn->insert_id;
        
        // Update overload request status
        $update_sql = "UPDATE overload_requests SET status = 'paid' WHERE request_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $request_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update request status.");
        }
        
        // Get teacher details for notification
        $teacher_sql = "SELECT t.user_id, o.course_name 
                       FROM overload_requests o 
                       JOIN teachers t ON o.teacher_id = t.teacher_id 
                       WHERE o.request_id = ?";
        $teacher_stmt = $conn->prepare($teacher_sql);
        $teacher_stmt->bind_param("i", $request_id);
        $teacher_stmt->execute();
        $teacher_result = $teacher_stmt->get_result();
        
        if ($teacher_row = $teacher_result->fetch_assoc()) {
            $teacher_id = $teacher_row['user_id'];
            $course_name = $teacher_row['course_name'];
            $formatted_amount = number_format($amount, 2);
            
            // Create notification message
            $message = "Payment of ETB $formatted_amount has been processed for your overload teaching: \"$course_name\". Transaction Ref: $transaction_ref";
            
            // Insert notification
            $notif_sql = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                         VALUES (?, 'Payment Processed', ?, 'success', NOW())";
            $notif_stmt = $conn->prepare($notif_sql);
            $notif_stmt->bind_param("is", $teacher_id, $message);
            $notif_stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Payment processed successfully! Payment ID: " . $payment_id . " (Ref: $transaction_ref)";
        header("Location: process_payment.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: process_payment.php");
        exit;
    }
}

// Get messages from session
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Fetch approved overloads without payment
$sql = "
SELECT 
    o.request_id, 
    u.full_name, 
    u.department,
    t.academic_rank, 
    o.course_name, 
    o.credit_hour,
    pr.rate_per_credit,
    o.submitted_at
FROM overload_requests o
JOIN teachers t ON o.teacher_id = t.teacher_id
JOIN users u ON t.user_id = u.user_id
JOIN payment_rates pr ON t.academic_rank = pr.academic_rank
LEFT JOIN payments p ON o.request_id = p.request_id
WHERE o.status = 'approved' 
AND p.payment_id IS NULL
ORDER BY o.submitted_at ASC
";

$result = $conn->query($sql);
$has_pending_payments = $result ? $result->num_rows > 0 : false;

// Store data for display
$rows = [];
$total_amount = 0;
$total_teachers = 0;
$total_credits = 0;

if ($has_pending_payments && $result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $total = $row['credit_hour'] * $row['rate_per_credit'];
        $total_amount += $total;
        $total_teachers++;
        $total_credits += $row['credit_hour'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payments | Woldiya University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .btn-pay {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-pay:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-pay:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background-color: #f8f9fa;
            border-radius: 12px;
            margin: 30px 0;
            border: 2px dashed #dee2e6;
        }
        .payment-summary {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 5px solid #004080;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        .summary-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .summary-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #004080;
            margin-bottom: 5px;
        }
        .summary-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            background: #004080;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .processing-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #28a745;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background-color: #004080;
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        tr:hover {
            background-color: #f8f9fa;
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
        .notification-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
    <!-- Processing Overlay -->
    <div id="processingOverlay" class="processing-overlay">
        <div style="background: white; padding: 30px; border-radius: 10px; text-align: center;">
            <div class="spinner"></div>
            <h3 style="color: #004080; margin-bottom: 10px;">Processing Payment</h3>
            <p>Please wait while we process your payment...</p>
        </div>
    </div>
    
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
                <li><a href="process_payment.php" class="active"><i class="fas fa-money-check-alt"></i> Process Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Payment Reports</a></li>
                <li><a href="pending_requests.php"><i class="fas fa-clock"></i> Pending Approvals</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-money-bill-wave"></i> Process Overload Payments</h2>
                <button onclick="location.reload()" class="btn-primary btn-sm">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="notification notification-success">
                    <div>
                        <i class="fas fa-check-circle"></i> 
                        <strong>Success!</strong>
                        <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                    <button class="notification-close" onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="notification notification-error">
                    <div>
                        <i class="fas fa-exclamation-circle"></i> 
                        <strong>Error!</strong>
                        <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                    <button class="notification-close" onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Payment Summary -->
            <?php if ($has_pending_payments): ?>
                <div class="payment-summary">
                    <h3><i class="fas fa-chart-pie"></i> Payment Summary</h3>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-value"><?php echo $total_teachers; ?></div>
                            <div class="summary-label">Teachers</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo $total_credits; ?></div>
                            <div class="summary-label">Total Credits</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value">ETB <?php echo number_format($total_amount, 2); ?></div>
                            <div class="summary-label">Total Amount</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo count($rows); ?></div>
                            <div class="summary-label">Pending Payments</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!$has_pending_payments): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="font-size: 72px; color: #6c757d; opacity: 0.4; margin-bottom: 25px;"></i>
                    <h3>All Payments Processed</h3>
                    <p>There are currently no approved overload requests waiting for payment.</p>
                    <p>All approved requests have been processed. Check back later for new approvals.</p>
                    <div style="margin-top: 30px;">
                        <a href="dashboard.php" class="btn-primary">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Approved Overloads Pending Payment</h3>
                        <p>Click "Process Payment" to complete payment for each approved request.</p>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Department</th>
                                    <th>Rank</th>
                                    <th>Course</th>
                                    <th>Credits</th>
                                    <th>Rate/Credit</th>
                                    <th>Total Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): 
                                    $total = $row['credit_hour'] * $row['rate_per_credit'];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="department-badge">
                                            <?php echo htmlspecialchars($row['department']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="rank-badge">
                                            <?php echo htmlspecialchars($row['academic_rank']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                    <td align="center"><?php echo $row['credit_hour']; ?></td>
                                    <td align="center">ETB <?php echo number_format($row['rate_per_credit'], 2); ?></td>
                                    <td align="center" style="font-weight: bold; color: #28a745;">
                                        ETB <?php echo number_format($total, 2); ?>
                                    </td>
                                    <td align="center">
                                        <!-- SIMPLE FORM -->
                                        <form method="POST" action="">
                                            <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                            <input type="hidden" name="amount" value="<?php echo $total; ?>">
                                            <button type="submit" name="pay" class="btn-pay" onclick="showProcessing()">
                                                <i class="fas fa-money-bill-wave"></i> Process Payment
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="padding: 20px; background: #f8f9fa; border-top: 1px solid #dee2e6;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>Total Amount:</strong>
                            </div>
                            <div style="font-size: 1.2rem; font-weight: bold; color: #28a745;">
                                ETB <?php echo number_format($total_amount, 2); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Instructions -->
                <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h4><i class="fas fa-info-circle"></i> Important Information</h4>
                    <ul>
                        <li>Each payment is processed individually</li>
                        <li>Teachers receive automatic notifications</li>
                        <li>Payment records are stored for audit purposes</li>
                        <li>Contact IT support for any discrepancies</li>
                    </ul>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Simple processing indicator
        function showProcessing() {
            const overlay = document.getElementById('processingOverlay');
            overlay.style.display = 'flex';
            
            // Disable all submit buttons
            const buttons = document.querySelectorAll('.btn-pay');
            buttons.forEach(btn => {
                btn.disabled = true;
            });
            
            // Safety timeout - hide overlay after 8 seconds
            setTimeout(() => {
                if (overlay.style.display === 'flex') {
                    overlay.style.display = 'none';
                    buttons.forEach(btn => {
                        btn.disabled = false;
                    });
                }
            }, 8000);
        }
        
        // Remove notifications after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.notification').forEach(notification => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(() => notification.remove(), 500);
            });
        }, 5000);
        
        // Ensure overlay is hidden on page load
        window.addEventListener('load', function() {
            const overlay = document.getElementById('processingOverlay');
            overlay.style.display = 'none';
            
            // Re-enable buttons if disabled
            const buttons = document.querySelectorAll('.btn-pay:disabled');
            buttons.forEach(btn => {
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>