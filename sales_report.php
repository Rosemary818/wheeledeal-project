<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - WheeledDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <img src="images/logo3.png" alt="Logo">
            <h1>WheeledDeal</h1>
        </div>
        <nav>
            <div class="nav-links">
                <a href="seller_dashboard.php">Back to Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="sales-report-section">
            <h2>Sold Vehicles Report</h2>
            
            <div class="sales-table-container">
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Vehicle Details</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sales_sql = "SELECT t.*, 
                            v.model as vehicle_model,
                            v.brand as vehicle_brand,
                            v.year as vehicle_year,
                            v.vehicle_type,
                            v.fuel_type
                            FROM tbl_transactions t
                            JOIN tbl_vehicles v ON t.vehicle_id = v.vehicle_id
                            WHERE v.seller_id = ? AND t.status = 'Completed'
                            AND (v.vehicle_type = 'ICE' OR v.vehicle_type = 'EV')
                            ORDER BY t.transaction_date DESC";
                        
                        $stmt = $conn->prepare($sales_sql);
                        if ($stmt === false) {
                            die("Error preparing statement: " . $conn->error);
                        }

                        $stmt->bind_param("i", $seller_id);
                        $stmt->execute();
                        $sales = $stmt->get_result();

                        if ($sales->num_rows > 0) {
                            while ($sale = $sales->fetch_assoc()) {
                                $vehicle_type = $sale['vehicle_type'];
                                $badge_class = ($vehicle_type == 'EV') ? 'ev' : 'ice';
                                ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($sale['transaction_date'])); ?></td>
                                    <td>
                                        <div class="vehicle-info">
                                            <strong><?php echo htmlspecialchars($sale['vehicle_brand'] . ' ' . $sale['vehicle_model']); ?></strong>
                                            <span class="year"><?php echo $sale['vehicle_year']; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo strtolower($badge_class); ?>">
                                            <?php echo $vehicle_type; ?>
                                        </span>
                                    </td>
                                    <td class="amount">₹<?php echo number_format($sale['amount'], 2); ?></td>
                                    <td><?php echo $sale['method']; ?></td>
                                    <td>
                                        <span class="status-badge completed">Sold</span>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="6" class="no-sales">No vehicles sold yet.</td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<style>
body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
    padding: 0;
    background: #f7f4f1;
}

header {
    background: white;
    padding: 15px 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
}

.logo img {
    height: 40px;
}

.logo h1 {
    margin: 0;
    font-size: 24px;
    color: #333;
}

.nav-links a {
    text-decoration: none;
    color: #333;
    margin-left: 20px;
    transition: color 0.3s;
}

.nav-links a:hover {
    color: #ff5722;
}

.container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 20px;
}

.sales-report-section {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.sales-report-section h2 {
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.sales-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.sales-table th,
.sales-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.sales-table th {
    background: #f8f9fa;
    font-weight: 500;
    color: #333;
}

.sales-table tr:hover {
    background-color: #f8f9fa;
}

.vehicle-info {
    display: flex;
    flex-direction: column;
}

.vehicle-info .year {
    color: #666;
    font-size: 0.9em;
}

.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.85em;
}

.badge.ice {
    background: #e2e3e5;
    color: #383d41;
}

.badge.ev {
    background: #d4edda;
    color: #155724;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.85em;
}

.status-badge.completed {
    background: #d4edda;
    color: #155724;
}

.amount {
    font-weight: 500;
    color: #155724;
}

.no-sales {
    text-align: center;
    color: #666;
    padding: 30px;
    font-style: italic;
}

@media (max-width: 768px) {
    header {
        flex-direction: column;
        padding: 15px;
    }

    .nav-links {
        margin-top: 15px;
    }

    .nav-links a {
        margin: 0 10px;
    }

    .sales-table-container {
        overflow-x: auto;
    }

    .sales-table th,
    .sales-table td {
        padding: 10px;
    }
}
</style>

</body>
</html> 