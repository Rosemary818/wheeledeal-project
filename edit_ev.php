<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$errors = [];



$vehicle_id = (int)$_GET['id'];

// Fetch all brands for dropdown
$brand_sql = "SELECT DISTINCT brand FROM tbl_vehicles WHERE brand IS NOT NULL ORDER BY brand";
$brand_result = $conn->query($brand_sql);

if ($brand_result === false) {
    $errors[] = "Error fetching brands: " . $conn->error;
} else {
    $brands = [];
    while($row = $brand_result->fetch_assoc()) {
        $brands[] = $row['brand'];
    }
}

// Fetch the vehicle data
$vehicle_query = "SELECT v.*, e.* 
                  FROM tbl_vehicles v 
                  LEFT JOIN tbl_ev e ON v.vehicle_id = e.vehicle_id 
                  WHERE v.vehicle_id = ?";
$vehicle_stmt = $conn->prepare($vehicle_query);
$vehicle_stmt->bind_param("i", $vehicle_id);
$vehicle_stmt->execute();
$vehicle_result = $vehicle_stmt->get_result();

// Check if the vehicle exists and belongs to this seller
if ($vehicle_result->num_rows === 0) {
    $_SESSION['error_message'] = "Vehicle not found or you don't have permission to edit it";
    header("Location: seller_dashboard.php");
    exit();
}

$vehicle_data = $vehicle_result->fetch_assoc();

// Separate vehicle data into base vehicle and EV-specific data
$vehicle = array(
    'vehicle_id' => $vehicle_data['vehicle_id'],
    'brand' => $vehicle_data['brand'],
    'model' => $vehicle_data['model'],
    'year' => $vehicle_data['year'],
    'price' => $vehicle_data['price'],
    'color' => $vehicle_data['color'],
    'kilometer' => $vehicle_data['kilometer'],
    'transmission' => $vehicle_data['transmission'],
    'description' => $vehicle_data['description'],
    'max_power' => $vehicle_data['max_power'],   // From tbl_vehicles
    'max_torque' => $vehicle_data['max_torque'], // From tbl_vehicles
    'number_of_owners' => $vehicle_data['number_of_owners']
    // Other base vehicle fields...
);

$ev_data = array(
    'range_km' => $vehicle_data['range_km'],
    'battery_capacity' => $vehicle_data['battery_capacity'],
    'charging_time_ac' => $vehicle_data['charging_time_ac'],
    'charging_time_dc' => $vehicle_data['charging_time_dc'],
    'electric_motor' => $vehicle_data['electric_motor'],
    'motor_type' => $vehicle_data['motor_type']
    // No max_power or max_torque here
);

// Only allow editing of EV vehicles
if (isset($vehicle['vehicle_type']) && $vehicle['vehicle_type'] != 'EV') {
    $_SESSION['error_message'] = "This is not an electric vehicle";
    header("Location: seller_dashboard.php");
    exit();
}

// Fetch EV-specific data
$ev_query = "SELECT * FROM tbl_ev WHERE vehicle_id = ?";
$ev_stmt = $conn->prepare($ev_query);
$ev_stmt->bind_param("i", $vehicle_id);
$ev_stmt->execute();
$ev_result = $ev_stmt->get_result();
$ev_data = $ev_result->fetch_assoc();

// Retrieve vehicle specifications data
$specs_query = "SELECT * FROM tbl_vehicle_specifications WHERE vehicle_id = ?";
$specs_stmt = $conn->prepare($specs_query);
$specs_stmt->bind_param("i", $vehicle_id);
$specs_stmt->execute();
$specs_result = $specs_stmt->get_result();
$vehicle_specs = $specs_result->fetch_assoc();

