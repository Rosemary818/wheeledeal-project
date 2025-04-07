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

// Set default to current month - we're keeping this for calculations but removing the filter buttons
$date_range = 'month';
$start_date = date('Y-m-d 00:00:00', strtotime('-30 days'));
$today = date('Y-m-d 23:59:59');
$range_title = 'Last 30 Days';

// Sales summary - modified to be case-insensitive and use proper date format
$sales_query = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(amount) as total_revenue,
                    AVG(amount) as average_sale
                FROM tbl_transactions 
                WHERE LOWER(status) = 'completed'
                AND transaction_date BETWEEN ? AND ?";
$sales_stmt = $conn->prepare($sales_query);
$sales_stmt->bind_param("ss", $start_date, $today);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result()->fetch_assoc();

// Add a query to get ALL transactions count regardless of status
$all_transactions_query = "SELECT COUNT(*) as total FROM tbl_transactions WHERE transaction_date BETWEEN ? AND ?";
$all_trans_stmt = $conn->prepare($all_transactions_query);
$all_trans_stmt->bind_param("ss", $start_date, $today);
$all_trans_stmt->execute();
$all_trans_count = $all_trans_stmt->get_result()->fetch_assoc()['total'];

// User registrations
$user_reg_query = "SELECT 
                      COUNT(*) as new_users
                  FROM tbl_users
                  WHERE created_at BETWEEN ? AND ?
                  AND role != 'admin'";
$user_reg_stmt = $conn->prepare($user_reg_query);
$user_reg_stmt->bind_param("ss", $start_date, $today);
$user_reg_stmt->execute();
$new_users = $user_reg_stmt->get_result()->fetch_assoc()['new_users'];

// Get vehicle type distribution (EV vs ICE)
$vehicle_type_query = "SELECT 
                         vehicle_type,
                         COUNT(*) as count
                       FROM tbl_vehicles
                       WHERE vehicle_type IN ('EV', 'ICE')
                       GROUP BY vehicle_type";
$vehicle_type_result = $conn->query($vehicle_type_query);

$vehicle_type_data = [];
$vehicle_type_labels = [];
$vehicle_type_colors = [
    'EV' => '#8bc34a',  // Light Green for Electric
    'ICE' => '#ff9800'  // Orange for Internal Combustion
];
$vehicle_type_detail = [];
$total_categorized = 0;

// Process vehicle type data
while($row = $vehicle_type_result->fetch_assoc()) {
    $type = $row['vehicle_type'];
    $count = $row['count'];
    $total_categorized += $count;
    
    $label = ($type == 'EV') ? 'Electric Vehicles' : 'Internal Combustion';
    
    $vehicle_type_detail[$type] = [
        'label' => $label,
        'count' => $count,
        'color' => $vehicle_type_colors[$type]
    ];
    
    $vehicle_type_labels[] = $label;
    $vehicle_type_data[] = $count;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .header h1 {
            font-size: 24px;
            color: #333;
        }
        
        .report-actions {
            display: flex;
            gap: 10px;
        }
        
        .report-btn {
            background-color: #2c3e50;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 15px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .report-btn:hover {
            background-color: #1a2530;
        }
        
        .report-btn i {
            font-size: 16px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        
        .summary-card-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .summary-card-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .chart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .chart-card h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .chart-card-full {
            grid-column: span 2;
        }
        
        .table-container {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .table-container h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            text-align: left;
            padding: 12px;
            background-color: #f5f7fa;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-warning {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        
        .badge-info {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-secondary {
            background-color: #f5f5f5;
            color: #616161;
        }
        
        .chart-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .inventory-chart-container {
            margin-bottom: 20px;
            max-height: 250px;
        }
        
        .inventory-legend {
            border-top: 1px solid #eee;
            padding-top: 15px;
            font-size: 14px;
        }
        
        .inventory-total {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .inventory-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px;
        }
        
        .inventory-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .inventory-label {
            font-weight: 500;
        }
        
        .inventory-count {
            font-weight: bold;
        }
        
        .inventory-percent {
            color: #666;
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
            <li><a href="admin_transactions.php"><i class="fas fa-money-bill-wave"></i> Transactions</a></li>
            <li><a href="admin_reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Reports & Analytics</h1>
            <div class="report-actions">
                <a href="generate_pdf_report.php?type=users" class="report-btn">
                    <i class="fas fa-file-pdf"></i> Users Report
                </a>
                <a href="generate_pdf_report.php?type=sales" class="report-btn">
                    <i class="fas fa-file-pdf"></i> Sales Report
                </a>
                <a href="generate_pdf_report.php?type=vehicles" class="report-btn">
                    <i class="fas fa-file-pdf"></i> Vehicles Report
                </a>
            </div>
        </div>
        
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-card-title">Total Sales (<?php echo $range_title; ?>)</div>
                <div class="summary-card-value"><?php echo $all_trans_count ?: 0; ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Completed Sales (<?php echo $range_title; ?>)</div>
                <div class="summary-card-value"><?php echo $sales_result['total_transactions'] ?: 0; ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Revenue (<?php echo $range_title; ?>)</div>
                <div class="summary-card-value">₹<?php echo number_format($sales_result['total_revenue'] ?: 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Average Sale (<?php echo $range_title; ?>)</div>
                <div class="summary-card-value">₹<?php echo number_format($sales_result['average_sale'] ?: 0); ?></div>
            </div>
        </div>
        
        <div class="chart-container">
            <!-- Removed Sales Trend Chart -->
        </div>

        <!-- Keep only the EV vs ICE chart -->
        <div class="chart-container">
            <div class="chart-card chart-card-full">
                <h2>Vehicle Type Distribution</h2>
                <p class="chart-description">Distribution of vehicles by propulsion type (Electric vs Internal Combustion)</p>
                <div class="ev-ice-container" style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <canvas id="vehicleTypeChart" style="max-height: 250px;"></canvas>
                    </div>
                    <div class="inventory-legend" style="flex: 1; border-top: none; display: flex; flex-direction: column; justify-content: center;">
                        <?php if($total_categorized > 0): ?>
                            <div class="inventory-total">Total Vehicles: <?php echo $total_categorized; ?></div>
                            <div class="inventory-breakdown">
                                <?php foreach($vehicle_type_detail as $type => $detail): ?>
                                    <div class="inventory-item">
                                        <span class="color-dot" style="background-color: <?php echo $detail['color']; ?>"></span>
                                        <span class="inventory-label"><?php echo $detail['label']; ?>:</span>
                                        <span class="inventory-count"><?php echo $detail['count']; ?></span>
                                        <span class="inventory-percent">(<?php echo round(($detail['count']/$total_categorized)*100); ?>%)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No vehicle type data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Vehicle Type Chart (EV vs ICE)
        const vehicleTypeCtx = document.getElementById('vehicleTypeChart').getContext('2d');
        const vehicleTypeChart = new Chart(vehicleTypeCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($vehicle_type_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($vehicle_type_data); ?>,
                    backgroundColor: [
                        <?php 
                        // Output the colors in the same order as the labels
                        $colors = [];
                        foreach($vehicle_type_detail as $detail) {
                            $colors[] = $detail['color'];
                        }
                        echo "'" . implode("', '", $colors) . "'";
                        ?>
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.formattedValue;
                                const dataset = context.dataset;
                                const total = dataset.data.reduce((acc, data) => acc + data, 0);
                                const percentage = Math.round((context.raw / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 