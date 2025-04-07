<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$vehicle_id = isset($_GET['vehicle_id']) ? $_GET['vehicle_id'] : null;
$is_ev = isset($_GET['is_ev']) && $_GET['is_ev'] == 1;

// Check if vehicle_id is provided
if (!$vehicle_id) {
    header("Location: buyer_dashboard.php");
    exit();
}

if ($is_ev) {
    // Fetch EV and seller details
    $sql = "SELECT v.*, e.range_km, e.battery_capacity, e.charging_time_ac, 
            e.charging_time_dc, e.electric_motor, e.max_power, e.max_torque,
            u.user_id as seller_id, u.name as seller_name 
            FROM tbl_vehicles v 
            JOIN tbl_ev e ON v.vehicle_id = e.vehicle_id
            JOIN tbl_users u ON v.seller_id = u.user_id 
            WHERE v.vehicle_id = ? AND v.vehicle_type = 'EV'";
    
    // Add error handling for prepare statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error . " (SQL: $sql)");
    }
    
    $stmt->bind_param("i", $vehicle_id);
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $vehicle = $result->fetch_assoc();
    
    if (!$vehicle) {
        header("Location: buyer_dashboard.php");
        exit();
    }
    
    // Fetch seller's available dates for this specific EV
    $sql = "SELECT * FROM tbl_seller_availability 
            WHERE seller_id = ? 
            AND vehicle_id = ?
            AND available_date >= CURDATE()
            AND status = 'available'
            ORDER BY available_date, start_time";
    
    // Add error handling for prepare statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error . " (SQL: $sql)");
    }
    
    $stmt->bind_param("ii", $vehicle['seller_id'], $vehicle_id);
} else {
    // Fetch regular vehicles
    $sql = "SELECT v.*, u.user_id as seller_id, u.name as seller_name 
            FROM tbl_vehicles v 
            JOIN tbl_users u ON v.seller_id = u.user_id 
            WHERE v.vehicle_id = ? AND v.vehicle_type != 'Electric'";
    
    // Add error handling for prepare statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error . " (SQL: $sql)");
    }
    
    $stmt->bind_param("i", $vehicle_id);
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $vehicle = $result->fetch_assoc();
    
    if (!$vehicle) {
        header("Location: buyer_dashboard.php");
        exit();
    }
    
    // Fetch seller's available dates for regular vehicles
    $sql = "SELECT * FROM tbl_seller_availability 
            WHERE seller_id = ? 
            AND vehicle_id = ?
            AND available_date >= CURDATE()
            AND status = 'available'
            ORDER BY available_date, start_time";
    
    // Add error handling for prepare statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error . " (SQL: $sql)");
    }
    
    $stmt->bind_param("ii", $vehicle['seller_id'], $vehicle_id);
}

