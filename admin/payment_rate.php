<?php
include("../config/session.php");
include("../config/db.php");

if ($_SESSION['role'] != 'admin') {
    die("Access Denied");
}

$success_msg = '';
$error_msg = '';

// Insert rate
if (isset($_POST['add'])) {
    $rank = $_POST['rank'];
    $rate = $_POST['rate'];
    $date = $_POST['date'];

    // Check if rate for this rank already exists for this date
    $check_sql = "SELECT * FROM payment_rates WHERE academic_rank = ? AND effective_date = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $rank, $date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_msg = "A payment rate for '$rank' already exists for the selected effective date.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO payment_rates (academic_rank, rate_per_credit, effective_date)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sds", $rank, $rate, $date);
        
        if ($stmt->execute()) {
            $success_msg = "Payment rate added successfully.";
        } else {
            $error_msg = "Error adding payment rate: " . $conn->error;
        }
    }
}

// Update rate
if (isset($_POST['update'])) {
    $rate_id = $_POST['rate_id'];
    $rank = $_POST['rank'];
    $rate = $_POST['rate'];
    $date = $_POST['date'];

    $stmt = $conn->prepare(
        "UPDATE payment_rates SET academic_rank = ?, rate_per_credit = ?, effective_date = ?
         WHERE rate_id = ?"
    );
    $stmt->bind_param("sdsi", $rank, $rate, $date, $rate_id);
    
    if ($stmt->execute()) {
        $success_msg = "Payment rate updated successfully.";
    } else {
        $error_msg = "Error updating payment rate: " . $conn->error;
    }
}

// Delete rate
if (isset($_GET['delete'])) {
    $rate_id = intval($_GET['delete']);
    
    // Check if rate is being used in any payments
    $check_sql = "SELECT COUNT(*) as usage_count FROM payments p 
                  JOIN overload_requests o ON p.request_id = o.request_id
                  JOIN teachers t ON o.teacher_id = t.teacher_id
                  WHERE t.academic_rank IN (SELECT academic_rank FROM payment_rates WHERE rate_id = ?)";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $rate_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_data = $check_result->fetch_assoc();
    
    if ($check_data['usage_count'] > 0) {
        $error_msg = "Cannot delete this rate because it is being used in existing payments.";
    } else {
        $delete_sql = "DELETE FROM payment_rates WHERE rate_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $rate_id);
        
        if ($delete_stmt->execute()) {
            $success_msg = "Payment rate deleted successfully.";
        } else {
            $error_msg = "Error deleting payment rate: " . $conn->error;
        }
    }
}

// Fetch rates
$sql = "SELECT * FROM payment_rates ORDER BY effective_date DESC, academic_rank ASC";
$result = $conn->query($sql);

// Calculate statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT academic_rank) as total_ranks,
    COUNT(*) as total_rates,
    MIN(effective_date) as earliest_rate,
    MAX(effective_date) as latest_rate,
    AVG(rate_per_credit) as average_rate
FROM payment_rates";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get distinct academic ranks
$ranks_sql = "SELECT DISTINCT academic_rank FROM payment_rates ORDER BY 
    CASE academic_rank
        WHEN 'Professor' THEN 1
        WHEN 'Associate Professor' THEN 2
        WHEN 'Assistant Professor' THEN 3
        WHEN 'Lecturer' THEN 4
        WHEN 'Assistant Lecturer' THEN 5
        ELSE 6
    END";
