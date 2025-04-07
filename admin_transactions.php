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

// Handle filtering
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = "";

if($filter == 'completed') {
    $where_clause = " WHERE LOWER(t.status) = 'completed'";
} elseif($filter == 'pending') {
    $where_clause = " WHERE LOWER(t.status) = 'pending'";
}

// Get all transactions
$transactions_query = "SELECT t.*, 
                        v.brand, v.model, v.year, v.vehicle_type,
                        u.name as buyer_name
                      FROM tbl_transactions t
                      LEFT JOIN tbl_vehicles v ON t.vehicle_id = v.vehicle_id
                      LEFT JOIN tbl_users u ON t.buyer_id = u.user_id" . 
                      $where_clause . 
                      " ORDER BY t.transaction_date DESC";

// Add this debug statement to see the actual SQL query
// echo "<p>Debug SQL: $transactions_query</p>";

$transactions_result = $conn->query($transactions_query);

// Add this to check if query executed successfully
if (!$transactions_result) {
    echo "Error in query: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Admin</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
        .filter-button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            background-color: #f0f0f0;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .filter-button:hover {
            background-color: #e0e0e0;
        }
        
        .filter-button.active {
            background-color: #2c3e50;
            color: white;
        }
        
        /* Transaction table styles */
        .transactions-table {
            width: 100%;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .transactions-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .transactions-table th {
            background-color: #2c3e50;
            color: white;
            text-align: left;
            padding: 15px;
            font-size: 14px;
        }
        
        .transactions-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .transactions-table tr:last-child td {
            border-bottom: none;
        }
        
        .transactions-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
            display: inline-block;
            min-width: 80px;
        }
        
        .status.completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status.pending {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        
        .status.cancelled {
            background-color: #ffebee;
            color: #c62828;
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
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="admin_listings.php"><i class="fas fa-car"></i> Listings</a></li>
            <li><a href="admin_transactions.php" class="active"><i class="fas fa-money-bill-wave"></i> Transactions</a></li>
            <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Transactions</h1>
            <div class="filter-buttons">
                <a href="admin_transactions.php" class="filter-button <?php echo $filter == 'all' ? 'active' : ''; ?>">All Transactions</a>
                <a href="admin_transactions.php?filter=completed" class="filter-button <?php echo $filter == 'completed' ? 'active' : ''; ?>">Completed</a>
                <a href="admin_transactions.php?filter=pending" class="filter-button <?php echo $filter == 'pending' ? 'active' : ''; ?>">Pending</a>
            </div>
        </div>
        
        <div class="transactions-table">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Buyer</th>
                        <th>Payment Method</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Transaction ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($transactions_result && $transactions_result->num_rows > 0) {
                        while ($transaction = $transactions_result->fetch_assoc()) {
                            $vehicle_name = $transaction['year'] . ' ' . $transaction['brand'] . ' ' . $transaction['model'];
                            $vehicle_name = $vehicle_name ?: 'Unknown Vehicle';
                            
                            $buyer_name = $transaction['buyer_name'] ?: 'Unknown Buyer';
                            
                            $amount = '₹' . number_format($transaction['amount']);
                            
                            $date = date('d M Y', strtotime($transaction['transaction_date']));
                            
                            $status_class = strtolower($transaction['status']);
                            $status_text = ucfirst($transaction['status']);
                            
                            // Use razorpay_payment_id if available, otherwise use transaction_id
                            $transaction_id = !empty($transaction['razorpay_payment_id']) ? 
                                $transaction['razorpay_payment_id'] : 
                                'TXN_' . $transaction['transaction_id'];
                            
                            echo "<tr>
                                <td>{$vehicle_name}</td>
                                <td>{$buyer_name}</td>
                                <td>{$transaction['method']}</td>
                                <td>{$amount}</td>
                                <td>{$date}</td>
                                <td><span class='status {$status_class}'>{$status_text}</span></td>
                                <td>{$transaction_id}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align:center;'>No transactions found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 