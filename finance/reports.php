<?php
include("../config/session.php");
include("../config/db.php");

// Debug mode - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SESSION['role'] != 'finance') {
    die("Access Denied");
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$teacher_id = $_GET['teacher_id'] ?? '';
$department = $_GET['department'] ?? '';

// Build the query with filters
$sql = "
SELECT 
    p.payment_id,
    u.full_name, 
    u.department,
    o.course_name, 
    p.amount, 
    p.payment_date,
    p.payment_status,
    p.transaction_ref,
    t.academic_rank
FROM payments p
JOIN overload_requests o ON p.request_id = o.request_id
JOIN teachers t ON o.teacher_id = t.teacher_id
JOIN users u ON t.user_id = u.user_id
WHERE 1=1
";

$params = [];
$types = '';

// Add filters
if (!empty($start_date)) {
    $sql .= " AND DATE(p.payment_date) >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if (!empty($end_date)) {
    $sql .= " AND DATE(p.payment_date) <= ?";
    $params[] = $end_date;
    $types .= 's';
}

if (!empty($teacher_id)) {
    $sql .= " AND u.user_id = ?";
    $params[] = $teacher_id;
    $types .= 'i';
}

if (!empty($department)) {
    $sql .= " AND u.department = ?";
    $params[] = $department;
    $types .= 's';
}

$sql .= " ORDER BY p.payment_date DESC";

echo "<!-- Debug: SQL Query = $sql -->\n";
echo "<!-- Debug: Params = " . implode(', ', $params) . " -->\n";

// Prepare and execute query
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error . "<br>SQL: " . $sql);
}

// Bind parameters if any
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
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

// Get summary statistics
$summary_sql = "
SELECT 
    COUNT(*) as total_payments,
    SUM(p.amount) as total_amount,
    AVG(p.amount) as average_payment,
    MIN(p.payment_date) as first_payment,
    MAX(p.payment_date) as last_payment
FROM payments p
JOIN overload_requests o ON p.request_id = o.request_id
JOIN teachers t ON o.teacher_id = t.teacher_id
JOIN users u ON t.user_id = u.user_id
WHERE 1=1
";

// Add same filters to summary
if (!empty($start_date)) {
    $summary_sql .= " AND DATE(p.payment_date) >= '$start_date'";
}
if (!empty($end_date)) {
    $summary_sql .= " AND DATE(p.payment_date) <= '$end_date'";
}
if (!empty($teacher_id)) {
    $summary_sql .= " AND u.user_id = $teacher_id";
}
if (!empty($department)) {
    $summary_sql .= " AND u.department = '$department'";
}

$summary_result = $conn->query($summary_sql);
$summary = $summary_result ? $summary_result->fetch_assoc() : null;

// Get distinct departments for filter dropdown
$departments_sql = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = $conn->query($departments_sql);

// Get teachers for filter dropdown
$teachers_sql = "SELECT u.user_id, u.full_name, u.department 
                 FROM users u 
                 JOIN teachers t ON u.user_id = t.user_id 
                 WHERE u.role = 'teacher' 
                 ORDER BY u.full_name";
$teachers_result = $conn->query($teachers_sql);