$ranks_result = $conn->query($ranks_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Rates | Admin Panel | Woldiya University</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        }

        /* ====== Dashboard Container ====== */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* ====== Sidebar Styles ====== */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary-color) 0%, #002855 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
        }

        .sidebar-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: white;
            text-align: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info i {
            font-size: 2.5rem;
            background: rgba(255, 255, 255, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-details h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: white;
        }

        .user-details p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--warning-color);
        }

        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: var(--warning-color);
            font-weight: 600;
        }

        .sidebar-menu a i {
            width: 20px;
            text-align: center;
        }

        .logout-link {
            color: #ff6b6b !important;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 20px;
        }

        /* ====== Main Content ====== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        /* ====== Page Header ====== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .page-header h2 {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h2 i {
            color: var(--secondary-color);
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        /* ====== Button Styles ====== */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 64, 128, 0.2);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
            color: #212529;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info-color), #138496);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.85rem;
        }

        /* ====== Card Styles ====== */
        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h3 {
            color: var(--primary-color);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 30px;
        }

        /* ====== Notification Styles ====== */
        .notification {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.5s ease;
        }

        .notification-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid var(--success-color);
        }

        .notification-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid var(--danger-color);
        }

        .notification-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border-left: 4px solid var(--info-color);
        }

        .notification i {
            font-size: 1.2rem;
        }

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

        /* ====== Stats Cards ====== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
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

        /* ====== Form Styles ====== */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            color: var(--primary-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
            color: var(--dark-color);
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 64, 128, 0.1);
        }

        .form-group input:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        /* ====== Rates Table ====== */
        .rates-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .rates-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .rates-header h3 {
            color: var(--primary-color);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rates-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .rates-table th {
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

        .rates-table th i {
            margin-right: 8px;
        }

        .rates-table td {
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .rates-table tr:hover {
            background: #f8fafc;
        }

        .rates-table tr:last-child td {
            border-bottom: none;
        }

        /* ====== Rank Badges ====== */
        .rank-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, rgba(0, 64, 128, 0.1), rgba(0, 102, 204, 0.1));
            color: var(--primary-color);
            border: 1px solid rgba(0, 64, 128, 0.2);
        }

        .rank-badge i {
            font-size: 1rem;
        }

        /* ====== Rate Display ====== */
        .rate-display {
            font-weight: 700;
            color: var(--success-color);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rate-display i {
            color: var(--success-color);
        }

        .rate-per-credit {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ====== Date Display ====== */
        .date-display {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .date-value {
            font-weight: 600;
            color: var(--dark-color);
        }

        .date-status {
            font-size: 0.85rem;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            width: fit-content;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .status-future {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-expired {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }

        /* ====== Action Buttons ====== */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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

        .btn-edit {
            background: rgba(0, 64, 128, 0.1);
            color: var(--primary-color);
        }

        .btn-edit:hover {
            background: rgba(0, 64, 128, 0.2);
        }

        .btn-delete {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        .btn-delete:hover {
            background: rgba(220, 53, 69, 0.2);
        }

        .btn-history {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }

        .btn-history:hover {
            background: rgba(23, 162, 184, 0.2);
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

        /* ====== Modal Styles ====== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal {
            background: white;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .modal-body {
            padding: 30px;
            text-align: center;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        /* ====== Responsive Design ====== */
        @media (max-width: 992px) {
            .sidebar {
                width: 250px;
            }
            
            .main-content {
                margin-left: 250px;
            }
            
            .form-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h3,
            .user-details,
            .sidebar-menu a span {
                display: none;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar-menu a i {
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .rates-container {
                padding: 20px 15px;
            }
            
            .rates-table th,
            .rates-table td {
                padding: 14px 16px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .page-header h2 {
                font-size: 1.6rem;
            }
            
            .card-header,
            .card-body {
                padding: 20px;
            }
            
            .notification {
                padding: 12px 15px;
                font-size: 0.9rem;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-value {
                font-size: 1.8rem;
            }
        }

        /* ====== Custom Scrollbar ====== */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* ====== Text Muted ====== */
        .text-muted {
            color: #6c757d !important;
            font-size: 0.85rem;
        }

        /* ====== Rate History Styles ====== */
        .rate-history {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--info-color);
        }

        .rate-history h4 {
            color: var(--info-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .history-rank {
            font-weight: 600;
            color: var(--primary-color);
        }

        .history-rate {
            font-weight: 700;
            color: var(--success-color);
        }

        .history-date {
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
                <div class="user-info">
                    <i class="fas fa-user-shield"></i>
                    <div class="user-details">
                        <h4><?php echo $_SESSION['username'] ?? 'Admin'; ?></h4>
                        <p>Administrator</p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                <li><a href="payment_rate.php" class="active"><i class="fas fa-money-check-alt"></i> Payment Rates</a></li>
                <li><a href="system_logs.php"><i class="fas fa-clipboard-list"></i> System Logs</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-money-check-alt"></i> Payment Rates</h2>
                <div class="header-actions">
                    <button class="btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Rates
                    </button>
                </div>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="notification notification-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="notification notification-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_ranks'] ?? 0; ?></div>
                    <div class="stat-label">Academic Ranks</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_rates'] ?? 0; ?></div>
                    <div class="stat-label">Rate Entries</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['average_rate'] ?? 0, 2); ?> ETB</div>
                    <div class="stat-label">Average Rate</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value">
                        <?php echo $stats['latest_rate'] ? date('M Y', strtotime($stats['latest_rate'])) : 'N/A'; ?>
                    </div>
                    <div class="stat-label">Latest Rate</div>
                </div>
            </div>
            
            <!-- Add Rate Form -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plus-circle"></i> Add New Payment Rate</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-graduation-cap"></i> Academic Rank *</label>
                                <select name="rank" required>
                                    <option value="">Select Academic Rank</option>
                                    <option value="Professor">Professor</option>
                                    <option value="Associate Professor">Associate Professor</option>
                                    <option value="Assistant Professor">Assistant Professor</option>
                                    <option value="Lecturer">Lecturer</option>
                                    <option value="Assistant Lecturer">Assistant Lecturer</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-coins"></i> Rate per Credit (ETB) *</label>
                                <input type="number" name="rate" required min="0" step="0.01" placeholder="Enter rate per credit">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar-day"></i> Effective Date *</label>
                                <input type="date" name="date" required>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="add" class="btn-success">
                                <i class="fas fa-save"></i> Add Rate
                            </button>
                            <button type="reset" class="btn-warning">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Current Rates Table -->
            <div class="rates-container">
                <div class="rates-header">
                    <h3><i class="fas fa-list-alt"></i> Existing Payment Rates</h3>
                    <div class="rates-count">
                        <span class="text-muted">
                            <i class="fas fa-layer-group"></i> 
                            Total Rates: <?php echo $result->num_rows; ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-wrapper">
                        <table class="rates-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-graduation-cap"></i> Academic Rank</th>
                                    <th><i class="fas fa-money-bill-wave"></i> Rate per Credit</th>
                                    <th><i class="fas fa-calendar-alt"></i> Effective Date</th>
                                    <th><i class="fas fa-info-circle"></i> Status</th>
                                    <th><i class="fas fa-cog"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): 
                                    // Determine status
                                    $today = date('Y-m-d');
                                    $effective_date = $row['effective_date'];
                                    $status = '';
                                    $status_class = '';
                                    
                                    if ($effective_date > $today) {
                                        $status = 'Future';
                                        $status_class = 'status-future';
                                    } elseif ($effective_date <= $today) {
                                        // Check if this is the most recent rate for this rank
                                        $check_recent_sql = "SELECT MAX(effective_date) as latest_date 
                                                            FROM payment_rates 
                                                            WHERE academic_rank = ? AND effective_date <= ?";
                                        $check_stmt = $conn->prepare($check_recent_sql);
                                        $check_stmt->bind_param("ss", $row['academic_rank'], $today);
                                        $check_stmt->execute();
                                        $check_result = $check_stmt->get_result();
                                        $check_data = $check_result->fetch_assoc();
                                        
                                        if ($check_data['latest_date'] == $effective_date) {
                                            $status = 'Active';
                                            $status_class = 'status-active';
                                        } else {
                                            $status = 'Expired';
                                            $status_class = 'status-expired';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge">
                                            <i class="fas fa-graduation-cap"></i>
                                            <?php echo htmlspecialchars($row['academic_rank']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="rate-display">
                                            <i class="fas fa-coins"></i>
                                            <?php echo number_format($row['rate_per_credit'], 2); ?> ETB
                                        </div>
                                        <div class="rate-per-credit">
                                            <i class="fas fa-percentage"></i>
                                            Per Credit Hour
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-display">
                                            <div class="date-value">
                                                <?php echo date('F d, Y', strtotime($row['effective_date'])); ?>
                                            </div>
                                            <span class="date-status <?php echo $status_class; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-muted" style="font-size: 0.85rem;">
                                            <?php if ($status == 'Active'): ?>
                                                <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                                                Currently in use
                                            <?php elseif ($status == 'Future'): ?>
                                                <i class="fas fa-clock" style="color: var(--warning-color);"></i>
                                                Starts on <?php echo date('M d, Y', strtotime($row['effective_date'])); ?>
                                            <?php else: ?>
                                                <i class="fas fa-history" style="color: #6c757d;"></i>
                                                Replaced by newer rate
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-edit" onclick="editRate(<?php echo $row['rate_id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="?delete=<?php echo $row['rate_id']; ?>" 
                                               class="btn-action btn-delete"
                                               onclick="return confirmDelete('<?php echo htmlspecialchars($row['academic_rank']); ?>', <?php echo $row['rate_id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                            <button class="btn-action btn-history" onclick="viewRateHistory('<?php echo htmlspecialchars($row['academic_rank']); ?>')">
                                                <i class="fas fa-history"></i> History
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
                        <i class="fas fa-coins"></i>
                        <h3>No Payment Rates Found</h3>
                        <p>No payment rates have been configured yet. Add your first payment rate using the form above.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Rate History Section -->
            <?php if ($ranks_result->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Rate History by Rank</h3>
                </div>
                <div class="card-body">
                    <?php while ($rank_row = $ranks_result->fetch_assoc()): 
                        $rank_history_sql = "SELECT * FROM payment_rates 
                                            WHERE academic_rank = ? 
                                            ORDER BY effective_date DESC";
                        $history_stmt = $conn->prepare($rank_history_sql);
                        $history_stmt->bind_param("s", $rank_row['academic_rank']);
                        $history_stmt->execute();
                        $history_result = $history_stmt->get_result();
                        
                        if ($history_result->num_rows > 0):
                    ?>
                    <div class="rate-history">
                        <h4><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($rank_row['academic_rank']); ?></h4>
                        <div class="history-list">
                            <?php while ($history_row = $history_result->fetch_assoc()): 
                                $history_status = '';
                                if ($history_row['effective_date'] > $today) {
                                    $history_status = '(Future)';
                                } elseif ($history_row['effective_date'] <= $today) {
                                    // Check if active
                                    $check_active_sql = "SELECT MAX(effective_date) as latest_date 
                                                        FROM payment_rates 
                                                        WHERE academic_rank = ? AND effective_date <= ?";
                                    $check_active_stmt = $conn->prepare($check_active_sql);
                                    $check_active_stmt->bind_param("ss", $rank_row['academic_rank'], $today);
                                    $check_active_stmt->execute();
                                    $check_active_result = $check_active_stmt->get_result();
                                    $check_active_data = $check_active_result->fetch_assoc();
                                    
                                    $history_status = ($check_active_data['latest_date'] == $history_row['effective_date']) ? '(Active)' : '(Past)';
                                }
                            ?>
                            <div class="history-item">
                                <div class="history-rate">
                                    <?php echo number_format($history_row['rate_per_credit'], 2); ?> ETB
                                </div>
                                <div class="history-date">
                                    Effective: <?php echo date('M d, Y', strtotime($history_row['effective_date'])); ?>
                                    <span class="text-muted"><?php echo $history_status; ?></span>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Edit Rate Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Payment Rate</h3>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="rate_id" id="editRateId">
                    <div class="form-group">
                        <label><i class="fas fa-graduation-cap"></i> Academic Rank</label>
                        <input type="text" name="rank" id="editRank" required readonly style="background: #f8f9fa;">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-coins"></i> Rate per Credit (ETB)</label>
                        <input type="number" name="rate" id="editRate" required min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-day"></i> Effective Date</label>
                        <input type="date" name="date" id="editDate" required>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" name="update" class="btn-success">
                            <i class="fas fa-save"></i> Update Rate
                        </button>
                        <button type="button" class="btn-warning" onclick="closeModal('editModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
            </div>
            <div class="modal-body">
                <i class="fas fa-trash-alt" style="font-size: 4rem; color: var(--danger-color); margin-bottom: 20px;"></i>
                <h4 style="margin-bottom: 15px; color: var(--dark-color);">Are you sure you want to delete this payment rate?</h4>
                <p id="deleteMessage" style="color: #666; margin-bottom: 10px;"></p>
                <p style="color: var(--danger-color); font-weight: 600;">
                    <i class="fas fa-exclamation-circle"></i> This action cannot be undone!
                </p>
                <div class="modal-actions">
                    <a href="#" id="confirmDeleteBtn" class="btn-danger">
                        <i class="fas fa-trash"></i> Delete Rate
                    </a>
                    <button class="btn-warning" onclick="closeModal('deleteModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Edit rate function
        function editRate(rateId) {
            // In a real application, you would fetch the rate data via AJAX
            // For now, we'll just show the modal with a message
            document.getElementById('editRateId').value = rateId;
            
            // Fetch rate details (in real app, use AJAX)
            // For demo, we'll use placeholders
            document.getElementById('editRank').value = 'Loading...';
            document.getElementById('editRate').value = '';
            document.getElementById('editDate').value = '';
            
            // Show modal
            document.getElementById('editModal').style.display = 'flex';
        }
        
        // Delete confirmation
        function confirmDelete(rank, rateId) {
            event.preventDefault();
            
            const modal = document.getElementById('deleteModal');
            const deleteMessage = document.getElementById('deleteMessage');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            
            deleteMessage.textContent = `You are about to delete the payment rate for: ${rank} (ID: ${rateId})`;
            confirmBtn.href = `?delete=${rateId}`;
            
            modal.style.display = 'flex';
        }
        
        // View rate history
        function viewRateHistory(rank) {
            alert(`Rate history for ${rank} would be displayed here.\n\nIn a production system, this would show a detailed chart of rate changes over time.`);
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
        
        // Auto-hide notifications after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.notification').forEach(notification => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(() => notification.remove(), 500);
            });
        }, 5000);
        
        // Set minimum date for effective date to today
        document.querySelector('input[name="date"]').min = new Date().toISOString().split('T')[0];
        
        // Set default date to today
        document.querySelector('input[name="date"]').value = new Date().toISOString().split('T')[0];
        
        // Rate calculator helper
        document.addEventListener('DOMContentLoaded', function() {
            // Add rate calculator to the form
            const rateInput = document.querySelector('input[name="rate"]');
            const formGroup = rateInput.closest('.form-group');
            
            if (formGroup) {
                const calculator = document.createElement('div');
                calculator.className = 'rate-calculator';
                calculator.style.cssText = 'margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px;';
                calculator.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <i class="fas fa-calculator" style="color: var(--primary-color);"></i>
                        <span style="font-size: 0.9rem; font-weight: 600; color: var(--dark-color);">Rate Calculator</span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; font-size: 0.85rem;">
                        <div>
                            <div style="color: #666;">For 1 credit:</div>
                            <div id="calc1" style="font-weight: 600; color: var(--success-color);">0.00 ETB</div>
                        </div>
                        <div>
                            <div style="color: #666;">For 3 credits:</div>
                            <div id="calc3" style="font-weight: 600; color: var(--success-color);">0.00 ETB</div>
                        </div>
                        <div>
                            <div style="color: #666;">For 6 credits:</div>
                            <div id="calc6" style="font-weight: 600; color: var(--success-color);">0.00 ETB</div>
                        </div>
                    </div>
                `;
                
                formGroup.appendChild(calculator);
                
                // Update calculator on input
                rateInput.addEventListener('input', function() {
                    const rate = parseFloat(this.value) || 0;
                    document.getElementById('calc1').textContent = rate.toFixed(2) + ' ETB';
                    document.getElementById('calc3').textContent = (rate * 3).toFixed(2) + ' ETB';
                    document.getElementById('calc6').textContent = (rate * 6).toFixed(2) + ' ETB';
                });
            }
            
            // Add required asterisks
            const requiredLabels = document.querySelectorAll('input[required], select[required]');
            requiredLabels.forEach(input => {
                const label = input.closest('.form-group')?.querySelector('label');
                if (label && !label.querySelector('.required')) {
                    label.innerHTML += ' <span style="color: var(--danger-color);">*</span>';
                }
            });
        });
        
        // Print functionality
        window.printRates = function() {
            const printContent = document.querySelector('.rates-container').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Payment Rates Report</title>
                    <style>
                        body { font-family: Arial; margin: 30px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .header h2 { color: #004080; margin-bottom: 5px; }
                        .header h3 { color: #0066cc; margin-top: 0; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th { background: #004080; color: white; padding: 12px; text-align: left; }
                        td { padding: 10px; border-bottom: 1px solid #ddd; }
                        .footer { margin-top: 40px; text-align: center; color: #666; font-size: 0.9rem; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>Woldiya University</h2>
                        <h3>Payment Rates Report</h3>
                        <p>Generated on: ${new Date().toLocaleDateString()}</p>
                    </div>
                    ${printContent}
                    <div class="footer">
                        <p>Confidential - For authorized use only</p>
                        <p>Generated by Woldiya University Overload Payment System</p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        };
    </script>
</body>
</html>