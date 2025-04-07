<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if a vehicle_id is provided
if (!isset($_GET['id'])) {
    header("Location: seller_dashboard.php");
    exit;
}

$vehicle_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Check if the specifications table exists
$specs_table_check = $conn->query("SHOW TABLES LIKE 'tbl_vehicle_specifications'");
$has_specs_table = $specs_table_check->num_rows > 0;

// Modify the query based on available tables
if ($has_specs_table) {
    // Include the specifications table
    $query = "SELECT v.*, 
              v.max_power, v.max_torque, /* Explicitly select from tbl_vehicles */
              s.length, s.width, s.height, s.wheelbase, s.ground_clearance, 
              s.kerb_weight, s.seating_capacity, s.boot_space, s.fuel_tank_capacity
              FROM tbl_vehicles v
              LEFT JOIN tbl_vehicle_specifications s ON v.vehicle_id = s.vehicle_id
              WHERE v.vehicle_id = ? AND v.seller_id = ?";
} else {
    // Only vehicles table exists
    $query = "SELECT v.* FROM tbl_vehicles v 
              WHERE v.vehicle_id = ? AND v.seller_id = ?";
}

// Prepare statement with error handling
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error in preparing statement: " . $conn->error);
}

$stmt->bind_param("ii", $vehicle_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if the vehicle exists and belongs to the user
if ($result->num_rows === 0) {
    header("Location: seller_dashboard.php");
    exit;
}

$vehicle = $result->fetch_assoc();

// Retrieve vehicle specifications data for display in the form
$specs_query = "SELECT * FROM tbl_vehicle_specifications WHERE vehicle_id = ?";
$specs_stmt = $conn->prepare($specs_query);
$specs_stmt->bind_param("i", $_GET['id']);
$specs_stmt->execute();
$specs_result = $specs_stmt->get_result();
$vehicle_specs = $specs_result->fetch_assoc();

// Initialize variables with default values
// Basic vehicle details (always present in tbl_vehicles)
$brand = $vehicle['brand'] ?? '';
$model = $vehicle['model'] ?? '';
$year = $vehicle['year'] ?? '';
$price = $vehicle['price'] ?? '';
$vehicle_type = $vehicle['vehicle_type'] ?? '';
$fuel_type = $vehicle['fuel_type'] ?? '';
$transmission = $vehicle['transmission'] ?? '';
$mileage = $vehicle['mileage'] ?? '';
$kilometer = $vehicle['kilometer'] ?? '';
$color = $vehicle['color'] ?? '';
$registration_type = $vehicle['registration_type'] ?? '';
$number_of_owners = $vehicle['number_of_owners'] ?? '';
$guarantee = $vehicle['guarantee'] ?? '';
$address = $vehicle['Address'] ?? '';
$description = $vehicle['description'] ?? '';
$front_suspension = $vehicle['front_suspension'] ?? '';
$rear_suspension = $vehicle['rear_suspension'] ?? '';
$front_brake_type = $vehicle['front_brake_type'] ?? '';
$rear_brake_type = $vehicle['rear_brake_type'] ?? '';
$minimum_turning_radius = $vehicle['minimum_turning_radius'] ?? '';
$wheels = $vehicle['wheels'] ?? '';
$spare_wheel = $vehicle['spare_wheel'] ?? '';
$front_tyres = $vehicle['front_tyres'] ?? '';
$rear_tyres = $vehicle['rear_tyres'] ?? '';

// Get max_power and max_torque directly from tbl_vehicles
$max_power = $vehicle['max_power'] ?? '';
$max_torque = $vehicle['max_torque'] ?? '';

// Specifications (may come from tbl_vehicle_specifications)
$length = $vehicle['length'] ?? '';
$width = $vehicle['width'] ?? '';
$height = $vehicle['height'] ?? '';
$wheelbase = $vehicle['wheelbase'] ?? '';
$ground_clearance = $vehicle['ground_clearance'] ?? '';
$kerb_weight = $vehicle['kerb_weight'] ?? '';
$seating_capacity = $vehicle['seating_capacity'] ?? '';
$boot_space = $vehicle['boot_space'] ?? '';
$fuel_tank_capacity = $vehicle['fuel_tank_capacity'] ?? '';

// Fields not in your database schema but possibly in your form
$engine = '';  // These are placeholders 
$engine_type = '';
$doors = '';
$no_of_rows = '';

// After fetching vehicle specifications, carefully fetch photos with error handling
try {
    // First check if the table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'vehicle_photos'");
    
    if ($check_table->num_rows === 0) {
        // Create the table if it doesn't exist
        $create_table_sql = "CREATE TABLE vehicle_photos (
            photo_id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            photo_file_name VARCHAR(255) NOT NULL,
            photo_file_path VARCHAR(255) NOT NULL,
            category VARCHAR(20) DEFAULT 'exterior',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES vehicle(vehicle_id) ON DELETE CASCADE
        )";
        
        if (!$conn->query($create_table_sql)) {
            throw new Exception("Could not create vehicle_photos table: " . $conn->error);
        }
    }
    
    // Check if category column exists in the table
    $check_column = $conn->query("SHOW COLUMNS FROM vehicle_photos LIKE 'category'");
    
    if ($check_column->num_rows === 0) {
        // Add the category column if it doesn't exist
        $add_column_sql = "ALTER TABLE vehicle_photos ADD COLUMN category VARCHAR(20) DEFAULT 'exterior'";
        
        if (!$conn->query($add_column_sql)) {
            throw new Exception("Could not add category column: " . $conn->error);
        }
    }
    
    // Now try to fetch photos
    $sql_photos = "SELECT * FROM vehicle_photos WHERE vehicle_id = ? ORDER BY category, photo_id";
    $stmt_photos = $conn->prepare($sql_photos);
    
    if ($stmt_photos === false) {
        throw new Exception("Error preparing photo statement: " . $conn->error);
    }
    
    $stmt_photos->bind_param("i", $vehicle_id);
    $stmt_photos->execute();
    $photos_result = $stmt_photos->get_result();
    
    // Organize photos by category
    $photos = [];
    $categories = ['exterior', 'interior', 'features', 'imperfections', 'highlights', 'tyres'];
    foreach ($categories as $category) {
        $photos[$category] = [];
    }
    
    while ($photo = $photos_result->fetch_assoc()) {
        $category = $photo['category'] ?: 'exterior'; // Default to exterior if no category
        $photos[$category][] = $photo;
    }
    $stmt_photos->close();
    
} catch (Exception $e) {
    // Handle the error, log it and continue without photos
    error_log("Error fetching photos: " . $e->getMessage());
    echo "<script>console.error('Photo error: " . addslashes($e->getMessage()) . "');</script>";
    
    // Initialize empty photos array to avoid errors later
    $photos = [];
    $categories = ['exterior', 'interior', 'features', 'imperfections', 'highlights', 'tyres'];
    foreach ($categories as $category) {
        $photos[$category] = [];
    }
}

