<?php
// department_head/approval_history.php
include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'department_head') {
    die("Access Denied");
}

$dept_head_id = $_SESSION['user_id'];

// Get filter parameters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status = $_GET['status'] ?? '';
$teacher_id = $_GET['teacher_id'] ?? '';

// Build query
$sql = "
SELECT 
    a.approval_id,
    a.request_id,
    a.decision,
    a.comment,
    a.approved_at,
    u.full_name as teacher_name,
    u.department,
    o.course_name,
    o.credit_hour,
    o.semester,
    o.academic_year,
    o.status as request_status,
    p.payment_status,
    p.amount
FROM approvals a
JOIN overload_requests o ON a.request_id = o.request_id
JOIN teachers t ON o.teacher_id = t.teacher_id
JOIN users u ON t.user_id = u.user_id
LEFT JOIN payments p ON o.request_id = p.request_id
WHERE a.department_head_id = ?
";

$params = [$dept_head_id];
$types = "i";

// Add filters
if (!empty($start_date)) {
    $sql .= " AND DATE(a.approved_at) >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if (!empty($end_date)) {
    $sql .= " AND DATE(a.approved_at) <= ?";
    $params[] = $end_date;
    $types .= "s";
}

if (!empty($status) && $status != 'all') {
    $sql .= " AND a.decision = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($teacher_id)) {
    $sql .= " AND u.user_id = ?";
    $params[] = $teacher_id;
    $types .= "i";
}

$sql .= " ORDER BY a.approved_at DESC";

// Prepare and execute
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$approvals = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get teachers for filter dropdown
$teachers_sql = "SELECT DISTINCT u.user_id, u.full_name 
                 FROM approvals a
                 JOIN overload_requests o ON a.request_id = o.request_id
                 JOIN teachers t ON o.teacher_id = t.teacher_id
                 JOIN users u ON t.user_id = u.user_id
                 WHERE a.department_head_id = ?
                 ORDER BY u.full_name";
$teachers_stmt = $conn->prepare($teachers_sql);
$teachers_stmt->bind_param("i", $dept_head_id);
$teachers_stmt->execute();
$teachers_result = $teachers_stmt->get_result();

// Get statistics
$stats_sql = "
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN decision = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN decision = 'rejected' THEN 1 ELSE 0 END) as rejected,
    MIN(approved_at) as first_approval,
    MAX(approved_at) as last_approval
FROM approvals 
WHERE department_head_id = ?
";

