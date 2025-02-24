<?php
session_start();
include 'db.php';

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
        // First, check in automobileusers (Admins, Sellers, Buyers)
        $stmt = $conn->prepare("SELECT user_id, name, email, password, role FROM automobileusers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['name'] = $user['name'];  // Store the correct name
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                if (strpos($user['role'], 'admin') !== false) {
                    $_SESSION['is_admin'] = 1;
                    header("Location: admin_dashboard.php");
                } else {
                    $_SESSION['is_admin'] = 0;
                    if (strpos($user['role'], 'seller') !== false) {
                        header("Location: seller_dashboard.php");
                    } else {
                        header("Location: index.php");
                    }
                }
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            // If not found in automobileusers, check in automobilelogin (Buyers only)
            $stmt = $conn->prepare("SELECT id AS user_id, email, password FROM automobilelogin WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $stmt->close();

                if (password_verify($password, $user['password'])) {
                    // Fetch the actual name from automobileusers
                    $stmt = $conn->prepare("SELECT name FROM automobileusers WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 1) {
                        $userData = $result->fetch_assoc();
                        $_SESSION['name'] = $userData['name'];  // Get real name from automobileusers
                    } else {
                        $_SESSION['name'] = "Unknown User";  // If somehow name is missing, set a fallback value
                    }
                    $stmt->close();

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = 'buyer';
                    $_SESSION['is_admin'] = 0;

                    header("Location: index.html");
                    exit();
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }
        }
    }
}

$conn->close();
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
            <p><a href="forgot.html">Forgot Password?</a></p>
        </div>
    </div>
</body>
</html>