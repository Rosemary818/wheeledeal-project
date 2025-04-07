<?php
session_start();
include 'db_connect.php'; // Database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Initialize filter variables
$where_conditions = [];
$params = [];
$types = '';

// Add user_id as first parameter for wishlist join
array_unshift($params, $user_id);
$types = 'i';

// Add condition to exclude user's own listings
$where_conditions[] = "v.seller_id != ?";
$params[] = $user_id;
$types .= 'i';

// Add condition to show only active EVs or where status is NULL
$where_conditions[] = "(v.status = 'active' OR v.status IS NULL)";

// Debug information
error_log("Starting browse_ev.php execution");

// Handle search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_conditions[] = "(e.electric_motor LIKE ? OR v.brand LIKE ? OR v.model LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}

// Handle price filter
if (isset($_GET['min_price']) && isset($_GET['max_price'])) {
    $where_conditions[] = "v.price BETWEEN ? AND ?";
    $params[] = (float)$_GET['min_price'];
    $params[] = (float)$_GET['max_price'];
    $types .= 'dd';
}

// Handle brand filter
if (isset($_GET['brands']) && !empty($_GET['brands'])) {
    $brand_placeholders = str_repeat('?,', count($_GET['brands']) - 1) . '?';
    $where_conditions[] = "v.brand IN ($brand_placeholders)";
    foreach ($_GET['brands'] as $brand) {
        $params[] = $brand;
        $types .= 's';
    }
}

// Handle year filter
if (isset($_GET['min_year']) && isset($_GET['max_year'])) {
    $where_conditions[] = "v.year BETWEEN ? AND ?";
    $params[] = (int)$_GET['min_year'];
    $params[] = (int)$_GET['max_year'];
    $types .= 'ii';
}

// EV-specific filters
// Handle range filter
if (isset($_GET['min_range']) && isset($_GET['max_range'])) {
    $where_conditions[] = "e.range_km BETWEEN ? AND ?";
    $params[] = (int)$_GET['min_range'];
    $params[] = (int)$_GET['max_range'];
    $types .= 'ii';
}

// Handle battery capacity filter
if (isset($_GET['min_battery']) && isset($_GET['max_battery'])) {
    $where_conditions[] = "e.battery_capacity BETWEEN ? AND ?";
    $params[] = (float)$_GET['min_battery'];
    $params[] = (float)$_GET['max_battery'];
    $types .= 'dd';
}

// Build the WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// First, verify table structure
$check_table = "SHOW TABLES LIKE 'tbl_vehicles'";
$table_exists = $conn->query($check_table);
if ($table_exists->num_rows === 0) {
    die("Error: tbl_vehicles table does not exist");
}

// Check table columns
$check_columns = "SHOW COLUMNS FROM tbl_vehicles";
$columns = $conn->query($check_columns);
if (!$columns) {
    die("Error checking table structure: " . $conn->error);
}

// Update main EV query to exclude seller's own listings and show active/null status EVs
$sql = "SELECT v.*, 
        CASE WHEN w.wishlist_id IS NOT NULL THEN 1 ELSE 0 END as is_wishlisted,
        e.range_km, e.battery_capacity, e.charging_time_ac, e.charging_time_dc, 
        e.electric_motor, v.max_power, v.max_torque,
        p.photo_file_path
        FROM tbl_vehicles v
        LEFT JOIN tbl_wishlist w ON w.vehicle_id = v.vehicle_id AND w.user_id = ?
        LEFT JOIN tbl_ev e ON e.vehicle_id = v.vehicle_id
        LEFT JOIN tbl_photos p ON p.vehicle_id = v.vehicle_id
        WHERE v.vehicle_type = 'EV' ";

// If there's a search term, adjust the query to use e.electric_motor not v.electric_motor
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $sql .= " AND (e.electric_motor LIKE ? OR v.brand LIKE ? OR v.model LIKE ?)";
    // Make sure to add these parameters to your params array and types string
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}

// Add any additional filters from search
if (!empty($where_clause)) {
    $sql .= " AND " . substr($where_clause, 6);
}

$sql .= " GROUP BY v.vehicle_id ORDER BY v.created_at DESC";

// Debug the SQL query
error_log("SQL Query: " . $sql);
error_log("Parameters: " . print_r($params, true));
error_log("Types: " . $types);

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error . "<br>SQL: " . $sql);
}

