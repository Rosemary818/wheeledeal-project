<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db_connect.php'; // Database connection file

// Check if seller is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

// Count active listings (not sold)
$active_query = "SELECT COUNT(*) as active_count FROM tbl_vehicles WHERE seller_id = ? AND status = 'active'";
$active_stmt = $conn->prepare($active_query);
$active_stmt->bind_param("i", $seller_id);
$active_stmt->execute();
$active_result = $active_stmt->get_result();
$active_count = $active_result->fetch_assoc()['active_count'];

// Count total listings (all vehicles ever listed)
$total_query = "SELECT COUNT(*) as total_count FROM tbl_vehicles WHERE seller_id = ?";
$total_stmt = $conn->prepare($total_query);
$total_stmt->bind_param("i", $seller_id);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_count = $total_result->fetch_assoc()['total_count'];

// Count completed sales from transactions table
$sales_query = "SELECT COUNT(DISTINCT t.vehicle_id) as sales_count 
                FROM tbl_transactions t 
                JOIN tbl_vehicles v ON t.vehicle_id = v.vehicle_id 
                WHERE v.seller_id = ? AND t.status = 'completed'";
$sales_stmt = $conn->prepare($sales_query);
$sales_stmt->bind_param("i", $seller_id);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();
$sales_count = $sales_result->fetch_assoc()['sales_count'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $year = intval($_POST['year']);
    $price = floatval(str_replace(',', '', $_POST['price']));
    $vehicle_type = trim($_POST['vehicle_type']);
    $fuel_type = trim($_POST['fuel_type']);
    $transmission = trim($_POST['transmission']);
    $mileage = intval($_POST['mileage']);
    $kilometer = intval($_POST['kilometer']);
    $color = trim($_POST['color']);
    $registration_type = trim($_POST['registration_type']);
    $number_of_owners = trim($_POST['number_of_owners']);
    $guarantee = trim($_POST['guarantee']);
    $address = trim($_POST['address']);
    $description = trim($_POST['description']);
    $status = 'Active';
    
    // Add these fields before guarantee
    $front_suspension = trim($_POST['front_suspension']);
    $rear_suspension = trim($_POST['rear_suspension']);
    $front_brake_type = trim($_POST['front_brake_type']);
    $rear_brake_type = trim($_POST['rear_brake_type']);
    $minimum_turning_radius = trim($_POST['minimum_turning_radius']);
    $wheels = trim($_POST['wheels']);
    $spare_wheel = trim($_POST['spare_wheel']);
    $front_tyres = trim($_POST['front_tyres']);
    $rear_tyres = trim($_POST['rear_tyres']);
    
    // Get form data for max_power and max_torque
    $max_power = $_POST['max_power'] ?? '';
    $max_torque = $_POST['max_torque'] ?? '';

    // Get form data for vehicle specifications
    $length = $_POST['length'] ?? 0;
    $width = $_POST['width'] ?? 0;
    $height = $_POST['height'] ?? 0;
    $wheelbase = $_POST['wheelbase'] ?? 0;
    $ground_clearance = $_POST['ground_clearance'] ?? 0;
    $kerb_weight = $_POST['kerb_weight'] ?? 0;
    $seating_capacity = $_POST['seating_capacity'] ?? 0;
    $boot_space = $_POST['boot_space'] ?? 0;
    $fuel_tank_capacity = $_POST['fuel_tank_capacity'] ?? 0;
    $engine = $_POST['engine'] ?? '';
    $engine_type = $_POST['engine_type'] ?? '';
    
    // Prepare SQL to insert into tbl_vehicles
    $sql = "INSERT INTO tbl_vehicles (
        seller_id, brand, model, year, price, vehicle_type, fuel_type, 
        transmission, mileage, kilometer, color, registration_type, 
        number_of_owners, guarantee, max_power, max_torque, Address, description,
        front_suspension, rear_suspension, front_brake_type, rear_brake_type,
        minimum_turning_radius, wheels, spare_wheel, front_tyres, rear_tyres, status
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?, 'Active'
    )";

    // Prepare and bind parameters
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(
            "issiisssiisssssssssssssssss", 
            $seller_id, $brand, $model, $year, $price, $vehicle_type, $fuel_type,
            $transmission, $mileage, $kilometer, $color, $registration_type,
            $number_of_owners, $guarantee, $max_power, $max_torque, $address, $description,
            $front_suspension, $rear_suspension, $front_brake_type, $rear_brake_type,
            $minimum_turning_radius, $wheels, $spare_wheel, $front_tyres, $rear_tyres
        );
        
        
        // Execute the statement
        if ($stmt->execute()) {
            $vehicle_id = $conn->insert_id;
            $stmt->close();
            
            // Now insert data into tbl_vehicle_specifications
            $sql_specs = "INSERT INTO tbl_vehicle_specifications (
                vehicle_id, length, width, height, wheelbase, 
                ground_clearance, kerb_weight, seating_capacity, 
                boot_space, fuel_tank_capacity, engine, engine_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_specs = $conn->prepare($sql_specs);
            if ($stmt_specs) {
                $stmt_specs->bind_param(
                    "iiiiiiiiiiss",
                    $vehicle_id, $length, $width, $height, $wheelbase,
                    $ground_clearance, $kerb_weight, $seating_capacity,
                    $boot_space, $fuel_tank_capacity, $engine, $engine_type
                );
                
                if ($stmt_specs->execute()) {
                    $stmt_specs->close();
                    // Success - continue with photo uploads
                    
                    // Handle multiple photo uploads for each category
                    $categories = ['exterior', 'interior', 'features', 'imperfections', 'highlights', 'tyres'];
                    $uploadedPhotos = 0;
                    $maxPhotos = 10; // Maximum number of photos per category

                    // Create uploads directory if it doesn't exist
                    if (!file_exists('uploads')) {
                        mkdir('uploads', 0777, true);
                    }

                    // Debug information
                    error_log("Starting photo upload process for vehicle ID: " . $vehicle_id);

                    foreach ($categories as $category) {
                        $field_name = $category . '_photos';
                        
                        // Debug information for each category
                        error_log("Processing category: " . $category . ", field name: " . $field_name);
                        error_log("FILES data: " . json_encode($_FILES[$field_name]));
                        
                        if (isset($_FILES[$field_name]) && !empty($_FILES[$field_name]['name'][0])) {
                            $photos = $_FILES[$field_name];
                            
                            foreach ($photos['tmp_name'] as $key => $tmp_name) {
                                if ($uploadedPhotos >= $maxPhotos) {
                                    break; // Stop if max photos limit reached
                                }
                                
                                $photo_name = $photos['name'][$key];
                                $photo_tmp = $photos['tmp_name'][$key];
                                
                                // Debug information for each file
                                error_log("Processing file: " . $photo_name);
                                
                                // Check for upload errors
                                if ($photos['error'][$key] !== UPLOAD_ERR_OK) {
                                    error_log("Upload error for file: " . $photo_name . " Error code: " . $photos['error'][$key]);
                                    continue; // Skip this file
                                }
                                
                                // Generate unique filename
                                $extension = pathinfo($photo_name, PATHINFO_EXTENSION);
                                $unique_filename = uniqid('vehicle_') . '.' . $extension;
                                $photo_path = "uploads/" . $unique_filename;
                                
                                // Verify it's an image
                                $check = getimagesize($photo_tmp);
                                if ($check !== false) {
                                    // Check file size (limit to 5MB)
                                    if ($photos['size'][$key] <= 5000000) {
                                        // Allow all image types
                                        $allowed_types = [
                                            'image/jpeg',
                                            'image/png',
                                            'image/jpg',
                                            'image/gif',
                                            'image/webp'
                                        ];

                                        if (in_array($photos['type'][$key], $allowed_types)) {
                                            if (move_uploaded_file($photo_tmp, $photo_path)) {
                                                // Insert photo path into database - FIXED TABLE NAME HERE
                                                $sql_photo = "INSERT INTO tbl_photos (vehicle_id, photo_file_name, photo_file_path, category) VALUES (?, ?, ?, ?)";
                                                $stmt_photo = $conn->prepare($sql_photo);
                                                if ($stmt_photo) {
                                                    $stmt_photo->bind_param("isss", $vehicle_id, $unique_filename, $photo_path, $category);
                                                    if ($stmt_photo->execute()) {
                                                        $stmt_photo->close();
                                                        $uploadedPhotos++;
                                                        error_log("Photo uploaded successfully: " . $photo_path);
                                                    } else {
                                                        error_log("Failed to execute photo insert: " . $stmt_photo->error);
                                                    }
                                                } else {
                                                    error_log("Failed to prepare statement for photo upload: " . $conn->error);
                                                }
                                            } else {
                                                error_log("Failed to move uploaded file: " . $photo_name . " from " . $photo_tmp . " to " . $photo_path);
                                                error_log("PHP error: " . error_get_last()['message']);
                                            }
                                        } else {
                                            error_log("Invalid file type for file: " . $photo_name . " (Type: " . $photos['type'][$key] . ")");
                                        }
                                    } else {
                                        error_log("File size exceeds limit for file: " . $photo_name);
                                    }
                                } else {
                                    error_log("File is not a valid image: " . $photo_name);
                                }
                            }
                        } else {
                            error_log("No files uploaded for category: " . $category);
                        }
                    }

                    if ($uploadedPhotos > 0) {
                        echo "<script>
                            alert('Vehicle listed successfully with " . $uploadedPhotos . " photos!');
                            window.location.href = 'seller_dashboard.php';
                        </script>";
                    } else {
                        echo "<script>
                            alert('Vehicle listed successfully but no photos were uploaded.');
                            window.location.href = 'seller_dashboard.php';
                        </script>";
                    }
                } else {
                    $error_message = "Error inserting specifications: " . $stmt_specs->error;
                    $stmt_specs->close();
                }
            } else {
                $error_message = "Error preparing specifications statement: " . $conn->error;
            }
        } else {
            $error_message = "Error: " . $conn->error;
        }
    } else {
        $error_message = "Error preparing statement: " . $conn->error;
    }
}


