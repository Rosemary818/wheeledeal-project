<?php
session_start();
date_default_timezone_set('Asia/Kolkata'); // Set timezone explicitly

include 'db.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_GET['token'];

    // Debugging token retrieval
   // echo "<p>Debug: Token received - $token</p>";
    //echo "<p>Debug: Current PHP Time - " . date('Y-m-d H:i:s') . "</p>";

    // Basic validation
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Fetch token details for debugging
        $stmt = $conn->prepare("SELECT reset_token, token_expiry FROM automobilelogin WHERE reset_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $token_expiry = new DateTime($row['token_expiry']);
            $current_time = new DateTime();

            //echo "<p>Debug: Token Expiry - " . $row['token_expiry'] . "</p>";

            // Check if the token is still valid
            if ($current_time > $token_expiry) {
                $error = "Invalid or expired token.";
            } else {
                // Token is valid, update password
                $new_password = password_hash($password, PASSWORD_BCRYPT);
                $update_stmt = $conn->prepare("UPDATE automobilelogin SET password = ?, reset_token = NULL, token_expiry = NULL WHERE reset_token = ?");
                $update_stmt->bind_param("ss", $new_password, $token);
                $update_stmt->execute();

                $success = "Password has been reset successfully.";
                // You can now <a href='login.php'>log in</a> with your new password.";
            }
        } else {
            $error = "Invalid or expired token.";
        }

        $stmt->close();
    }
}

$conn->close();
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
            height: 100vh;
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
        }
        .reset-card button:hover {
            background-color: #e63f15;
        }
        .reset-card p {
            margin: 15px 0;
            font-size: 14px;
            color: #555;
        }
        .reset-card .black-text {
            color: #000;
        }
        .logo {
            margin-bottom: 20px;
        }
        .logo img {
            height: 60px;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
        .success {
            color: green;
            margin-bottom: 10px;
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

            <?php if (!empty($error)) { ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>

            <?php if (!empty($success)) { ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php } ?>

            <form action="" method="POST">
                <input type="password" name="password" placeholder="New Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <button type="submit">Reset Password</button>
            </form>

            <p class="black-text">Remembered your password? <a href="login.php">Log In</a></p>
        </div>
    </div>
</body>
</html>