// Bind parameters if there are any
if (!empty($params)) {
    try {
        $stmt->bind_param($types, ...$params);
    } catch (Exception $e) {
        die("Binding parameters failed: " . $e->getMessage());
    }
}

// Execute with error checking
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$ev_result = $stmt->get_result();
if ($ev_result === false) {
    die("Getting results failed: " . $stmt->error);
}

// Get all available brands for filter
$brand_sql = "SELECT DISTINCT brand 
              FROM tbl_vehicles 
              WHERE vehicle_type = 'EV' 
              ORDER BY brand";
$brand_result = $conn->query($brand_sql);
$brands = [];

if ($brand_result && $brand_result->num_rows > 0) {
    while ($row = $brand_result->fetch_assoc()) {
        $brands[] = $row['brand'];
    }
}

// Handle wishlist toggle
if (isset($_POST['toggle_wishlist']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $vehicle_id = $_POST['vehicle_id'];
    
    // Check if already in wishlist
    $check_sql = "SELECT wishlist_id FROM tbl_wishlist WHERE user_id = ? AND vehicle_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $vehicle_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Remove from wishlist
        $delete_sql = "DELETE FROM tbl_wishlist WHERE user_id = ? AND vehicle_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $user_id, $vehicle_id);
        $delete_stmt->execute();
    } else {
        // Add to wishlist
        $insert_sql = "INSERT INTO tbl_wishlist (user_id, vehicle_id, added_at) VALUES (?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $user_id, $vehicle_id);
        $insert_stmt->execute();
    }
    
    // Preserve GET parameters
    $params = $_GET;
    $redirect_url = 'browse_ev.php';
    if (!empty($params)) {
        $redirect_url .= '?' . http_build_query($params);
    }
    
    header("Location: " . $redirect_url);
    exit;
}

