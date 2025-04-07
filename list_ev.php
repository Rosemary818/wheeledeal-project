<?php
session_start();
include 'db_connect.php'; // Database connection file

// Check if seller is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

// Get brands for dropdown - Fixed query
$brand_sql = "SELECT DISTINCT brand FROM tbl_vehicles WHERE brand IS NOT NULL ORDER BY brand";
$brand_result = $conn->query($brand_sql);

// Add error checking
if ($brand_result === false) {
    // Handle query error
    $_SESSION['error_message'] = "Error fetching brands: " . $conn->error;
} else {
    $brands = [];
    while ($row = $brand_result->fetch_assoc()) {
        $brands[] = $row['brand'];
    }
}

// Message handling
$success_message = '';
$error_message = '';

// Check for messages in session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear the message
}

// Fetch all EV vehicles for the current seller
$sql = "SELECT * FROM ev_details WHERE seller_id = $seller_id ORDER BY created_at DESC";
$result = $conn->query($sql);

// Debug information removed

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vehicle_type = $_POST['vehicle_type'];
    
    // Validate vehicle type
    if (!in_array($vehicle_type, ['ICE', 'EV'])) {
        $_SESSION['error_message'] = "Invalid vehicle type selected";
        header("Location: list_ev.php");
        exit();
    }

    // Prepare the vehicle insert statement
    $vehicle_sql = "INSERT INTO tbl_vehicles (
        seller_id, brand, model, year, price, vehicle_type, 
        transmission, mileage, kilometer, color,
        registration_type, number_of_owners, guarantee, Address,
        description, front_suspension, rear_suspension,
        front_brake_type, rear_brake_type, minimum_turning_radius,
        wheels, spare_wheel, front_tyres, rear_tyres, 
        max_power, max_torque, status
    ) VALUES (
        ?, ?, ?, ?, ?, ?, 
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, 'Active'
    )";

    $stmt = $conn->prepare($vehicle_sql);

    // Check if prepare was successful
    if ($stmt === false) {
        $_SESSION['error_message'] = "Prepare failed: " . $conn->error;
        header("Location: list_ev.php");
        exit();
    }

    // Get form data
    $seller_id = $_SESSION['user_id'];
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $year = $_POST['year'];
    $price = $_POST['price'];
    $transmission = $_POST['transmission'];
    $mileage = $_POST['mileage'];
    $kilometer = $_POST['kilometer'];
    $color = $_POST['color'];
    $registration_type = $_POST['registration_type'];
    $number_of_owners = $_POST['number_of_owners'];
    $guarantee = $_POST['guarantee'];
    $address = $_POST['address'];
    $description = $_POST['description'];
    $front_suspension = $_POST['front_suspension'];
    $rear_suspension = $_POST['rear_suspension'];
    $front_brake_type = $_POST['front_brake_type'];
    $rear_brake_type = $_POST['rear_brake_type'];
    $minimum_turning_radius = $_POST['minimum_turning_radius'];
    $wheels = $_POST['wheels'];
    $spare_wheel = $_POST['spare_wheel'];
    $front_tyres = $_POST['front_tyres'];
    $rear_tyres = $_POST['rear_tyres'];
    $max_power = $_POST['max_power'] ?? '';  // For tbl_vehicles
    $max_torque = $_POST['max_torque'] ?? ''; // For tbl_vehicles

    // Updated bind_param with all parameters for tbl_vehicles
    $stmt->bind_param("issiissssssssssssssssssss", 
        $seller_id, $brand, $model, $year, $price,
        $vehicle_type, $transmission, $mileage, $kilometer,
        $color, $registration_type, $number_of_owners, $guarantee, $address,
        $description, $front_suspension, $rear_suspension,
        $front_brake_type, $rear_brake_type, $minimum_turning_radius,
        $wheels, $spare_wheel, $front_tyres, $rear_tyres,
        $max_power, $max_torque
    );

    // If vehicle insert successful, insert EV details
    if ($stmt->execute()) {
        $vehicle_id = $conn->insert_id;
        
        // Only insert EV details if vehicle type is EV
        if ($vehicle_type === 'EV') {
            // IMPORTANT: Create a direct, hardcoded query for tbl_ev
            // This avoids any possibility of max_power being included from variables
            $range_km = $_POST['range_km'] ?? null;
            $battery_capacity = $_POST['battery_capacity'] ?? null;
            $charging_time_ac = $_POST['charging_time_ac'] ?? null;
            $charging_time_dc = $_POST['charging_time_dc'] ?? null;
            $electric_motor = $_POST['electric_motor'] ?? null;
            $motor_type = $_POST['motor_type'] ?? null;
            
            // RADICAL SOLUTION: Use direct query instead of prepared statement
            // This guarantees we're only including the exact fields we want
            $direct_sql = "INSERT INTO tbl_ev (
                vehicle_id, range_km, battery_capacity, charging_time_ac, 
                charging_time_dc, electric_motor, motor_type
            ) VALUES (
                $vehicle_id, 
                " . ($range_km ? "'" . $conn->real_escape_string($range_km) . "'" : "NULL") . ", 
                " . ($battery_capacity ? "'" . $conn->real_escape_string($battery_capacity) . "'" : "NULL") . ",
                " . ($charging_time_ac ? "'" . $conn->real_escape_string($charging_time_ac) . "'" : "NULL") . ",
                " . ($charging_time_dc ? "'" . $conn->real_escape_string($charging_time_dc) . "'" : "NULL") . ",
                " . ($electric_motor ? "'" . $conn->real_escape_string($electric_motor) . "'" : "NULL") . ",
                " . ($motor_type ? "'" . $conn->real_escape_string($motor_type) . "'" : "NULL") . "
            )";
            
            // Execute the direct query
            if ($conn->query($direct_sql)) {
                $_SESSION['success_message'] = "Electric vehicle listed successfully!";
                header("Location: seller_dashboard.php");
                exit();
            } else {
                // If EV insert fails, delete the vehicle entry
                $delete_sql = "DELETE FROM tbl_vehicles WHERE vehicle_id = $vehicle_id";
                $conn->query($delete_sql);
                
                $_SESSION['error_message'] = "Error listing EV details: " . $conn->error;
                header("Location: list_ev.php");
                exit();
            }
        } else {
            $_SESSION['success_message'] = "Vehicle listed successfully!";
            header("Location: seller_dashboard.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Error listing vehicle: " . $stmt->error;
        header("Location: list_ev.php");
        exit();
    }
}

