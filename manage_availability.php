<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

// First, let's modify the vehicle selection query to use the new tables
$vehicles_sql = "SELECT 
    CASE WHEN v.vehicle_type = 'Electric' THEN 'ev' ELSE 'normal' END as vehicle_type,
    v.vehicle_id as id,
    v.brand,
    v.model,
    v.year 
    FROM tbl_vehicles v
    WHERE v.seller_id = ?";

// Fix the vehicle query binding
$stmt = $conn->prepare($vehicles_sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fix the availability fetch query - updated to remove ev_id references
$availability_sql = "SELECT 
    sa.*,
    v.brand,
    v.model,
    v.year,
    v.vehicle_type
    FROM tbl_seller_availability sa
    JOIN tbl_vehicles v ON sa.vehicle_id = v.vehicle_id 
    WHERE sa.seller_id = ?
    AND sa.available_date >= CURDATE()
    ORDER BY sa.available_date, sa.start_time";

// Fix the availability query binding
$avail_stmt = $conn->prepare($availability_sql);
if (!$avail_stmt) {
    die("Prepare failed: " . $conn->error);
}
if (!$avail_stmt->bind_param("i", $seller_id)) {
    die("Binding parameters failed: " . $avail_stmt->error);
}
if (!$avail_stmt->execute()) {
    die("Execute failed: " . $avail_stmt->error);
}
$availabilities = $avail_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process the results for display
foreach ($availabilities as &$availability) {
    $availability['vehicle_type'] = $availability['vehicle_type'] === 'Electric' ? 'ev' : 'normal';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vehicle_id = $_POST['vehicle_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $selected_days = isset($_POST['days']) ? $_POST['days'] : [];

    if (!empty($selected_days)) {
        try {
            $conn->begin_transaction();
            $success_count = 0;

            $start_date_obj = new DateTime($start_date);
            $end_date_obj = new DateTime($end_date);
            $current_date = clone $start_date_obj;

            while ($current_date <= $end_date_obj) {
                $current_day = $current_date->format('N');
                if (in_array($current_day, $selected_days)) {
                    $formatted_date = $current_date->format('Y-m-d');
                    
                    // Check for existing availability
                    $check_sql = "SELECT id FROM tbl_seller_availability 
                                WHERE seller_id = ? 
                                AND vehicle_id = ?
                                AND available_date = ? 
                                AND start_time = ? 
                                AND end_time = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("iisss", 
                        $seller_id, 
                        $vehicle_id,
                        $formatted_date,
                        $start_time,
                        $end_time
                    );
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();

                    if ($result->num_rows === 0) {
                        $sql = "INSERT INTO tbl_seller_availability 
                                (seller_id, vehicle_id, available_date, start_time, end_time, status) 
                                VALUES (?, ?, ?, ?, ?, 'available')";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iisss", 
                            $seller_id,
                            $vehicle_id,
                            $formatted_date, 
                            $start_time, 
                            $end_time
                        );
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Database error: " . $stmt->error);
                        } else {
                            $success_count++;
                        }
                    }
                }
                $current_date->modify('+1 day');
            }

            $conn->commit();
            if ($success_count > 0) {
                $success_message = "Successfully added availability for $success_count day(s)!";
            }

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
            error_log("Availability insertion error: " . $e->getMessage());
        }
    } else {
        $error_message = "Please select at least one day of the week.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Availability - WheeleDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="dashboard-header">
        <div class="header-left">
            <div class="logo-container">
                <img src="images/logo3.png" alt="WheeleDeal Logo" class="logo">
                <h1>WheeleDeal</h1>
            </div>
        </div>
        <div class="header-right">
            <a href="seller_dashboard.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Selling 
            </a>
        </div>
    </header>

    <div class="container">
        <div class="availability-card">
            <h2>Manage Your Vehicle Availability</h2>
            
            <?php if (isset($success_message)): ?>
                <div class="alert success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="POST" class="availability-form">
                <div class="form-group">
                    <label for="vehicle_id">Select Vehicle:</label>
                    <select name="vehicle_id" id="vehicle_id" required>
                        <option value="">Choose a vehicle</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['id']; ?>">
                                <?php 
                                    echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model'] . ' (' . $vehicle['year'] . ')');
                                    echo $vehicle['vehicle_type'] === 'ev' ? ' - EV' : '';
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="date-range">
                    <div class="form-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               required>
                    </div>


                    <div class="form-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Select Days:</label>
                    <div class="days-checkbox">
                        <label><input type="checkbox" name="days[]" value="1"> Monday</label>
                        <label><input type="checkbox" name="days[]" value="2"> Tuesday</label>
                        <label><input type="checkbox" name="days[]" value="3"> Wednesday</label>
                        <label><input type="checkbox" name="days[]" value="4"> Thursday</label>
                        <label><input type="checkbox" name="days[]" value="5"> Friday</label>
                        <label><input type="checkbox" name="days[]" value="6"> Saturday</label>
                        <label><input type="checkbox" name="days[]" value="7"> Sunday</label>
                    </div>
                </div>

                <div class="time-range">
                    <div class="form-group">
                        <label for="start_time">Start Time:</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time:</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Add Availability</button>
            </form>

            <div class="existing-availabilities">
                <h3>Your Scheduled Availabilities</h3>
                <?php if (empty($availabilities)): ?>
                    <p class="no-availabilities">No availabilities scheduled.</p>
                <?php else: ?>
                    <div class="availability-grid">
                        <?php 
                        $current_vehicle = null;
                        $current_date = null;
                        foreach ($availabilities as $slot): 
                            $vehicle_name = $slot['brand'] . ' ' . $slot['model'] . ' (' . $slot['year'] . ')';
                            if ($current_vehicle !== $vehicle_name):
                                $current_vehicle = $vehicle_name;
                        ?>
                            <div class="vehicle-header">
                                <i class="fas fa-car"></i>
                                <?php echo htmlspecialchars($vehicle_name); ?>
                            </div>
                        <?php 
                            endif;
                            $date = new DateTime($slot['available_date']);
                            if ($current_date !== $slot['available_date']):
                                $current_date = $slot['available_date'];
                        ?>
                            <div class="date-header">
                                <?php echo $date->format('l, F j, Y'); ?>
                            </div>
                        <?php endif; ?>
                            <div class="time-slot <?php echo $slot['status']; ?>">
                                <span class="slot-time">
                                    <?php 
                                    $start = new DateTime($slot['start_time']);
                                    $end = new DateTime($slot['end_time']);
                                    echo $start->format('g:i A') . ' - ' . $end->format('g:i A');
                                    ?>
                                </span>
                                <span class="status-badge"><?php echo ucfirst($slot['status']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #f8f9fa;
        margin: 0;
        padding: 20px;
    }

    .container {
        max-width: 800px;
        margin: 0 auto;
    }

    .availability-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        padding: 30px;
    }

    h2 {
        color: #2c3e50;
        margin-bottom: 30px;
    }

    .availability-form {
        display: grid;
        gap: 20px;
        margin-bottom: 40px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    label {
        color: #495057;
        font-weight: 500;
    }

    input[type="date"],
    input[type="time"] {
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        font-size: 16px;
    }

    .submit-btn {
        background: #ff5722;
        color: white;
        border: none;
        padding: 12px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        font-size: 16px;
        transition: background 0.3s ease;
    }

    .submit-btn:hover {
        background: #f4511e;
    }

    .alert {
        padding: 15px;
        margin: 15px 0;
        border-radius: 5px;
    }

    .success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .availability-grid {
        display: flex;
        flex-direction: column;
        gap: 15px;
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
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        border: 1px solid #dee2e6;
        border-radius: 5px;
    }

    .time-slot.booked {
        background: #f8f9fa;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 500;
    }

    .time-slot.available .status-badge {
        background: #d4edda;
        color: #155724;
    }

    .time-slot.booked .status-badge {
        background: #cce5ff;
        color: #004085;
    }

    .no-availabilities {
        text-align: center;
        color: #6c757d;
        padding: 20px;
    }

    .date-range, .time-range {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .days-checkbox {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px;
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 5px;
    }

    .days-checkbox label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: normal;
        cursor: pointer;
    }

    .days-checkbox input[type="checkbox"] {
        width: 16px;
        height: 16px;
    }

    @media (max-width: 768px) {
        .container {
            padding: 10px;
        }

        .availability-card {
            padding: 20px;
        }

        .date-range, .time-range {
            grid-template-columns: 1fr;
        }
    }

    .vehicle-header {
        background: #ff5722;
        color: white;
        padding: 15px;
        border-radius: 5px;
        margin-top: 30px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    select {
        width: 100%;
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        font-size: 16px;
        color: #495057;
    }

    select:focus {
        border-color: #ff5722;
        outline: none;
    }

    .dashboard-header {
        background: white;
        padding: 15px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }

    .header-left {
        display: flex;
        align-items: center;
    }

    .header-right {
        display: flex;
        justify-content: flex-end;
        align-items: center;
    }

    .logo-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .logo {
        height: 40px;
        width: auto;
    }

    .logo-container h1 {
        color: #ff5722;
        font-size: 24px;
        margin: 0;
    }

    .back-button {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #666;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s;
        padding: 8px 15px;
        border-radius: 5px;
    }

    .back-button:hover {
        color: #ff5722;
        background: #f8f9fa;
    }

    .back-button i {
        font-size: 18px;
    }

    @media (max-width: 768px) {
        .dashboard-header {
            padding: 15px;
        }

        .logo-container h1 {
            font-size: 20px;
        }

        .logo {
            height: 30px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');

    // Set minimum dates
    const today = new Date().toISOString().split('T')[0];
    startDateInput.min = today;
    endDateInput.min = today;

    // Update end date minimum when start date changes
    startDateInput.addEventListener('change', function() {
        endDateInput.min = this.value;
        if (endDateInput.value < this.value) {
            endDateInput.value = this.value;
        }
    });

    // Set minimum end time when start time changes
    startTimeInput.addEventListener('change', function() {
        endTimeInput.min = this.value;
        if (endTimeInput.value < this.value) {
            endTimeInput.value = this.value;
        }
    });

    // Select all days button
    const selectAllBtn = document.createElement('button');
    selectAllBtn.type = 'button';
    selectAllBtn.className = 'select-all-btn';
    selectAllBtn.textContent = 'Select All Days';
    document.querySelector('.days-checkbox').insertAdjacentElement('beforebegin', selectAllBtn);

    selectAllBtn.addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('input[name="days[]"]');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
        this.textContent = allChecked ? 'Select All Days' : 'Unselect All Days';
    });
});
</script>

</body>
</html> 