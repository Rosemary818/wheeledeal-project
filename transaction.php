<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if vehicle_id is provided
if (!isset($_GET['vehicle_id'])) {
    header('Location: buyer_dashboard.php');
    exit();
}

$vehicle_id = $_GET['vehicle_id'];
$buyer_id = $_SESSION['user_id'];

// Fetch vehicle details
$sql = "SELECT v.*, vp.photo_file_path, u.name as seller_name
        FROM vehicle v 
        LEFT JOIN vehicle_photos vp ON v.vehicle_id = vp.vehicle_id
        LEFT JOIN automobileusers u ON v.seller_id = u.user_id
        WHERE v.vehicle_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

// Handle transaction submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['payment_method'];
    $amount = $vehicle['price'];
    
    $sql = "INSERT INTO tbl_transaction (vehicle_id, buyer_id, method, amount, payment_status) 
            VALUES (?, ?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iids", $vehicle_id, $buyer_id, $method, $amount);
    
    if ($stmt->execute()) {
        $transaction_id = $conn->insert_id;
        // Redirect to payment processing page (you can implement this later)
        header("Location: process_payment.php?transaction_id=" . $transaction_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Transaction - WheeleDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <img src="images/logo3.png" alt="Logo">
            <h1>WheeledDeal</h1>
        </div>
        <nav>
            <div class="icons">
                <?php if (isset($_SESSION['name'])): ?>
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
                <?php endif; ?>
                <div class="nav-links">
                    <a href="buyer_dashboard.php">Back to Dashboard</a>
                    <a href="my_wishlist.php">My Wishlist</a>
                    <a href="view_test_drives.php">My Test Drives</a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="transaction-wrapper">
            <div class="vehicle-summary">
                <h2>Vehicle Details</h2>
                <div class="vehicle-card">
                    <img src="<?php echo htmlspecialchars($vehicle['photo_file_path'] ?? 'uploads/default_car_image.jpg'); ?>" alt="Vehicle Image">
                    <div class="vehicle-info">
                        <h3><?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['model']); ?></h3>
                        <p class="seller">Seller: <?php echo htmlspecialchars($vehicle['seller_name']); ?></p>
                        <p class="price">₹<?php echo number_format($vehicle['price']); ?></p>
                    </div>
                </div>
            </div>

            <div class="payment-section">
                <h2>Payment Details</h2>
                <form method="POST" class="payment-form">
                    <div class="form-group">
                        <label>Select Payment Method:</label>
                        <div class="payment-methods">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Ready Cash" required>
                                <span class="method-name">
                                    <i class="fas fa-money-bill-wave"></i>
                                    Ready Cash
                                    <small>Pay directly to seller</small>
                                </span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="UPI">
                                <span class="method-name">
                                    <i class="fas fa-mobile-alt"></i>
                                    UPI
                                </span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Credit Card">
                                <span class="method-name">
                                    <i class="fas fa-credit-card"></i>
                                    Credit Card
                                </span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Debit Card">
                                <span class="method-name">
                                    <i class="fas fa-credit-card"></i>
                                    Debit Card
                                </span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Net Banking">
                                <span class="method-name">
                                    <i class="fas fa-university"></i>
                                    Net Banking
                                </span>
                            </label>
                        </div>
                    </div>

                    <div id="ready-cash-info" class="payment-info" style="display: none;">
                        <div class="info-box">
                            <h3><i class="fas fa-info-circle"></i> Ready Cash Information</h3>
                            <ul>
                                <li>Meet the seller at a safe location</li>
                                <li>Verify the vehicle thoroughly before payment</li>
                                <li>Get proper documentation and receipts</li>
                                <li>Consider bringing someone along</li>
                            </ul>
                        </div>
                    </div>

                    <div class="amount-summary">
                        <div class="amount-row">
                            <span>Vehicle Price:</span>
                            <span>₹<?php echo number_format($vehicle['price']); ?></span>
                        </div>
                        <div class="amount-row total">
                            <span>Total Amount:</span>
                            <span>₹<?php echo number_format($vehicle['price']); ?></span>
                        </div>
                    </div>

                    <button type="submit" class="proceed-btn">Proceed with Payment</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        body {
            margin: 0;
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f7f4f1;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo img {
            height: 40px;
            width: auto;
        }

        .logo h1 {
            font-size: 24px;
            margin: 0;
            color: #333;
        }

        nav {
            display: flex;
            align-items: center;
        }

        .icons {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .transaction-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .vehicle-summary, .payment-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .vehicle-card {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .vehicle-card img {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }

        .vehicle-info h3 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .price {
            color: #ff5722;
            font-size: 24px;
            font-weight: 600;
            margin: 10px 0;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-option:hover {
            border-color: #ff5722;
        }

        .method-name {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .method-name i {
            font-size: 20px;
            color: #ff5722;
            margin-right: 8px;
        }

        .method-name small {
            font-size: 12px;
            color: #666;
        }

        .amount-summary {
            margin: 30px 0;
            padding: 20px;
            background: #f8f8f8;
            border-radius: 8px;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .amount-row.total {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            font-weight: 600;
            font-size: 18px;
        }

        .proceed-btn {
            width: 100%;
            padding: 15px;
            background: #ff5722;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .proceed-btn:hover {
            background: #e64a19;
        }

        .info-box {
            background: #fff3e0;
            border: 1px solid #ffe0b2;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }

        .info-box h3 {
            color: #f57c00;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }

        .info-box ul {
            margin: 0;
            padding-left: 20px;
        }

        .info-box li {
            color: #666;
            margin: 5px 0;
            font-size: 14px;
        }

        .payment-info {
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .transaction-wrapper {
                grid-template-columns: 1fr;
            }

            .vehicle-card {
                flex-direction: column;
            }

            .vehicle-card img {
                width: 100%;
                height: 200px;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
    document.querySelectorAll('input[name="payment_method"]').forEach(input => {
        input.addEventListener('change', function() {
            const readyCashInfo = document.getElementById('ready-cash-info');
            if (this.value === 'Ready Cash') {
                readyCashInfo.style.display = 'block';
            } else {
                readyCashInfo.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html> 