// Add this at the beginning of your file
$upload_dir = 'uploads/vehicles/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate required fields according to schema
    $required_fields = ['brand', 'model', 'year', 'price', 'vehicle_type', 
                       'transmission', 'kilometer', 'registration_type', 'number_of_owners'];
    
    $errors = [];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst($field) . " is required";
        }
    }
    
    // Validate enums
    $valid_vehicle_types = ['ICE', 'EV'];
    if (!in_array($_POST['vehicle_type'], $valid_vehicle_types)) {
        $errors[] = "Invalid vehicle type";
    }
    
    $valid_fuel_types = ['Petrol', 'Diesel', 'CNG', ''];
    if (!empty($_POST['fuel_type']) && !in_array($_POST['fuel_type'], $valid_fuel_types)) {
        $errors[] = "Invalid fuel type";
    }
    
    $valid_registration_types = ['Individual', 'Commercial'];
    if (!in_array($_POST['registration_type'], $valid_registration_types)) {
        $errors[] = "Invalid registration type";
    }
    
    // Handle photo uploads
    if (!empty($_FILES['new_photos'])) {
        $upload_dir = 'uploads/vehicles/'; // Make sure this directory exists and is writable
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['new_photos']['name'] as $category => $files) {
            for ($i = 0; $i < count($files); $i++) {
                if ($_FILES['new_photos']['error'][$category][$i] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['new_photos']['tmp_name'][$category][$i];
                    $name = $_FILES['new_photos']['name'][$category][$i];
                    
                    // Generate unique filename
                    $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                    $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $unique_filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        // Insert into database
                        $sql_insert_photo = "INSERT INTO tbl_photos (vehicle_id, photo_file_name, photo_file_path, category) VALUES (?, ?, ?, ?)";
                        $stmt_photo = $conn->prepare($sql_insert_photo);
                        
                        if ($stmt_photo) {
                            $full_path = $file_path; // or use absolute URL if needed
                            $stmt_photo->bind_param("isss", 
                                $vehicle_id,
                                $name,
                                $full_path,
                                $category
                            );
                            
                            if (!$stmt_photo->execute()) {
                                $errors[] = "Error saving photo to database: " . $stmt_photo->error;
                            }
                            $stmt_photo->close();
                        } else {
                            $errors[] = "Error preparing photo statement: " . $conn->error;
                        }
                    } else {
                        $errors[] = "Error moving uploaded file";
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // COMPLETELY DIFFERENT APPROACH: Use individual UPDATE statements instead of one big query
            // Set common values
            $vehicle_id = (int)$_GET['id'];
            $seller_id = (int)$_SESSION['user_id'];
            
            // Update brand
            $sql = "UPDATE tbl_vehicles SET brand = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $_POST['brand'], $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update model
            $sql = "UPDATE tbl_vehicles SET model = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $_POST['model'], $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update year
            $sql = "UPDATE tbl_vehicles SET year = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $year = (int)$_POST['year'];
            $stmt->bind_param("iii", $year, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update price
            $sql = "UPDATE tbl_vehicles SET price = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $price = (float)$_POST['price'];
            $stmt->bind_param("dii", $price, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update vehicle_type
            $sql = "UPDATE tbl_vehicles SET vehicle_type = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $_POST['vehicle_type'], $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update fuel_type
            $sql = "UPDATE tbl_vehicles SET fuel_type = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $fuel_type = $_POST['fuel_type'] ?? null;
            $stmt->bind_param("sii", $fuel_type, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update transmission
            $sql = "UPDATE tbl_vehicles SET transmission = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $_POST['transmission'], $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update mileage
            $sql = "UPDATE tbl_vehicles SET mileage = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $mileage = !empty($_POST['mileage']) ? (int)$_POST['mileage'] : null;
            $stmt->bind_param("iii", $mileage, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update kilometer
            $sql = "UPDATE tbl_vehicles SET kilometer = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $kilometer = (int)$_POST['kilometer'];
            $stmt->bind_param("iii", $kilometer, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update color
            $sql = "UPDATE tbl_vehicles SET color = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $color = $_POST['color'] ?? null;
            $stmt->bind_param("sii", $color, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update registration_type
            $sql = "UPDATE tbl_vehicles SET registration_type = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $_POST['registration_type'], $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update number_of_owners
            $sql = "UPDATE tbl_vehicles SET number_of_owners = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $number_of_owners = (int)$_POST['number_of_owners'];
            $stmt->bind_param("iii", $number_of_owners, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update guarantee
            $sql = "UPDATE tbl_vehicles SET guarantee = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $guarantee = $_POST['guarantee'] ?? null;
            $stmt->bind_param("sii", $guarantee, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update max_power
            $sql = "UPDATE tbl_vehicles SET max_power = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $max_power = $_POST['max_power'] ?? null;
            $stmt->bind_param("sii", $max_power, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update max_torque
            $sql = "UPDATE tbl_vehicles SET max_torque = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $max_torque = $_POST['max_torque'] ?? null;
            $stmt->bind_param("sii", $max_torque, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update Address
            $sql = "UPDATE tbl_vehicles SET Address = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $address = $_POST['Address'] ?? null;
            $stmt->bind_param("sii", $address, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update description
            $sql = "UPDATE tbl_vehicles SET description = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $description = $_POST['description'] ?? null;
            $stmt->bind_param("sii", $description, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update front_suspension
            $sql = "UPDATE tbl_vehicles SET front_suspension = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $front_suspension = $_POST['front_suspension'] ?? null;
            $stmt->bind_param("sii", $front_suspension, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update rear_suspension
            $sql = "UPDATE tbl_vehicles SET rear_suspension = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $rear_suspension = $_POST['rear_suspension'] ?? null;
            $stmt->bind_param("sii", $rear_suspension, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update front_brake_type
            $sql = "UPDATE tbl_vehicles SET front_brake_type = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $front_brake_type = $_POST['front_brake_type'] ?? null;
            $stmt->bind_param("sii", $front_brake_type, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update rear_brake_type
            $sql = "UPDATE tbl_vehicles SET rear_brake_type = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $rear_brake_type = $_POST['rear_brake_type'] ?? null;
            $stmt->bind_param("sii", $rear_brake_type, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update minimum_turning_radius
            $sql = "UPDATE tbl_vehicles SET minimum_turning_radius = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $min_turning_radius = $_POST['minimum_turning_radius'] ?? null;
            $stmt->bind_param("sii", $min_turning_radius, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update wheels
            $sql = "UPDATE tbl_vehicles SET wheels = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $wheels = $_POST['wheels'] ?? null;
            $stmt->bind_param("sii", $wheels, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update spare_wheel
            $sql = "UPDATE tbl_vehicles SET spare_wheel = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $spare_wheel = $_POST['spare_wheel'] ?? null;
            $stmt->bind_param("sii", $spare_wheel, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update front_tyres
            $sql = "UPDATE tbl_vehicles SET front_tyres = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $front_tyres = $_POST['front_tyres'] ?? null;
            $stmt->bind_param("sii", $front_tyres, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Update rear_tyres
            $sql = "UPDATE tbl_vehicles SET rear_tyres = ? WHERE vehicle_id = ? AND seller_id = ?";
            $stmt = $conn->prepare($sql);
            $rear_tyres = $_POST['rear_tyres'] ?? null;
            $stmt->bind_param("sii", $rear_tyres, $vehicle_id, $seller_id);
            $stmt->execute();
            
            // Now handle specifications table
            if (isset($_POST['engine']) || isset($_POST['engine_type'])) {
                // Get engine data from the form
                $engine = $_POST['engine'] ?? null;
                $engine_type = $_POST['engine_type'] ?? null;
                $length = !empty($_POST['length']) ? (int)$_POST['length'] : null;
                $width = !empty($_POST['width']) ? (int)$_POST['width'] : null;
                $height = !empty($_POST['height']) ? (int)$_POST['height'] : null;
                $wheelbase = !empty($_POST['wheelbase']) ? (int)$_POST['wheelbase'] : null;
                $ground_clearance = !empty($_POST['ground_clearance']) ? (int)$_POST['ground_clearance'] : null;
                $kerb_weight = !empty($_POST['kerb_weight']) ? (int)$_POST['kerb_weight'] : null;
                $seating_capacity = !empty($_POST['seating_capacity']) ? (int)$_POST['seating_capacity'] : null;
                $boot_space = !empty($_POST['boot_space']) ? (int)$_POST['boot_space'] : null;
                $fuel_tank_capacity = !empty($_POST['fuel_tank_capacity']) ? (int)$_POST['fuel_tank_capacity'] : null;
                
                // Check if spec record exists
                $check_spec = $conn->prepare("SELECT spec_id FROM tbl_vehicle_specifications WHERE vehicle_id = ?");
                $check_spec->bind_param("i", $vehicle_id);
                $check_spec->execute();
                $spec_result = $check_spec->get_result();
                
                if ($spec_result->num_rows > 0) {
                    // Update engine
                    if (isset($_POST['engine'])) {
                        $sql = "UPDATE tbl_vehicle_specifications SET engine = ? WHERE vehicle_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $engine, $vehicle_id);
                        $stmt->execute();
                    }
                    
                    // Update engine_type
                    if (isset($_POST['engine_type'])) {
                        $sql = "UPDATE tbl_vehicle_specifications SET engine_type = ? WHERE vehicle_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $engine_type, $vehicle_id);
                        $stmt->execute();
                    }
                    
                    // Update other specification fields...
                    // (Similar individual updates for each field)
                } else {
                    // Insert new specs
                    $sql_specs = "INSERT INTO tbl_vehicle_specifications 
                        (vehicle_id, length, width, height, wheelbase, ground_clearance, 
                        kerb_weight, seating_capacity, boot_space, engine, engine_type, 
                        fuel_tank_capacity) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt_specs = $conn->prepare($sql_specs);
                    $stmt_specs->bind_param(
                        "iiiiiiiiissi",
                        $vehicle_id,
                        $length,
                        $width,
                        $height,
                        $wheelbase,
                        $ground_clearance,
                        $kerb_weight,
                        $seating_capacity,
                        $boot_space,
                        $engine,
                        $engine_type,
                        $fuel_tank_capacity
                    );
                    $stmt_specs->execute();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            echo "<script>
                alert('Vehicle updated successfully!');
                window.location.href = 'seller_dashboard.php';
            </script>";
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "Error updating vehicle: " . $e->getMessage();
        }
    }
    
    // Display any errors
    if (!empty($errors)) {
        echo "<script>alert('" . implode("\\n", $errors) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vehicle - WheeledDeal</title>
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
                <a href="profile.php">
                    <img src="images/login.png" alt="Profile">
                    <?php if (isset($_SESSION['name'])): ?>
                        <?php echo htmlspecialchars($_SESSION['name']); ?>
                    <?php endif; ?>
                </a>
                <a href="logout.php">Logout</a>
                <a href="#" onclick="document.getElementById('switchRoleForm').submit(); return false;" class="switch-role-btn">Back to Buying</a>
                <a href="seller_dashboard.php" class="switch-role-btn">Back to Selling</a>
            </div>
            <form id="switchRoleForm" action="switch_role.php" method="POST" style="display:none;">
                <input type="hidden" name="role" value="buyer">
            </form>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="action-section">
            <h2>Edit Vehicle</h2>
            <form class="vehicle-form" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="input-group">
                        <input type="text" name="brand" 
                               value="<?php echo htmlspecialchars($vehicle['brand'] ?? ''); ?>" 
                               placeholder="Brand" maxlength="255" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="model" 
                               value="<?php echo htmlspecialchars($vehicle['model'] ?? ''); ?>" 
                               placeholder="Model" maxlength="255" required>
                    </div>
                    <div class="input-group">
                        <input type="number" name="year" 
                               value="<?php echo htmlspecialchars($vehicle['year'] ?? ''); ?>" 
                               placeholder="Year" required>
                    </div>
                    <div class="input-group">
                        <input type="number" name="price" step="0.01" 
                               value="<?php echo htmlspecialchars($vehicle['price'] ?? ''); ?>" 
                               placeholder="Price" required>
                    </div>
                    <div class="input-group">
                        <select name="vehicle_type" required>
                            <option value="">Select Vehicle Type</option>
                            <option value="ICE" <?php echo ($vehicle['vehicle_type'] ?? '') === 'ICE' ? 'selected' : ''; ?>>ICE</option>
                            <option value="EV" <?php echo ($vehicle['vehicle_type'] ?? '') === 'EV' ? 'selected' : ''; ?>>EV</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <input type="number" 
                               name="kilometer" 
                               value="<?php echo htmlspecialchars($vehicle['kilometer'] ?? ''); ?>"
                               placeholder="Kilometers Driven" 
                               min="0" 
                               required>
                    </div>
                    <div class="input-group">
                        <input type="number" 
                               name="mileage" 
                               value="<?php echo htmlspecialchars($vehicle['mileage'] ?? ''); ?>"
                               placeholder="Mileage" 
                               min="0"
                               step="0.01">
                    </div>
                    <div class="input-group">
                        <select name="fuel_type">
                            <option value="">Select Fuel Type</option>
                            <option value="Petrol" <?php echo ($vehicle['fuel_type'] ?? '') === 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                            <option value="Diesel" <?php echo ($vehicle['fuel_type'] ?? '') === 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                            <option value="CNG" <?php echo ($vehicle['fuel_type'] ?? '') === 'CNG' ? 'selected' : ''; ?>>CNG</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <select name="registration_type" required>
                            <option value="">Select Registration Type</option>
                            <option value="Individual" <?php echo ($vehicle['registration_type'] ?? '') === 'Individual' ? 'selected' : ''; ?>>Individual</option>
                            <option value="Commercial" <?php echo ($vehicle['registration_type'] ?? '') === 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <input type="text" name="engine" 
                               value="<?php echo htmlspecialchars($vehicle_specs['engine'] ?? ''); ?>" 
                               placeholder="Engine" maxlength="255">
                    </div>
                    <div class="input-group">
                        <input type="text" name="engine_type" 
                               value="<?php echo htmlspecialchars($vehicle_specs['engine_type'] ?? ''); ?>" 
                               placeholder="Engine Type" maxlength="50">
                    </div>
                    <div class="input-group">
                        <input type="text" name="max_power" value="<?php echo htmlspecialchars($max_power); ?>" placeholder="Max Power (bhp)" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="max_torque" value="<?php echo htmlspecialchars($max_torque); ?>" placeholder="Max Torque (Nm)" required>
                    </div>
                    <div class="input-group">
                        <select name="transmission" required>
                            <option value="Automatic" <?php echo $vehicle['transmission'] == 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                            <option value="Manual" <?php echo $vehicle['transmission'] == 'Manual' ? 'selected' : ''; ?>>Manual</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <input type="number" name="length" value="<?php echo htmlspecialchars($length); ?>" placeholder="Length (mm)" required>
                    </div>
                    <div class="input-group">
                        <input type="number" name="width" value="<?php echo htmlspecialchars($width); ?>" placeholder="Width (mm)" required>
                    </div>
                    <div class="input-group">
                        <input type="number" name="height" value="<?php echo htmlspecialchars($height); ?>" placeholder="Height (mm)" required>
                    </div>
                    <div class="input-group">
                        <input type="number" name="wheelbase" value="<?php echo htmlspecialchars($wheelbase); ?>" placeholder="Wheelbase (mm)" required>
                    </div>
                    <div class="input-group">
                        <input type="number" name="ground_clearance" value="<?php echo htmlspecialchars($ground_clearance); ?>" placeholder="Ground Clearance (mm)" required>
                    </div>
                    <div class="input-group">
                        <input type="number" name="kerb_weight" value="<?php echo htmlspecialchars($kerb_weight); ?>" placeholder="Kerb Weight (kg)" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="seating_capacity" value="<?php echo htmlspecialchars($seating_capacity); ?>" placeholder="Seating Capacity" required>
                    </div>
                    <div class="input-group">
                        <input type="number" name="boot_space" value="<?php echo htmlspecialchars($boot_space); ?>" placeholder="Boot Space (litres)" required>
                    </div>
                    <div class="input-group">
                       <input type="number" name="fuel_tank_capacity" value="<?php echo htmlspecialchars($fuel_tank_capacity); ?>" placeholder="Fuel Tank Capacity (litres)" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="front_suspension" value="<?php echo htmlspecialchars($front_suspension); ?>" placeholder="Front Suspension" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="rear_suspension" value="<?php echo htmlspecialchars($rear_suspension); ?>" placeholder="Rear Suspension" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="front_brake_type" value="<?php echo htmlspecialchars($front_brake_type); ?>" placeholder="Front Brake Type" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="rear_brake_type" value="<?php echo htmlspecialchars($rear_brake_type); ?>" placeholder="Rear Brake Type" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="minimum_turning_radius" value="<?php echo htmlspecialchars($minimum_turning_radius); ?>" placeholder="Minimum Turning Radius" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="wheels" value="<?php echo htmlspecialchars($wheels); ?>" placeholder="Wheels" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="spare_wheel" value="<?php echo htmlspecialchars($spare_wheel); ?>" placeholder="Spare Wheel" required>
                    </div>
                    <div class="input-group">
                        <input type="text" 
                               name="front_tyres" 
                               value="<?php echo htmlspecialchars($front_tyres); ?>" 
                               placeholder="Front Tyres (e.g., 195/55 R16)" 
                               pattern="[0-9]{3}/[0-9]{2}\s*R[0-9]{2}"
                               title="Please enter in format: 195/55 R16"
                               required>
                    </div>
                    <div class="input-group">
                        <input type="text" 
                               name="rear_tyres" 
                               value="<?php echo htmlspecialchars($rear_tyres); ?>" 
                               placeholder="Rear Tyres (e.g., 195/55 R16)" 
                               pattern="[0-9]{3}/[0-9]{2}\s*R[0-9]{2}"
                               title="Please enter in format: 195/55 R16"
                               required>
                    </div>
                    <div class="input-group">
                        <input type="text" 
                               name="color" 
                               value="<?php echo htmlspecialchars($vehicle['color'] ?? ''); ?>" 
                               placeholder="Enter Vehicle Color" 
                               required>
                    </div>
                    <div class="input-group">
                        <select name="number_of_owners" required>
                            <option value="">Select Number of Owners</option>
                            <option value="1" <?php echo $vehicle['number_of_owners'] == '1' ? 'selected' : ''; ?>>1st Owner</option>
                            <option value="2" <?php echo $vehicle['number_of_owners'] == '2' ? 'selected' : ''; ?>>2nd Owner</option>
                            <option value="3" <?php echo $vehicle['number_of_owners'] == '3' ? 'selected' : ''; ?>>3rd Owner</option>
                            <option value="4" <?php echo $vehicle['number_of_owners'] == '4' ? 'selected' : ''; ?>>4th Owner</option>
                            <option value="5+" <?php echo $vehicle['number_of_owners'] == '5+' ? 'selected' : ''; ?>>5+ Owners</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <select name="guarantee" required>
                            <option value="">Select Guarantee</option>
                            <option value="6 months" <?php echo $vehicle['guarantee'] == '6 months' ? 'selected' : ''; ?>>6 months guarantee</option>
                            <option value="1 year" <?php echo $vehicle['guarantee'] == '1 year' ? 'selected' : ''; ?>>1 year</option>
                            <option value="None" <?php echo $vehicle['guarantee'] == 'None' ? 'selected' : ''; ?>>No guarantee</option>
                        </select>
                    </div>
                    <div class="input-group textarea-group">
                        <textarea name="description" placeholder="Vehicle Description" required><?php echo htmlspecialchars($vehicle['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="input-group">
                        <input type="text" 
                               name="Address" 
                               value="<?php echo htmlspecialchars($vehicle['Address'] ?? ''); ?>"
                               placeholder="Enter Address">
                    </div>
                </div>
                <div class="form-section photo-section">
                    <h3>Vehicle Photos</h3>
                    
                    <?php
                    // Debug: Print any upload errors
                    if (!empty($_FILES)) {
                        echo '<pre>';
                        print_r($_FILES);
                        echo '</pre>';
                    }

                    $categories = ['exterior', 'interior', 'features', 'imperfections', 'highlights', 'tyres'];
                    
                    // Process file uploads
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['new_photos'])) {
                        $upload_dir = 'uploads/vehicles/';
                        
                        // Create directory if it doesn't exist
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        // Debug: Check directory permissions
                        echo "Upload directory exists: " . (file_exists($upload_dir) ? 'Yes' : 'No') . "<br>";
                        echo "Upload directory writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "<br>";

                        foreach ($_FILES['new_photos']['name'] as $category => $files) {
                            if (!is_array($files)) continue;
                            
                            foreach ($files as $key => $filename) {
                                if ($_FILES['new_photos']['error'][$category][$key] === UPLOAD_ERR_OK) {
                                    $tmp_name = $_FILES['new_photos']['tmp_name'][$category][$key];
                                    $name = basename($filename);
                                    
                                    // Generate unique filename
                                    $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                                    $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
                                    $file_path = $upload_dir . $unique_filename;
                                    
                                    // Debug: Print file information
                                    echo "Processing file: $name<br>";
                                    echo "Temp location: $tmp_name<br>";
                                    echo "Destination: $file_path<br>";
                                    
                                    if (move_uploaded_file($tmp_name, $file_path)) {
                                        // Insert into database
                                        $sql_insert_photo = "INSERT INTO tbl_photos (vehicle_id, photo_file_name, photo_file_path, category) VALUES (?, ?, ?, ?)";
                                        $stmt_photo = $conn->prepare($sql_insert_photo);
                                        
                                        if ($stmt_photo) {
                                            $stmt_photo->bind_param("isss", 
                                                $vehicle_id,
                                                $name,
                                                $file_path,
                                                $category
                                            );
                                            
                                            if ($stmt_photo->execute()) {
                                                echo "Successfully uploaded and saved to database: $name<br>";
                                            } else {
                                                echo "Database error: " . $stmt_photo->error . "<br>";
                                            }
                                            $stmt_photo->close();
                                        } else {
                                            echo "Prepare statement error: " . $conn->error . "<br>";
                                        }
                                    } else {
                                        echo "Failed to move uploaded file: $name<br>";
                                    }
                                } else {
                                    echo "Upload error for file in category $category: " . 
                                         $_FILES['new_photos']['error'][$category][$key] . "<br>";
                                }
                            }
                        }
                    }
                    ?>

                    <!-- Photo upload interface -->
                    <?php foreach ($categories as $category): ?>
                        <div class="photo-category">
                            <h4><?php echo ucfirst($category); ?> Photos</h4>
                            
                            <!-- Display existing photos -->
                            <div class="photo-grid">
                                <?php
                                $sql_photos = "SELECT * FROM tbl_photos WHERE vehicle_id = ? AND category = ?";
                                $stmt_photos = $conn->prepare($sql_photos);
                                $stmt_photos->bind_param("is", $vehicle_id, $category);
                                $stmt_photos->execute();
                                $result_photos = $stmt_photos->get_result();
                                
                                while ($photo = $result_photos->fetch_assoc()):
                                ?>
                                    <div class="photo-item">
                                        <img src="<?php echo htmlspecialchars($photo['photo_file_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($photo['photo_file_name']); ?>">
                                        <button type="button" class="delete-photo" 
                                                data-photo-id="<?php echo $photo['photo_id']; ?>">
                                            Delete
                                        </button>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <!-- Upload new photos -->
                            <div class="upload-section">
                                <label for="<?php echo $category; ?>_photos">Upload <?php echo ucfirst($category); ?> Photos:</label>
                                <input type="file" 
                                       id="<?php echo $category; ?>_photos"
                                       name="new_photos[<?php echo $category; ?>][]" 
                                       accept="image/*" 
                                       multiple 
                                       class="photo-upload">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="button-group">
                    <button type="submit" class="update-btn">Update Vehicle</button>
                    <a href="seller_dashboard.php" class="cancel-btn">Cancel</a>
                    <a href="manage_availability.php" class="manage-availability-btn">
                        <i class="fas fa-calendar-alt"></i>
                        Manage Test Drive Availability
                    </a>
                </div>
            </form>
        </div>
    </div>

<script>
    // Existing code...
    
    // Preview multiple selected images
    document.getElementById('vehicle_photo').addEventListener('change', function(e) {
        const previewContainer = document.getElementById('preview-container');
        previewContainer.innerHTML = ''; // Clear previous previews
        
        if (this.files.length > 0) {
            const fileCount = this.files.length;
            const maxPreviewCount = Math.min(fileCount, 10); // Limit previews to 10 max
            
            for (let i = 0; i < maxPreviewCount; i++) {
                const file = this.files[i];
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-item';
                    
                    reader.onload = function(e) {
                        previewItem.innerHTML = `
                            <img src="${e.target.result}" class="preview-image" alt="Preview">
                            <span class="preview-filename">${file.name}</span>
                        `;
                        previewContainer.appendChild(previewItem);
                    };
                    
                    reader.readAsDataURL(file);
                }
            }
            
            if (fileCount > maxPreviewCount) {
                const moreItem = document.createElement('div');
                moreItem.className = 'preview-item more-photos';
                moreItem.innerHTML = `<span>+${fileCount - maxPreviewCount} more</span>`;
                previewContainer.appendChild(moreItem);
            }
        }
    });
    
    // Existing code for photo gallery...
</script>

<style>
    body {
        margin: 0;
        font-family: 'Poppins', Arial, sans-serif;
        font-size: 18px;
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
    }

    .logo img {
        height: 24px;
        margin-right: 10px;
    }

    .logo h1 {
        font-size: 24px;
        margin: 0;
        color: #333;
    }

    .dashboard-container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 0 20px;
    }

    .action-section {
        background: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 35px;
        margin-bottom: 35px;
    }

    .input-group {
        margin-bottom: 25px;
    }

    .input-group input,
    .input-group select,
    .input-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 20px;
        outline: none;
        font-size: 14px;
        box-sizing: border-box;
    }

    .textarea-group {
        grid-column: 1 / -1;
    }

    .textarea-group textarea {
        height: 120px;
        resize: vertical;
    }

    .photo-upload {
        margin: 20px 0;
    }

    .photo-upload label {
        display: block;
        margin-bottom: 10px;
        color: #333;
    }

    .button-group {
        display: flex;
        gap: 15px;
        justify-content: flex-start;
        margin-top: 20px;
    }

    .update-btn, .cancel-btn {
        padding: 12px 25px;
        border-radius: 20px;
        font-size: 16px;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
    }

    .update-btn {
        background-color: #ff5722;
        color: white;
        border: none;
    }

    .cancel-btn {
        background-color: #f5f5f5;
        color: #333;
        border: 1px solid #ddd;
    }

    .update-btn:hover {
        background-color: #e64a19;
    }

    .cancel-btn:hover {
        background-color: #ebebeb;
    }

    .photo-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }

    .preview-item {
        position: relative;
        width: 100px;
        height: 100px;
        border-radius: 5px;
        overflow: hidden;
    }

    .preview-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .remove-preview {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: rgba(0, 0, 0, 0.5);
        color: white;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
        line-height: 1;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .button-group {
            flex-direction: column;
        }

        .update-btn, .cancel-btn {
            width: 100%;
        }
    }

    /* Update these specific styles */
    .icons {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .icons a {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: #333;
        font-size: 14px;
    }

    .icons img {
        width: 12px;
        height: 12px;
        margin-right: 5px;
    }

    nav {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    /* Photo gallery styles */
    .photo-gallery {
        position: relative;
        height: 200px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .photo-item {
        width: 100%;
        height: 200px;
        display: none;
        position: relative;
    }
    
    .photo-item.active {
        display: block;
    }
    
    .preview-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .remove-photo {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: rgba(0, 0, 0, 0.5);
        color: white;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
        line-height: 1;
    }
    
    .photo-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.5);
        color: white;
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
    }
    
    .photo-nav.prev {
        left: 10px;
    }
    
    .photo-nav.next {
        right: 10px;
    }
    
    .photo-dots {
        position: absolute;
        bottom: 10px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 5px;
    }
    
    .dot {
        width: 10px;
        height: 10px;
        background-color: #bbb;
        border-radius: 50%;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    
    .dot.active {
        background-color: #ff5722;
    }

    /* Multiple photo upload styles */
    .help-text {
        font-size: 0.8em;
        color: #666;
        margin-top: 5px;
    }
    
    .photo-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }
    
    .preview-item {
        position: relative;
        width: 100px;
        height: 100px;
        border-radius: 5px;
        overflow: hidden;
        border: 1px solid #ddd;
    }
    
    .preview-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .preview-filename {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0,0,0,0.6);
        color: white;
        font-size: 10px;
        padding: 2px 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .more-photos {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f5f5f5;
        color: #333;
        font-size: 0.9em;
    }

    /* Photo upload styles */
    .photo-section {
        margin-top: 30px;
        border: 1px solid #ddd;
        padding: 20px;
        border-radius: 10px;
    }
    
    .photo-category-tabs {
        display: flex;
        gap: 5px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .category-tab {
        padding: 8px 15px;
        background-color: #f1f1f1;
        border: 1px solid #ddd;
        border-radius: 20px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .category-tab.active {
        background-color: #ff5722;
        color: white;
        border-color: #ff5722;
    }
    
    .category-panel {
        display: none;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
    .category-panel.active {
        display: block;
    }
    
    .category-panel h4 {
        margin-top: 0;
        color: #333;
    }
    
    .category-panel h5 {
        margin: 15px 0 10px;
        color: #555;
    }
    
    .existing-photos {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 10px;
        margin: 15px 0;
    }
    
    .existing-photo-item {
        position: relative;
        height: 120px;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #ddd;
    }
    
    .existing-photo-item.marked-delete {
        opacity: 0.5;
        border: 2px solid red;
    }
    
    .preview-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 10px;
        margin-top: 15px;
    }
    
    .preview-item {
        position: relative;
        height: 120px;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #ddd;
    }
    
    .preview-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .remove-existing,
    .remove-preview {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(255, 0, 0, 0.7);
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
    }

    /* Regular navigation links */
    nav .icons a {
        display: inline-block;
        margin-left: 20px;
        text-decoration: none;
        color: #333;
        font-weight: 500;
        transition: color 0.3s ease;
    }

    nav .icons a:hover {
        color: #ff5722;
    }

    /* Special styling for the Switch Role buttons with oval shape */
    .switch-role-btn {
        background-color: #ff5722 !important;
        color: white !important;
        padding: 8px 20px !important;
        border-radius: 50px !important; /* Large value for oval/pill shape */
        font-weight: 500 !important;
        transition: background-color 0.3s ease !important;
    }

    .switch-role-btn:hover {
        background-color: #e64a19 !important;
        color: white !important;
    }

    nav .icons a img {
        vertical-align: middle;
        margin-right: 8px;
    }

    .manage-availability-btn {
        flex: 1;
        padding: 12px 25px;
        border-radius: 20px;
        cursor: pointer;
        font-size: 16px;
        text-align: center;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: background-color 0.3s;
        background-color: #4CAF50;
        color: white;
    }

    .manage-availability-btn:hover {
        background-color: #45a049;
    }

    .manage-availability-btn i {
        font-size: 18px;
    }

    .input-group select,
    .input-group input[type="number"] {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        color: #333;
        background-color: #fff;
    }

    .input-group select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 36px;
    }

    /* Remove spinner buttons from number input */
    .input-group input[type="number"]::-webkit-inner-spin-button,
    .input-group input[type="number"]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .input-group input[type="number"] {
        -moz-appearance: textfield;
    }

    .input-group select:focus,
    .input-group input:focus {
        outline: none;
        border-color: #ddd;
        box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.05);
    }
</style>

<script>
    // Add at the end of your file
    document.addEventListener('DOMContentLoaded', function() {
        // Category tab switching
        const categoryTabs = document.querySelectorAll('.category-tab');
        categoryTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Deactivate all tabs and panels
                categoryTabs.forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.category-panel').forEach(p => p.classList.remove('active'));
                
                // Activate the clicked tab and its panel
                const category = this.getAttribute('data-category');
                this.classList.add('active');
                document.getElementById(`${category}-panel`).classList.add('active');
            });
        });
        
        // Handle file preview for each category
        const categories = ['exterior', 'interior', 'features', 'imperfections', 'highlights', 'tyres'];
        
        categories.forEach(category => {
            const fileInput = document.getElementById(`${category}_photos`);
            if (!fileInput) return;
            
            fileInput.addEventListener('change', function() {
                const previewContainer = document.getElementById(`${category}-preview`);
                previewContainer.innerHTML = '';
                
                // Generate previews
                Array.from(this.files).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        const previewItem = document.createElement('div');
                        previewItem.className = 'preview-item';
                        
                        reader.onload = function(e) {
                            previewItem.innerHTML = `
                                <img src="${e.target.result}" class="preview-image" alt="Preview">
                                <button type="button" class="remove-preview" data-index="${index}" data-category="${category}">×</button>
                            `;
                            previewContainer.appendChild(previewItem);
                            
                            // Add event listener to the remove button
                            const removeBtn = previewItem.querySelector('.remove-preview');
                            removeBtn.addEventListener('click', function() {
                                previewItem.remove();
                            });
                        };
                        
                        reader.readAsDataURL(file);
                    }
                });
            });
        });
    });
    
    // Track photos to be deleted
    const photosToDelete = [];
    
    function markPhotoForDeletion(photoId, buttonElement) {
        const photoItem = buttonElement.parentElement;
        
        if (photoItem.classList.contains('marked-delete')) {
            // Unmark for deletion
            photoItem.classList.remove('marked-delete');
            const index = photosToDelete.indexOf(photoId);
            if (index > -1) {
                photosToDelete.splice(index, 1);
            }
        } else {
            // Mark for deletion
            photoItem.classList.add('marked-delete');
            photosToDelete.push(photoId);
        }
        
        // Update hidden input with comma-separated IDs
        document.getElementById('delete_photo_ids').value = photosToDelete.join(',');
    }
</script>
</body>
</html>