<?php
session_start();
include 'db_connect.php';

// Initialize error message
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        try {
            // Check in users table
            $query = "SELECT user_id, name, email, password, role FROM tbl_users WHERE email = :email";
            $stmt = $conn->prepare($query);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Store user data in session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['is_admin'] = ($user['role'] === 'admin') ? 1 : 0;

                // Check if this is the user's first login
                $check_login = "SELECT * FROM tbl_login WHERE user_id = :user_id";
                $check_stmt = $conn->prepare($check_login);
                $check_stmt->execute(['user_id' => $user['user_id']]);

                if ($check_stmt->rowCount() === 0) {
                    // First time login - store in tbl_login
                    $login_query = "INSERT INTO tbl_login (user_id) VALUES (:user_id)";
                    $login_stmt = $conn->prepare($login_query);
                    $login_stmt->execute(['user_id' => $user['user_id']]);
                }

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WheeledDeal</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f7f4f1;
        }

        .login-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 20px;
            background-color: #f7f4f1;
        }

        .login-card {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        .login-card h2 {
            font-size: 28px;
            margin-bottom: 20px;
            color: #333;
        }

        .login-card input {
            width: 100%;
            padding: 12px 15px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .login-card button {
            width: 100%;
            padding: 12px 15px;
            background-color: #ff471a;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        .login-card button:hover {
            background-color: #e63f15;
        }

        .login-card p {
            margin: 15px 0;
            font-size: 14px;
            color: #555;
        }

        .login-card a {
            color: #007bff;
            text-decoration: none;
        }

        .login-card a:hover {
            text-decoration: underline;
        }

        .login-card .black-text {
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <img src="images/logo3.png" alt="WheeledDeal Logo">
            </div>
            <h2>Login to WheeledDeal</h2>

            <?php if (!empty($error)) { ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php } ?>

            <form action="" method="POST">
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>

            <p class="black-text">Don't have an account? <a href="signup.php">Sign Up</a></p>
            <p><a href="forgot-password.php">Forgot Password?</a></p>
        </div>
    </div>
</body>
</html>