// Create upload directory if it doesn't exist
$upload_dir = 'uploads/vehicles/' . $vehicle_id . '/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update vehicle data one field at a time
        $fields = [
            'brand', 'model', 'year', 'price', 'transmission', 'mileage', 
            'kilometer', 'color', 'registration_type', 'number_of_owners', 
            'guarantee', 'max_power', 'max_torque', 'Address', 'description', 
            'front_suspension', 'rear_suspension', 'front_brake_type', 
            'rear_brake_type', 'minimum_turning_radius', 'wheels', 
            'spare_wheel', 'front_tyres', 'rear_tyres'
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $sql = "UPDATE tbl_vehicles SET $field = ? WHERE vehicle_id = ? AND seller_id = ?";
                $stmt = $conn->prepare($sql);
                
                // Handle numeric fields
                if (in_array($field, ['year', 'mileage', 'kilometer', 'number_of_owners'])) {
                    $value = (int)$_POST[$field];
                    $stmt->bind_param("iii", $value, $vehicle_id, $seller_id);
                } elseif ($field === 'price') {
                    $value = (float)$_POST[$field];
                    $stmt->bind_param("dii", $value, $vehicle_id, $seller_id);
                } else {
                    $value = $_POST[$field];
                    $stmt->bind_param("sii", $value, $vehicle_id, $seller_id);
                }
                
                $stmt->execute();
            }
        }
        
        // Update EV-specific fields
        $ev_fields = [
            'range_km', 'battery_capacity', 'charging_time_ac', 
            'charging_time_dc', 'electric_motor', 'motor_type'
        ];
        
        foreach ($ev_fields as $field) {
            if (isset($_POST[$field])) {
                $sql = "UPDATE tbl_ev SET $field = ? WHERE vehicle_id = ?";
                $stmt = $conn->prepare($sql);
                $value = $_POST[$field];
                $stmt->bind_param("si", $value, $vehicle_id);
                $stmt->execute();
            }
        }
        
        // Update specifications
        $spec_fields = [
            'engine', 
            'engine_type'
        ];
        
        foreach ($spec_fields as $field) {
            if (isset($_POST[$field])) {
                if ($vehicle_specs) {
                    // Update existing specs
                    $sql = "UPDATE tbl_vehicle_specifications SET $field = ? WHERE vehicle_id = ?";
                    $stmt = $conn->prepare($sql);
                    
                    // Handle numeric fields
                    if (in_array($field, ['other_numeric_field'])) {
                        $value = !empty($_POST[$field]) ? (int)$_POST[$field] : null;
                        $stmt->bind_param("ii", $value, $vehicle_id);
                    } else {
                        $value = $_POST[$field];
                        $stmt->bind_param("si", $value, $vehicle_id);
                    }
                    
                    $stmt->execute();
                } else {
                    // If no specs record exists, we'll need to create one
                    $specs_values = [];
                    $specs_types = [];
                    $specs_params = [];
                    $specs_fields = [];
                    
                    foreach ($spec_fields as $spec_field) {
                        if (isset($_POST[$spec_field])) {
                            $specs_fields[] = $spec_field;
                            $specs_params[] = '?';
                            
                            if (in_array($spec_field, ['other_numeric_field'])) {
                                $specs_values[] = !empty($_POST[$spec_field]) ? (int)$_POST[$spec_field] : null;
                                $specs_types[] = 'i';
                            } else {
                                $specs_values[] = $_POST[$spec_field];
                                $specs_types[] = 's';
                            }
                        }
                    }
                    
                    // Only create a specs record if we have data
                    if (!empty($specs_fields)) {
                        $sql = "INSERT INTO tbl_vehicle_specifications (vehicle_id, " . implode(', ', $specs_fields) . ") 
                                VALUES (?, " . implode(', ', $specs_params) . ")";
                        
                        $stmt = $conn->prepare($sql);
                        
                        // Build the bind_param types string
                        $types = 'i' . implode('', $specs_types);
                        
                        // Create the parameter array with vehicle_id as first element
                        array_unshift($specs_values, $vehicle_id);
                        
                        // Call bind_param with the types string and values array
                        $stmt->bind_param($types, ...$specs_values);
                        $stmt->execute();
                    }
                }
            }
        }
        
        // Process photo uploads
        $photo_categories = ['exterior', 'interior', 'features', 'imperfections', 'highlights', 'tyres'];
        
        foreach ($photo_categories as $category) {
            // Check if files were uploaded for this category
            if (isset($_FILES[$category . '_photos']) && $_FILES[$category . '_photos']['error'][0] != 4) { // Error 4 means no file was uploaded
                $category_dir = $upload_dir . $category . '/';
                
                // Create category directory if it doesn't exist
                if (!file_exists($category_dir)) {
                    mkdir($category_dir, 0777, true);
                }
                
                // Process each uploaded file
                $files = $_FILES[$category . '_photos'];
                $file_count = count($files['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    // Skip if there was an upload error
                    if ($files['error'][$i] != 0) {
                        continue;
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                    $new_filename = $category . '_' . time() . '_' . $i . '.' . $file_extension;
                    $target_file = $category_dir . $new_filename;
                    
                    // Move the uploaded file
                    if (move_uploaded_file($files['tmp_name'][$i], $target_file)) {
                        // Get file name and path for database
                        $file_name = $new_filename;
                        $file_path = $category_dir . $new_filename;
                        
                        // Insert into the correct table with correct column names
                        $photo_sql = "INSERT INTO tbl_photos (vehicle_id, photo_file_name, photo_file_path, category) 
                                     VALUES (?, ?, ?, ?)";
                        $photo_stmt = $conn->prepare($photo_sql);
                        
                        // Check if prepare was successful
                        if ($photo_stmt === false) {
                            // Prepare failed, display error
                            echo "<div class='alert alert-danger'>Error preparing statement: " . $conn->error . "</div>";
                        } else {
                            // Bind parameters using the correct table structure
                            $photo_stmt->bind_param("isss", $vehicle_id, $file_name, $file_path, $category);
                            $result = $photo_stmt->execute();
                            
                            // Check for execution errors
                            if (!$result) {
                                echo "<div class='alert alert-danger'>Error saving photo to database: " . $photo_stmt->error . "</div>";
                            }
                        }
                    }
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Electric vehicle updated successfully!";
        header("Location: seller_dashboard.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $errors[] = "Error updating vehicle: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Electric Vehicle - WheeleDeal</title>
    <style>
        :root {
            --primary-color: #FF6B35;
            --secondary-color: #f5f5f5;
            --accent-blue: #E9F5FF;
            --text-dark: #333;
            --text-light: #666;
            --border-color: #ddd;
            --white: #fff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background-color: #f7f4f1;
            color: var(--text-dark);
            line-height: 1.6;
        }
        
        header {
            background-color: var(--white);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 15px 30px;
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
            height: 30px;
        }
        
        .logo span {
            font-weight: 600;
            font-size: 24px;
            color:#333;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-size: 14px;
        }
        
        .back-to-selling {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .page-title {
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 30px;
            color: var(--text-dark);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 500;
            margin: 20px 0;
            color: var(--text-dark);
        }
        
        .section-subtitle {
            font-size: 16px;
            font-weight: 500;
            margin: 15px 0;
            color: var(--text-dark);
        }
        
        .form-container {
            background-color: var(--white);
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-size: 14px;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .specifications-section {
            background-color: var(--accent-blue);
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .button-container {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            align-items: center;
        }
        
        .update-vehicle-btn {
            background-color: #FF6B35;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 25px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .cancel-btn {
            background-color: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 50px;
            padding: 12px 25px;
            font-size: 16px;
            cursor: pointer;
        }
        
        .manage-test-drive-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 25px;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-grow: 1;
        }
        
        .calendar-icon:before {
            content: "";
            display: inline-block;
            width: 20px;
            height: 20px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M19,4H17V3a1,1,0,0,0-2,0V4H9V3A1,1,0,0,0,7,3V4H5A3,3,0,0,0,2,7V19a3,3,0,0,0,3,3H19a3,3,0,0,0,3-3V7A3,3,0,0,0,19,4Zm1,15a1,1,0,0,1-1,1H5a1,1,0,0,1-1-1V10H20Z"/></svg>');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
        }
        
        .photo-section {
            margin-top: 30px;
        }
        
        .photo-category {
            margin-bottom: 20px;
        }
        
        .photo-category h3 {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .file-input {
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            position: absolute;
            z-index: -1;
        }
        
        .upload-button {
            display: inline-block;
            padding: 8px 16px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .upload-button:hover {
            background-color: #f5f5f5;
            border-color: #aaa;
        }
        
        .file-input:focus + .upload-button {
            outline: 1px dotted #000;
            outline: -webkit-focus-ring-color auto 5px;
        }
        
        .file-input + .upload-button {
            cursor: pointer;
        }
        
        .file-name-display {
            display: inline-block;
            margin-left: 10px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="images/logo3.png" alt="WheeleDeal">
            <span>WheeleDeal</span>
        </div>
        <div class="nav-links">
            <a href="profile.php">My Profile</a>
            <a href="manage_test_drives.php">Manage Test Drives</a>
            <button class="back-to-selling" onclick="window.location.href='seller_dashboard.php'">Back to Selling</button>
        </div>
    </header>
    
    <div class="container">
        <h1 class="page-title">Edit Electric Vehicle</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <?php 
                    echo htmlspecialchars($_SESSION['success_message']);
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                <?php 
                    echo htmlspecialchars($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-container">
                <h2 class="section-title">Enter Electric Vehicle Details</h2>
                
                <h3 class="section-subtitle">Basic Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="brand">Brand</label>
                        <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($vehicle['brand'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="model">Model</label>
                        <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($vehicle['model'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="year">Year</label>
                        <input type="number" id="year" name="year" value="<?php echo htmlspecialchars($vehicle['year'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price</label>
                        <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($vehicle['price'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="color">Color</label>
                        <input type="text" id="color" name="color" value="<?php echo htmlspecialchars($vehicle['color'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="kilometer">Kilometer</label>
                        <input type="number" id="kilometer" name="kilometer" value="<?php echo htmlspecialchars($vehicle['kilometer'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="specifications-section">
                <h2 class="section-title">Electric Vehicle Specifications</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="range_km">Range (km)</label>
                        <input type="text" id="range_km" name="range_km" value="<?php echo htmlspecialchars($ev_data['range_km'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="battery_capacity">Battery Capacity (kWh)</label>
                        <input type="text" id="battery_capacity" name="battery_capacity" value="<?php echo htmlspecialchars($ev_data['battery_capacity'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="charging_time_ac">Charging Time (AC)</label>
                        <input type="text" id="charging_time_ac" name="charging_time_ac" value="<?php echo htmlspecialchars($ev_data['charging_time_ac'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="charging_time_dc">Charging Time (DC)</label>
                        <input type="text" id="charging_time_dc" name="charging_time_dc" value="<?php echo htmlspecialchars($ev_data['charging_time_dc'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="max_torque">Max Torque</label>
                        <input type="text" id="max_torque" name="max_torque" placeholder="e.g., 350 Nm" value="<?php echo htmlspecialchars($vehicle['max_torque'] ?? ''); ?>">
                    </div>
            
                    
                    <div class="form-group">
                        <label for="max_power">Max Power</label>
                        <input type="text" id="max_power" name="max_power" placeholder="e.g., 150 kW (201 hp)" value="<?php echo htmlspecialchars($vehicle['max_power'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-container">
                <h2 class="section-title">Additional Specifications</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="electric_motor">Electric Motor</label>
                        <input type="text" id="electric_motor" name="electric_motor" value="<?php echo htmlspecialchars($ev_data['electric_motor'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="motor_type">Motor Type</label>
                        <input type="text" id="motor_type" name="motor_type" placeholder="e.g., AC Induction" value="<?php echo htmlspecialchars($ev_data['motor_type'] ?? ''); ?>">
                    </div>
                    
                   
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="transmission">Transmission</label>
                        <select id="transmission" name="transmission">
                            <option value="Single-speed" <?php echo ($vehicle['transmission'] === 'Single-speed') ? 'selected' : ''; ?>>Single-speed</option>
                            <option value="Multi-speed" <?php echo ($vehicle['transmission'] === 'Multi-speed') ? 'selected' : ''; ?>>Multi-speed</option>
                        </select>
                    </div>
                    
                
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($vehicle['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="form-container">
                <h2 class="section-title">Vehicle Photos</h2>
                
                <div class="photo-category">
                    <h3>Exterior Photos</h3>
                    <input type="file" name="exterior_photos[]" multiple accept="image/*" class="file-input" id="exterior_photos">
                    <label for="exterior_photos" class="upload-button">Choose Files</label>
                    <span class="max-files">Max 10 images</span>
                </div>
                
                <div class="photo-category">
                    <h3>Interior Photos</h3>
                    <input type="file" name="interior_photos[]" multiple accept="image/*" class="file-input" id="interior_photos">
                    <label for="interior_photos" class="upload-button">Choose Files</label>
                    <span class="max-files">Max 10 images</span>
                </div>
                
                <div class="photo-category">
                    <h3>Features Photos</h3>
                    <input type="file" name="features_photos[]" multiple accept="image/*" class="file-input" id="features_photos">
                    <label for="features_photos" class="upload-button">Choose Files</label>
                    <span class="max-files">Max 10 images</span>
                </div>
                
                <div class="photo-category">
                    <h3>Imperfections Photos</h3>
                    <input type="file" name="imperfections_photos[]" multiple accept="image/*" class="file-input" id="imperfections_photos">
                    <label for="imperfections_photos" class="upload-button">Choose Files</label>
                    <span class="max-files">Max 10 images</span>
                </div>
                
                <div class="photo-category">
                    <h3>Highlights Photos</h3>
                    <input type="file" name="highlights_photos[]" multiple accept="image/*" class="file-input" id="highlights_photos">
                    <label for="highlights_photos" class="upload-button">Choose Files</label>
                    <span class="max-files">Max 10 images</span>
                </div>
                
                <div class="photo-category">
                    <h3>Tyres Photos</h3>
                    <input type="file" name="tyres_photos[]" multiple accept="image/*" class="file-input" id="tyres_photos">
                    <label for="tyres_photos" class="upload-button">Choose Files</label>
                    <span class="max-files">Max 6 images</span>
                </div>
            </div>
            
            <div class="button-container">
                <button type="submit" class="update-vehicle-btn">Update Vehicle</button>
                <button type="button" class="cancel-btn" onclick="window.location.href='seller_dashboard.php'">Cancel</button>
                <button type="button" class="manage-test-drive-btn" onclick="window.location.href='manage_availability.php?vehicle_id=<?php echo $vehicle_id; ?>'">
                    <i class="calendar-icon"></i> Manage Test Drive Availability
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInputs = document.querySelectorAll('.file-input');
            
            fileInputs.forEach(input => {
                input.addEventListener('change', function(e) {
                    const label = this.nextElementSibling;
                    
                    if (this.files.length > 1) {
                        // Multiple files selected
                        label.textContent = `${this.files.length} files selected`;
                    } else if (this.files.length === 1) {
                        // Single file selected
                        label.textContent = this.files[0].name;
                    } else {
                        // No files selected
                        label.textContent = 'Choose Files';
                    }
                    
                    // Add file name display after the label
                    let fileNameDisplay = label.nextElementSibling;
                    if (!fileNameDisplay || !fileNameDisplay.classList.contains('file-name-display')) {
                        fileNameDisplay = document.createElement('span');
                        fileNameDisplay.classList.add('file-name-display');
                        label.parentNode.insertBefore(fileNameDisplay, label.nextSibling);
                    }
                    
                    if (this.files.length > 0) {
                        fileNameDisplay.textContent = `Selected ${this.files.length} file(s)`;
                    } else {
                        fileNameDisplay.textContent = '';
                    }
                });
            });
        });
    </script>
</body>
</html> 