<?php
session_start();
include 'db_connect.php';

// Get the transaction ID from either GET parameter or session
$transaction_id = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : 
                (isset($_SESSION['current_transaction_id']) ? $_SESSION['current_transaction_id'] : 0);

// Debug information
if ($transaction_id == 0) {
    echo "Transaction ID not found. Debug info:<br>";
    echo "GET transaction_id: " . (isset($_GET['transaction_id']) ? $_GET['transaction_id'] : 'Not set') . "<br>";
    echo "SESSION transaction_id: " . (isset($_SESSION['current_transaction_id']) ? $_SESSION['current_transaction_id'] : 'Not set') . "<br>";
    exit();
}

// Debug the transaction ID
// echo "Looking for transaction ID: " . $transaction_id . "<br>";

// First, check if the transaction exists
$check_sql = "SELECT * FROM tbl_transactions WHERE transaction_id = ?";
$check_stmt = $conn->prepare($check_sql);
if ($check_stmt === false) {
    die("Error preparing check statement: " . $conn->error);
}

$check_stmt->bind_param("i", $transaction_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    die("Transaction ID $transaction_id not found in tbl_transactions table.");
}

// Now fetch the full transaction details from the updated tables
$sql = "SELECT t.*, 
        v.brand, v.model, v.year,
        seller.name as seller_name,
        seller.email as seller_email,
        seller.phone as seller_number
        FROM tbl_transactions t
        LEFT JOIN tbl_vehicles v ON t.vehicle_id = v.vehicle_id
        LEFT JOIN tbl_users seller ON v.seller_id = seller.user_id
        WHERE t.transaction_id = ?";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing main statement: " . $conn->error);
}

$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Debug the query
    echo "Transaction found but join query failed. Debug info:<br>";
    echo "SQL: $sql<br>";
    echo "Transaction ID: $transaction_id<br>";
    exit();
}

$transaction = $result->fetch_assoc();

// Debug transaction data
if (!$transaction) {
    echo "Failed to fetch transaction data. Debug info:<br>";
    echo "Result rows: " . $result->num_rows . "<br>";
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - WheeleDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f7f9fc;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .success-icon {
            font-size: 80px;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .transaction-details {
            margin-top: 40px;
            text-align: left;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 15px;
        }
        
        .detail-label {
            width: 40%;
            font-weight: 500;
            color: #666;
        }
        
        .detail-value {
            width: 60%;
            color: #333;
        }
        
        .buttons {
            margin-top: 40px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #ff5722;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            margin: 0 10px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #e64a19;
        }
        
        .buttons .btn:last-child {
            background: #0066cc; /* Blue color for the bill button */
        }
        
        .buttons .btn:last-child:hover {
            background: #0055aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <i class="fas fa-check-circle success-icon"></i>
        <h1>Payment Successful!</h1>
        <p>Your booking has been completed successfully. Thank you for your payment.</p>
        
        <div class="transaction-details">
            <h2>Transaction Details</h2>
            <div class="detail-row">
                <div class="detail-label">Vehicle:</div>
                <div class="detail-value">
                    <?php 
                    if (isset($transaction['brand']) && isset($transaction['model']) && isset($transaction['year'])) {
                        echo htmlspecialchars($transaction['brand'] . ' ' . $transaction['model'] . ' (' . $transaction['year'] . ')');
                    } else {
                        echo "Vehicle details not available";
                    }
                    ?>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Booking Amount:</div>
                <div class="detail-value">₹<?php echo number_format($transaction['amount']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Payment Method:</div>
                <div class="detail-value"><?php echo htmlspecialchars($transaction['method']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Transaction ID:</div>
                <div class="detail-value"><?php echo htmlspecialchars($transaction['transaction_id']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Seller:</div>
                <div class="detail-value">
                    <?php if (isset($transaction['seller_name'])): ?>
                        <?php echo htmlspecialchars($transaction['seller_name']); ?><br>
                        Email: <?php echo htmlspecialchars($transaction['seller_email']); ?><br>
                        Phone: <?php echo htmlspecialchars($transaction['seller_number']); ?>
                    <?php else: ?>
                        Seller information not available
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="buttons">
            <a href="buyer_dashboard.php" class="btn">Back to Dashboard</a>
            <a href="generate_invoice.php?transaction_id=<?php echo htmlspecialchars($transaction['transaction_id']); ?>" class="btn">Download Bill</a>
        </div>
    </div>
</body>
</html> 