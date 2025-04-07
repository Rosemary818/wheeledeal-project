<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

include 'db_connect.php';

// Initialize variables
$error = '';
$success = '';

// Get token from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Verify token exists in database
if (!empty($token)) {
    $check = $conn->query("SELECT user_id FROM tbl_login WHERE reset_token = '" . 
                         $conn->real_escape_string($token) . "' LIMIT 1");
    
    if ($check && $check->num_rows > 0) {
        $user = $check->fetch_assoc();
        $user_id = $user['user_id'];
        
        // Process form submission
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $password = $_POST['password'];
            $confirm = $_POST['confirm_password'];
            
            if ($password === $confirm) {
                // Hash the password
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                
                // Update password
                $update_pw = $conn->query("UPDATE tbl_users SET password = '" . 
                                       $conn->real_escape_string($hashed) . "' WHERE user_id = " . (int)$user_id);
                                       
                // Clear the token
                $clear_token = $conn->query("UPDATE tbl_login SET reset_token = NULL, token_expiry = NULL 
                                          WHERE user_id = " . (int)$user_id);
                
                if ($update_pw && $clear_token) {
                    $success = "Password updated successfully!";
                } else {
                    $error = "Error updating password: " . $conn->error;
                }
            } else {
                $error = "Passwords do not match";
            }
        }
    } else {
        $error = "Invalid or expired token. Please request a new password reset link.";
    }
} else {
    $error = "No token provided";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - WheeledDeal</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f7f4f1;
        }
        .reset-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            background-color: #f7f4f1;
        }
        .reset-card {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .reset-card h2 {
            font-size: 28px;
            margin-bottom: 20px;
            color: #333;
        }
        .reset-card input {
            width: 100%;
            padding: 12px 15px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .reset-card button {
            width: 100%;
            padding: 12px 15px;
            background-color: #ff471a;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .reset-card button:hover {
            background-color: #e63f15;
        }
        .reset-card p {
            margin: 15px 0;
            font-size: 14px;
            color: #555;
        }
        .black-text {
            color: #000;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .logo {
            margin-bottom: 20px;
        }
        .logo img {
            height: 60px;
        }
        .error {
            color: #e74c3c;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #fdecea;
            border-radius: 5px;
            border-left: 4px solid #e74c3c;
            text-align: left;
        }
        .success-box {
            color: #2ecc71;
            background-color: #e8f8f5;
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #2ecc71;
            margin-bottom: 25px;
            text-align: left;
        }
        .success-box h3 {
            color: #27ae60;
            margin-top: 0;
            margin-bottom: 10px;
        }
        .success-box p {
            color: #333;
            margin-bottom: 0;
        }
        .login-button {
            display: block;
            text-decoration: none;
            text-align: center;
            margin-top: 10px;
            background-color: #ff471a;
            color: #fff;
            padding: 12px 15px;
            border-radius: 5px;
        }
        .login-link {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }
        .login-link:hover {
            text-decoration: underline;
        }
        .divider {
            height: 1px;
            background-color: #eee;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="logo">
                <img src="images/logo3.png" alt="WheeledDeal Logo">
            </div>
            <h2>Reset Your Password</h2>

            <?php if (!empty($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success-box">
                    <h3>Success!</h3>
                    <p><?php echo $success; ?> You can now log in with your new password.</p>
                </div>
                <div class="login-section">
                    <a href="login.php" class="login-button">Log In Now</a>
                </div>
            <?php else: ?>
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST">
                    <input type="password" name="password" placeholder="New Password" required>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                    <button type="submit">Reset Password</button>
                </form>
                
                <div class="divider"></div>
                
                <p class="black-text">Remembered your password? <a href="login.php" class="login-link">Log In</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
