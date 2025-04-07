<?php
session_start();
include 'db_connect.php';

// Store logout time in tbl_login if user was logged in
if (isset($_SESSION['user_id'])) {
    $query = "INSERT INTO tbl_login (user_id, login_time) VALUES (?, NOW())";
    $stmt = $conn->prepare($query);
    
    if ($stmt === false) {
        // Log the error but continue with logout
        error_log("Database error during logout: " . $conn->error);
    } else {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }
}

// Destroy all session data
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - WheeledDeal</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f7f4f1;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .logout-container {
            text-align: center;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .logout-container h2 {
            color: #333;
        }
        .logout-container button {
            background-color: #ff471a;
            color: #fff;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        .logout-container button:hover {
            background-color: #e63f15;
        }
    </style>
</head>
<body>

<div class="logout-container">
    <h2>Are you sure you want to log out?</h2>
    <form method="POST">
        <button type="submit">Logout</button>
    </form>
</div>

</body>
</html>
