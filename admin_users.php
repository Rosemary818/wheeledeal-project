<?php
session_start();
require_once 'db_connect.php';

// Simple admin authentication - just check if user exists and has admin role
// This matches what's likely in your admin_dashboard.php
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

// Get the user's role from the database - don't rely on session variables
$user_id = $_SESSION['user_id'];
$admin_check = $conn->query("SELECT role FROM tbl_users WHERE user_id = $user_id");
$is_admin = false;

if ($admin_check && $admin_check->num_rows > 0) {
    $user_data = $admin_check->fetch_assoc();
    $is_admin = ($user_data['role'] == 'admin');
}

if (!$is_admin) {
    header("Location: login.php");
    exit;
}

// Fetch all users
$users_query = "SELECT * FROM tbl_users WHERE role != 'admin' ORDER BY created_at DESC";
$users_result = $conn->query($users_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            display: flex;
            background-color: #f5f7fa;
        }
        
        .sidebar {
            width: 126px;
            height: 100vh;
            background-color: #2c3e50;
            color: white;
            position: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 20px;
        }
        
        .sidebar-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .sidebar-logo img {
            width: 60px;
            height: 60px;
        }
        
        .sidebar-logo h2 {
            font-size: 14px;
            text-align: center;
            margin-top: 5px;
        }
        
        .nav-menu {
            list-style: none;
            width: 100%;
        }
        
        .nav-menu li {
            margin-bottom: 5px;
        }
        
        .nav-menu a {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #b8c7ce;
            text-decoration: none;
            padding: 15px 0;
            transition: all 0.3s;
            font-size: 12px;
        }
        
        .nav-menu a:hover, .nav-menu a.active {
            background-color: #1a2530;
            color: white;
        }
        
        .nav-menu i {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .main-content {
            margin-left: 126px;
            padding: 20px;
            width: calc(100% - 126px);
        }
        
        .header {
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
        }
        
        .users-table {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
        }
        
        .users-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th {
            background-color: #34495e;
            color: white;
            text-align: left;
            padding: 12px 20px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .users-table td {
            padding: 12px 20px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .users-table tr:last-child td {
            border-bottom: none;
        }
        
        .users-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .action-buttons button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            font-size: 14px;
        }
        
        .edit-btn {
            color: #3498db;
        }
        
        .delete-btn {
            color: #e74c3c;
        }
        
        .add-user-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="images/logo3.png" alt="WheeledDeal Logo">
            <h2>WheeledDeal<br>Admin</h2>
        </div>
        <ul class="nav-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="admin_users.php" class="active"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="admin_listings.php"><i class="fas fa-car"></i> Listings</a></li>
            <li><a href="admin_transactions.php"><i class="fas fa-money-bill-wave"></i> Transactions</a></li>
            <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>User Management</h1>
        </div>
        
        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone Number</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($users_result && $users_result->num_rows > 0) {
                        while ($user = $users_result->fetch_assoc()) {
                            // Determine the user's name based on your database structure
                            // This assumes you have fields like first_name, last_name or name
                            $name = isset($user['name']) ? $user['name'] : 
                                   (isset($user['first_name']) ? $user['first_name'] . ' ' . $user['last_name'] : $user['email']);
                            
                            // Get phone number
                            $phone = isset($user['phone']) ? $user['phone'] : 
                                   (isset($user['phone_number']) ? $user['phone_number'] : 'N/A');
                            
                            echo "<tr>
                                <td>{$name}</td>
                                <td>{$phone}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='2'>No users found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 