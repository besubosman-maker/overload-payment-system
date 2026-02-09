<?php
include("../config/session.php");
include("../config/db.php");

$user_id = $_SESSION['user_id'];

// Get teacher_id with error handling
$teacher_id = null;
$teacher_result = $conn->query("SELECT teacher_id FROM teachers WHERE user_id=$user_id");

if ($teacher_result === false) {
    die("Database error: " . $conn->error);
}

if ($teacher_result && $teacher_result->num_rows > 0) {
    $teacher = $teacher_result->fetch_assoc();
    $teacher_id = $teacher['teacher_id'];
} else {
    die("Teacher profile not found. Please contact administrator.");
}

// Fetch payment history with detailed information
$sql = "SELECT 
    p.payment_id,
    p.request_id,
    o.course_name,
    o.credit_hour,
    o.semester,
    o.academic_year,
    p.amount,
    p.payment_status,
    p.payment_date,
    p.processed_by,
    u.full_name as processed_by_name,
    a.approved_at as approval_date,
    p.notes
FROM payments p
JOIN overload_requests o ON p.request_id = o.request_id
LEFT JOIN users u ON p.processed_by = u.user_id
LEFT JOIN approvals a ON p.request_id = a.request_id
WHERE o.teacher_id = $teacher_id
ORDER BY p.payment_date DESC";

$result = $conn->query($sql);

if ($result === false) {
    die("Database error in payment query: " . $conn->error);
}

// Calculate statistics
$stats_sql = "SELECT 
    COUNT(*) as total_payments,
    COALESCE(SUM(amount), 0) as total_amount,
    COALESCE(AVG(amount), 0) as average_payment,
    MIN(payment_date) as first_payment,
    MAX(payment_date) as last_payment,
    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments
FROM payments p
JOIN overload_requests o ON p.request_id = o.request_id
WHERE o.teacher_id = $teacher_id";

$stats_result = $conn->query($stats_sql);
if ($stats_result === false) {
    die("Database error in stats query: " . $conn->error);
}

$stats = $stats_result->fetch_assoc();

// Calculate monthly totals for the current year
$monthly_data = [];
$monthly_labels = [];
$monthly_totals = [];

$monthly_sql = "SELECT 
    MONTH(payment_date) as month,
    YEAR(payment_date) as year,
    COALESCE(SUM(amount), 0) as monthly_total,
    COUNT(*) as payment_count
FROM payments p
JOIN overload_requests o ON p.request_id = o.request_id
WHERE o.teacher_id = $teacher_id 
    AND YEAR(payment_date) = YEAR(CURDATE())
    AND payment_status = 'paid'
GROUP BY YEAR(payment_date), MONTH(payment_date)
ORDER BY month ASC";

$monthly_result = $conn->query($monthly_sql);
if ($monthly_result && $monthly_result !== false) {
    while ($row = $monthly_result->fetch_assoc()) {
        $monthly_data[] = $row;
        $monthly_labels[] = date('F', mktime(0, 0, 0, $row['month'], 1));
        $monthly_totals[] = $row['monthly_total'];
    }
}

// Get distinct academic years for filter
$years_sql = "SELECT DISTINCT academic_year FROM overload_requests WHERE teacher_id=$teacher_id ORDER BY academic_year DESC";
$years_result = $conn->query($years_sql);
$academic_years = [];
if ($years_result && $years_result !== false) {
    while ($year = $years_result->fetch_assoc()) {
        $academic_years[] = $year['academic_year'];
    }
}

