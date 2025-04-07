<?php
session_start();
include 'db_connect.php';

// Add session debugging
error_log("Session ID: " . session_id());
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if vehicle_id is provided
if (!isset($_GET['vehicle_id'])) {
    header('Location: buyer_dashboard.php');
    exit();
}

$vehicle_id = $_GET['vehicle_id'] ?? null;
$buyer_id = $_SESSION['user_id'];

// Fetch vehicle details
$sql = "SELECT v.*, p.photo_file_path, u.name as seller_name, u.email as seller_email, u.phone as seller_phone 
        FROM tbl_vehicles v 
        LEFT JOIN tbl_photos p ON v.vehicle_id = p.vehicle_id
        LEFT JOIN tbl_users u ON v.seller_id = u.user_id
        WHERE v.vehicle_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $vehicle_id);

$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

// Your Razorpay Key (Use test key for development)
$razorpayKey = "rzp_test_UCkwPwdXFEbdg0";

// Set fixed amount for all vehicles
$amount = 50000; // ₹50,000
$razorpayAmount = $amount * 100; // Convert to paise (₹50,000 = 5000000 paise)

// Variable to store transaction ID for use in JavaScript
$transaction_id = null;

// First, handle the form submission and create transaction BEFORE Razorpay initialization
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['payment_method'];
    $current_date = date('Y-m-d H:i:s');
    
    if ($method === 'Ready Cash') {
        // Handle Ready Cash option
        $sql = "INSERT INTO tbl_transactions (vehicle_id, buyer_id, method, amount, status, transaction_date) 
                VALUES (?, ?, ?, ?, 'Pending', ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("iisds", $vehicle_id, $buyer_id, $method, $amount, $current_date);
        
        if ($stmt->execute()) {
            $transaction_id = $conn->insert_id;
            error_log("Ready Cash transaction created: ID = $transaction_id");
            
            // Update status of the vehicle
            $update_sql = "UPDATE tbl_vehicles SET status = 'Inactive' WHERE vehicle_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $vehicle_id);
            $update_stmt->execute();
            
            header("Location: process_payment.php?transaction_id=" . $transaction_id);
            exit();
        } else {
            die("Execute failed: " . $stmt->error);
        }
    } else {
        // For online payments (Razorpay)
        $order_id = 'ORD_' . time() . '_' . rand(1000, 9999);
        
        // Corrected column name from payment_status to status
        $sql = "INSERT INTO tbl_transactions (vehicle_id, buyer_id, method, amount, status, transaction_date, razorpay_order_id) 
                VALUES (?, ?, ?, ?, 'Pending', ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die("Prepare failed for online payment: " . $conn->error . " SQL: " . $sql);
        }
        $stmt->bind_param("iisdss", $vehicle_id, $buyer_id, $method, $amount, $current_date, $order_id);
        
        if (!$stmt->execute()) {
            die("Failed to insert transaction: " . $stmt->error);
        }
        
        $transaction_id = $conn->insert_id;
        $_SESSION['current_transaction_id'] = $transaction_id;
        error_log("Online transaction created: ID = $transaction_id, Session ID = " . session_id());
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
                
                <!-- Seller Information Section -->
                <div class="seller-information">
                    <h2>Seller Information</h2>
                    <div class="seller-details">
                        <p class="seller-name"><?php echo htmlspecialchars($vehicle['seller_name']); ?></p>
                        <p class="seller-phone">
                            <i class="fas fa-phone"></i>
                            <?php echo htmlspecialchars($vehicle['seller_phone']); ?>
                        </p>
                        <p class="seller-email">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($vehicle['seller_email']); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="payment-section">
                <h2>Payment Details</h2>
                <form method="POST" class="payment-form">
                    <input type="hidden" id="stored_transaction_id" value="<?php echo $transaction_id; ?>">
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
                        <div class="amount-row">
                            <span>Booking Amount:</span>
                            <span>₹<?php echo number_format($amount); ?></span>
                        </div>
                        <div class="amount-row total">
                            <span>Total Amount to Pay:</span>
                            <span>₹<?php echo number_format($amount); ?></span>
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
        .seller-information {
            margin-top: 30px;
            background: #f8f8f8;
            padding: 20px;
            border-radius: 10px;
        }
        
        .seller-information h2 {
            color: #333;
            margin: 0 0 20px 0;
            font-size: 20px;
            border-bottom: 2px solid #ff5722;
            padding-bottom: 10px;
            display: inline-block;
        }
        
        .seller-details p {
            margin: 15px 0;
            font-size: 16px;
            color: #333;
        }
        
        .seller-details .seller-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .seller-details i {
            color: #ff5722;
            margin-right: 10px;
            width: 20px;
            text-align: center;
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

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    // Debug console logs
    console.log("Page loaded");
    <?php if (isset($_SESSION['current_transaction_id'])): ?>
    console.log("Session transaction ID: <?php echo $_SESSION['current_transaction_id']; ?>");
    <?php endif; ?>
    <?php if ($transaction_id): ?>
    console.log("Direct transaction ID: <?php echo $transaction_id; ?>");
    <?php endif; ?>
    
    document.querySelector('.payment-form').addEventListener('submit', function(e) {
        const method = document.querySelector('input[name="payment_method"]:checked').value;
        console.log("Payment method selected:", method);
        
        if (method !== 'Ready Cash') {
            e.preventDefault();
            
            // Get stored transaction ID or create one if needed
            let transactionId = document.getElementById('stored_transaction_id').value;
            
            <?php if (isset($_SESSION['current_transaction_id'])): ?>
            // Use session transaction ID if available
            transactionId = transactionId || "<?php echo $_SESSION['current_transaction_id']; ?>";
            <?php endif; ?>
            
            console.log("Using transaction ID:", transactionId);
            
            if (!transactionId) {
                // If no transaction ID is available, submit the form to create one first
                console.log("No transaction ID found, submitting form normally");
                this.submit();
                return;
            }
            
            var options = {
                "key": "<?php echo $razorpayKey; ?>",
                "amount": <?php echo $razorpayAmount; ?>,
                "currency": "INR",
                "name": "WheeleDeal",
                "description": "Vehicle Booking Payment",
                "image": "images/logo3.png",
                "handler": function (response) {
                    console.log("Payment success, updating database with payment ID:", response.razorpay_payment_id);
                    
                    // Show processing indicator
                    document.querySelector('.proceed-btn').textContent = "Processing...";
                    document.querySelector('.proceed-btn').disabled = true;
                    
                    const requestData = {
                        payment_id: response.razorpay_payment_id,
                        transaction_id: transactionId,
                        vehicle_id: <?php echo $vehicle_id ?: 'null'; ?>,
                        method: method
                    };
                    
                    console.log("Sending data to update_payment.php:", JSON.stringify(requestData));
                    
                    fetch('update_payment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(requestData)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log("Server response:", data);
                        if (data.success) {
                            window.location.href = 'payment_success.php?transaction_id=' + transactionId;
                        } else {
                            alert('Payment verification failed: ' + (data.message || 'Unknown error'));
                            document.querySelector('.proceed-btn').textContent = "Proceed with Payment";
                            document.querySelector('.proceed-btn').disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error details:', error);
                        alert('Payment processing error. Please contact support. Error: ' + error.message);
                        document.querySelector('.proceed-btn').textContent = "Proceed with Payment";
                        document.querySelector('.proceed-btn').disabled = false;
                    });
                },
                "prefill": {
                    "name": "<?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : ''; ?>",
                    "email": "<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>",
                    "contact": "<?php echo isset($_SESSION['phone']) ? htmlspecialchars($_SESSION['phone']) : ''; ?>"
                },
                "theme": {
                    "color": "#ff5722"
                },
                "modal": {
                    "ondismiss": function() {
                        console.log("Payment modal dismissed");
                        document.querySelector('.proceed-btn').textContent = "Proceed with Payment";
                        document.querySelector('.proceed-btn').disabled = false;
                    }
                }
            };
            
            document.querySelector('.proceed-btn').textContent = "Opening Payment...";
            document.querySelector('.proceed-btn').disabled = true;
            
            try {
                var rzp1 = new Razorpay(options);
                rzp1.open();
                console.log("Razorpay modal opened");
            } catch (error) {
                console.error("Razorpay error:", error);
                alert("Payment gateway error. Please try again or choose a different payment method.");
                document.querySelector('.proceed-btn').textContent = "Proceed with Payment";
                document.querySelector('.proceed-btn').disabled = false;
            }
        }
    });

    // Show/hide Ready Cash info based on payment method selection
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
