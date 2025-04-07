<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History - WheeleDeal</title>
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
                <a href="buyer_dashboard.php">Back to Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="purchase-history-section">
            <h2>Purchase History</h2>
            <div class="purchase-container">
                <?php
                // Use DISTINCT and JOIN with seller details, GROUP BY transaction_id to prevent duplicates
                $purchase_sql = "SELECT DISTINCT t.*, 
                    v.model, v.brand, v.year, v.fuel_type, 
                    p.photo_file_path,
                    s.name as seller_name, s.email as seller_email, s.phone as seller_phone
                    FROM tbl_transactions t
                    JOIN tbl_vehicles v ON t.vehicle_id = v.vehicle_id
                    LEFT JOIN tbl_photos p ON v.vehicle_id = p.vehicle_id
                    JOIN tbl_users s ON v.seller_id = s.user_id
                    WHERE t.buyer_id = ? AND t.status = 'Completed'
                    GROUP BY t.transaction_id 
                    ORDER BY t.transaction_date DESC";
                
                $stmt = $conn->prepare($purchase_sql);
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $purchases = $stmt->get_result();

                if ($purchases->num_rows > 0) {
                    while ($purchase = $purchases->fetch_assoc()) {
                        // Get transaction date from database
                        $transaction_date = strtotime($purchase['transaction_date']);
                        // Calculate service end date (2 months from transaction)
                        $service_end_date = strtotime('+2 months', $transaction_date);
                        ?>
                        <div class="purchase-card">
                            <div class="purchase-header">
                                <h3><?php echo htmlspecialchars($purchase['brand'] . ' ' . $purchase['model'] . ' ' . $purchase['year']); ?>
                                    <?php if(strtolower($purchase['fuel_type']) == 'electric'): ?>
                                        <span class="ev-badge">EV</span>
                                    <?php endif; ?>
                                </h3>
                                <span class="purchase-date">
                                    <?php echo date('d M Y h:i A', $transaction_date); ?>
                                </span>
                            </div>
                            <div class="purchase-details">
                                <div class="detail-row">
                                    <span class="label">Transaction ID:</span>
                                    <span class="value"><?php echo $purchase['transaction_id']; ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Amount:</span>
                                    <span class="value">₹<?php echo number_format($purchase['amount'], 2); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Payment Method:</span>
                                    <span class="value"><?php echo $purchase['method']; ?></span>
                                </div>
                                <?php if (!empty($purchase['razorpay_order_id'])): ?>
                                <div class="detail-row">
                                    <span class="label">Order ID:</span>
                                    <span class="value"><?php echo $purchase['razorpay_order_id']; ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($purchase['razorpay_payment_id'])): ?>
                                <div class="detail-row">
                                    <span class="label">Payment ID:</span>
                                    <span class="value"><?php echo $purchase['razorpay_payment_id']; ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Added Seller Details Section -->
                                <div class="seller-details">
                                    <h4>Seller Information</h4>
                                    <div class="detail-row">
                                        <span class="label">Seller Name:</span>
                                        <span class="value"><?php echo htmlspecialchars($purchase['seller_name']); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">Seller Email:</span>
                                        <span class="value"><?php echo htmlspecialchars($purchase['seller_email']); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">Seller Phone:</span>
                                        <span class="value"><?php echo htmlspecialchars($purchase['seller_phone']); ?></span>
                                    </div>
                                </div>

                                <div class="service-receipt">
                                    <h4>Free Service Details</h4>
                                    <div class="service-details">
                                        <div class="service-row">
                                            <span class="label">Service Period:</span>
                                            <span class="value">2 Months</span>
                                        </div>
                                        <div class="service-row">
                                            <span class="label">Valid From:</span>
                                            <span class="value"><?php echo date('d M Y', $transaction_date); ?></span>
                                        </div>
                                        <div class="service-row">
                                            <span class="label">Valid Until:</span>
                                            <span class="value"><?php echo date('d M Y', $service_end_date); ?></span>
                                        </div>
                                        <div class="service-inclusions">
                                            <h5>Service Includes:</h5>
                                            <ul>
                                                <li>Regular Maintenance Check</li>
                                                <li>Oil Change</li>
                                                <li>Filter Replacement</li>
                                                <li>Brake Inspection</li>
                                                <li>General Vehicle Inspection</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <button class="download-receipt" onclick="window.location.href='generate_service_receipt.php?transaction_id=<?php echo $purchase['transaction_id']; ?>'">
                                        <i class="fas fa-download"></i> Download Service Receipt
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="no-purchases">No completed purchases found.</div>';
                }
                ?>
            </div>
        </div>
    </div>

<style>
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background-color: #f7f4f1;
}

header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 30px;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
    margin: 40px auto;
    padding: 0 20px;
}

.purchase-history-section {
    background: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.purchase-history-section h2 {
    color: #333;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.purchase-container {
    display: grid;
    gap: 20px;
}

.purchase-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    transition: transform 0.2s;
    border: 1px solid #e0e0e0;
}

.purchase-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.purchase-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
}

.purchase-header h3 {
    margin: 0;
    color: #333;
    font-size: 1.2em;
}

.ev-badge {
    background-color: #4CAF50;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    margin-left: 8px;
    font-weight: normal;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.label {
    color: #666;
    font-weight: 500;
}

.value {
    color: #333;
}

.no-purchases {
    text-align: center;
    padding: 40px;
    color: #666;
    background: #f8f9fa;
    border-radius: 8px;
    font-size: 1.1em;
    border: 1px solid #e0e0e0;
}

.seller-details {
    margin-top: 15px;
    padding: 15px;
    background: #f1f8e9;
    border-radius: 5px;
    border-left: 3px solid #4CAF50;
}

.seller-details h4 {
    margin-top: 0;
    color: #2E7D32;
    font-size: 1.1em;
    margin-bottom: 10px;
}

.download-receipt {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #ff5722;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    margin-top: 15px;
    cursor: pointer;
    width: 100%;
    transition: background-color 0.3s;
    text-decoration: none;
    font-size: 14px;
}

.download-receipt:hover {
    background: #ff5722;
}

.download-receipt i {
    font-size: 16px;
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

    .purchase-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .detail-row {
        flex-direction: column;
        gap: 4px;
    }
}
</style>

</body>
</html> 