// Format dates
function formatDate($date) {
    if (!$date || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return 'N/A';
    return date('M d, Y', strtotime($date));
}

function formatCurrency($amount) {
    return number_format($amount, 2) . ' ETB';
}

// Check if we have any payments
$has_payments = $result->num_rows > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History | Woldiya University</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (!empty($monthly_data)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
    <style>
        /* ====== CSS Variables ====== */
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

        /* ====== Base Styles ====== */
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

        /* ====== Main Container ====== */
        .payment-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ====== Header ====== */
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

        /* ====== Stats Cards ====== */
        .stats-grid {
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
            background: linear-gradient(135deg, rgba(0, 64, 128, 0.1), rgba(0, 102, 204, 0.1));
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

        /* ====== Chart Container ====== */
        .chart-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: var(--box-shadow);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .chart-header h3 {
            color: var(--primary-color);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-controls {
            display: flex;
            gap: 10px;
        }

        .chart-btn {
            padding: 8px 20px;
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

        .chart-btn:hover,
        .chart-btn.active {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(0, 64, 128, 0.05);
        }

        .chart-wrapper {
            height: 300px;
            position: relative;
        }

        /* ====== Filters ====== */
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

        .filter-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .filter-item {
            flex: 1;
            min-width: 200px;
        }

        .filter-item label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
            color: var(--dark-color);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23004080' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 14px;
            padding-right: 40px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        /* ====== Payments Table ====== */
        .payments-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .payments-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .payments-header h3 {
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

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .payment-table th {
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

        .payment-table th i {
            margin-right: 8px;
        }

        .payment-table td {
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .payment-table tr:hover {
            background: #f8fafc;
        }

        .payment-table tr:last-child td {
            border-bottom: none;
        }

        /* ====== Status Badges ====== */
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

        .status-paid {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-failed {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        /* ====== Payment Info ====== */
        .payment-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .payment-id {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 1rem;
        }

        .payment-details {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .payment-details span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .amount-cell {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        /* ====== Action Buttons ====== */
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
            white-space: nowrap;
        }

        .btn-receipt {
            background: rgba(0, 64, 128, 0.1);
            color: var(--primary-color);
        }

        .btn-receipt:hover {
            background: rgba(0, 64, 128, 0.2);
        }

        .btn-download {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .btn-download:hover {
            background: rgba(40, 167, 69, 0.2);
        }

        .btn-print {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .btn-print:hover {
            background: rgba(108, 117, 125, 0.2);
        }

        /* ====== Details Modal ====== */
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
            max-width: 700px;
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
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
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

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .detail-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
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

        .detail-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success-color);
        }

        /* ====== Empty State ====== */
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

        /* ====== Footer Actions ====== */
        .footer-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        .btn-primary {
            flex: 1;
            min-width: 200px;
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
            min-width: 200px;
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

        /* ====== Export Section ====== */
        .export-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-top: 30px;
            box-shadow: var(--box-shadow);
        }

        .export-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .export-header h3 {
            color: var(--primary-color);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .export-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .export-btn {
            padding: 12px 25px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .export-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(0, 64, 128, 0.05);
        }

        /* ====== Responsive Design ====== */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .filters-header,
            .payments-header,
            .export-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-item {
                min-width: 100%;
            }
            
            .chart-controls,
            .export-options {
                width: 100%;
                flex-wrap: wrap;
            }
            
            .chart-btn,
            .export-btn {
                flex: 1;
                min-width: 120px;
                justify-content: center;
            }
            
            .payments-container {
                padding: 20px 15px;
            }
            
            .payment-table th,
            .payment-table td {
                padding: 14px 16px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
            
            .footer-actions {
                flex-direction: column;
            }
            
            .btn-primary,
            .btn-secondary {
                width: 100%;
                min-width: auto;
            }
            
            .modal {
                padding: 0;
            }
            
            .modal-header,
            .modal-body {
                padding: 20px;
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
            
            .chart-wrapper {
                height: 250px;
            }
        }

        /* ====== Print Styles ====== */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .page-header h2,
            .page-header p,
            .stats-grid,
            .chart-container,
            .filters-container,
            .export-section,
            .footer-actions,
            .btn-action {
                display: none;
            }
            
            .payments-container {
                box-shadow: none;
                border: 1px solid #ddd;
                padding: 0;
            }
            
            .payment-table {
                min-width: auto;
            }
            
            .payment-table th {
                background: #f2f2f2 !important;
                color: black !important;
            }
        }

        /* ====== Custom Scrollbar ====== */
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

        /* ====== Animations ====== */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .slide-in {
            animation: slideIn 0.5s ease;
        }

        /* ====== Currency formatting ====== */
        .currency {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        /* ====== Course Name Styling ====== */
        .course-name {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <!-- Header -->
        <div class="page-header">
            <h2><i class="fas fa-money-bill-wave"></i> Payment History</h2>
            <p>Track all your overload payment transactions and download receipts</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_payments'] ?? 0); ?></div>
                <div class="stat-label">Total Payments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_amount'] ?? 0, 2); ?> ETB</div>
                <div class="stat-label">Total Amount</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['average_payment'] ?? 0, 2); ?> ETB</div>
                <div class="stat-label">Average Payment</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $stats['pending_payments'] ?? 0; ?></div>
                <div class="stat-label">Pending Payments</div>
            </div>
        </div>

        <!-- Payment Chart -->
        <?php if (!empty($monthly_data)): ?>
        <div class="chart-container">
            <div class="chart-header">
                <h3><i class="fas fa-chart-bar"></i> Monthly Payment Trends (<?php echo date('Y'); ?>)</h3>
                <div class="chart-controls">
                    <button class="chart-btn active" onclick="changeChartType('bar')">
                        <i class="fas fa-chart-bar"></i> Bar
                    </button>
                    <button class="chart-btn" onclick="changeChartType('line')">
                        <i class="fas fa-chart-line"></i> Line
                    </button>
                    <button class="chart-btn" onclick="changeChartType('pie')">
                        <i class="fas fa-chart-pie"></i> Pie
                    </button>
                </div>
            </div>
            <div class="chart-wrapper">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-container">
            <div class="filters-header">
                <h3><i class="fas fa-filter"></i> Filter Payments</h3>
            </div>
            <div class="filter-group">
                <div class="filter-item">
                    <label for="statusFilter"><i class="fas fa-tasks"></i> Payment Status</label>
                    <select id="statusFilter" class="filter-select" onchange="filterPayments()">
                        <option value="all">All Status</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="yearFilter"><i class="fas fa-calendar"></i> Academic Year</label>
                    <select id="yearFilter" class="filter-select" onchange="filterPayments()">
                        <option value="all">All Years</option>
                        <?php foreach ($academic_years as $year): ?>
                        <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="semesterFilter"><i class="fas fa-calendar-alt"></i> Semester</label>
                    <select id="semesterFilter" class="filter-select" onchange="filterPayments()">
                        <option value="all">All Semesters</option>
                        <option value="1">Semester I</option>
                        <option value="2">Semester II</option>
                        <option value="summer">Summer</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="payments-container">
            <div class="payments-header">
                <h3><i class="fas fa-history"></i> Payment Transactions</h3>
                <button class="btn-action btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>

            <?php if ($has_payments): ?>
                <div class="table-container">
                    <table class="payment-table" id="paymentsTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> Payment ID</th>
                                <th><i class="fas fa-book"></i> Course Details</th>
                                <th><i class="fas fa-calendar-alt"></i> Date</th>
                                <th><i class="fas fa-money-bill-wave"></i> Amount</th>
                                <th><i class="fas fa-tasks"></i> Status</th>
                                <th><i class="fas fa-user-tie"></i> Processed By</th>
                                <th><i class="fas fa-cog"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): 
                                // Status badge
                                $status_class = 'status-' . $row['payment_status'];
                                $status_text = ucfirst($row['payment_status']);
                                
                                // Format dates
                                $payment_date = formatDate($row['payment_date']);
                                $approval_date = formatDate($row['approval_date']);
                                
                                // Processed by name
                                $processed_by = $row['processed_by_name'] ?: 'System';
                            ?>
                            <tr class="payment-row slide-in" 
                                data-status="<?php echo $row['payment_status']; ?>"
                                data-year="<?php echo $row['academic_year']; ?>"
                                data-semester="<?php echo $row['semester']; ?>"
                                data-id="<?php echo $row['payment_id']; ?>">
                                <td>
                                    <div class="payment-info">
                                        <div class="payment-id">#<?php echo $row['payment_id']; ?></div>
                                        <div class="payment-details">
                                            <span>Request: #<?php echo $row['request_id']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="payment-info">
                                        <div class="course-name"><?php echo htmlspecialchars($row['course_name']); ?></div>
                                        <div class="payment-details">
                                            <span><i class="fas fa-clock"></i> <?php echo $row['credit_hour']; ?> Credits</span>
                                            <span><i class="fas fa-calendar"></i> Sem <?php echo $row['semester']; ?></span>
                                            <span><i class="fas fa-university"></i> <?php echo $row['academic_year']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo $payment_date; ?></div>
                                    <?php if ($approval_date != 'N/A'): ?>
                                    <div style="font-size: 0.85rem; color: #666;">Approved: <?php echo $approval_date; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="amount-cell">
                                    <?php echo formatCurrency($row['amount']); ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($processed_by); ?></div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-receipt" onclick="viewPaymentDetails(<?php echo $row['payment_id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn-action btn-download" onclick="downloadReceipt(<?php echo $row['payment_id']; ?>)">
                                            <i class="fas fa-download"></i> Receipt
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
                    <i class="fas fa-wallet"></i>
                    <h3>No Payment History</h3>
                    <p>You haven't received any payments yet. Payments will appear here once your approved overload requests are processed.</p>
                    <a href="view_status.php" class="btn-primary" style="margin-top: 20px; display: inline-flex; width: auto;">
                        <i class="fas fa-tasks"></i> Check Request Status
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Export Section -->
        <?php if ($has_payments): ?>
        <div class="export-section">
            <div class="export-header">
                <h3><i class="fas fa-file-export"></i> Export Payment History</h3>
            </div>
            <div class="export-options">
                <button class="export-btn" onclick="exportToCSV()">
                    <i class="fas fa-file-csv"></i> Export as CSV
                </button>
                <button class="export-btn" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf"></i> Export as PDF
                </button>
                <button class="export-btn" onclick="printSummary()">
                    <i class="fas fa-print"></i> Print Summary
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer Actions -->
        <div class="footer-actions">
            <a href="dashboard.php" class="btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="view_status.php" class="btn-secondary">
                <i class="fas fa-tasks"></i> Request Status
            </a>
            <a href="submit_overload.php" class="btn-secondary">
                <i class="fas fa-plus-circle"></i> New Request
            </a>
        </div>
    </div>

    <!-- Payment Details Modal -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-receipt"></i> Payment Details</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="paymentModalContent">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Initialize Chart
        <?php if (!empty($monthly_data)): ?>
        let paymentChart;
        let chartType = 'bar';
        
        function initChart() {
            const ctx = document.getElementById('paymentChart').getContext('2d');
            
            const data = {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Monthly Payments (ETB)',
                    data: <?php echo json_encode($monthly_totals); ?>,
                    backgroundColor: [
                        'rgba(0, 64, 128, 0.7)',
                        'rgba(0, 102, 204, 0.7)',
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(23, 162, 184, 0.7)',
                        'rgba(111, 66, 193, 0.7)',
                        'rgba(253, 126, 20, 0.7)',
                        'rgba(102, 16, 242, 0.7)',
                        'rgba(214, 51, 132, 0.7)',
                        'rgba(32, 201, 151, 0.7)',
                        'rgba(108, 117, 125, 0.7)'
                    ],
                    borderColor: [
                        'rgba(0, 64, 128, 1)',
                        'rgba(0, 102, 204, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(23, 162, 184, 1)',
                        'rgba(111, 66, 193, 1)',
                        'rgba(253, 126, 20, 1)',
                        'rgba(102, 16, 242, 1)',
                        'rgba(214, 51, 132, 1)',
                        'rgba(32, 201, 151, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 6
                }]
            };
            
            const options = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 14,
                                family: "'Segoe UI', 'Roboto', sans-serif"
                            },
                            color: '#333'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += new Intl.NumberFormat('en-ET', {
                                    style: 'currency',
                                    currency: 'ETB'
                                }).format(context.parsed.y);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'ETB ' + value.toLocaleString();
                            },
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                }
            };
            
            paymentChart = new Chart(ctx, {
                type: chartType,
                data: data,
                options: options
            });
        }
        
        function changeChartType(type) {
            chartType = type;
            
            // Update button states
            document.querySelectorAll('.chart-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.toLowerCase().includes(type)) {
                    btn.classList.add('active');
                }
            });
            
            // Update chart
            if (paymentChart) {
                paymentChart.destroy();
            }
            initChart();
        }
        
        // Initialize chart on page load
        document.addEventListener('DOMContentLoaded', initChart);
        <?php endif; ?>

        // Filter payments
        function filterPayments() {
            const statusFilter = document.getElementById('statusFilter').value;
            const yearFilter = document.getElementById('yearFilter').value;
            const semesterFilter = document.getElementById('semesterFilter').value;
            const rows = document.querySelectorAll('.payment-row');
            
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const rowYear = row.getAttribute('data-year');
                const rowSemester = row.getAttribute('data-semester');
                
                let showRow = true;
                
                if (statusFilter !== 'all' && rowStatus !== statusFilter) {
                    showRow = false;
                }
                
                if (yearFilter !== 'all' && rowYear !== yearFilter) {
                    showRow = false;
                }
                
                if (semesterFilter !== 'all' && rowSemester !== semesterFilter) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        }

        // View payment details
        async function viewPaymentDetails(paymentId) {
            try {
                // Show loading
                const modalContent = document.getElementById('paymentModalContent');
                modalContent.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                        <p style="margin-top: 15px; color: #666;">Loading payment details...</p>
                    </div>
                `;
                
                // Show modal
                document.getElementById('paymentModal').classList.add('active');
                
                // Fetch payment details
                setTimeout(() => {
                    loadPaymentDetails(paymentId);
                }, 500);
            } catch (error) {
                console.error('Error loading payment details:', error);
                modalContent.innerHTML = `
                    <div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <h4 style="margin: 0 0 10px 0;">Error</h4>
                            <p style="margin: 0;">Failed to load payment details. Please try again.</p>
                        </div>
                    </div>
                `;
            }
        }

        function loadPaymentDetails(paymentId) {
            const modalContent = document.getElementById('paymentModalContent');
            
            // Find the row with this paymentId
            const row = document.querySelector(`.payment-row[data-id="${paymentId}"]`);
            if (!row) {
                modalContent.innerHTML = '<p>Payment details not found.</p>';
                return;
            }
            
            // Extract data from the row
            const paymentInfo = row.querySelector('.payment-info').cloneNode(true);
            const courseName = row.querySelector('.course-name').textContent;
            const courseDetails = row.querySelector('.payment-details').textContent;
            const dateCell = row.cells[2].cloneNode(true);
            const amountCell = row.cells[3].cloneNode(true);
            const statusBadge = row.querySelector('.status-badge').cloneNode(true);
            const processedBy = row.cells[5].cloneNode(true);
            
            modalContent.innerHTML = `
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-hashtag"></i> Payment ID
                        </div>
                        <div class="detail-value">${paymentInfo.innerHTML}</div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-money-bill-wave"></i> Amount
                        </div>
                        <div class="detail-amount">${amountCell.innerHTML}</div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-tasks"></i> Status
                        </div>
                        <div style="margin-top: 10px;">${statusBadge.outerHTML}</div>
                    </div>
                </div>
                
                <div class="detail-grid">
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
                            <i class="fas fa-calendar-alt"></i> Date Information
                        </div>
                        <div class="detail-value">${dateCell.innerHTML}</div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-user-tie"></i> Processed By
                        </div>
                        <div class="detail-value">${processedBy.innerHTML}</div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 8px; border-left: 4px solid var(--success-color);">
                    <div class="detail-label" style="margin-bottom: 15px;">
                        <i class="fas fa-info-circle"></i> Payment Information
                    </div>
                    <ul style="margin: 0; padding-left: 20px; color: #666; font-size: 0.95rem;">
                        <li>Payments are typically processed within 7-10 working days</li>
                        <li>For payment inquiries, contact the finance department</li>
                        <li>Keep this receipt for your records</li>
                        <li>Tax information is available upon request</li>
                    </ul>
                </div>
                
                <div style="margin-top: 30px; display: flex; gap: 15px;">
                    <button class="btn-primary" onclick="downloadReceipt(${paymentId})" style="flex: 1;">
                        <i class="fas fa-download"></i> Download Receipt
                    </button>
                    <button class="btn-secondary" onclick="printReceipt(${paymentId})" style="flex: 1;">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                </div>
            `;
        }

        // Download receipt
        function downloadReceipt(paymentId) {
            // In a real application, this would generate and download a PDF receipt
            alert(`Receipt for payment #${paymentId} would be downloaded as PDF.\n\nIn a production system, this would generate a proper receipt with university logo, signatures, and transaction details.`);
            
            // Simulate download
            const receiptContent = `
                WOLDIYA UNIVERSITY
                OFFICIAL PAYMENT RECEIPT
                
                Receipt No: #${paymentId}
                Date: ${new Date().toLocaleDateString()}
                
                Teacher: <?php echo $_SESSION['username'] ?? 'Teacher'; ?>
                Payment Amount: [Amount from database]
                Payment Status: Paid
                
                This is an official receipt from Woldiya University.
                For inquiries, contact: finance@woldiyauniversity.edu.et
            `;
            
            const blob = new Blob([receiptContent], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `payment-receipt-${paymentId}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Print receipt
        function printReceipt(paymentId) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Payment Receipt #${paymentId}</title>
                    <style>
                        body { font-family: Arial; margin: 40px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .header h2 { color: #004080; margin-bottom: 5px; }
                        .header h3 { color: #0066cc; margin-top: 0; }
                        .receipt-info { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        .receipt-info th, .receipt-info td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                        .receipt-info th { background-color: #f2f2f2; }
                        .amount { font-size: 1.5rem; font-weight: bold; color: #28a745; }
                        .footer { margin-top: 40px; text-align: center; color: #666; font-size: 0.9rem; }
                        .signature { margin-top: 60px; border-top: 1px solid #000; width: 300px; padding-top: 10px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>Woldiya University</h2>
                        <h3>Official Payment Receipt</h3>
                    </div>
                    
                    <table class="receipt-info">
                        <tr>
                            <th>Receipt Number:</th>
                            <td>#${paymentId}</td>
                        </tr>
                        <tr>
                            <th>Date Issued:</th>
                            <td>${new Date().toLocaleDateString()}</td>
                        </tr>
                        <tr>
                            <th>Teacher:</th>
                            <td><?php echo $_SESSION['username'] ?? 'Teacher'; ?></td>
                        </tr>
                        <tr>
                            <th>Payment Amount:</th>
                            <td class="amount">[Amount from database] ETB</td>
                        </tr>
                        <tr>
                            <th>Payment Status:</th>
                            <td><strong style="color: #28a745;">PAID</strong></td>
                        </tr>
                    </table>
                    
                    <div class="footer">
                        <p>This is an official receipt from Woldiya University Finance Department</p>
                        <p>For verification: finance@woldiyauniversity.edu.et | Phone: +251-XXX-XXXXXX</p>
                        
                        <div style="display: flex; justify-content: space-between; margin-top: 40px;">
                            <div class="signature">
                                <p>Teacher's Signature</p>
                            </div>
                            <div class="signature">
                                <p>Finance Officer's Signature</p>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => printWindow.print(), 250);
        }

        // Export functions
        function exportToCSV() {
            const rows = document.querySelectorAll('.payment-row:not([style*="display: none"])');
            let csv = 'Payment ID,Request ID,Course,Credits,Semester,Year,Amount,Status,Payment Date,Processed By\n';
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const paymentId = row.getAttribute('data-id');
                const requestId = row.querySelector('.payment-details span').textContent.replace('Request: #', '');
                const course = row.querySelector('.course-name').textContent;
                const credits = row.querySelector('.payment-details span:nth-child(1)').textContent.replace(' Credits', '');
                const semester = row.querySelector('.payment-details span:nth-child(2)').textContent.replace('Sem ', '');
                const year = row.querySelector('.payment-details span:nth-child(3)').textContent;
                const amount = cells[3].textContent;
                const status = cells[4].querySelector('.status-badge').textContent;
                const date = cells[2].querySelector('div').textContent;
                const processedBy = cells[5].querySelector('div').textContent;
                
                csv += `"${paymentId}","${requestId}","${course}","${credits}","${semester}","${year}","${amount}","${status}","${date}","${processedBy}"\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `payment-history-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            alert('Payment history exported as CSV successfully!');
        }

        function exportToPDF() {
            alert('PDF export feature would generate a formatted PDF report with all payment details, charts, and university branding.\n\nIn a production system, this would use a PDF generation library like jsPDF or a server-side solution.');
        }

        function printSummary() {
            const printWindow = window.open('', '_blank');
            const totalPayments = <?php echo $stats['total_payments'] ?? 0; ?>;
            const totalAmount = <?php echo $stats['total_amount'] ?? 0; ?>;
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Payment Summary Report</title>
                    <style>
                        body { font-family: Arial; margin: 30px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .summary { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        .summary th, .summary td { padding: 12px; border: 1px solid #ddd; }
                        .summary th { background: #f2f2f2; }
                        .total { font-weight: bold; color: #004080; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>Woldiya University - Payment Summary</h2>
                        <h3>Generated on: ${new Date().toLocaleDateString()}</h3>
                        <p>Teacher: <?php echo $_SESSION['username'] ?? 'Teacher'; ?></p>
                    </div>
                    
                    <table class="summary">
                        <tr>
                            <th>Total Payments</th>
                            <td>${totalPayments}</td>
                        </tr>
                        <tr>
                            <th>Total Amount Received</th>
                            <td class="total">${totalAmount.toLocaleString()} ETB</td>
                        </tr>
                        <tr>
                            <th>Average Payment</th>
                            <td>${(totalAmount / Math.max(totalPayments, 1)).toLocaleString(undefined, {minimumFractionDigits: 2})} ETB</td>
                        </tr>
                        <tr>
                            <th>Report Period</th>
                            <td><?php echo date('Y'); ?> (Current Year)</td>
                        </tr>
                    </table>
                    
                    <p style="margin-top: 30px; color: #666;">
                        This report summarizes all overload teaching payments for the current user.
                        For detailed transaction history, refer to the full payment history report.
                    </p>
                    
                    <div style="margin-top: 40px; text-align: center; color: #999; font-size: 0.9rem;">
                        <p>Generated by Woldiya University Overload Payment System</p>
                        <p>Confidential - For authorized use only</p>
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
            document.getElementById('paymentModal').classList.remove('active');
        }

        // Close modal on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });

        // Close modal when clicking outside
        document.getElementById('paymentModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('paymentModal')) {
                closeModal();
            }
        });

        // Initialize search
        document.addEventListener('DOMContentLoaded', function() {
            // Add search functionality if we have payments
            <?php if ($has_payments): ?>
            const searchDiv = document.createElement('div');
            searchDiv.style.marginLeft = 'auto';
            searchDiv.innerHTML = `
                <input type="text" id="paymentSearch" placeholder="Search payments..." 
                       style="padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 8px; 
                              width: 250px; font-size: 0.95rem;"
                       onkeyup="searchPayments()">
            `;
            
            const paymentsHeader = document.querySelector('.payments-header');
            paymentsHeader.insertBefore(searchDiv, paymentsHeader.lastChild);
            
            // Add hover effects
            const rows = document.querySelectorAll('.payment-row');
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
            <?php endif; ?>
        });

        // Search payments
        function searchPayments() {
            const searchTerm = document.getElementById('paymentSearch').value.toLowerCase();
            const rows = document.querySelectorAll('.payment-row');
            
            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                row.style.display = rowText.includes(searchTerm) ? '' : 'none';
            });
        }

        // Refresh data
        function refreshData() {
            const refreshBtn = event?.target;
            if (refreshBtn) {
                const originalHTML = refreshBtn.innerHTML;
                refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
                refreshBtn.disabled = true;
                
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }
        }
    </script>
</body>
</html>