<?php
include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'finance') {
    die("Access Denied");
}

$finance_id = $_SESSION['user_id'];

// Handle payment processing
if (isset($_POST['pay'])) {
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
        
        // Insert payment record - CORRECTED based on your table structure
        // payments table has: payment_id, request_id, amount, payment_status, processed_by, payment_date, transaction_ref, notes, created_at
        // We need to insert all required fields except payment_id (auto-increment)
        $payment_sql = "INSERT INTO payments (request_id, amount, payment_status, processed_by, payment_date, transaction_ref, notes, created_at)
                       VALUES (?, ?, 'paid', ?, NOW(), ?, ?, NOW())";
        $payment_stmt = $conn->prepare($payment_sql);
        
        // We have 6 parameters to bind:
        // 1. request_id (integer)
        // 2. amount (double/float)
        // 3. processed_by (integer - finance_id)
        // 4. transaction_ref (string)
        // 5. notes (string)
        // Note: 'paid' and NOW() for payment_date/created_at are hardcoded
        
        $payment_stmt->bind_param("idiss", $request_id, $amount, $finance_id, $transaction_ref, $notes);
        
        if (!$payment_stmt->execute()) {
            throw new Exception("Failed to process payment: " . $conn->error);
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
        
        $_SESSION['success'] = "Payment processed successfully! Transaction #" . $payment_id . " (Ref: $transaction_ref)";
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
        }
        .btn-pay:hover {
            background-color: #218838;
        }
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background-color: #f8f9fa;
            border-radius: 12px;
            margin: 30px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background-color: #004080;
            color: white;
            padding: 12px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        .notification {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .notification-success {
            background-color: #d4edda;
            color: #155724;
        }
        .notification-error {
            background-color: #f8d7da;
            color: #721c24;
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
    </style>
</head>
<body>
    <!-- Processing Overlay -->
    <div id="processingOverlay" class="processing-overlay">
        <div style="background: white; padding: 30px; border-radius: 10px; text-align: center;">
            <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #28a745; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <h3 style="color: #004080;">Processing Payment</h3>
            <p>Please wait...</p>
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
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="notification notification-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$has_pending_payments): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="font-size: 72px; color: #6c757d; margin-bottom: 25px;"></i>
                    <h3>All Payments Processed</h3>
                    <p>No approved requests waiting for payment.</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Approved Overloads Pending Payment</h3>
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
                                    <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td><?php echo htmlspecialchars($row['academic_rank']); ?></td>
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
                            <tfoot>
                                <tr>
                                    <td colspan="6" align="right"><strong>Total:</strong></td>
                                    <td align="center" style="font-weight: bold; color: #28a745;">
                                        ETB <?php echo number_format($total_amount, 2); ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function showProcessing() {
            document.getElementById('processingOverlay').style.display = 'flex';
        }
        
        // Remove notifications after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.notification').forEach(notification => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>