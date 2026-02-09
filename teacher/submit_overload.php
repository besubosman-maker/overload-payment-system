<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include("../config/session.php");
include("../config/db.php");

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get teacher ID
$result = $conn->query("SELECT teacher_id FROM teachers WHERE user_id=$user_id");
if (!$result) {
    die("Error: " . $conn->error);
}
$teacher = $result->fetch_assoc();
if (!$teacher) {
    die("Error: Teacher not found for user ID: $user_id");
}
$teacher_id = $teacher['teacher_id'];

// Fetch courses from database
$courses_result = $conn->query("SELECT course_id, course_code, course_name FROM courses ORDER BY course_code");
$courses = [];
if ($courses_result) {
    while ($row = $courses_result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// If no courses in database, use default courses
if (empty($courses)) {
    $courses = [
        ['course_id' => 1, 'course_code' => 'CS101', 'course_name' => 'Introduction to Computer Science'],
        ['course_id' => 2, 'course_code' => 'CS201', 'course_name' => 'Data Structures'],
        ['course_id' => 3, 'course_code' => 'CS301', 'course_name' => 'Algorithms'],
        ['course_id' => 4, 'course_code' => 'MATH101', 'course_name' => 'Calculus I'],
        ['course_id' => 5, 'course_code' => 'MATH201', 'course_name' => 'Calculus II'],
        ['course_id' => 6, 'course_code' => 'PHYS101', 'course_name' => 'General Physics'],
        ['course_id' => 7, 'course_code' => 'ENG101', 'course_name' => 'English Composition'],
        ['course_id' => 8, 'course_code' => 'CS401', 'course_name' => 'Database Systems'],
        ['course_id' => 9, 'course_code' => 'CS402', 'course_name' => 'Software Engineering'],
        ['course_id' => 10, 'course_code' => 'CS403', 'course_name' => 'Computer Networks'],
    ];
}

$success_message = "";
if (isset($_POST['submit'])) {
    $course_id  = isset($_POST['course']) ? $_POST['course'] : '';
    $credit  = isset($_POST['credit']) ? $_POST['credit'] : '';
    $semester = isset($_POST['semester']) ? $_POST['semester'] : '';
    $year    = isset($_POST['year']) ? $_POST['year'] : '';
    $notes = isset($_POST['justification']) ? $_POST['justification'] : '';
    
    // Find the selected course
    $course_name = 'Unknown Course';
    foreach ($courses as $course) {
        if ($course['course_id'] == $course_id) {
            $course_name = $course['course_name'];
            break;
        }
    }
    
    // Validate required fields
    if (empty($course_id) || empty($credit) || empty($semester) || empty($year)) {
        $success_message = "Error: All required fields must be filled!";
    } else {
        // Insert using direct query
        $course_name_escaped = $conn->real_escape_string($course_name);
        $semester_escaped = $conn->real_escape_string($semester);
        $year_escaped = $conn->real_escape_string($year);
        $notes_escaped = $conn->real_escape_string($notes);
        
        $sql = "INSERT INTO overload_requests 
                (teacher_id, course_name, credit_hour, semester, academic_year, notes, status, submitted_at) 
                VALUES ('$teacher_id', '$course_name_escaped', '$credit', '$semester_escaped', '$year_escaped', '$notes_escaped', 'pending', NOW())";
        
        if ($conn->query($sql)) {
            $success_message = "Overload request submitted successfully!";
        } else {
            $success_message = "Database Error: " . $conn->error;
        }
    }
}

// Get teacher info for payment calculation
$teacher_info = $conn->query("SELECT academic_rank FROM teachers WHERE teacher_id=$teacher_id");
$teacher_data = $teacher_info->fetch_assoc();
$academic_rank = $teacher_data['academic_rank'] ?? 'lecturer';

// Get payment rate
$rate_query = $conn->query("SELECT rate_per_credit FROM payment_rates WHERE academic_rank='$academic_rank'");
$rate_data = $rate_query->fetch_assoc();
$rate_per_credit = $rate_data['rate_per_credit'] ?? 500;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Overload | Woldiya University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ====== CSS VARIABLES ====== */
        :root {
            --primary-color: #004080;
            --secondary-color: #0066cc;
            --accent-color: #00a8ff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #dee2e6;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 6px 12px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }

        /* ====== BASE STYLES ====== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        /* ====== CONTAINER STYLES ====== */
        .submit-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* ====== PAGE HEADER ====== */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .page-header h2 {
            color: var(--primary-color);
            margin: 0 0 15px 0;
            font-size: 2.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .page-header h2 .floating {
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ====== MESSAGE STYLES ====== */
        .success-message, .error-message {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            animation: slideIn 0.5s ease;
            box-shadow: var(--shadow);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left: 5px solid var(--success-color);
        }

        .error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-left: 5px solid var(--danger-color);
        }

        .success-message i, .error-message i {
            font-size: 2rem;
            flex-shrink: 0;
        }

        .success-message i {
            color: var(--success-color);
        }

        .error-message i {
            color: var(--danger-color);
        }

        .success-message h4, .error-message h4 {
            margin: 0 0 5px 0;
            color: var(--dark-color);
        }

        .success-message p, .error-message p {
            margin: 0;
            color: var(--dark-color);
        }

        /* ====== SUBMISSION CARD ====== */
        .submission-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 40px;
            transition: var(--transition);
        }

        .submission-card:hover {
            box-shadow: var(--shadow-hover);
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 50px;
            margin-bottom: 30px;
            border-left: 4px solid var(--primary-color);
        }

        .status-indicator i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .status-indicator span {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.95rem;
        }

        /* ====== FORM STYLES ====== */
        .submission-form {
            display: grid;
            gap: 30px;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1rem;
        }

        .form-group label i {
            color: var(--secondary-color);
            width: 20px;
        }

        .required {
            color: var(--danger-color);
            margin-left: 5px;
        }

        .form-control, .course-search {
            width: 100%;
            padding: 15px 20px;
            padding-left: 50px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: white;
            box-sizing: border-box;
        }

        .form-control:focus, .course-search:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(0, 168, 255, 0.1);
        }

        .form-icon {
            position: absolute;
            left: 20px;
            top: 47px;
            color: var(--primary-color);
            pointer-events: none;
            z-index: 2;
        }

        .select-wrapper {
            position: relative;
        }

        .select-wrapper::after {
            content: '\f078';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            pointer-events: none;
            z-index: 1;
        }

        /* ====== COURSE SEARCH ====== */
        .course-search-container {
            position: relative;
            margin-bottom: 10px;
        }

        .course-search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            z-index: 2;
        }

        .course-search-clear {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 5px;
            display: none;
            z-index: 2;
        }

        .course-search-clear:hover {
            color: var(--danger-color);
        }

        .course-list-container {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            margin-top: 5px;
            display: none;
            position: absolute;
            width: 100%;
            background: white;
            z-index: 1000;
            box-shadow: var(--shadow-hover);
        }

        .course-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .course-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: var(--transition);
        }

        .course-item:hover {
            background: #f8fafc;
            transform: translateX(5px);
        }

        .course-item.selected {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .course-code {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1rem;
        }

        .course-item.selected .course-code {
            color: white;
        }

        .course-name {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 5px;
        }

        .course-item.selected .course-name {
            color: rgba(255, 255, 255, 0.9);
        }

        .no-results {
            padding: 30px 20px;
            text-align: center;
            color: #94a3b8;
            font-style: italic;
        }

        /* ====== SELECTED COURSE DISPLAY ====== */
        .selected-course-display {
            background: linear-gradient(135deg, #f0f7ff 0%, #e3f2fd 100%);
            border: 2px solid #c3dafe;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 15px;
            display: none;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .selected-course-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .selected-course-code {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .selected-course-name {
            color: #475569;
            font-size: 1rem;
        }

        .change-course {
            background: none;
            border: none;
            color: var(--secondary-color);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 20px;
            transition: var(--transition);
        }

        .change-course:hover {
            background: rgba(0, 102, 204, 0.1);
        }

        /* ====== RADIO BUTTONS ====== */
        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .radio-option {
            flex: 1;
            min-width: 150px;
        }

        .radio-option input {
            display: none;
    z-index: 2;
        }

        .radio-option label {
            display: block;
            padding: 18px 20px;
            background: #f8fafc;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .radio-option label:hover {
            border-color: var(--accent-color);
            transform: translateY(-2px);
        }

        .radio-option input:checked + label {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* ====== CALCULATOR STYLES ====== */
        .credit-calculator {
            background: linear-gradient(135deg, #fff9db 0%, #ffeaa7 100%);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-top: 10px;
            border: 2px solid var(--warning-color);
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .calculator-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calculator-header h4 {
            margin: 0;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .calculator-results {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .calculator-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .calculator-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }

        .calculator-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        /* ====== TEXTAREA STYLES ====== */
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            padding: 15px;
            line-height: 1.5;
        }

        .char-counter {
            text-align: right;
            font-size: 0.85rem;
            margin-top: 8px;
            color: #666;
            transition: var(--transition);
        }

        /* ====== BUTTON STYLES ====== */
        .form-actions {
            display: flex;
            gap: 20px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid var(--border-color);
        }

        .btn-submit, .btn-back {
            flex: 1;
            padding: 18px 30px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-decoration: none;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .btn-back {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--border-color);
        }

        .btn-back:hover {
            background: #f8f9fa;
            border-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        /* ====== LOADING OVERLAY ====== */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 1.2rem;
            color: var(--primary-color);
            font-weight: 500;
        }

        /* ====== RESPONSIVE DESIGN ====== */
        @media (max-width: 768px) {
            .submit-container {
                padding: 10px;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .page-header h2 {
                font-size: 1.8rem;
                flex-direction: column;
                gap: 10px;
            }
            
            .submission-card {
                padding: 25px;
            }
            
            .radio-group {
                flex-direction: column;
            }
            
            .calculator-results {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-submit, .btn-back {
                width: 100%;
            }
        }

        /* ====== UTILITY CLASSES ====== */
        .continue-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 30px 0;
            flex-wrap: wrap;
        }

        .continue-buttons a {
            text-decoration: none;
        }

        /* ====== SCROLLBAR STYLING ====== */
        ::-webkit-scrollbar {
            width: 8px;
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
    </style>
</head>
<body>
    <div class="submit-container">
        <div class="page-header">
            <h2><i class="fas fa-chalkboard-teacher floating"></i> Submit Overload Teaching</h2>
            <p>Select a course and fill in the details to submit your overload teaching request</p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="<?php echo (strpos($success_message, 'Error') === false) ? 'success-message' : 'error-message'; ?>">
                <i class="fas <?php echo (strpos($success_message, 'Error') === false) ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                <div>
                    <h4><?php echo (strpos($success_message, 'Error') === false) ? 'Success!' : 'Error!'; ?></h4>
                    <p><?php echo $success_message; ?></p>
                </div>
            </div>
            
            <?php if (strpos($success_message, 'Error') === false): ?>
                <div class="continue-buttons">
                    <a href="submit_overload.php" class="btn-submit">
                        <i class="fas fa-plus"></i> Submit Another Request
                    </a>
                    <a href="dashboard.php" class="btn-back">
                        <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (empty($success_message) || strpos($success_message, 'Error') !== false): ?>
        <div class="submission-card">
            <div class="status-indicator">
                <i class="fas fa-info-circle"></i>
                <span>Your academic rank: <?php echo ucfirst(str_replace('_', ' ', $academic_rank)); ?></span>
            </div>
            
            <form method="POST" class="submission-form" id="overloadForm">
                <!-- Course Selection with Search -->
                <div class="form-group">
                    <label for="course">
                        <i class="fas fa-book"></i> Select Course
                        <span class="required">*</span>
                    </label>
                    
                    <div class="course-search-container">
                        <div class="course-search-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <input type="text" 
                               class="course-search" 
                               placeholder="Search for a course by code or name..."
                               id="courseSearch">
                        <button type="button" class="course-search-clear" id="clearSearch">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="course-list-container" id="courseListContainer">
                        <ul class="course-list" id="courseList">
                            <?php foreach ($courses as $course): ?>
                            <li class="course-item" 
                                data-course-id="<?php echo $course['course_id']; ?>"
                                data-course-code="<?php echo htmlspecialchars($course['course_code']); ?>"
                                data-course-name="<?php echo htmlspecialchars($course['course_name']); ?>">
                                <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="no-results" id="noResults" style="display: none;">
                            No courses found matching your search
                        </div>
                    </div>
                    
                    <!-- Selected course display -->
                    <div class="selected-course-display" id="selectedCourseDisplay">
                        <div class="selected-course-header">
                            <div class="selected-course-code" id="selectedCourseCode"></div>
                            <button type="button" class="change-course" id="changeCourse">
                                <i class="fas fa-exchange-alt"></i> Change
                            </button>
                        </div>
                        <div class="selected-course-name" id="selectedCourseName"></div>
                    </div>
                    
                    <!-- Hidden input for form submission -->
                    <input type="hidden" name="course" id="selectedCourseId" required>
                </div>
                
                <div class="form-group">
                    <label for="credit">
                        <i class="fas fa-clock"></i> Credit Hours
                        <span class="required">*</span>
                    </label>
                    <div class="form-icon">
                        <i class="fas fa-hashtag"></i>
                    </div>
                    <div class="select-wrapper">
                        <select id="credit" name="credit" class="form-control" required onchange="calculatePayment()">
                            <option value="">Select credit hours</option>
                            <option value="1">1 Credit Hour</option>
                            <option value="2">2 Credit Hours</option>
                            <option value="3">3 Credit Hours</option>
                            <option value="4">4 Credit Hours</option>
                            <option value="5">5 Credit Hours</option>
                            <option value="6">6 Credit Hours</option>
                        </select>
                    </div>
                </div>
                
                <div class="credit-calculator" id="creditCalculator" style="display: none;">
                    <div class="calculator-header">
                        <h4><i class="fas fa-calculator"></i> Payment Calculator</h4>
                        <span class="status-indicator" style="background: rgba(255, 193, 7, 0.1); color: var(--warning-color);">
                            Estimate
                        </span>
                    </div>
                    <div class="calculator-results">
                        <div class="calculator-item">
                            <div class="calculator-label">Credit Hours</div>
                            <div class="calculator-value" id="calcCredit">0</div>
                        </div>
                        <div class="calculator-item">
                            <div class="calculator-label">Rate per Credit</div>
                            <div class="calculator-value" id="calcRate"><?php echo $rate_per_credit; ?> ETB</div>
                        </div>
                        <div class="calculator-item">
                            <div class="calculator-label">Estimated Payment</div>
                            <div class="calculator-value" id="calcTotal">0 ETB</div>
                        </div>
                    </div>
                    <p style="margin-top: 15px; color: #666; font-size: 0.85rem; text-align: center;">
                        <i class="fas fa-info-circle"></i> Rate based on your <?php echo ucfirst(str_replace('_', ' ', $academic_rank)); ?> rank
                    </p>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-calendar-alt"></i> Semester
                        <span class="required">*</span>
                    </label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="semester1" name="semester" value="1" required>
                            <label for="semester1">Semester I</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="semester2" name="semester" value="2" required>
                            <label for="semester2">Semester II</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="summer" name="semester" value="summer" required>
                            <label for="summer">Summer</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="year">
                        <i class="fas fa-calendar"></i> Academic Year
                        <span class="required">*</span>
                    </label>
                    <div class="form-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="select-wrapper">
                        <select id="year" name="year" class="form-control" required>
                            <option value="">Select academic year</option>
                            <?php
                            $current_year = date('Y');
                            for ($i = 0; $i < 5; $i++):
                                $year1 = $current_year + $i;
                                $year2 = $year1 + 1;
                                $value = $year1 . '/' . $year2;
                                $display = $year1 . ' - ' . $year2;
                            ?>
                            <option value="<?php echo $value; ?>"><?php echo $display; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="justification">
                        <i class="fas fa-comment-alt"></i> Additional Notes (Optional)
                    </label>
                    <textarea id="justification" 
                              name="justification" 
                              class="form-control" 
                              placeholder="Add any additional notes or justification for this overload request..."
                              rows="4"></textarea>
                    <div class="char-counter" id="charCounter">0/500 characters</div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Overload Request
                    </button>
                    <a href="dashboard.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
        </div>
        <div class="loading-text">Submitting your request...</div>
    </div>
    
    <script>
        // Course selection functionality
        const courseSearch = document.getElementById('courseSearch');
        const courseListContainer = document.getElementById('courseListContainer');
        const courseList = document.getElementById('courseList');
        const courseItems = document.querySelectorAll('.course-item');
        const selectedCourseDisplay = document.getElementById('selectedCourseDisplay');
        const selectedCourseCode = document.getElementById('selectedCourseCode');
        const selectedCourseName = document.getElementById('selectedCourseName');
        const selectedCourseId = document.getElementById('selectedCourseId');
        const changeCourseBtn = document.getElementById('changeCourse');
        const clearSearchBtn = document.getElementById('clearSearch');
        const noResults = document.getElementById('noResults');
        
        // Show course list on search focus
        courseSearch.addEventListener('focus', function() {
            courseListContainer.style.display = 'block';
            filterCourses();
        });
        
        // Filter courses based on search
        courseSearch.addEventListener('input', filterCourses);
        
        // Clear search
        clearSearchBtn.addEventListener('click', function() {
            courseSearch.value = '';
            clearSearchBtn.style.display = 'none';
            filterCourses();
            courseSearch.focus();
        });
        
        // Show/hide clear button
        courseSearch.addEventListener('input', function() {
            clearSearchBtn.style.display = this.value ? 'block' : 'none';
        });
        
        // Select course
        courseItems.forEach(item => {
            item.addEventListener('click', function() {
                const courseId = this.getAttribute('data-course-id');
                const courseCode = this.getAttribute('data-course-code');
                const courseName = this.getAttribute('data-course-name');
                
                // Set selected course
                selectedCourseId.value = courseId;
                selectedCourseCode.textContent = courseCode;
                selectedCourseName.textContent = courseName;
                
                // Show selected course display
                selectedCourseDisplay.style.display = 'block';
                
                // Hide course list
                courseListContainer.style.display = 'none';
                
                // Clear search
                courseSearch.value = '';
                clearSearchBtn.style.display = 'none';
                
                // Update all course items
                courseItems.forEach(i => i.classList.remove('selected'));
                this.classList.add('selected');
                
                // Enable form validation
                selectedCourseId.setCustomValidity('');
            });
        });
        
        // Change course
        changeCourseBtn.addEventListener('click', function() {
            selectedCourseDisplay.style.display = 'none';
            courseListContainer.style.display = 'block';
            courseSearch.focus();
        });
        
        // Filter courses function
        function filterCourses() {
            const searchTerm = courseSearch.value.toLowerCase();
            let hasResults = false;
            
            courseItems.forEach(item => {
                const courseCode = item.getAttribute('data-course-code').toLowerCase();
                const courseName = item.getAttribute('data-course-name').toLowerCase();
                
                if (courseCode.includes(searchTerm) || courseName.includes(searchTerm)) {
                    item.style.display = 'block';
                    hasResults = true;
                } else {
                    item.style.display = 'none';
                }
            });
            
            noResults.style.display = hasResults ? 'none' : 'block';
        }
        
        // Close course list when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.course-search-container') && 
                !event.target.closest('.course-list-container') &&
                !event.target.closest('.selected-course-display')) {
                courseListContainer.style.display = 'none';
            }
        });
        
        // Payment calculation
        function calculatePayment() {
            const creditSelect = document.getElementById('credit');
            const credit = parseInt(creditSelect.value) || 0;
            const rate = <?php echo $rate_per_credit; ?>;
            const total = credit * rate;
            
            document.getElementById('calcCredit').textContent = credit;
            document.getElementById('calcTotal').textContent = total.toLocaleString() + ' ETB';
            
            // Show/hide calculator
            const calculator = document.getElementById('creditCalculator');
            calculator.style.display = credit > 0 ? 'block' : 'none';
        }
        
        // Form validation and submission
        document.getElementById('overloadForm').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            // Show loading overlay
            document.getElementById('loadingOverlay').style.display = 'flex';
        });
        
        function validateForm() {
            // Check if course is selected
            const selectedCourseId = document.getElementById('selectedCourseId').value;
            if (!selectedCourseId) {
                alert('Please select a course.');
                document.getElementById('courseSearch').focus();
                return false;
            }
            
            // Check credit hours
            const credit = document.getElementById('credit').value;
            if (!credit || credit < 1 || credit > 6) {
                alert('Please select valid credit hours (1-6).');
                document.getElementById('credit').focus();
                return false;
            }
            
            // Check semester
            const semesterSelected = document.querySelector('input[name="semester"]:checked');
            if (!semesterSelected) {
                alert('Please select a semester.');
                return false;
            }
            
            // Check academic year
            const year = document.getElementById('year').value;
            if (!year) {
                alert('Please select an academic year.');
                document.getElementById('year').focus();
                return false;
            }
            
            return true;
        }
        
        // Character counter for justification
        const justification = document.getElementById('justification');
        const charCounter = document.getElementById('charCounter');
        
        justification.addEventListener('input', function() {
            const count = this.value.length;
            charCounter.textContent = `${count}/500 characters`;
            
            if (count > 500) {
                charCounter.style.color = 'var(--danger-color)';
                this.style.borderColor = 'var(--danger-color)';
            } else if (count > 400) {
                charCounter.style.color = 'var(--warning-color)';
                this.style.borderColor = 'var(--warning-color)';
            } else {
                charCounter.style.color = '#666';
                this.style.borderColor = '#dee2e6';
            }
        });
        
        // Auto-resize textarea
        justification.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Auto-select current year by default
        window.addEventListener('load', function() {
            const currentYear = '<?php echo date("Y") . '/' . (date("Y") + 1); ?>';
            const yearSelect = document.getElementById('year');
            const option = Array.from(yearSelect.options).find(opt => opt.value === currentYear);
            if (option) {
                option.selected = true;
            }
            
            // Initialize calculation
            calculatePayment();
        });
    </script>
</body>
</html>