// When fetching EV data, also check if it's in the user's wishlist
$user_id = $_SESSION['user_id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Electric Vehicles - WheeleDeal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css">
    <style>
        /* Base styles */
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header styles - Matching exactly with your design */
        header {
            background-color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .logo-container {
            display: flex;
            align-items: center;
        }
        
        .logo-container img {
            height: 50px;
            margin-right: 10px;
        }
        
        .brand-name {
            font-size: 30px;
            font-weight: 700;
            color: #333;
        }
        
        .search-container {
            flex: 1;
            max-width: 600px;
            margin: 0 20px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #777;
            cursor: pointer;
        }
        
        .nav-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .nav-link {
            color: #555;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .nav-link:hover {
            color: #FF6B35;
        }
        
        .switch-button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #FF6B35;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .switch-button:hover {
            background-color: #e55a2b;
        }
        
        /* Browse title */
        .browse-title {
            text-align: center;
            margin: 2rem 0;
            color: #2A9D8F;
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        /* Add new container layout */
        .main-container {
            display: flex;
            gap: 2rem;
            margin: 0 auto;
            padding: 0 1rem;
            max-width: 100%; /* Changed from fixed width */
        }

        /* Style for left filter section */
        .filter-section {
            width: 300px;
            flex-shrink: 0;
            position: sticky;
            top: 20px;
            height: fit-content;
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* Style for right content section */
        .content-section {
            flex: 1;
            min-width: 0; /* Prevents flex item from overflowing */
        }

        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Adjust existing styles */
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-height: 150px;
            overflow-y: auto;
        }

        .filter-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
        }
        
        .filter-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .filter-input:focus {
            border-color: #2A9D8F;
            outline: none;
        }
        
        .range-slider {
            width: 100%;
            margin: 0.5rem 0;
        }
        
        .range-values {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .filter-checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #495057;
        }
        
        .filter-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }
        
        .filter-button {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        
        .filter-button:hover {
            transform: translateY(-2px);
        }
        
        .apply-button {
            background-color: #FF6B35;
            color: white;
        }
        
        .apply-button:hover {
            background-color: #e55a2b;
        }
        
        .reset-button {
            background-color: #e9ecef;
            color: #495057;
        }
        
        .reset-button:hover {
            background-color: #dee2e6;
        }
        
        /* EV Grid */
        .ev-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            padding: 2rem;
        }
        
        .vehicle-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .vehicle-image {
            height: 200px;
            position: relative;
        }
        
        .vehicle-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.8);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #333;
        }
        
        .prev { left: 10px; }
        .next { right: 10px; }
        
        .image-dots {
            position: absolute;
            bottom: 10px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 5px;
        }
        
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
        }
        
        .dot.active {
            background: #2A9D8F;
        }
        
        .card-content {
            padding: 1.5rem;
            background: white;  /* White background */
        }
        
        .vehicle-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin: 0 0 0.5rem 0;
        }
        
        .price {
            color: #2A9D8F;
            font-size: 1.5rem;
            margin: 0.5rem 0 1rem 0;
        }
        
        .specs-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .spec-row {
            display: flex;
            justify-content: space-between;
        }
        
        .spec-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;  /* Darker text color for better contrast */
        }
        
        .spec-item i {
            color: #2A9D8F;
        }
        
        .spec-value {
            color: #333;  /* Darker text color for better contrast */
        }
        
        .card-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }
        
        .view-details-btn {
            background: #FF6B35;
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .wishlist-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #ccc;
            cursor: pointer;
        }
        
        .wishlist-btn.active {
            color: #FF6B35;
        }
        
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 15px;
        }
        
        .no-results h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .no-results p {
            color: #666;
        }
        
        /* EV advantages section */
        .ev-advantages {
            background-color: white;
            padding: 3rem;
            width: 100%;
            margin: 3rem 0;
            text-align: center;
        }
        
        .advantages-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .advantages-subtitle {
            color: #666;
            max-width: 800px;
            margin: 0 auto 3rem;
        }
        
        .advantages-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            padding: 0;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .advantage-card {
            background-color: #f8f9fa;
            padding: 2.5rem;
            border-radius: 8px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .advantage-icon {
            flex-shrink: 0;
            width: 50px;
        }
        
        .advantage-icon i {
            font-size: 2rem;
            color: #2A9D8F;
        }
        
        .advantage-content {
            flex: 1;
        }
        
        .advantage-card h3 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .advantage-card p {
            color: #666;
            line-height: 1.5;
            font-size: 0.95rem;
            margin: 0;
        }
        
        @media (max-width: 1024px) {
            .main-container {
                flex-direction: column;
            }

            .filter-section {
                width: 100%;
                position: static;
            }

            .advantages-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .ev-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                padding: 1rem;
                gap: 1rem;
            }
            
            .vehicle-image {
                height: 200px;
            }
            
            .card-content {
                padding: 1rem;
            }
            
            .view-details-btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }
            
            .ev-advantages {
                padding: 2rem 1rem;
            }
            
            .advantage-card {
                padding: 1.5rem;
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
        }
        
        /* Primary button style - for View Details */
        .primary-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            background-color: #FF6B35;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 500;
            transition: background-color 0.3s, transform 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .primary-button:hover {
            background-color: #e55a2b;
            transform: translateY(-2px);
        }
        
        .primary-button:active {
            transform: translateY(0);
        }
        
        .wishlist-form {
            display: inline;
            margin: 0;
        }
        
        .wishlist-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #ccc;
            cursor: pointer;
        }
        
        .wishlist-btn:hover {
            transform: scale(1.1);
            color: #FF6B35;
        }
        
        .wishlist-btn.wishlist-active {
            color: #FF6B35;
        }

        /* Responsive design */
        @media (max-width: 1024px) {
            .main-container {
                flex-direction: column;
            }

            .filter-section {
                width: 100%;
                position: static;
            }
        }

        .vehicle-info {
            flex: 1;
            color: white;
            margin-bottom: 1rem;
        }

        .vehicle-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
            color: white;
        }

        .vehicle-subtitle {
            font-size: 1rem;
            color: #e0e0e0;
            margin: 0.3rem 0;
        }

        .price-section {
            margin: 0.8rem 0;
        }

        .price {
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
            margin: 0;
        }

        .specs-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 0.8rem;
        }

        .spec-item {
            text-align: left;
            color: #e0e0e0;
        }

        .spec-value {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .card-content {
            position: relative;
            background: white;
        }

        .card-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .vehicle-title {
                font-size: 1.2rem;
            }

            .vehicle-subtitle {
                font-size: 0.9rem;
            }

            .price {
                font-size: 1.1rem;
            }

            .specs-grid {
                gap: 0.5rem;
            }

            .spec-value {
                font-size: 0.8rem;
            }
        }

        /* Add EV badge styling */
        .ev-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 2;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        /* Override any existing styles that might hide or change the text */
        .vehicle-title {
            font-size: 1.3rem !important;
            font-weight: 600 !important;
            color: #333 !important;
            margin: 0 0 0.5rem 0 !important;
            display: block !important;
            visibility: visible !important;
            text-shadow: none !important;
            opacity: 1 !important;
        }
        
        .price {
            color: #2A9D8F !important;
            font-size: 1.5rem !important;
            font-weight: 600 !important;
            margin: 0.5rem 0 1rem 0 !important;
            display: block !important;
            visibility: visible !important;
            text-shadow: none !important;
            opacity: 1 !important;
        }
        
        .card-content {
            background-color: white !important;
            padding: 1.5rem !important;
            position: relative !important;
            z-index: 1 !important;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="logo-container">
            <img src="images/logo3.png" alt="WheeleDeal">
            <span class="brand-name">WheeleDeal</span>
        </div>
        
        <div class="search-container">
            <form action="browse_ev.php" method="GET">
                <input type="text" name="search" placeholder="Search vehicles..." class="search-input">
                <button type="submit" class="search-button">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        
        <div class="nav-container">
            <a href="index.php" class="nav-link">Back to Home</a>
            <a href="my_wishlist.php" class="nav-link">My Wishlist</a>
            <a href="profile.php" class="nav-link">My Profile</a>
            <a href="view_test_drives.php" class="nav-link">My Test Drives</a>
            <a href="seller_dashboard.php" class="switch-button">Switch to Selling</a>
        </div>
    </header>

    <div class="container">
        <h1 class="browse-title">Browse Electric Vehicles</h1>
        
        <div class="main-container">
            <!-- Left Filter Section -->
            <div class="filter-section">
                <h2 class="filter-title"><i class="fas fa-filter"></i> Filter Electric Vehicles</h2>
                
                <form id="filterForm" action="" method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="search" class="filter-label">Search</label>
                        <input type="text" id="search" name="search" class="filter-input" 
                               placeholder="Search by model, brand..."
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Price Range (₹)</label>
                        <input type="range" id="minPrice" name="min_price" min="100000" max="5000000" step="50000" 
                               value="<?php echo isset($_GET['min_price']) ? $_GET['min_price'] : '100000'; ?>" class="range-slider">
                        <input type="range" id="maxPrice" name="max_price" min="100000" max="5000000" step="50000" 
                               value="<?php echo isset($_GET['max_price']) ? $_GET['max_price'] : '5000000'; ?>" class="range-slider">
                        <div class="range-values">
                            <span id="minPriceValue">₹100,000</span>
                            <span id="maxPriceValue">₹5,000,000</span>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Year Range</label>
                        <input type="range" id="minYear" name="min_year" min="2000" max="2024" step="1" 
                               value="<?php echo isset($_GET['min_year']) ? $_GET['min_year'] : '2000'; ?>" class="range-slider">
                        <input type="range" id="maxYear" name="max_year" min="2000" max="2024" step="1" 
                               value="<?php echo isset($_GET['max_year']) ? $_GET['max_year'] : '2024'; ?>" class="range-slider">
                        <div class="range-values">
                            <span id="minYearValue">2000</span>
                            <span id="maxYearValue">2024</span>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Range (km)</label>
                        <input type="range" id="minRange" name="min_range" min="100" max="800" step="10" 
                               value="<?php echo isset($_GET['min_range']) ? $_GET['min_range'] : '100'; ?>" class="range-slider">
                        <input type="range" id="maxRange" name="max_range" min="100" max="800" step="10" 
                               value="<?php echo isset($_GET['max_range']) ? $_GET['max_range'] : '800'; ?>" class="range-slider">
                        <div class="range-values">
                            <span id="minRangeValue">100 km</span>
                            <span id="maxRangeValue">800 km</span>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Battery Capacity (kWh)</label>
                        <input type="range" id="minBattery" name="min_battery" min="20" max="120" step="5" 
                               value="<?php echo isset($_GET['min_battery']) ? $_GET['min_battery'] : '20'; ?>" class="range-slider">
                        <input type="range" id="maxBattery" name="max_battery" min="20" max="120" step="5" 
                               value="<?php echo isset($_GET['max_battery']) ? $_GET['max_battery'] : '120'; ?>" class="range-slider">
                        <div class="range-values">
                            <span id="minBatteryValue">20 kWh</span>
                            <span id="maxBatteryValue">120 kWh</span>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Brands</label>
                        <div class="filter-checkbox-group">
                            <?php foreach ($brands as $brand): ?>
                                <label class="filter-checkbox-label">
                                    <input type="checkbox" name="brands[]" value="<?php echo htmlspecialchars($brand); ?>"
                                        <?php echo (isset($_GET['brands']) && in_array($brand, $_GET['brands'])) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($brand); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="filter-button apply-button">Apply Filters</button>
                        <button type="button" onclick="resetFilters()" class="filter-button reset-button">Reset Filters</button>
                    </div>
                </form>
            </div>

            <!-- Right Content Section -->
            <div class="content-section">
                <!-- EV listings -->
                <div class="ev-grid">
                    <?php if ($ev_result && $ev_result->num_rows > 0): ?>
                        <?php while ($row = $ev_result->fetch_assoc()): ?>
                            <div class="vehicle-card">
                                <div class="vehicle-image">
                                    <?php if (!empty($row['photo_file_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($row['photo_file_path']); ?>" alt="<?php echo htmlspecialchars($row['brand'] . ' ' . $row['model']); ?>">
                                    <?php else: ?>
                                        <img src="images/no-image.png" alt="No image available">
                                    <?php endif; ?>
                                    <!-- EV badge -->
                                    <span class="ev-badge">EV</span>
                                </div>
                                <div class="card-content" style="background-color: white;">
                                    <!-- Add clear vehicle title here with enforced styling -->
                                    <h2 class="vehicle-title" style="color: #333 !important; font-size: 1.3rem; font-weight: 600; margin: 0 0 0.5rem 0; display: block; visibility: visible;">
                                        <?php echo htmlspecialchars($row['brand'] . ' ' . $row['model']); ?>
                                    </h2>
                                    <p class="price" style="color: #2A9D8F !important; font-size: 1.5rem; font-weight: 600; margin: 0.5rem 0 1rem 0; display: block; visibility: visible;">
                                        ₹<?php echo number_format($row['price']); ?>
                                    </p>
                                    
                                    <div class="specs-grid">
                                        <div class="spec-row">
                                            <div class="spec-item">
                                                <i class="fas fa-calendar-alt"></i>
                                                <span class="spec-value"><?php echo htmlspecialchars($row['year']); ?></span>
                                            </div>
                                            <div class="spec-item">
                                                <i class="fas fa-road"></i>
                                                <span class="spec-value"><?php echo htmlspecialchars(number_format($row['kilometer'])); ?> km</span>
                                            </div>
                                        </div>
                                        <div class="spec-row">
                                            <div class="spec-item">
                                                <i class="fas fa-battery-full"></i>
                                                <span class="spec-value"><?php echo htmlspecialchars($row['battery_capacity']); ?> kWh</span>
                                            </div>
                                            <div class="spec-item">
                                                <i class="fas fa-bolt"></i>
                                                <span class="spec-value"><?php echo htmlspecialchars($row['range_km']); ?> km</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-actions">
                                        <a href="evdetails.php?id=<?php echo $row['vehicle_id']; ?>" class="view-details-btn">View Details</a>
                                        <form class="wishlist-form" method="POST">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $row['vehicle_id']; ?>">
                                            <button type="submit" name="toggle_wishlist" class="wishlist-btn <?php echo $row['is_wishlisted'] ? 'active' : ''; ?>">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <h3>No electric vehicles found</h3>
                            <p>Try adjusting your filters or check back later for new listings.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Full-width EV Advantages -->
                <div class="ev-advantages">
                    <h2 class="advantages-title">Why Choose an Electric Vehicle?</h2>
                    <p class="advantages-subtitle">Electric vehicles are revolutionizing the way we drive. Here's why you should consider making the switch.</p>
                    
                    <div class="advantages-grid">
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-leaf"></i>
                            </div>
                            <div class="advantage-content">
                                <h3>Eco-Friendly</h3>
                                <p>Zero emissions help reduce air pollution and combat climate change.</p>
                            </div>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="advantage-content">
                                <h3>Cost Savings</h3>
                                <p>Lower operating costs with cheaper electricity and less maintenance.</p>
                            </div>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <div class="advantage-content">
                                <h3>Performance</h3>
                                <p>Instant torque for quick acceleration and a smooth, quiet ride.</p>
                            </div>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-home"></i>
                            </div>
                            <div class="advantage-content">
                                <h3>Home Charging</h3>
                                <p>Charge at home overnight and start each day with a full "tank".</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js"></script>
    
    <script>
        // Initialize Swiper for each vehicle
        document.addEventListener('DOMContentLoaded', function() {
            <?php 
            if ($ev_result && $ev_result->num_rows > 0) {
                $ev_result->data_seek(0);
                while ($ev = $ev_result->fetch_assoc()) {
                    echo "new Swiper('.vehicle-swiper-" . $ev['vehicle_id'] . "', {
                        slidesPerView: 1,
                        spaceBetween: 0,
                        loop: true,
                        pagination: {
                            el: '.swiper-pagination',
                            clickable: true,
                        },
                        navigation: {
                            nextEl: '.swiper-button-next',
                            prevEl: '.swiper-button-prev',
                        },
                    });";
                }
            }
            ?>
            
            // Update price range display
            updatePriceRange();
            updateYearRange();
            updateRangeDisplay();
            updateBatteryDisplay();
        });
        
        // Update price range display
        function updatePriceRange() {
            document.getElementById('minPriceValue').textContent = '₹' + 
                Number(document.getElementById('minPrice').value).toLocaleString();
            document.getElementById('maxPriceValue').textContent = '₹' + 
                Number(document.getElementById('maxPrice').value).toLocaleString();
        }
        
        // Update year range display
        function updateYearRange() {
            document.getElementById('minYearValue').textContent = 
                document.getElementById('minYear').value;
            document.getElementById('maxYearValue').textContent = 
                document.getElementById('maxYear').value;
        }
        
        // Update range display
        function updateRangeDisplay() {
            document.getElementById('minRangeValue').textContent = 
                document.getElementById('minRange').value + ' km';
            document.getElementById('maxRangeValue').textContent = 
                document.getElementById('maxRange').value + ' km';
        }
        
        // Update battery display
        function updateBatteryDisplay() {
            document.getElementById('minBatteryValue').textContent = 
                document.getElementById('minBattery').value + ' kWh';
            document.getElementById('maxBatteryValue').textContent = 
                document.getElementById('maxBattery').value + ' kWh';
        }
        
        // Reset filters
        function resetFilters() {
            // Reset the search input
            document.getElementById('search').value = '';
            
            // Reset price range sliders
            document.getElementById('minPrice').value = 100000;
            document.getElementById('maxPrice').value = 5000000;
            updatePriceRange();
            
            // Reset year range sliders
            document.getElementById('minYear').value = 2000;
            document.getElementById('maxYear').value = 2024;
            updateYearRange();
            
            // Reset range sliders
            document.getElementById('minRange').value = 100;
            document.getElementById('maxRange').value = 800;
            updateRangeDisplay();
            
            // Reset battery sliders
            document.getElementById('minBattery').value = 20;
            document.getElementById('maxBattery').value = 120;
            updateBatteryDisplay();
            
            // Reset brand checkboxes
            const checkboxes = document.querySelectorAll('input[name="brands[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Submit form to apply reset filters
            window.location.href = 'browse_ev.php';
        }
        
        // Add event listeners
        document.getElementById('minPrice').addEventListener('input', updatePriceRange);
        document.getElementById('maxPrice').addEventListener('input', updatePriceRange);
        document.getElementById('minYear').addEventListener('input', updateYearRange);
        document.getElementById('maxYear').addEventListener('input', updateYearRange);
        document.getElementById('minRange').addEventListener('input', updateRangeDisplay);
        document.getElementById('maxRange').addEventListener('input', updateRangeDisplay);
        document.getElementById('minBattery').addEventListener('input', updateBatteryDisplay);
        document.getElementById('maxBattery').addEventListener('input', updateBatteryDisplay);
    </script>
</body>
</html> 