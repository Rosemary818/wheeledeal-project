<?php
// Enable error reporting at the beginning of your file
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db_connect.php'; // Include your database connection file
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Add this to verify the connection is working
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']); // Trim input

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='error'>Invalid email address.</div>";
    } else {
        // Check if email exists in the database (tbl_users)
        $query = "SELECT user_id FROM tbl_users WHERE email = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            $message = "<div class='error'>Database error: " . $conn->error . "</div>";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $user_id = $user['user_id'];
                
                // Generate a token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // IMPORTANT: First check if user already has tokens in tbl_login
                $check_query = "SELECT COUNT(*) as count FROM tbl_login WHERE user_id = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $row_count = $check_result->fetch_assoc()['count'];
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    if ($row_count > 0) {
                        // User exists in tbl_login, update their token
                        $update_query = "UPDATE tbl_login SET reset_token = ?, token_expiry = ? WHERE user_id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bind_param("ssi", $token, $expiry, $user_id);
                        $update_stmt->execute();
                    } else {
                        // User doesn't exist in tbl_login, insert new row
                        $insert_query = "INSERT INTO tbl_login (user_id, reset_token, token_expiry) VALUES (?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert_query);
                        $insert_stmt->bind_param("iss", $user_id, $token, $expiry);
                        $insert_stmt->execute();
                    }
                    
                    // Commit changes
                    $conn->commit();
                    
                    // Send email with reset link
                    $resetLink = "http://localhost/Sample/resetpassword.php?token=$token";
                    $subject = "Password Reset Request";
                    $emailMessage = "Click <a href='$resetLink'>here</a> to reset your password. This link will expire in 1 hour.";
                    
                    $mail = new PHPMailer(true);
                    
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'wheeleddeal@gmail.com'; // Your email
                        $mail->Password = 'aeyx tgob nqim wivb'; // Your email password (use app password if 2FA is enabled)
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        // Recipients
                        $mail->setFrom('wheeleddeal@gmail.com', 'WheeledDeal');
                        $mail->addAddress($email);

                        // Email content
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body = $emailMessage;

                        $mail->send();
                        $message = "<div class='success'>A reset link has been sent to your email.</div>";
                    } catch (Exception $e) {
                        $message = "<div class='error'>Failed to send email. Error: " . $mail->ErrorInfo . "</div>";
                    }
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $message = "<div class='error'>Database error: " . $e->getMessage() . "</div>";
                }
            } else {
                $message = "<div class='error'>No account found with this email.</div>";
            }
            
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - WheeledDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f7f4f1;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 30px;
            width: 100%;
            max-width: 400px;
        }
        .form-title {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }
        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .logo img {
            height: 60px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            background-color: #ff5722;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 12px 20px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #e64a19;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        .login-link a {
            color: #ff5722;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="images/logo3.png" alt="WheeledDeal Logo">
        </div>
        <h2 class="form-title">Forgot Password</h2>
        
        <?php if (!empty($message)) echo $message; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
            </div>
            <button type="submit">Send Reset Link</button>
        </form>
        <div class="login-link">
            Remember your password? <a href="login.php">Login</a>
        </div>
    </div>
</body>
</html>