if (!empty($start_date)) {
    $stats_sql .= " AND DATE(approved_at) >= '$start_date'";
}
if (!empty($end_date)) {
    $stats_sql .= " AND DATE(approved_at) <= '$end_date'";
}

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $dept_head_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval History | Woldiya University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filter-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
            font-size: 14px;
        }
        .filter-group select, .filter-group input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 4px solid #004080;
        }
        .stat-card.approved {
            border-top-color: #28a745;
        }
        .stat-card.rejected {
            border-top-color: #dc3545;
        }
        .stat-card.info {
            border-top-color: #17a2b8;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #004080;
            margin-bottom: 5px;
        }
        .stat-card.approved .stat-value {
            color: #28a745;
        }
        .stat-card.rejected .stat-value {
            color: #dc3545;
        }
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .approval-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid #004080;
        }
        .approval-card.approved {
            border-left-color: #28a745;
        }
        .approval-card.rejected {
            border-left-color: #dc3545;
        }
        .approval-header {
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
        .decision-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .decision-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .decision-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .request-info {
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
        .comment-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 4px solid #6c757d;
        }
        .comment-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }
        .comment-text {
            color: #495057;
            line-height: 1.5;
            margin: 0;
        }
        .payment-info {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 4px solid #28a745;
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
        .empty-state h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            padding: 20px;
        }
        .page-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
        }
        .page-btn.active {
            background: #004080;
            color: white;
            border-color: #004080;
        }
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            .approval-header {
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
                <li><a href="approval_history.php" class="active"><i class="fas fa-history"></i> Approval History</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-history"></i> Approval History</h2>
                <div class="header-actions">
                    <button onclick="window.print()" class="btn-primary btn-sm">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button onclick="exportToCSV()" class="btn-success btn-sm">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-card">
                <h3><i class="fas fa-filter"></i> Filter History</h3>
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="start_date"><i class="fas fa-calendar-alt"></i> Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date"><i class="fas fa-calendar-alt"></i> End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status"><i class="fas fa-check-circle"></i> Decision</label>
                        <select id="status" name="status">
                            <option value="all">All Decisions</option>
                            <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="teacher_id"><i class="fas fa-user"></i> Teacher</label>
                        <select id="teacher_id" name="teacher_id">
                            <option value="">All Teachers</option>
                            <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['user_id']; ?>" 
                                    <?php echo ($teacher_id == $teacher['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="approval_history.php" class="btn-warning">
                            <i class="fas fa-redo"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Decisions</div>
                </div>
                
                <div class="stat-card approved">
                    <div class="stat-value"><?php echo $stats['approved'] ?? 0; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                
                <div class="stat-card rejected">
                    <div class="stat-value"><?php echo $stats['rejected'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-value">
                        <?php 
                        if ($stats['first_approval'] && $stats['last_approval']) {
                            $first = new DateTime($stats['first_approval']);
                            $last = new DateTime($stats['last_approval']);
                            echo $first->format('M Y') . ' - ' . $last->format('M Y');
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Approval Period</div>
                </div>
            </div>
            
            <!-- Approval History -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Approval Records (<?php echo count($approvals); ?>)</h3>
                    <p class="text-muted">Your decision history for overload requests</p>
                </div>
                
                <?php if (empty($approvals)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h3>No Approval History Found</h3>
                        <p>No approval records match your current filters. Try adjusting your filter criteria.</p>
                        <p>You need to approve or reject some overload requests first.</p>
                        <div class="action-buttons" style="margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
                            <a href="approve_overload.php" class="btn-primary">
                                <i class="fas fa-check-circle"></i> Review Requests
                            </a>
                            <a href="approval_history.php" class="btn-warning">
                                <i class="fas fa-redo"></i> Clear Filters
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="approvals-list">
                        <?php foreach ($approvals as $approval): ?>
                        <div class="approval-card <?php echo $approval['decision']; ?>">
                            <div class="approval-header">
                                <div class="teacher-info">
                                    <h4><?php echo htmlspecialchars($approval['teacher_name']); ?></h4>
                                    <small class="text-muted"><?php echo htmlspecialchars($approval['department']); ?></small>
                                </div>
                                <div class="approval-meta">
                                    <span class="decision-badge decision-<?php echo $approval['decision']; ?>">
                                        <?php echo strtoupper($approval['decision']); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y H:i', strtotime($approval['approved_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="request-info">
                                <div class="info-item">
                                    <div class="info-label">Course</div>
                                    <div class="info-value"><?php echo htmlspecialchars($approval['course_name']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Credit Hours</div>
                                    <div class="info-value"><?php echo $approval['credit_hour']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Semester</div>
                                    <div class="info-value"><?php echo htmlspecialchars($approval['semester']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Academic Year</div>
                                    <div class="info-value"><?php echo htmlspecialchars($approval['academic_year']); ?></div>
                                </div>
                            </div>
                            
                            <?php if (!empty($approval['comment'])): ?>
                            <div class="comment-box">
                                <span class="comment-label">Your Comments</span>
                                <p class="comment-text"><?php echo htmlspecialchars($approval['comment']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($approval['decision'] == 'approved' && $approval['payment_status']): ?>
                            <div class="payment-info">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>Payment Status:</strong> 
                                        <span class="status-badge status-<?php echo $approval['payment_status']; ?>">
                                            <?php echo ucfirst($approval['payment_status']); ?>
                                        </span>
                                    </div>
                                    <?php if ($approval['amount']): ?>
                                    <div>
                                        <strong>Amount:</strong> 
                                        <span style="color: #28a745; font-weight: bold;">
                                            ETB <?php echo number_format($approval['amount'], 2); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="action-buttons" style="margin-top: 15px; display: flex; gap: 10px;">
                                <a href="#" class="btn-primary btn-sm" onclick="viewRequestDetails(<?php echo $approval['request_id']; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <?php if ($approval['decision'] == 'approved' && (!$approval['payment_status'] || $approval['payment_status'] == 'pending')): ?>
                                <span class="text-warning" style="font-size: 12px;">
                                    <i class="fas fa-clock"></i> Awaiting payment processing
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination (simplified) -->
                    <div class="pagination">
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <span style="margin: 0 10px;">...</span>
                        <button class="page-btn">Next</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Export Options -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-download"></i> Export Options</h3>
                </div>
                <div class="export-options" style="display: flex; gap: 15px; padding: 20px;">
                    <button onclick="exportToCSV()" class="btn-success">
                        <i class="fas fa-file-csv"></i> Export as CSV
                    </button>
                    <button onclick="exportToPDF()" class="btn-danger">
                        <i class="fas fa-file-pdf"></i> Export as PDF
                    </button>
                    <button onclick="printReport()" class="btn-primary">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function viewRequestDetails(requestId) {
            // In a real implementation, this would open a modal or new page
            alert(`Viewing details for Request #${requestId}\n\nIn a full implementation, this would show complete request details.`);
        }
        
        function exportToCSV() {
            // Simple CSV export
            const table = document.createElement('table');
            const headers = ['Teacher', 'Course', 'Decision', 'Date', 'Comments'];
            const headerRow = document.createElement('tr');
            
            headers.forEach(header => {
                const th = document.createElement('th');
                th.textContent = header;
                headerRow.appendChild(th);
            });
            table.appendChild(headerRow);
            
            // Add data rows (simplified)
            document.querySelectorAll('.approval-card').forEach(card => {
                const teacher = card.querySelector('.teacher-info h4').textContent;
                const course = card.querySelector('.info-item .info-value').textContent;
                const decision = card.querySelector('.decision-badge').textContent;
                const date = card.querySelector('.text-muted').textContent;
                const comment = card.querySelector('.comment-text') ? card.querySelector('.comment-text').textContent : 'N/A';
                
                const row = document.createElement('tr');
                [teacher, course, decision, date, comment].forEach(text => {
                    const td = document.createElement('td');
                    td.textContent = text;
                    row.appendChild(td);
                });
                table.appendChild(row);
            });
            
            // Convert to CSV
            const csv = [];
            table.querySelectorAll('tr').forEach(row => {
                const rowData = [];
                row.querySelectorAll('th, td').forEach(cell => {
                    rowData.push(`"${cell.textContent.replace(/"/g, '""')}"`);
                });
                csv.push(rowData.join(','));
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `approval-history-${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
            
            alert('CSV export started. Check your downloads folder.');
        }
        
        function exportToPDF() {
            alert('PDF export feature would generate a formatted PDF report.\nIn a full implementation, this would use a PDF library.');
        }
        
        function printReport() {
            const printContent = document.querySelector('.approvals-list').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Approval History Report - Woldiya University</title>
                    <style>
                        body { font-family: Arial; margin: 30px; }
                        h1 { color: #004080; text-align: center; }
                        .print-header { text-align: center; margin-bottom: 30px; }
                        .print-date { text-align: right; margin-bottom: 20px; color: #666; }
                        .approval-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
                        .approval-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
                        .teacher-info h4 { margin: 0; }
                        .decision-badge { padding: 3px 10px; border-radius: 3px; font-size: 12px; }
                        .decision-approved { background: #d4edda; color: #155724; }
                        .decision-rejected { background: #f8d7da; color: #721c24; }
                        .request-info { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10px; }
                        .info-item { background: #f8f9fa; padding: 5px; }
                        .info-label { font-size: 11px; color: #666; }
                        .info-value { font-weight: bold; }
                        @media print {
                            button { display: none; }
                            .filter-card { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h1>Woldiya University</h1>
                        <h3>Approval History Report</h3>
                        <p>Department Head: <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Department Head'); ?></p>
                    </div>
                    <div class="print-date">
                        Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}<br>
                        Total Records: <?php echo count($approvals); ?>
                    </div>
                    ${printContent}
                </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }
        
        // Set default end date to today if not set
        window.addEventListener('load', function() {
            const endDateInput = document.getElementById('end_date');
            if (endDateInput && !endDateInput.value) {
                endDateInput.value = new Date().toISOString().split('T')[0];
            }
            
            // Set default start date to 30 days ago
            const startDateInput = document.getElementById('start_date');
            if (startDateInput && !startDateInput.value) {
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                startDateInput.value = thirtyDaysAgo.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>