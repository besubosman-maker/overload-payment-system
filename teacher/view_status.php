<?php
include("../config/session.php");
include("../config/db.php");

$user_id = $_SESSION['user_id'];

// Get teacher_id
$teacher_result = $conn->query("SELECT teacher_id FROM teachers WHERE user_id=$user_id");
$teacher = $teacher_result->fetch_assoc();
$teacher_id = $teacher['teacher_id'];

// Fetch overload requests
$sql = "
SELECT o.request_id, o.course_name, o.credit_hour, o.semester, 
       o.academic_year, o.status, o.submitted_at,
       COALESCE(p.amount, 0) as amount,
       p.payment_status,
       a.decision as approval_decision,
       a.comment as approval_comment,
       a.decision_date as approval_date
FROM overload_requests o
LEFT JOIN payments p ON o.request_id = p.request_id
LEFT JOIN approvals a ON o.request_id = a.request_id
WHERE o.teacher_id = $teacher_id
ORDER BY o.submitted_at DESC
";

$result = $conn->query($sql);

// Calculate statistics
$stats_sql = "
SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    COALESCE(SUM(p.amount), 0) as total_paid
FROM overload_requests o
LEFT JOIN payments p ON o.request_id = p.request_id
WHERE o.teacher_id = $teacher_id
";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overload Status | Woldiya University</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* view-status.css - Integrated */
        :root {
            --primary-color: #004080;
            --secondary-color: #0066cc;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-radius: 12px;
            --box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        /* Main Container */
        .status-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            color: var(--dark-color);
        }

        .page-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .page-header p {
            font-size: 1.1rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: none;
        }

        .stat-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--primary-color)20, var(--secondary-color)20);
            color: var(--primary-color);
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 0.95rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card.pending .stat-icon { background: rgba(255, 193, 7, 0.1); color: var(--warning-color); }
        .stat-card.approved .stat-icon { background: rgba(40, 167, 69, 0.1); color: var(--success-color); }
        .stat-card.rejected .stat-icon { background: rgba(220, 53, 69, 0.1); color: var(--danger-color); }
        .stat-card.paid .stat-icon { background: rgba(0, 64, 128, 0.1); color: var(--primary-color); }

        /* Filters */
        .filters-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .filters-header h3 {
            color: var(--primary-color);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover,
        .filter-btn.active {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(0, 64, 128, 0.05);
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
        }

        /* Requests Table */
        .requests-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .requests-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .requests-header h3 {
            color: var(--primary-color);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .status-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .status-table th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
        }

        .status-table th i {
            margin-right: 8px;
        }

        .status-table td {
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .status-table tr:hover {
            background: #f8fafc;
        }

        .status-table tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-approved {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .status-paid {
            background: rgba(0, 64, 128, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(0, 64, 128, 0.3);
        }

        /* Course Info */
        .course-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .course-name {
            font-weight: 600;
            color: var(--dark-color);
        }

        .course-details {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            gap: 10px;
        }

        .course-details span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view {
            background: rgba(0, 64, 128, 0.1);
            color: var(--primary-color);
        }

        .btn-view:hover {
            background: rgba(0, 64, 128, 0.2);
        }

        .btn-print {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .btn-print:hover {
            background: rgba(108, 117, 125, 0.2);
        }

        /* Details Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            padding: 20px;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: white;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
        }

        .detail-item {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value {
            font-size: 1rem;
            color: var(--dark-color);
            font-weight: 500;
        }

        .comment-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            margin-top: 10px;
        }

        .comment-box .detail-label {
            color: var(--primary-color);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: var(--border-radius);
            margin: 20px 0;
            border: 2px dashed #dee2e6;
        }

        .empty-state i {
            font-size: 80px;
            color: var(--primary-color);
            margin-bottom: 25px;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: var(--dark-color);
            margin-bottom: 15px;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .empty-state p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto 25px;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        /* Footer Actions */
        .footer-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-primary {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 16px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 64, 128, 0.2);
        }

        .btn-secondary {
            flex: 1;
            background: white;
            color: var(--dark-color);
            padding: 16px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-3px);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .page-header h2 {
                font-size: 2rem;
                flex-direction: column;
                gap: 10px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .filters-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .filter-buttons {
                width: 100%;
            }
            
            .filter-btn {
                flex: 1;
                justify-content: center;
            }
            
            .requests-container {
                padding: 20px 15px;
            }
            
            .status-table th,
            .status-table td {
                padding: 14px 16px;
            }
            
            .footer-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .page-header h2 {
                font-size: 1.8rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }
            
            .stat-value {
                font-size: 1.8rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
            
            .modal {
                padding: 0;
            }
            
            .modal-header,
            .modal-body {
                padding: 20px;
            }
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .page-header h2,
            .page-header p,
            .stats-container,
            .filters-container,
            .footer-actions,
            .btn-action {
                display: none;
            }
            
            .requests-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .status-table {
                min-width: auto;
            }
            
            .status-table th {
                background: #f2f2f2 !important;
                color: black !important;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* Animations */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(0, 100, 255, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(0, 100, 255, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(0, 100, 255, 0);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Loading State */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading:after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            100% {
                left: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="status-container">
        <!-- Header -->
        <div class="page-header">
            <h2><i class="fas fa-tasks"></i> Overload Request Status</h2>
            <p>Track the status of all your submitted overload teaching requests</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_requests'] ?? 0; ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            
            <div class="stat-card approved">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['approved'] ?? 0; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            
            <div class="stat-card paid">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_paid'] ?? 0); ?> ETB</div>
                <div class="stat-label">Total Paid</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <div class="filters-header">
                <h3><i class="fas fa-filter"></i> Filter Requests</h3>
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterRequests('all')">
                        <i class="fas fa-layer-group"></i> All
                    </button>
                    <button class="filter-btn" onclick="filterRequests('pending')">
                        <i class="fas fa-clock"></i> Pending
                    </button>
                    <button class="filter-btn" onclick="filterRequests('approved')">
                        <i class="fas fa-check-circle"></i> Approved
                    </button>
                    <button class="filter-btn" onclick="filterRequests('rejected')">
                        <i class="fas fa-times-circle"></i> Rejected
                    </button>
                    <button class="filter-btn" onclick="filterRequests('paid')">
                        <i class="fas fa-money-bill-wave"></i> Paid
                    </button>
                </div>
            </div>
        </div>

        <!-- Requests Table -->
        <div class="requests-container">
            <div class="requests-header">
                <h3><i class="fas fa-history"></i> Request History</h3>
                <button class="btn-action btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <div class="table-container">
                    <table class="status-table" id="requestsTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> ID</th>
                                <th><i class="fas fa-book"></i> Course Details</th>
                                <th><i class="fas fa-calendar-alt"></i> Semester/Year</th>
                                <th><i class="fas fa-clock"></i> Submitted</th>
                                <th><i class="fas fa-tasks"></i> Status</th>
                                <th><i class="fas fa-money-bill-wave"></i> Payment</th>
                                <th><i class="fas fa-cog"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): 
                                // Determine status badge
                                $status_class = 'status-' . $row['status'];
                                $status_text = ucfirst($row['status']);
                                
                                // Payment status
                                $payment_status = $row['payment_status'] ?? 'unpaid';
                                $payment_class = $payment_status == 'paid' ? 'status-paid' : 'status-pending';
                                $payment_text = $payment_status == 'paid' ? 'Paid' : 'Pending';
                                
                                // Format dates
                                $submitted_date = date('M d, Y', strtotime($row['submitted_at']));
                                $submitted_time = date('h:i A', strtotime($row['submitted_at']));
                            ?>
                            <tr class="request-row" 
                                data-status="<?php echo $row['status']; ?>"
                                data-payment="<?php echo $payment_status; ?>"
                                data-id="<?php echo $row['request_id']; ?>">
                                <td>#<?php echo $row['request_id']; ?></td>
                                <td>
                                    <div class="course-info">
                                        <div class="course-name"><?php echo htmlspecialchars($row['course_name']); ?></div>
                                        <div class="course-details">
                                            <span><i class="fas fa-clock"></i> <?php echo $row['credit_hour']; ?> Credits</span>
                                            <span><i class="fas fa-calendar"></i> Sem <?php echo $row['semester']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['academic_year']); ?></strong>
                                </td>
                                <td>
                                    <div><?php echo $submitted_date; ?></div>
                                    <div style="font-size: 0.85rem; color: #666;"><?php echo $submitted_time; ?></div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['amount'] > 0): ?>
                                        <span class="status-badge <?php echo $payment_class; ?>">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                            <?php echo number_format($row['amount']); ?> ETB
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                            <?php echo $payment_text; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-view" onclick="viewDetails(<?php echo $row['request_id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn-action btn-print" onclick="printRequest(<?php echo $row['request_id']; ?>)">
                                            <i class="fas fa-print"></i> Print
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Requests Found</h3>
                    <p>You haven't submitted any overload teaching requests yet.</p>
                    <a href="submit_overload.php" class="btn-primary" style="margin-top: 20px; display: inline-flex; width: auto;">
                        <i class="fas fa-plus-circle"></i> Submit Your First Request
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer Actions -->
        <div class="footer-actions">
            <a href="submit_overload.php" class="btn-primary">
                <i class="fas fa-plus-circle"></i> Submit New Request
            </a>
            <a href="dashboard.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="payment_history.php" class="btn-secondary">
                <i class="fas fa-history"></i> Payment History
            </a>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal-overlay" id="detailsModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Request Details</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Filter requests by status
        function filterRequests(status) {
            const rows = document.querySelectorAll('.request-row');
            const filterBtns = document.querySelectorAll('.filter-btn');
            
            // Update active filter button
            filterBtns.forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.toLowerCase().includes(status)) {
                    btn.classList.add('active');
                }
            });
            
            // Show/hide rows based on filter
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const rowPayment = row.getAttribute('data-payment');
                
                if (status === 'all') {
                    row.style.display = '';
                } else if (status === 'paid') {
                    row.style.display = rowPayment === 'paid' ? '' : 'none';
                } else {
                    row.style.display = rowStatus === status ? '' : 'none';
                }
            });
        }

        // View request details
        async function viewDetails(requestId) {
            try {
                // Show loading
                const modalContent = document.getElementById('modalContent');
                modalContent.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                        <p style="margin-top: 15px; color: #666;">Loading details...</p>
                    </div>
                `;
                
                // Show modal
                document.getElementById('detailsModal').classList.add('active');
                
                // In a real application, you would fetch details from server
                // For now, we'll use static data
                setTimeout(() => {
                    loadRequestDetails(requestId);
                }, 500);
            } catch (error) {
                console.error('Error loading details:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <h4 style="margin: 0 0 10px 0;">Error</h4>
                            <p style="margin: 0;">Failed to load request details. Please try again.</p>
                        </div>
                    </div>
                `;
            }
        }

        function loadRequestDetails(requestId) {
            // This would normally come from an AJAX request
            // For demonstration, we'll use static data
            const modalContent = document.getElementById('modalContent');
            
            // Find the row with this requestId
            const row = document.querySelector(`.request-row[data-id="${requestId}"]`);
            if (!row) {
                modalContent.innerHTML = '<p>Request details not found.</p>';
                return;
            }
            
            // Extract data from the row
            const courseName = row.querySelector('.course-name').textContent;
            const courseDetails = row.querySelector('.course-details').textContent;
            const semesterYear = row.cells[2].textContent;
            const submittedDate = row.cells[3].textContent;
            const statusBadge = row.querySelector('.status-badge').cloneNode(true);
            const paymentBadge = row.querySelector('td:nth-child(6) .status-badge').cloneNode(true);
            
            modalContent.innerHTML = `
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-hashtag"></i> Request ID
                    </div>
                    <div class="detail-value">#${requestId}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-book"></i> Course Information
                    </div>
                    <div class="detail-value">${courseName}</div>
                    <div style="font-size: 0.9rem; color: #666; margin-top: 5px;">
                        ${courseDetails}
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-calendar-alt"></i> Academic Period
                    </div>
                    <div class="detail-value">${semesterYear}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-clock"></i> Submission Details
                    </div>
                    <div class="detail-value">Submitted on ${submittedDate}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-tasks"></i> Request Status
                    </div>
                    <div style="margin-top: 10px;">${statusBadge.outerHTML}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-money-bill-wave"></i> Payment Status
                    </div>
                    <div style="margin-top: 10px;">${paymentBadge.outerHTML}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-comments"></i> Approval Comments
                    </div>
                    <div class="comment-box">
                        <div class="detail-label" style="margin-bottom: 10px;">
                            <i class="fas fa-user-tie"></i> Department Head Comments
                        </div>
                        <div class="detail-value">
                            Request is under review. You will be notified once a decision is made.
                        </div>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-info-circle"></i> Additional Information
                    </div>
                    <div class="detail-value">
                        <ul style="margin: 10px 0 0 20px; color: #666; font-size: 0.95rem;">
                            <li>Average processing time: 3-5 working days</li>
                            <li>Payment processing: 7-10 working days after approval</li>
                            <li>Contact department head for urgent requests</li>
                        </ul>
                    </div>
                </div>
            `;
        }

        // Print individual request
        function printRequest(requestId) {
            // Create a print window with request details
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Overload Request #${requestId}</title>
                    <style>
                        body { font-family: Arial; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .header h2 { color: #004080; }
                        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        .info-table th, .info-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                        .info-table th { background-color: #f2f2f2; }
                        .status-badge { padding: 5px 10px; border-radius: 4px; font-weight: bold; }
                        .status-pending { background: #fff3cd; color: #856404; }
                        .status-approved { background: #d4edda; color: #155724; }
                        .footer { margin-top: 40px; text-align: center; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>Woldiya University</h2>
                        <h3>Overload Request Report</h3>
                        <p>Request ID: #${requestId}</p>
                    </div>
                    <p>Printed on: ${new Date().toLocaleDateString()}</p>
                    <table class="info-table">
                        <tr><th>Field</th><th>Details</th></tr>
                        <tr><td>Request ID</td><td>#${requestId}</td></tr>
                        <tr><td>Print Date</td><td>${new Date().toLocaleDateString()}</td></tr>
                        <tr><td>Printed By</td><td><?php echo $_SESSION['username'] ?? 'User'; ?></td></tr>
                    </table>
                    <div class="footer">
                        <p>This is an official document from Woldiya University Overload Payment System</p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => printWindow.print(), 250);
        }

        // Close modal
        function closeModal() {
            document.getElementById('detailsModal').classList.remove('active');
        }

        // Close modal on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });

        // Close modal when clicking outside
        document.getElementById('detailsModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('detailsModal')) {
                closeModal();
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const rows = document.querySelectorAll('.request-row');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.1)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
            
            // Add search functionality
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Search requests...';
            searchInput.style.cssText = `
                padding: 10px 15px;
                border: 2px solid #e2e8f0;
                border-radius: 8px;
                width: 100%;
                max-width: 300px;
                font-size: 0.95rem;
                margin-left: auto;
            `;
            
            // Add search input to filters header
            const filtersHeader = document.querySelector('.filters-header');
            filtersHeader.appendChild(searchInput);
            
            // Search functionality
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.request-row');
                
                rows.forEach(row => {
                    const rowText = row.textContent.toLowerCase();
                    row.style.display = rowText.includes(searchTerm) ? '' : 'none';
                });
            });
        });

        // Refresh data
        function refreshData() {
            const refreshBtn = event.target;
            const originalHTML = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            refreshBtn.disabled = true;
            
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
    </script>
</body>
</html>