<?php
// auth/login.php
session_start();

// Debug: Check if session exists
echo "<!-- Session Debug: ";
print_r($_SESSION);
echo " -->";

// If user is already logged in, show a message and logout option
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    ?>
    <!DOCTYPE html>
<html>
<head>
    <title>Already Logged In</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            text-align: center; 
            padding: 50px; 
            background: #f5f7fa;
        }
        .message-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        h2 { color: #004080; }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 10px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn-continue {
            background: #004080;
            color: white;
        }
        .btn-logout {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="message-box">
        <h2>You are already logged in!</h2>
        <p>Welcome back, <?php echo $_SESSION['username'] ?? 'User'; ?>!</p>
        <p>You are currently logged in as: <strong><?php echo $_SESSION['role'] ?? 'Unknown'; ?></strong></p>
        
        <p style="margin-top: 30px;">
            <a href="../teacher/dashboard.php" class="btn btn-continue">
                Go to Dashboard
            </a>
            <br><br>
            <a href="logout.php" class="btn btn-logout">
                Logout First
            </a>
        </p>
    </div>
</body>
</html>
    <?php
    exit();
}

// Include database connection
include("../config/db.php");

// Initialize variables
$error = '';
$username = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Hash password with md5
        $hashed_password = md5($password);
        
        // Prepare SQL
        $sql = "SELECT * FROM users WHERE username=? AND password=? AND status='active'";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ss", $username, $hashed_password);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'] ?? '';
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['department'] = $user['department'] ?? '';
                $_SESSION['loggedin'] = true;
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'teacher':
                        header("Location: ../teacher/dashboard.php");
                        exit();
                    case 'department_head':
                        header("Location: ../department_head/dashboard.php");
                        exit();
                    case 'finance':
                        header("Location: ../finance/dashboard.php");
                        exit();
                    case 'admin':
                        header("Location: ../admin/dashboard.php");
                        exit();
                    default:
                        header("Location: ../index.php");
                        exit();
                }
            } else {
                $error = "Invalid username or password.";
            }
            $stmt->close();
        } else {
            $error = "Database error. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Woldiya University</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-login:hover {
            background: #5a67d8;
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-message {
            background: #dfd;
            color: #373;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .demo-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .demo-info h4 {
            margin-bottom: 10px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>Woldiya University</h2>
            <p>Overload Payment System Login</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
            <div class="success-message">
                You have been logged out successfully.
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       placeholder="Enter your username"
                       required
                       autofocus
                       value="<?php echo htmlspecialchars($username); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       placeholder="Enter your password"
                       required>
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <div class="demo-info">
            <h4>Demo Credentials:</h4>
            <p><strong>Teacher:</strong> teacher1 / password123</p>
            <p><strong>Dept Head:</strong> depthead1 / password123</p>
            <p><strong>Finance:</strong> finance1 / password123</p>
            <p><strong>Admin:</strong> admin / password123</p>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="../index.php" style="color: #667eea;">← Back to Home</a>
        </div>
    </div>
    
    <script>
        // Auto-focus on username field
        document.getElementById('username').focus();
        
        // Simple password toggle (optional)
        const togglePassword = document.createElement('button');
        togglePassword.type = 'button';
        togglePassword.innerHTML = '👁️';
        togglePassword.style.position = 'absolute';
        togglePassword.style.right = '10px';
        togglePassword.style.top = '35px';
        togglePassword.style.background = 'none';
        togglePassword.style.border = 'none';
        togglePassword.style.cursor = 'pointer';
        
        const passwordField = document.getElementById('password');
        passwordField.parentElement.style.position = 'relative';
        passwordField.parentElement.appendChild(togglePassword);
        
        togglePassword.addEventListener('click', function() {
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                togglePassword.innerHTML = '👁️‍🗨️';
            } else {
                passwordField.type = 'password';
                togglePassword.innerHTML = '👁️';
            }
        });
    </script>
</body>
</html>