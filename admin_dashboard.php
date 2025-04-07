<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}
require_once 'db_connect.php';

// Check if user is admin
$user_id = $_SESSION['user_id'];
$admin_check = $conn->prepare("SELECT role FROM tbl_users WHERE user_id = ?");
$admin_check->bind_param("i", $user_id);
$admin_check->execute();
$result = $admin_check->get_result();
$user = $result->fetch_assoc();

if(!$result || $user['role'] != 'admin'){
    header("Location: login.php");
    exit;
}

// Fetch dashboard statistics
// Total Users
$users_result = $conn->query("SELECT COUNT(*) as count FROM tbl_users WHERE role != 'admin'");
$total_users = $users_result->fetch_assoc()['count'];

// Total Listings
$listings_result = $conn->query("SELECT COUNT(*) as count FROM tbl_vehicles");
$total_listings = $listings_result->fetch_assoc()['count'];

// Total Sales
$sales_result = $conn->query("SELECT COUNT(*) as count FROM tbl_transactions WHERE status = 'completed'");
$total_sales = $sales_result->fetch_assoc()['count'];

// Pending Sales
$pending_result = $conn->query("SELECT COUNT(*) as count FROM tbl_vehicles WHERE status = 'pending'");
$pending_sales = $pending_result->fetch_assoc()['count'];

// Recent Users
$recent_users = $conn->query("SELECT user_id, CONCAT(first_name, ' ', last_name) as name, created_at 
                             FROM tbl_users 
                             WHERE role != 'admin' 
                             ORDER BY created_at DESC 
                             LIMIT 15");

// Recent Listings
$recent_listings = $conn->query("SELECT id, make, model, year, created_at 
                                FROM tbl_vehicles 
                                ORDER BY created_at DESC 
                                LIMIT 15");

// Format date and time
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        
        .date-display {
            color: #666;
            font-size: 14px;
        }
        
        .dashboard-cards {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            flex: 1;
            padding: 20px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        
        .card-icon {
            height: 40px;
            width: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .card:nth-child(1) {
            border-left: 5px solid #ffc107;
        }
        
        .card:nth-child(2) {
            border-left: 5px solid #4caf50;
        }
        
        .card:nth-child(3) {
            border-left: 5px solid #03a9f4;
        }
        
        .card:nth-child(4) {
            border-left: 5px solid #e91e63;
        }
        
        .card h3 {
            font-size: 14px;
            color: #555;
            margin-bottom: 5px;
            text-align: center;
        }
        
        .card p {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .recent-activity {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .recent-activity h2 {
            color: #333;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .activity-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .activity-section h3 {
            color: #555;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            display: flex;
            align-items: center;
        }
        
        .activity-icon {
            width: 24px;
            height: 24px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 12px;
        }
        
        .activity-text {
            font-size: 14px;
            color: #333;
        }
        
        .activity-date {
            font-size: 12px;
            color: #888;
        }
        
        .user-icon {
            background-color: #bbdefb;
            color: #1976d2;
        }
        
        .car-icon {
            background-color: #c8e6c9;
            color: #388e3c;
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
            <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="admin_listings.php"><i class="fas fa-car"></i> Listings</a></li>
            <li><a href="admin_transactions.php"><i class="fas fa-money-bill-wave"></i> Transactions</a></li>
            <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <div class="date-display">
                <?php echo date("l, F j, Y"); ?>
            </div>
        </div>
        
        <div class="dashboard-cards">
            <div class="card">
                <i class="fas fa-users" style="color: #ffc107;"></i>
                <h3>Total Users</h3>
                <p><?php echo $total_users; ?></p>
            </div>
            <div class="card">
                <i class="fas fa-car" style="color: #4caf50;"></i>
                <h3>Total Listings</h3>
                <p><?php echo $total_listings; ?></p>
            </div>
            <div class="card">
                <i class="fas fa-money-bill-wave" style="color: #03a9f4;"></i>
                <h3>Total Sales</h3>
                <p><?php echo $total_sales; ?></p>
            </div>
            <div class="card">
                <i class="fas fa-clock" style="color: #e91e63;"></i>
                <h3>Pending Sales</h3>
                <p><?php echo $pending_sales; ?></p>
            </div>
        </div>
        
        <div class="recent-activity">
            <h2>Recent Activity</h2>
            <div class="activity-grid">
                <div class="activity-section">
                    <h3>Latest Users</h3>
                    <ul class="activity-list">
                        <?php
                        if ($recent_users && $recent_users->num_rows > 0) {
                            while ($user = $recent_users->fetch_assoc()) {
                                $date = date('M d, Y', strtotime($user['created_at']));
                                echo "<li class='activity-item'>
                                    <div class='activity-info'>
                                        <div class='activity-icon user-icon'><i class='fas fa-user'></i></div>
                                        <span class='activity-text'>{$user['name']} joined</span>
                                    </div>
                                    <span class='activity-date'>$date</span>
                                </li>";
                            }
                        } else {
                            echo "<li class='activity-item'>No recent users</li>";
                        }
                        ?>
                    </ul>
                </div>
                <div class="activity-section">
                    <h3>Latest Listings</h3>
                    <ul class="activity-list">
                        <?php
                        if ($recent_listings && $recent_listings->num_rows > 0) {
                            while ($vehicle = $recent_listings->fetch_assoc()) {
                                $date = date('M d, Y', strtotime($vehicle['created_at']));
                                echo "<li class='activity-item'>
                                    <div class='activity-info'>
                                        <div class='activity-icon car-icon'><i class='fas fa-car'></i></div>
                                        <span class='activity-text'>{$vehicle['year']} {$vehicle['make']} {$vehicle['model']}</span>
                                    </div>
                                    <span class='activity-date'>$date</span>
                                </li>";
                            }
                        } else {
                            echo "<li class='activity-item'>No recent listings</li>";
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>