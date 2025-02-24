<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$vehicle_id = isset($_GET['vehicle_id']) ? $_GET['vehicle_id'] : null;

if (!$vehicle_id) {
    header("Location: buyer_dashboard.php");
    exit();
}

// Fetch vehicle details including seller_id
$sql = "SELECT v.*, u.user_id as seller_id 
        FROM vehicle v 
        JOIN automobileusers u ON v.seller_id = u.user_id 
        WHERE v.vehicle_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicle = $result->fetch_assoc();

if (!$vehicle) {
    header("Location: buyer_dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $requested_date = $_POST['requested_date'];
    $requested_time = $_POST['requested_time'];
    $seller_id = $vehicle['seller_id'];

    $sql = "INSERT INTO tbl_testdrive (vehicle_id, buyer_id, seller_id, requested_date, requested_time, status) 
            VALUES (?, ?, ?, ?, ?, 'Pending')";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iiiss", $vehicle_id, $buyer_id, $seller_id, $requested_date, $requested_time);
        
        if ($stmt->execute()) {
            $success_message = "Test drive request submitted successfully!";
        } else {
            $error_message = "Error submitting request: " . $conn->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Test Drive - WheeleDeal</title>
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
            <div class="icons">
                <div class="nav-links">
                    <a href="index.php">Back to Home</a>
                    <a href="buyer_dashboard.php">Back to Dashboard</a>
                    <!-- <a href="logout.php">Logout</a> -->
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="test-drive-card">
            <h2>Request Test Drive</h2>
            
            <div class="vehicle-details">
                <h3><?php echo htmlspecialchars($vehicle['model']); ?></h3>
                <p>Year: <?php echo htmlspecialchars($vehicle['year']); ?></p>
                <p>Price: ₹<?php echo number_format($vehicle['price']); ?></p>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="POST" class="test-drive-form">
                <div class="form-group">
                    <label for="requested_date">Preferred Date</label>
                    <input type="date" id="requested_date" name="requested_date" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="requested_time">Preferred Time</label>
                    <input type="time" id="requested_time" name="requested_time" 
                           min="09:00" max="18:00" required>
                    <small>Available times: 9:00 AM - 6:00 PM</small>
                </div>

                <button type="submit" class="submit-btn">Request Test Drive</button>
            </form>
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
        padding: 10px 20px;
        background-color: white;
        border-bottom: 1px solid #ddd;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .logo img {
        height: 24px;
    }

    .container {
        max-width: 800px;
        margin: 40px auto;
        padding: 0 20px;
    }

    .test-drive-card {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .vehicle-details {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #555;
        font-weight: 500;
    }

    .form-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }

    .submit-btn {
        background-color: #ff5722;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        width: 100%;
    }

    .submit-btn:hover {
        background-color: #e64a19;
    }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 5px;
    }

    .success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }
    }

    .nav-links a {
        text-decoration: none;
        color: #333;
        margin-right: 20px;
        transition: color 0.3s;
    }

    .nav-links a:hover {
        color: orange;
    }
</style>

</body>
</html> 