// Modified query to include photo information based on your exact table structure
$sql = "SELECT v.*, p.photo_file_path as main_photo 
        FROM tbl_vehicles v 
        LEFT JOIN tbl_transactions t ON v.vehicle_id = t.vehicle_id 
        LEFT JOIN (
            SELECT vehicle_id, photo_file_path
            FROM tbl_photos 
            WHERE category = 'exterior'
            GROUP BY vehicle_id
        ) p ON v.vehicle_id = p.vehicle_id
        WHERE v.seller_id = ? 
        AND v.vehicle_type = 'ICE'
        AND (t.status IS NULL OR t.status != 'Completed')";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WheeledDeal - Seller Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f7f4f1;
        }

        /* Header styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-img {
            height: 40px;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }

        .nav-section {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-link {
            color: #333;
            text-decoration: none;
            font-size: 14px;
        }

        /* Main content styles */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }

        .list-ev-button {
            background-color: #FF6B35;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: #666;
        }

        .stat-card p {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-top: 0.5rem;
        }

        /* Form styles */
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            padding: 2rem;
        }

        .input-group {
            margin-bottom: 1rem;
        }

        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 12px 20px;  /* Increased horizontal padding */
            border: 1px solid #ddd;
            border-radius: 25px;  /* Increased border-radius for oval shape */
            font-size: 14px;
            color: #333;
            background: white;
            transition: all 0.3s ease;
        }

        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            border-color: #ddd;
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.05);
            outline: none;
        }

        /* Update validation styles */
        .input-group input:invalid {
            border-color: #ddd;  /* Changed from #ff4444 to #ddd */
        }

        .input-group input:invalid:focus {
            border-color: #ddd;  /* Changed from #ff4444 to #ddd */
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.05);
        }

        .input-group input:valid {
            border-color: #ddd;
        }

        /* Photo upload section */
        .photo-section {
            grid-column: 1 / -1;
            margin-top: 2rem;
        }

        .photo-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: #f5f5f5;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            background: #ff5722;
            color: white;
        }

        .photo-upload-area {
            border: 2px dashed #ddd;
            padding: 2rem;
            text-align: center;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .upload-section {
            display: none;
        }

        .upload-section.active {
            display: block;
        }

        input[type="file"] {
            max-width: 100%;
        }

        /* Action buttons */
        .action-buttons {
            grid-column: 1 / -1;
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .primary-btn {
            background: #ff5722;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
        }

        .secondary-btn {
            background: #4CAF50;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
        }

        .current-listings {
            background: white;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-top: 24px;
        }

        .current-listings h2 {
            font-size: 20px;
            margin-bottom: 24px;
            color: #333;
        }

        .listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        .vehicle-card {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s;
        }

        .vehicle-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .vehicle-image {
            height: 200px;
            overflow: hidden;
            background: #f5f5f5;
        }

        .vehicle-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .no-image {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }

        .vehicle-details {
            padding: 16px;
        }

        .vehicle-details h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #333;
        }

        .price {
            font-size: 20px;
            font-weight: 600;
            color: #ff5722;
            margin-bottom: 8px;
        }

        .year, .mileage {
            color: #666;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .card-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .edit-btn, .delete-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-size: 14px;
        }

        .edit-btn {
            background: #4CAF50;
            color: white;
        }

        .delete-btn {
            background: #ff5722;
            color: white;
        }

        .no-listings {
            text-align: center;
            padding: 48px;
            color: #666;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .full-width {
            grid-column: 1 / -1; /* Makes the element span all columns */
            margin-bottom: 16px;
        }

        textarea {
            width: 100%;
            padding: 12px 20px !important;
            border: 1px solid #ddd;
            border-radius: 25px !important;
            font-size: 14px;
            color: #333;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        textarea:focus {
            border-color: #ff5722;
            outline: none;
        }

        /* Add tooltip for tyre format */
        .input-group input[name="front_tyres"]::placeholder,
        .input-group input[name="rear_tyres"]::placeholder {
            font-size: 13px;
        }

        .back-to-buying {
            background: #FF5722;
            color: #FFFFFF;  /* Pure white text */
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 14px;
        }

        /* Update select elements to match */
        .input-group select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 1em;
            padding-right: 40px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-section">
            <img src="images/logo3.png" alt="WD" class="logo-img">
            <h1 class="logo-text">WheeledDeal</h1>
        </div>
        <div class="nav-section">
            <a href="index.php" class="nav-link">Home</a>
            <a href="profile.php" class="nav-link">My Profile</a>
            <a href="manage_test_drives.php" class="nav-link">Manage Test Drives</a>
            <a href="sales_report.php" class="nav-link">Sales Report</a>
            <a href="buyer_dashboard.php" class="back-to-buying">Back to Buying</a>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-header">
            <h1 class="page-title">Seller Dashboard</h1>
            <a href="list_ev.php" class="list-ev-button">List an Electric Vehicle</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Active Listings</h3>
                <p><?php echo $active_count; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Listings</h3>
                <p><?php echo $total_count; ?></p>
            </div>
            <div class="stat-card">
                <h3>Completed Sales</h3>
                <p><?php echo $sales_count; ?></p>
            </div>
        </div>

        <div class="form-container">
            <h2>List Your Vehicle</h2>
            <form class="form-grid" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" enctype="multipart/form-data">
                <!-- Basic Vehicle Information -->
                <div class="input-group">
                    <input type="text" name="brand" placeholder="Brand" required>
                </div>
                <div class="input-group">
                    <input type="text" name="model" placeholder="Model" required>
                </div>
                <div class="input-group">
                    <input type="number" name="year" placeholder="Year (YYYY)" min="1900" max="2024" required>
                </div>
                <div class="input-group">
                    <input type="number" name="mileage" placeholder="Mileage" step="1">
                </div>
                <div class="input-group">
                    <input type="number" name="kilometer" placeholder="Kilometers Driven" required>
                </div>
                <div class="input-group">
                    <input type="text" name="color" placeholder="Color">
                </div>
                <div class="input-group">
                    <input type="number" name="price" placeholder="Price" step="0.01" required>
                </div>
                
                <!-- Add max_power and max_torque fields (from tbl_vehicles) -->
                <div class="input-group">
                    <input type="text" name="max_power" placeholder="Max Power (e.g., 120 bhp @ 6000 rpm)">
                </div>
                <div class="input-group">
                    <input type="text" name="max_torque" placeholder="Max Torque (e.g., 170 Nm @ 1,750-2,500 rpm)">
                </div>
                
                <!-- Add engine and engine_type fields (from tbl_vehicle_specifications) -->
                <div class="input-group">
                    <input type="text" name="engine" placeholder="Engine (e.g., 1.5L i-VTEC)">
                </div>
                <div class="input-group">
                    <input type="text" name="engine_type" placeholder="Engine Type (e.g., Inline-4)">
                </div>
                
                <div class="input-group">
                    <select name="vehicle_type" required>
                        <option value="">Select Vehicle Type</option>
                        <option value="ICE">ICE</option>
                        <option value="EV">EV</option>
                    </select>
                </div>
                <div class="input-group">
                    <select name="fuel_type">
                        <option value="">Select Fuel Type</option>
                        <option value="Petrol">Petrol</option>
                        <option value="Diesel">Diesel</option>
                        <option value="CNG">CNG</option>
                    </select>
                </div>
                <div class="input-group">
                    <input type="text" name="transmission" placeholder="Transmission" required>
                </div>
                <div class="input-group">
                    <select name="registration_type" required>
                        <option value="">Select Registration Type</option>
                        <option value="Individual">Individual</option>
                        <option value="Commercial">Commercial</option>
                    </select>
                </div>
                <div class="input-group">
                    <input type="number" name="number_of_owners" placeholder="Number of Owners" min="1" required>
                </div>

                <!-- Vehicle Specifications -->
                <div class="input-group">
                    <input type="number" name="length" placeholder="Length (mm)" required>
                </div>
                <div class="input-group">
                    <input type="number" name="width" placeholder="Width (mm)" required>
                </div>
                <div class="input-group">
                    <input type="number" name="height" placeholder="Height (mm)" required>
                </div>
                <div class="input-group">
                    <input type="number" name="wheelbase" placeholder="Wheelbase (mm)" required>
                </div>
                <div class="input-group">
                    <input type="number" name="ground_clearance" placeholder="Ground Clearance (mm)" required>
                </div>
                <div class="input-group">
                    <input type="number" name="kerb_weight" placeholder="Kerb Weight (kg)" required>
                </div>
                <div class="input-group">
                    <input type="number" name="seating_capacity" placeholder="Seating Capacity" required>
                </div>
                <div class="input-group">
                    <input type="number" name="boot_space" placeholder="Boot Space (liters)" required>
                </div>
                <div class="input-group">
                    <input type="number" name="fuel_tank_capacity" placeholder="Fuel Tank Capacity (liters)" required>
                </div>
                <div class="input-group">
                    <input type="text" name="front_suspension" placeholder="Front Suspension">
                </div>
                <div class="input-group">
                    <input type="text" name="rear_suspension" placeholder="Rear Suspension">
                </div>
                <div class="input-group">
                    <input type="text" name="front_brake_type" placeholder="Front Brake Type">
                </div>
                <div class="input-group">
                    <input type="text" name="rear_brake_type" placeholder="Rear Brake Type">
                </div>
                <div class="input-group">
                    <input type="text" name="minimum_turning_radius" placeholder="Minimum Turning Radius">
                </div>
                <div class="input-group">
                    <input type="text" name="wheels" placeholder="Wheels">
                </div>
                <div class="input-group">
                    <input type="text" name="spare_wheel" placeholder="Spare Wheel">
                </div>
                <div class="input-group">
                    <input type="text" name="front_tyres" placeholder="Front Tyres (e.g., 165/65 R14)" 
                           pattern="\d{3}/\d{2}\sR\d{2}" 
                           title="Format: 165/65 R14">
                </div>
                <div class="input-group">
                    <input type="text" name="rear_tyres" placeholder="Rear Tyres (e.g., 165/65 R14)"
                           pattern="\d{3}/\d{2}\sR\d{2}" 
                           title="Format: 165/65 R14">
                </div>
                <div class="input-group">
                    <input type="text" name="guarantee" placeholder="Guarantee">
                </div>

                <!-- Description and Address Row -->
                <div class="input-group">
                    <input type="text" name="address" placeholder="Address">
                </div>
                <div class="input-group full-width">
                    <textarea name="description" placeholder="Vehicle Description" rows="4"></textarea>
                </div>

                <!-- Photo Upload Section -->
                <div class="photo-section">
                    <h3>Vehicle Photos</h3>
                    <div class="photo-tabs">
                        <button type="button" class="tab-btn active" data-tab="exterior">Exterior</button>
                        <button type="button" class="tab-btn" data-tab="interior">Interior</button>
                        <button type="button" class="tab-btn" data-tab="features">Features</button>
                        <button type="button" class="tab-btn" data-tab="imperfections">Imperfections</button>
                        <button type="button" class="tab-btn" data-tab="highlights">Highlights</button>
                        <button type="button" class="tab-btn" data-tab="tyres">Tyres</button>
                    </div>
                    <div class="photo-upload-area">
                        <div class="upload-section active" id="exterior-upload">
                            <input type="file" name="exterior_photos[]" multiple accept="image/*">
                        </div>
                        <div class="upload-section" id="interior-upload">
                            <input type="file" name="interior_photos[]" multiple accept="image/*">
                        </div>
                        <div class="upload-section" id="features-upload">
                            <input type="file" name="features_photos[]" multiple accept="image/*">
                        </div>
                        <div class="upload-section" id="imperfections-upload">
                            <input type="file" name="imperfections_photos[]" multiple accept="image/*">
                        </div>
                        <div class="upload-section" id="highlights-upload">
                            <input type="file" name="highlights_photos[]" multiple accept="image/*">
                        </div>
                        <div class="upload-section" id="tyres-upload">
                            <input type="file" name="tyres_photos[]" multiple accept="image/*">
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <button type="submit" class="primary-btn">List Vehicle</button>
                    <a href="manage_availability.php" class="secondary-btn" style="text-decoration: none;">Manage Test Drive Availability</a>
                </div>
            </form>
        </div>

        <!-- Current Listings Section -->
        <div class="current-listings">
            <h2>Your Current Listings</h2>
            <?php
            if ($result->num_rows > 0) {
                echo '<div class="listings-grid">';
                while ($vehicle = $result->fetch_assoc()) {
                    ?>
                    <div class="vehicle-card">
                        <div class="vehicle-image">
                            <?php if ($vehicle['main_photo']): ?>
                                <img src="<?php echo htmlspecialchars($vehicle['main_photo']); ?>" alt="Vehicle Image">
                            <?php else: ?>
                                <div class="no-image">No Image Available</div>
                            <?php endif; ?>
                        </div>
                        <div class="vehicle-details">
                            <h3><?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?></h3>
                            <p class="price">₹<?php echo number_format($vehicle['price'], 2); ?></p>
                            <p class="year"><?php echo htmlspecialchars($vehicle['year']); ?></p>
                            <p class="mileage"><?php echo number_format($vehicle['kilometer']); ?> km</p>
                            <div class="card-actions">
                                <a href="edit_vehicle.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="edit-btn">Edit</a>
                                <button onclick="deleteVehicle(<?php echo $vehicle['vehicle_id']; ?>)" class="delete-btn">Delete</button>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                echo '</div>';
            } else {
                echo '<div class="no-listings">
                        <p>You have no vehicles listed. Add a vehicle to get started.</p>
                      </div>';
            }
            ?>
        </div>
    </div>

    <script>
    function deleteVehicle(vehicleId) {
        if (confirm('Are you sure you want to delete this vehicle?')) {
            fetch('delete_vehicle.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    vehicle_id: vehicleId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting vehicle: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting vehicle');
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.tab-btn');
        const uploadSections = document.querySelectorAll('.upload-section');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and sections
                tabs.forEach(t => t.classList.remove('active'));
                uploadSections.forEach(section => section.classList.remove('active'));

                // Add active class to clicked tab
                tab.classList.add('active');

                // Show corresponding upload section
                const targetSection = document.getElementById(`${tab.dataset.tab}-upload`);
                targetSection.classList.add('active');
            });
        });
    });
    </script>
</body>
</html>