$has_payments = $result->num_rows > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reports | Woldiya University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .report-filters {
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
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 4px solid #004080;
        }
        .summary-card.success {
            border-top-color: #28a745;
        }
        .summary-card.warning {
            border-top-color: #ffc107;
        }
        .summary-card.info {
            border-top-color: #17a2b8;
        }
        .summary-value {
            font-size: 2rem;
            font-weight: bold;
            color: #004080;
            margin-bottom: 5px;
        }
        .summary-card.success .summary-value {
            color: #28a745;
        }
        .summary-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        .department-badge {
            display: inline-block;
            background: #e9ecef;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            color: #495057;
            margin-top: 5px;
        }
        .export-options {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
        .empty-state p {
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto 25px;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
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
            transition: all 0.3s;
        }
        .page-btn:hover {
            background: #004080;
            color: white;
            border-color: #004080;
        }
        .page-btn.active {
            background: #004080;
            color: white;
            border-color: #004080;
        }
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            .summary-cards {
                grid-template-columns: 1fr;
            }
            .table-container {
                overflow-x: auto;
            }
        }
    </style>
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Payment Reports</a></li>
                <li><a href="pending_requests.php"><i class="fas fa-clock"></i> Pending Approvals</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-chart-bar"></i> Overload Payment Reports</h2>
                <div class="header-actions">
                    <button onclick="window.print()" class="btn-primary btn-sm">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button onclick="exportToPDF()" class="btn-success btn-sm">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                </div>
            </div>
            
            <!-- Filters Card -->
            <div class="report-filters">
                <h3><i class="fas fa-filter"></i> Filter Reports</h3>
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
                        <label for="teacher_id"><i class="fas fa-user"></i> Teacher</label>
                        <select id="teacher_id" name="teacher_id">
                            <option value="">All Teachers</option>
                            <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['user_id']; ?>" 
                                    <?php echo ($teacher_id == $teacher['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name'] . ' (' . $teacher['department'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="department"><i class="fas fa-building"></i> Department</label>
                        <select id="department" name="department">
                            <option value="">All Departments</option>
                            <?php while ($dept = $departments_result->fetch_assoc()): ?>
                                <option value="<?php echo $dept['department']; ?>" 
                                    <?php echo ($department == $dept['department']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="reports.php" class="btn-warning">
                            <i class="fas fa-redo"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Summary Statistics -->
            <?php if ($summary): ?>
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-value"><?php echo number_format($summary['total_payments']); ?></div>
                    <div class="summary-label">Total Payments</div>
                </div>
                
                <div class="summary-card success">
                    <div class="summary-value">ETB <?php echo number_format($summary['total_amount'] ?? 0, 2); ?></div>
                    <div class="summary-label">Total Amount</div>
                </div>
                
                <div class="summary-card info">
                    <div class="summary-value">ETB <?php echo number_format($summary['average_payment'] ?? 0, 2); ?></div>
                    <div class="summary-label">Average Payment</div>
                </div>
                
                <div class="summary-card warning">
                    <div class="summary-value">
                        <?php 
                        if ($summary['first_payment'] && $summary['last_payment']) {
                            $first = new DateTime($summary['first_payment']);
                            $last = new DateTime($summary['last_payment']);
                            echo $first->format('M Y') . ' - ' . $last->format('M Y');
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </div>
                    <div class="summary-label">Payment Period</div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!$has_payments): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Payment Records Found</h3>
                    <p>No payment records match your current filters. Try adjusting your filter criteria or check back later.</p>
                    <div class="action-buttons" style="margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
                        <a href="process_payment.php" class="btn-primary">
                            <i class="fas fa-money-check-alt"></i> Process Payments
                        </a>
                        <a href="reports.php" class="btn-warning">
                            <i class="fas fa-redo"></i> Clear Filters
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Reports Table -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Payment Details (<?php echo $result->num_rows; ?> records)</h3>
                        <div class="export-options">
                            <button onclick="exportToCSV('reports-table', 'payment-reports-<?php echo date('Y-m-d'); ?>.csv')" class="btn-success btn-sm">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </button>
                            <button onclick="exportToExcel()" class="btn-primary btn-sm">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table id="reports-table" class="sortable">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Teacher</th>
                                    <th>Department</th>
                                    <th>Academic Rank</th>
                                    <th>Course</th>
                                    <th>Amount (ETB)</th>
                                    <th>Payment Date</th>
                                    <th>Status</th>
                                    <th>Transaction Ref</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $row['payment_id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="department-badge">
                                            <?php echo htmlspecialchars($row['department']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['academic_rank']); ?></td>
                                    <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                    <td style="font-weight: bold; color: #28a745;">
                                        ETB <?php echo number_format($row['amount'], 2); ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $row['payment_status']; ?>">
                                            <?php echo ucfirst($row['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($row['transaction_ref'] ?? 'N/A'); ?></small>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="5" style="text-align: right; padding: 15px;">
                                        Total:
                                    </td>
                                    <td colspan="4" style="padding: 15px; color: #28a745; font-size: 1.1rem;">
                                        ETB <?php echo number_format($summary['total_amount'] ?? 0, 2); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- Chart Section -->
                    <div class="chart-container">
                        <h3><i class="fas fa-chart-line"></i> Payment Trends</h3>
                        <canvas id="paymentChart" height="100"></canvas>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Report Actions -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-cog"></i> Report Actions</h3>
                </div>
                <div class="quick-actions" style="display: flex; gap: 15px; flex-wrap: wrap; padding: 20px;">
                    <button onclick="generateMonthlyReport()" class="btn-primary">
                        <i class="fas fa-calendar"></i> Monthly Report
                    </button>
                    <button onclick="generateDepartmentReport()" class="btn-success">
                        <i class="fas fa-building"></i> Department Report
                    </button>
                    <button onclick="generateTeacherReport()" class="btn-info">
                        <i class="fas fa-user-tie"></i> Teacher Report
                    </button>
                    <button onclick="generateAnnualReport()" class="btn-warning">
                        <i class="fas fa-file-alt"></i> Annual Report
                    </button>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Initialize Chart.js
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($has_payments): ?>
            // Fetch data for chart
            fetch('api/payment_chart.php?<?php echo http_build_query($_GET); ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderPaymentChart(data.data);
                    }
                })
                .catch(error => console.error('Error loading chart:', error));
            <?php endif; ?>
        });
        
        function renderPaymentChart(chartData) {
            const ctx = document.getElementById('paymentChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Payment Amount (ETB)',
                        data: chartData.amounts,
                        borderColor: '#004080',
                        backgroundColor: 'rgba(0, 64, 128, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Payment Trends Over Time'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'ETB ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function exportToPDF() {
            alert('PDF export feature would generate a formatted PDF report.\nIn a full implementation, this would use a PDF library like TCPDF or DomPDF.');
            // In real implementation:
            // window.location.href = 'generate_pdf.php?' + new URLSearchParams(window.location.search);
        }
        
        function exportToExcel() {
            // Simple CSV export
            exportToCSV('reports-table', 'payment-reports-<?php echo date('Y-m-d'); ?>.xls');
        }
        
        function generateMonthlyReport() {
            const currentDate = new Date();
            const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
            
            const startDate = firstDay.toISOString().split('T')[0];
            const endDate = lastDay.toISOString().split('T')[0];
            
            window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}`;
        }
        
        function generateDepartmentReport() {
            const department = prompt('Enter department name (or leave blank for all):');
            if (department !== null) {
                const url = new URL(window.location.href);
                if (department) {
                    url.searchParams.set('department', department);
                } else {
                    url.searchParams.delete('department');
                }
                window.location.href = url.toString();
            }
        }
        
        function generateTeacherReport() {
            const teacherName = prompt('Enter teacher name to search:');
            if (teacherName) {
                // In real implementation, this would search and filter
                alert(`Searching for payments to ${teacherName}...\nIn a full implementation, this would filter the report.`);
            }
        }
        
        function generateAnnualReport() {
            const year = prompt('Enter year for annual report:', new Date().getFullYear());
            if (year) {
                const startDate = `${year}-01-01`;
                const endDate = `${year}-12-31`;
                window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}`;
            }
        }
        
        // Auto-apply date range shortcuts
        document.getElementById('start_date')?.addEventListener('focus', function() {
            this.showPicker();
        });
        
        document.getElementById('end_date')?.addEventListener('focus', function() {
            this.showPicker();
        });
        
        // Set default end date to today if not set
        window.addEventListener('load', function() {
            const endDateInput = document.getElementById('end_date');
            if (endDateInput && !endDateInput.value) {
                endDateInput.value = new Date().toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>