// Add error handling for execute
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$availabilities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $availability_id = $_POST['availability_id'];
    $chosen_time = $_POST['chosen_time']; // Get the selected specific time
    
    // Verify availability still exists and is not booked
    // First, let's verify the correct column name for the primary key in tbl_seller_availability
    // It could be 'id' or 'availability_id' or something else
    $check_sql = "SELECT * FROM tbl_seller_availability WHERE id = ? AND status = 'available'";
    
    // Add error handling for prepare statement
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        // Try alternative column name if 'id' fails
        $error_message = "First prepare attempt failed: " . $conn->error . ". Trying alternative column name.";
        error_log($error_message);
        
        // Try with 'availability_id' instead
        $check_sql = "SELECT * FROM tbl_seller_availability WHERE availability_id = ? AND status = 'available'";
        $check_stmt = $conn->prepare($check_sql);
        
        if (!$check_stmt) {
            die("Prepare failed: " . $conn->error . " (SQL: $check_sql)");
        }
    }
    
    $check_stmt->bind_param("i", $availability_id);
    if (!$check_stmt->execute()) {
        die("Execute failed: " . $check_stmt->error);
    }
    
    $availability = $check_stmt->get_result()->fetch_assoc();
    
    if ($availability) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update availability status
            $update_column = strpos($check_sql, "id =") !== false ? "id" : "availability_id";
            $update_sql = "UPDATE tbl_seller_availability SET status = 'booked' WHERE $update_column = ?";
            
            $update_stmt = $conn->prepare($update_sql);
            if (!$update_stmt) {
                throw new Exception("Update prepare failed: " . $conn->error);
            }
            
            $update_stmt->bind_param("i", $availability_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Update execute failed: " . $update_stmt->error);
            }
            
            // Use the chosen specific time instead of the start_time
            $status = "Pending";
            
            $insert_sql = "INSERT INTO tbl_test_drives (vehicle_id, buyer_id, seller_id, requested_date, requested_time, status) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            if (!$insert_stmt) {
                throw new Exception("Insert prepare failed: " . $conn->error);
            }
            
            $insert_stmt->bind_param("iiisss", 
                $vehicle_id, 
                $buyer_id, 
                $vehicle['seller_id'],
                $availability['available_date'],
                $chosen_time, // Use the specifically selected time
                $status
            );
            
            if (!$insert_stmt->execute()) {
                throw new Exception("Insert execute failed: " . $insert_stmt->error);
            }
            
            $conn->commit();
            $success_message = "Test drive request submitted successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error submitting request: " . $e->getMessage();
            error_log("Test drive request error: " . $e->getMessage());
        }
    } else {
        $error_message = "Selected time slot is no longer available.";
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
                    <a href="view_test_drives.php">My Test Drives</a>
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
                <p>Seller: <?php echo htmlspecialchars($vehicle['seller_name']); ?></p>
                <?php if ($is_ev): ?>
                    <p>Range: <?php echo htmlspecialchars($vehicle['range_km']); ?> km</p>
                    <p>Battery: <?php echo htmlspecialchars($vehicle['battery_capacity']); ?> kWh</p>
                <?php endif; ?>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if (empty($availabilities)): ?>
                <div class="alert info">
                    No available time slots found. Please check back later.
                </div>
            <?php else: ?>
                <form method="POST" class="test-drive-form">
                    <div class="availability-grid">
                        <?php 
                        $current_date = null;
                        foreach ($availabilities as $slot): 
                            $date = new DateTime($slot['available_date']);
                            if ($current_date !== $slot['available_date']):
                                $current_date = $slot['available_date'];
                        ?>
                            <div class="date-header">
                                <?php echo $date->format('l, F j, Y'); ?>
                            </div>
                        <?php endif; ?>
                            <div class="time-slot-container">
                                <label class="time-slot">
                                    <input type="radio" name="availability_id" value="<?php echo $slot['id']; ?>" 
                                           data-start="<?php echo $slot['start_time']; ?>" 
                                           data-end="<?php echo $slot['end_time']; ?>"
                                           class="slot-radio" required>
                                    <span class="slot-range">
                                        <?php 
                                        $start = new DateTime($slot['start_time']);
                                        $end = new DateTime($slot['end_time']);
                                        echo $start->format('g:i A') . ' - ' . $end->format('g:i A');
                                        ?>
                                    </span>
                                </label>
                                <div class="specific-time-selector" style="display: none;">
                                    <label for="specific_time_<?php echo $slot['id']; ?>">Choose specific time:</label>
                                    <select name="specific_time_<?php echo $slot['id']; ?>" class="specific-time" id="specific_time_<?php echo $slot['id']; ?>">
                                        <?php
                                        // Generate time options in 30-minute intervals within the range
                                        $current = clone $start;
                                        $interval = new DateInterval('PT30M'); // 30 minute intervals
                                        
                                        while ($current < $end) {
                                            echo '<option value="' . $current->format('H:i:s') . '">' . 
                                                 $current->format('g:i A') . '</option>';
                                            $current->add($interval);
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <input type="hidden" name="chosen_time" id="chosen_time">
                    </div>

                    <button type="submit" class="submit-btn">Request Test Drive</button>
                </form>
            <?php endif; ?>
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

    .info {
        background-color: #e2e3e5;
        color: #383d41;
        border: 1px solid #d6d8db;
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

    .availability-grid {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin: 20px 0;
    }

    .date-header {
        background: #f8f9fa;
        padding: 10px 15px;
        border-radius: 5px;
        font-weight: 500;
        color: #2c3e50;
        margin-top: 15px;
    }

    .time-slot {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .time-slot:hover {
        background: #f8f9fa;
    }

    .time-slot input[type="radio"] {
        margin-right: 10px;
    }

    .slot-time {
        font-size: 14px;
        color: #495057;
    }

    .time-slot input[type="radio"]:checked + .slot-time {
        color: #ff5722;
        font-weight: 500;
    }

    .time-slot-container {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        background-color: white;
        transition: all 0.3s ease;
    }

    .time-slot-container:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .time-slot {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        cursor: pointer;
    }

    .slot-range {
        font-weight: 500;
        color: #333;
        margin-left: 10px;
    }

    .specific-time-selector {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #e0e0e0;
    }

    .specific-time-selector label {
        display: block;
        margin-bottom: 5px;
        color: #666;
        font-size: 0.9em;
    }

    .specific-time {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: #f9f9f9;
        font-family: 'Poppins', sans-serif;
    }

    .slot-radio:checked + .slot-range {
        color: #ff5722;
    }

    .time-slot-container.selected {
        border-color: #ff5722;
        background-color: #fff8f5;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get all radio buttons
    const radioButtons = document.querySelectorAll('.slot-radio');
    const specificTimeSelectors = document.querySelectorAll('.specific-time-selector');
    const chosenTimeInput = document.getElementById('chosen_time');
    
    // Add event listeners to all radio buttons
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            // Hide all time selectors first
            specificTimeSelectors.forEach(selector => {
                selector.style.display = 'none';
            });
            
            // Show the time selector for the selected slot
            if (this.checked) {
                const container = this.closest('.time-slot-container');
                const timeSelector = container.querySelector('.specific-time-selector');
                timeSelector.style.display = 'block';
                
                // Update the hidden input with the currently selected specific time
                const specificTime = timeSelector.querySelector('.specific-time');
                chosenTimeInput.value = specificTime.value;
                
                // Add event listener to update hidden input when specific time changes
                specificTime.addEventListener('change', function() {
                    chosenTimeInput.value = this.value;
                });
            }
        });
    });
});
</script>

</body>
</html> 