// Fetch user's electric vehicles
$vehicles_query = "SELECT * FROM tbl_vehicles WHERE seller_id = ? AND vehicle_type = 'EV' ORDER BY created_at DESC";
$vehicles_stmt = $conn->prepare($vehicles_query);
$vehicles_stmt->bind_param("i", $seller_id);
$vehicles_stmt->execute();
$vehicles_result = $vehicles_stmt->get_result();

// Add the form HTML to match these fields
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Electric Vehicles - WheeleDeal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #343a40;
            background-color: #f7f4f1;
        }
        
        /* Header styles to match your image */
        header {
            background-color: white;
            padding: 15px 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
        }
        
        .logo-image {
            height: 40px;
            width: auto;
            margin-right: 10px;
        }
        
        .brand-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .nav-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-links a {
            color: #333;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-button {
            background-color: #FF6B35;
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 500;
            text-decoration: none;
            font-size: 14px;
            margin-left: 15px;
        }
        
        /* Continue with your existing styles for the form */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 25px;
            color: #333;
        }
        
        .ev-form-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .section-title {
            color: var(--secondary-color);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--light-color);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group input,
        .input-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
            color: #333;
            background: white;
            outline: none;
            transition: border-color 0.3s;
        }
        
        .input-group input:focus,
        .input-group select:focus {
            border-color: #666;
        }
        
        .input-group input::placeholder {
            color: #999;
        }
        
        /* Style for select dropdowns */
        .input-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M8 11l-7-7h14l-7 7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 30px;
        }
        
        /* Style for textarea */
        .input-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 15px;
            font-size: 14px;
            color: #333;
            background: white;
            outline: none;
            transition: border-color 0.3s;
            resize: vertical;
            min-height: 100px;
        }
        
        .ev-specs {
            background-color: #f0f9ff;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
            margin-top: 1.5rem;
        }
        
        .submit-button {
            display: block;
            width: 100%;
            max-width: 400px;
            margin: 30px auto;
            padding: 15px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .submit-button:hover {
            background-color: #45a049;
        }
        
        .submit-button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .submit-button i {
            font-size: 20px;
        }
        
        .file-input-container {
            margin-top: 1.5rem;
        }
        
        .file-input-group {
            margin-bottom: 1rem;
        }
        
        .file-input-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .go-back {
            display: inline-flex;
            align-items: center;
            color: var(--gray-color);
            text-decoration: none;
            margin-bottom: 1.5rem;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .go-back:hover {
            color: var(--primary-color);
        }
        
        .go-back i {
            margin-right: 0.5rem;
        }
        
        .input-help {
            font-size: 0.8rem;
            color: var(--gray-color);
            margin-top: 0.25rem;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo-icon {
            background-color: #FF6B35;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            margin-right: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .logo-icon:before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 60%;
            background-color: #FF6B35;
        }
        
        .logo-icon:after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 20%;
            right: 20%;
            height: 40%;
            background-color: #FF6B35;
        }
        
        /* Add message styles */
        .message-container {
            margin: 20px auto;
            max-width: 1200px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        /* Add these new styles */
        .page-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 2rem 0;
        }
        
        .list-ev-button, .manage-availability-button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .list-ev-button:hover, .manage-availability-button:hover {
            background-color: #45a049;
        }
        
        .manage-availability-button i {
            font-size: 20px;
        }
        
        .ev-listings-section {
            margin-top: 3rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        
        .ev-listings {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .ev-listing-card {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .ev-listing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .ev-listing-details {
            padding: 1.5rem;
        }
        
        .ev-listing-details h4 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .ev-specs-preview {
            margin-bottom: 1.5rem;
        }
        
        .ev-specs-preview p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .ev-listing-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .action-button {
            flex: 1;
            padding: 10px 0;
            text-align: center;
            border-radius: 4px;
            font-weight: 500;
            text-decoration: none;
            color: white;
            transition: opacity 0.2s;
        }
        
        .action-button:hover {
            opacity: 0.9;
        }
        
        .edit-button {
            background-color: #4CAF50;
        }
        
        .delete-button {
            background-color: #FF5722;
        }
        
        .no-listings {
            text-align: center;
            padding: 2rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 1.5rem;
        }
        
        .ev-listing-photo {
            height: 200px;
            overflow: hidden;
            position: relative;
            border-radius: 8px 8px 0 0;
        }
        
        .ev-listing-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .ev-listing-card:hover .ev-listing-photo img {
            transform: scale(1.05);
        }

        .file-input {
            border: 2px dashed #ddd;
            padding: 15px;
            border-radius: 5px;
            width: 100%;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }

        .file-input:hover {
            border-color: #4CAF50;
        }

        .input-help {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .input-group {
            margin-bottom: 12px;
        }

        .input-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .manage-availability-container {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
        }

        .manage-availability-button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .manage-availability-button:hover {
            background-color: #45a049;
        }

        .manage-availability-button i {
            font-size: 20px;
        }

        .button-container {
            display: flex;
            gap: 15px;
            margin: 2rem 0;
        }

        .list-vehicle-btn, .manage-availability-btn {
            flex: 1;
            padding: 15px 20px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .list-vehicle-btn {
            background-color: #FF6B35;
            color: white;
        }

        .manage-availability-btn {
            background-color: #4CAF50;
            color: white;
        }

        .list-vehicle-btn:hover {
            background-color: #e85f2d;
        }

        .manage-availability-btn:hover {
            background-color: #45a049;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .form-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 992px) {
            .form-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Add these styles for the vehicle type selector */
        .input-group select[name="vehicle_type"] {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
            color: #333;
            background: white;
            transition: all 0.3s ease;
        }

        .input-group select[name="vehicle_type"]:focus {
            border-color: #FF5722;
            box-shadow: 0 0 0 2px rgba(255, 87, 34, 0.1);
        }

        /* Style for the options */
        .input-group select[name="vehicle_type"] option {
            padding: 10px;
        }

        /* Update styles to match input field */
        .input-group input[name="brand"] {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
            color: #333;
            background: white;
            transition: all 0.3s ease;
        }

        .input-group input[name="brand"]:focus {
            border-color: #FF5722;
            box-shadow: 0 0 0 2px rgba(255, 87, 34, 0.1);
        }

        .input-group input[name="brand"]::placeholder {
            color: #999;
        }

        /* Add these styles for the new fields */
        .full-width {
            grid-column: 1 / -1;
        }

        textarea {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
            color: #333;
            background: white;
            transition: all 0.3s ease;
            resize: vertical;
            min-height: 100px;
        }

        textarea:focus {
            border-color: #FF5722;
            box-shadow: 0 0 0 2px rgba(255, 87, 34, 0.1);
        }

        .form-section {
            margin-bottom: 25px;
        }

        .form-section h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 18px;
        }

        .input-group select,
        .input-group input {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
            color: #333;
            background: white;
            transition: all 0.3s ease;
        }

        .input-group select:focus,
        .input-group input:focus {
            border-color: #FF5722;
            box-shadow: 0 0 0 2px rgba(255, 87, 34, 0.1);
        }

        /* Style for select dropdowns */
        .input-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M8 11l-7-7h14l-7 7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }

        /* Add these styles to update the form layout */
        .form-section {
            margin-bottom: 30px;
        }

        .subsection-title {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
            gap: 15px;
        }

        .two-column {
            display: flex;
            justify-content: space-between;
        }

        .two-column .input-group {
            width: calc(50% - 10px);
        }

        .input-group {
            margin-bottom: 12px;
        }

        .half-width {
            width: 45%;
        }

        .input-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .modern-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 13px;
            color: #333;
            background-color: white;
            transition: all 0.3s ease;
        }

        .modern-input:focus {
            border-color: #666;
            outline: none;
        }

        select.modern-input {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M8 11l-7-7h14l-7 7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 30px;
        }

        .page-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 25px;
            color: #333;
        }

        .section-title {
            font-size: 18px;
            color: #444;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }

        .ev-form-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .two-column .input-group {
                width: 100%;
            }
            
            .half-width {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo-container">
            <img src="images/logo3.png" alt="WheeleDeal Logo" class="logo-image">
            <span class="brand-name">WheeleDeal</span>
        </div>
        <div class="nav-container">
            <div class="welcome-text">Welcome, <?php echo isset($_SESSION['name']) ? $_SESSION['name'] : 'User'; ?>!</div>
            <div class="nav-links">
                <!-- <a href="home.php">Back to Home</a> -->
                <a href="profile.php">My Profile</a>
                <a href="manage_test_drives.php">Manage Test Drives</a>
            </div>
            <a href="seller_dashboard.php" class="back-button">Back to Selling</a>
        </div>
    </header>
    
    <!-- Message container -->
    <div class="message-container">
        <?php if ($success_message): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="container">
        <h2 class="page-title">List Your Electric Vehicle</h2>
        
        <!-- Debug information section removed -->
        
        <!-- EV Form Section -->
        <div class="ev-form-card">
            <h3 class="section-title">Enter Electric Vehicle Details</h3>
            
            <form action="process_vehicle.php" method="post" enctype="multipart/form-data" id="ev-form">
                <input type="hidden" name="is_ev" value="1">
                
                <!-- Basic info with updated layout -->
                <div class="form-section">
                    <h4 class="subsection-title">Basic Information</h4>
                    
                    <div class="form-row two-column">
                        <div class="input-group">
                            <select name="vehicle_type" class="modern-input" required>
                                <option value="">Select Vehicle Type</option>
                                <option value="ICE">ICE (Internal Combustion Engine)</option>
                                <option value="EV">EV (Electric Vehicle)</option>
                            </select>
                        </div>
                        
                        <div class="input-group">
                            <input type="text" name="brand" class="modern-input" placeholder="Enter Brand Name" required>
                        </div>
                    </div>
                    
                    <div class="form-row two-column">
                        <div class="input-group">
                            <label for="model">Model</label>
                            <input type="text" id="model" name="model" class="modern-input" required>
                        </div>
                        
                        <div class="input-group">
                            <label for="year">Year</label>
                            <input type="number" id="year" name="year" class="modern-input" min="1900" max="2099" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group half-width">
                            <label for="price">Price</label>
                            <input type="number" id="price" name="price" class="modern-input" min="0" step="0.01" required>
                        </div>
                    </div>
                </div>
                
                <!-- EV Specs -->
                <div class="form-section ev-specs">
                    <h4>Electric Vehicle Specifications</h4>
                    <div class="form-grid">
                        <div class="input-group">
                            <label for="range_km">Range (km)</label>
                            <input type="number" id="range_km" name="range_km" min="0" required>
                        </div>
                        <div class="input-group">
                            <label for="battery_capacity">Battery Capacity (kWh)</label>
                            <input type="number" id="battery_capacity" name="battery_capacity" min="0" step="0.1" required>
                        </div>
                        <div class="input-group">
                            <label for="charging_time_ac">Charging Time (AC)</label>
                            <input type="text" id="charging_time_ac" name="charging_time_ac" required>
                        </div>
                        <div class="input-group">
                            <label for="charging_time_dc">Charging Time (DC)</label>
                            <input type="text" id="charging_time_dc" name="charging_time_dc" required>
                        </div>
                        <div class="input-group">
                            <label for="boot_space">Boot Space (liters)</label>
                            <input type="number" id="boot_space" name="boot_space" min="0" required>
                        </div>
                    </div>
                </div>
                
                <!-- Vehicle Specifications Form Card -->
                <div class="ev-form-card">
                    <h2 class="section-title">Additional Specifications</h2>
                    <div class="form-grid">
                        <div class="input-group">
                            <label for="engine">Electric Motor</label>
                            <input type="text" id="engine" name="engine" required placeholder="e.g., Permanent Magnet Synchronous Motor">
                        </div>
                        
                        <div class="input-group">
                            <label for="engine_type">Motor Type</label>
                            <input type="text" id="engine_type" name="engine_type" required placeholder="e.g., AC Induction">
                        </div>
                        
                        <div class="input-group">
                            <label for="max_power">Maximum Power</label>
                            <input type="text" id="max_power" name="max_power" placeholder="e.g., 150 hp">
                        </div>
                        
                        <div class="input-group">
                            <label for="max_torque">Maximum Torque</label>
                            <input type="text" id="max_torque" name="max_torque" placeholder="e.g., 250 Nm">
                        </div>

                        <!-- Add kilometer and color fields after max_torque -->
                        <div class="input-group">
                            <label for="kilometer">Kilometer</label>
                            <input type="number" id="kilometer" name="kilometer" placeholder="e.g., 5000">
                        </div>

                        <div class="input-group">
                            <label for="color">Color</label>
                            <input type="text" id="color" name="color" placeholder="e.g., Midnight Blue">
                        </div>
                        
                        <!-- Suspension and brakes -->
                        <div class="input-group">
                            <label for="front_suspension">Front Suspension</label>
                            <input type="text" id="front_suspension" name="front_suspension" required>
                        </div>
                        
                        <div class="input-group">
                            <label for="rear_suspension">Rear Suspension</label>
                            <input type="text" id="rear_suspension" name="rear_suspension" required>
                        </div>
                        
                        <div class="input-group">
                            <label for="front_brake_type">Front Brake Type</label>
                            <input type="text" id="front_brake_type" name="front_brake_type" required>
                        </div>
                        
                        <div class="input-group">
                            <label for="rear_brake_type">Rear Brake Type</label>
                            <input type="text" id="rear_brake_type" name="rear_brake_type" required>
                        </div>
                        
                        <!-- Other fields -->
                        <div class="input-group">
                            <label for="minimum_turning_radius">Minimum Turning Radius (m)</label>
                            <input type="text" id="minimum_turning_radius" name="minimum_turning_radius" required>
                        </div>
                        
                        <div class="input-group">
                            <label for="no_of_rows">Number of Rows</label>
                            <input type="text" id="no_of_rows" name="no_of_rows" required>
                        </div>
                        
                        <!-- Wheels and tires -->
                        <div class="input-group">
                            <label for="wheels">Wheels</label>
                            <input type="text" id="wheels" name="wheels" required placeholder="e.g., Alloy">
                        </div>
                        
                        <div class="input-group">
                            <label for="spare_wheel">Spare Wheel</label>
                            <input type="text" id="spare_wheel" name="spare_wheel" required>
                        </div>
                        
                        <div class="input-group">
                            <label for="front_tyres">Front Tyres</label>
                            <input type="text" id="front_tyres" name="front_tyres" required placeholder="e.g., 215/55 R17">
                        </div>
                        
                        <div class="input-group">
                            <label for="rear_tyres">Rear Tyres</label>
                            <input type="text" id="rear_tyres" name="rear_tyres" required placeholder="e.g., 215/55 R17">
                        </div>

                        <!-- New fields added here -->
                        <div class="input-group">
                            <label for="transmission">Transmission</label>
                            <select name="transmission" id="transmission" required>
                                <option value="">Select Transmission</option>
                                <option value="Manual">Manual</option>
                                <option value="Automatic">Automatic</option>
                                <option value="Semi-Automatic">Semi-Automatic</option>
                            </select>
                        </div>

                        <div class="input-group">
                            <label for="mileage">Mileage (km/l)</label>
                            <input type="text" 
                                   id="mileage"
                                   name="mileage" 
                                   required
                                   pattern="[0-9.]+"
                                   title="Please enter a valid mileage number">
                        </div>

                        <div class="input-group">
                            <label for="registration_type">Registration Type</label>
                            <select name="registration_type" id="registration_type" required>
                                <option value="">Select Registration Type</option>
                                <option value="Individual">Individual</option>
                                <option value="Commercial">Commercial</option>
                                <option value="Corporate">Corporate</option>
                            </select>
                        </div>

                        <div class="input-group">
                            <label for="number_of_owners">Number of Previous Owners</label>
                            <input type="number" 
                                   id="number_of_owners"
                                   name="number_of_owners" 
                                   min="0"
                                   required>
                        </div>

                        <div class="input-group">
                            <label for="guarantee">Guarantee/Warranty Details</label>
                            <input type="text" 
                                   id="guarantee"
                                   name="guarantee" 
                                   required>
                        </div>

                        <div class="input-group full-width">
                            <label for="address">Vehicle Location/Address</label>
                            <textarea id="address"
                                      name="address" 
                                      rows="3"
                                      required></textarea>
                        </div>

                        <div class="input-group full-width">
                            <label for="description">Vehicle Description</label>
                            <textarea id="description"
                                      name="description" 
                                      rows="5"
                                      required></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Photo Upload Section -->
                <div class="form-section">
                    <h4>Vehicle Photos</h4>
                    <div class="form-grid">
                        <!-- Exterior Photos -->
                        <div class="input-group">
                            <label for="exterior_photos">Exterior Photos</label>
                            <input type="file" id="exterior_photos" name="exterior_photos[]" multiple accept="image/*" class="file-input">
                            <div class="input-help">Select multiple exterior photos</div>
                        </div>

                        <!-- Interior Photos -->
                        <div class="input-group">
                            <label for="interior_photos">Interior Photos</label>
                            <input type="file" id="interior_photos" name="interior_photos[]" multiple accept="image/*" class="file-input">
                            <div class="input-help">Select multiple interior photos</div>
                        </div>

                        <!-- Features Photos -->
                        <div class="input-group">
                            <label for="features_photos">Features Photos</label>
                            <input type="file" id="features_photos" name="features_photos[]" multiple accept="image/*" class="file-input">
                            <div class="input-help">Select photos of special features</div>
                        </div>

                        <!-- Imperfections Photos -->
                        <div class="input-group">
                            <label for="imperfections_photos">Imperfections Photos</label>
                            <input type="file" id="imperfections_photos" name="imperfections_photos[]" multiple accept="image/*" class="file-input">
                            <div class="input-help">Select photos of any imperfections</div>
                        </div>

                        <!-- Highlights Photos -->
                        <div class="input-group">
                            <label for="highlights_photos">Highlights Photos</label>
                            <input type="file" id="highlights_photos" name="highlights_photos[]" multiple accept="image/*" class="file-input">
                            <div class="input-help">Select photos of vehicle highlights</div>
                        </div>

                        <!-- Tyres Photos -->
                        <div class="input-group">
                            <label for="tyres_photos">Tyres Photos</label>
                            <input type="file" id="tyres_photos" name="tyres_photos[]" multiple accept="image/*" class="file-input">
                            <div class="input-help">Select photos of the tyres</div>
                        </div>
                    </div>
                </div>
                
                <!-- Submit button at the end of the form -->
                <div class="button-container">
                    <button type="submit" class="list-vehicle-btn">
                        List Electric Vehicle
                    </button>
                    <a href="manage_availability.php" class="manage-availability-btn">
                        <i class="fas fa-calendar-alt"></i> Manage Test Drive Availability
                    </a>
                </div>
            </form>
        </div>
        
        <!-- EV Listings Section moved below the form -->
        <div class="ev-listings-section">
            <h3>Your Electric Vehicle Listings</h3>
            
            <?php if ($vehicles_result->num_rows > 0): ?>
                <div class="ev-listings">
                    <?php while ($row = $vehicles_result->fetch_assoc()): ?>
                        <div class="ev-listing-card">
                            <div class="ev-listing-photo">
                                <?php
                                // Get the first image for this vehicle
                                $image_query = "SELECT photo_file_path FROM tbl_photos WHERE vehicle_id = ? AND category = 'exterior' LIMIT 1";
                                $image_stmt = $conn->prepare($image_query);
                                $image_stmt->bind_param("i", $row['vehicle_id']);
                                $image_stmt->execute();
                                $image_result = $image_stmt->get_result();
                                
                                if ($image_result && $image_result->num_rows > 0) {
                                    $image_data = $image_result->fetch_assoc();
                                    $image_path = !empty($image_data['photo_file_path']) ? $image_data['photo_file_path'] : 'images/default-vehicle.jpg';
                                } else {
                                    $image_path = 'images/default-vehicle.jpg';
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($row['brand'] . ' ' . $row['model']); ?>">
                            </div>
                            <div class="ev-listing-details">
                                <h4><?php echo htmlspecialchars($row['brand'] . ' ' . $row['model']); ?></h4>
                                <div class="ev-specs-preview">
                                    <p><strong>Vehicle Type:</strong> <?php echo htmlspecialchars($row['vehicle_type']); ?></p>
                                    <p><strong>Year:</strong> <?php echo htmlspecialchars($row['year']); ?></p>
                                    <p><strong>Price:</strong> ₹<?php echo number_format($row['price']); ?></p>
                                    <p><strong>Transmission:</strong> <?php echo htmlspecialchars($row['transmission']); ?></p>
                                    <p><strong>Mileage:</strong> <?php echo htmlspecialchars($row['mileage']); ?> km/l</p>
                                </div>
                                <div class="ev-listing-actions">
                                    <a href="edit_ev.php?id=<?php echo $row['vehicle_id']; ?>" class="action-button edit-button">Edit</a>
                                    <a href="delete_vehicle.php?id=<?php echo $row['vehicle_id']; ?>" class="action-button delete-button" onclick="return confirm('Are you sure you want to delete this vehicle?');">Delete</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-listings">
                    <p>You haven't listed any vehicles yet.</p>
                    <p>Fill out the form above to add your first vehicle listing.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle "Other" brand option
            const brandSelect = document.getElementById('brand');
            const otherBrandContainer = document.getElementById('other-brand-container');
            
            brandSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    otherBrandContainer.style.display = 'block';
                } else {
                    otherBrandContainer.style.display = 'none';
                }
            });
            
            // Form validation and submission handling
            const form = document.getElementById('ev-form');
            const submitButton = document.querySelector('.submit-button');
            
            form.addEventListener('submit', function(e) {
                // Prevent default form submission
                e.preventDefault();
                
                // Basic form validation
                let isValid = true;
                const requiredFields = form.querySelectorAll('[required]');
                
                requiredFields.forEach(field => {
                    if (!field.value) {
                        isValid = false;
                        field.classList.add('invalid');
                    } else {
                        field.classList.remove('invalid');
                    }
                });
                
                if (!isValid) {
                    // Show error message for missing fields
                    const errorContainer = document.createElement('div');
                    errorContainer.className = 'error-message';
                    errorContainer.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please fill in all required fields.';
                    
                    const messageContainer = document.querySelector('.message-container');
                    messageContainer.innerHTML = '';
                    messageContainer.appendChild(errorContainer);
                    
                    // Scroll to top to see error
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }
                
                // If validation passes, submit the form
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitButton.disabled = true;
                form.submit();
            });

            document.querySelectorAll('input[type="file"]').forEach(input => {
                input.addEventListener('change', function(e) {
                    const fileName = Array.from(this.files)
                        .map(file => file.name)
                        .join(', ');
                    const helpText = this.parentElement.querySelector('.input-help');
                    if (this.files.length > 0) {
                        helpText.textContent = `Selected: ${this.files.length} file(s) - ${fileName}`;
                    } else {
                        helpText.textContent = 'No files chosen';
                    }
                });
            });

            // Add this JavaScript to handle vehicle type selection
            const vehicleTypeSelect = document.querySelector('select[name="vehicle_type"]');
            const evSpecsSection = document.querySelector('.ev-specs');

            vehicleTypeSelect.addEventListener('change', function() {
                if (this.value === 'EV') {
                    evSpecsSection.style.display = 'block';
                    // Make EV-specific fields required
                    document.querySelectorAll('.ev-specs input').forEach(input => {
                        input.required = true;
                    });
                } else {
                    evSpecsSection.style.display = 'none';
                    // Remove required from EV-specific fields
                    document.querySelectorAll('.ev-specs input').forEach(input => {
                        input.required = false;
                    });
                }
            });

            // Add this JavaScript to handle fuel type based on vehicle type
            const vehicleTypeSelect = document.querySelector('select[name="vehicle_type"]');
            const fuelTypeSelect = document.querySelector('select[name="fuel_type"]');

            vehicleTypeSelect.addEventListener('change', function() {
                if (this.value === 'EV') {
                    fuelTypeSelect.value = 'Electric';
                    fuelTypeSelect.disabled = true;
                } else {
                    fuelTypeSelect.disabled = false;
                    fuelTypeSelect.value = '';
                }
            });
        });
    </script>
</body>
</html> 