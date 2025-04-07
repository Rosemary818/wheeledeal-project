<?php
session_start();
include 'db_connect.php';

if (!isset($_GET['id'])) {
    header('Location: buyer_dashboard.php');
    exit();
}

$vehicle_id = $_GET['id'];

// Update the SQL query to match exact tbl_vehicles structure
$sql = "SELECT 
            v.*,
            vs.length,
            vs.width,
            vs.height,
            vs.wheelbase,
            vs.ground_clearance,
            vs.kerb_weight,
            vs.seating_capacity,
            vs.boot_space,
            vs.fuel_tank_capacity,
            vs.engine,          /* Make sure engine is selected */
            vs.engine_type,     /* Make sure engine_type is selected */
            u.name as seller_name,
            u.email as seller_email,
            u.phone as seller_phone
        FROM tbl_vehicles v
        JOIN tbl_users u ON v.seller_id = u.user_id
        LEFT JOIN tbl_vehicle_specifications vs ON v.vehicle_id = vs.vehicle_id
        WHERE v.vehicle_id = ? AND v.vehicle_type != 'EV'";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

if (!$vehicle) {
    header('Location: buyer_dashboard.php');
    exit();
}

// Fetch ALL photos for this vehicle, grouped by category
$sql_photos = "SELECT * FROM tbl_photos WHERE vehicle_id = ? ORDER BY category, photo_id";
$stmt_photos = $conn->prepare($sql_photos);
$stmt_photos->bind_param("i", $vehicle_id);
$stmt_photos->execute();
$photos_result = $stmt_photos->get_result();

// Organize photos by category
$photos = [];
$categories = ['exterior', 'interior', 'features', 'imperfections', 'highlights', 'tyres'];
$category_names = [
    'exterior' => 'Exterior',
    'interior' => 'Interior',
    'features' => 'Features',
    'imperfections' => 'Imperfections',
    'highlights' => 'Highlights',
    'tyres' => 'Tyres'
];
$category_icons = [
    'exterior' => 'fa-car',
    'interior' => 'fa-chair',
    'features' => 'fa-star',
    'imperfections' => 'fa-exclamation-circle',
    'highlights' => 'fa-lightbulb',
    'tyres' => 'fa-circle'
];

foreach ($categories as $category) {
    $photos[$category] = [];
}

// Assign photos to their respective categories
while ($photo = $photos_result->fetch_assoc()) {
    $category = $photo['category'] ?: 'exterior'; // Default to exterior if no category
    $photos[$category][] = $photo;
}
$stmt_photos->close();

// Find first available photo for main display
$main_photo = null;
$active_category = 'exterior'; // Default category
foreach ($categories as $category) {
    if (!empty($photos[$category])) {
        $main_photo = $photos[$category][0]['photo_file_path'];
        $active_category = $category;
        break;
    }
}

// If no photos found, use default
if ($main_photo === null) {
    $main_photo = 'images/default_car_image.jpg';
}

// Fetch vehicle specifications
$sql_specs = "SELECT * FROM tbl_vehicle_specifications WHERE vehicle_id = ?";
$stmt_specs = $conn->prepare($sql_specs);
if ($stmt_specs === false) {
    die("Error preparing specifications statement: " . $conn->error);
}

$stmt_specs->bind_param("i", $vehicle_id);
$stmt_specs->execute();
$vehicle_specifications = $stmt_specs->get_result()->fetch_assoc();

// If no specifications found, set default values
if (!$vehicle_specifications) {
    $vehicle_specifications = [
        'length' => 'N/A',
        'width' => 'N/A',
        'height' => 'N/A',
        'wheelbase' => 'N/A',
        'ground_clearance' => 'N/A',
        'kerb_weight' => 'N/A',
        'seating_capacity' => 'N/A',
        'boot_space' => 'N/A',
        'fuel_tank_capacity' => 'N/A'
    ];
}

// Update the query for "Other Vehicles You Might Like" section to show same brand vehicles and exclude EVs
$query_other_vehicles = "SELECT v.*, p.photo_file_path
                        FROM tbl_vehicles v
                        LEFT JOIN (
                            SELECT vehicle_id, photo_file_path 
                            FROM tbl_photos 
                            WHERE category = 'exterior' 
                            GROUP BY vehicle_id
                        ) p ON v.vehicle_id = p.vehicle_id
                        WHERE v.vehicle_id != ? 
                        AND v.brand = ?
                        AND v.vehicle_type != 'EV'
                        ORDER BY RAND() 
                        LIMIT 3";

$stmt_other_vehicles = $conn->prepare($query_other_vehicles);
$stmt_other_vehicles->bind_param("is", $vehicle_id, $vehicle['brand']);
$stmt_other_vehicles->execute();
$result_other_vehicles = $stmt_other_vehicles->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['model']); ?> - WheeleDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .user-name {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px; /* Increased spacing between links */
        }

        .nav-links a {
            text-decoration: none; /* Removes underline */
            color: #333;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-links a:hover {
            color: #ff5722; /* Orange color on hover */
        }

        .nav-links a.active {
            color: #ff5722; /* Orange color for active link */
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .vehicle-details-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-areas: 
                "gallery info"
                "specs info";
            gap: 20px;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .vehicle-gallery {
            grid-area: gallery;
            margin-bottom: 10px; /* Add a little space between gallery and specs */
        }

        .vehicle-info {
            grid-area: info;
            flex: 1;
            color: black;
            margin-bottom: 1rem;
        }

        .specifications-container {
            grid-area: specs;
            margin-top: 0; /* Remove top margin to place it closer to gallery */
        }

        .specs-heading {
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
            font-weight: 600;
            border-bottom: 2px solid #ff5722;
            padding-bottom: 8px;
            display: inline-block;
        }

        .specs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .specs-table tr {
            border-bottom: 1px solid #eee;
        }

        .specs-table tr:last-child {
            border-bottom: none;
        }

        .specs-label {
            font-weight: 500;
            color: #555;
            padding: 10px 5px;
            width: 40%;
        }

        .specs-value {
            padding: 10px 5px;
            color: #333;
            background-color: #f9f9f9;
        }

        .vehicle-images {
            position: relative;
        }

        .vehicle-gallery {
            position: relative;
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .gallery-main {
            position: relative;
            height: 320px;
            background-color: #f5f5f5;
            overflow: hidden;
        }
        
        .gallery-main img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: opacity 0.3s;
        }
        
        .gallery-brand {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 10;
        }
        
        .brand-badge {
            display: inline-block;
            background-color: #000;
            color: white;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        
        .gallery-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 35px;
            height: 35px;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .gallery-arrow.prev {
            left: 15px;
        }
        
        .gallery-arrow.next {
            right: 15px;
        }
        
        .gallery-arrow:hover {
            background-color: white;
            box-shadow: 0 3px 8px rgba(0,0,0,0.3);
        }
        
        .gallery-arrow i {
            font-size: 18px;
            color: #333;
        }
        
        .gallery-categories {
            display: flex;
            justify-content: space-between;
            padding: 10px 5px;
            background-color: white;
            border-top: 1px solid #eee;
        }
        
        .category-item {
            text-align: center;
            cursor: pointer;
            padding: 5px 2px;
            width: calc(100% / 6);
            transition: all 0.2s;
        }
        
        .category-item.active {
            opacity: 1;
            color: #ff5722;
        }
        
        .category-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin: 0 auto;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f5f5f5;
            margin-bottom: 3px;
            border: 2px solid transparent;
        }
        
        .category-item.active .category-icon {
            border-color: #ff5722;
        }
        
        .category-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .category-icon i {
            font-size: 20px;
            color: #555;
        }
        
        .category-item.active .category-icon i {
            color: #ff5722;
        }
        
        .category-name {
            font-size: 10px;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .gallery-main {
                height: 250px;
            }
            
            .category-icon {
                width: 35px;
                height: 35px;
            }
        }

        .vehicle-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
            color: black;
        }

        .vehicle-subtitle {
            font-size: 1rem;
            color: #333;
            margin: 0.3rem 0;
        }

        .price-section {
            margin: 0.8rem 0;
        }

        .price {
            font-size: 1.3rem;
            font-weight: 600;
            color: black;
            margin: 0;
        }

        .key-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f8f8;
            border-radius: 10px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .detail-item i {
            color: #ff5722;
            font-size: 20px;
            width: 24px;
            text-align: center;
        }

        .detail-item span {
            color: #333;
            font-size: 16px;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .test-drive-btn {
            background-color: #f0f0f0;
            color: #333;
        }

        .buy-now-btn {
            background-color: #ff5722;
            color: white;
        }

        .buy-now-btn:hover {
            background-color: #e64a19;
            transform: translateY(-2px);
        }

        .test-drive-btn:hover {
            background-color: #e0e0e0;
            transform: translateY(-2px);
        }

        .seller-info {
            background: #f8f8f8;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .seller-info h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 22px;
        }

        .seller-info p {
            margin: 10px 0;
            color: #666;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }

        .vehicle-description {
            color: #666;
            line-height: 1.6;
            font-size: 16px;
        }

        .vehicle-description h3 {
            color: #333;
            margin: 0 0 15px 0;
            font-size: 22px;
        }

        .specifications {
            margin-top: 30px;
            background: #f8f8f8;
            padding: 20px;
            border-radius: 10px;
        }

        .specifications h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .specifications table {
            width: 100%;
            border-collapse: collapse;
        }

        .specifications th, .specifications td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .specifications th {
            background-color: #f0f0f0;
        }

        @media (min-width: 992px) {
            .key-details {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .vehicle-details-wrapper {
                grid-template-columns: 1fr;
                grid-template-areas: 
                    "gallery"
                    "specs"
                    "info";
            }

            .gallery-main {
                height: 300px;
            }

            .key-details {
                grid-template-columns: 1fr;
            }
        }

        .vehicle-details {
            margin: 20px;
        }

        .other-vehicles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 40px auto;
            padding: 0 30px;
            max-width: 1400px;
        }

        .vehicle-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .vehicle-card:hover {
            transform: translateY(-5px);
        }

        .vehicle-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .vehicle-card .card-content {
            padding: 20px;
        }

        .vehicle-card h3 {
            font-size: 1.4rem;
            margin: 15px 0;
            color: #333;
        }

        .vehicle-card .price {
            color: #ff5722;
            font-size: 1.3rem;
            font-weight: 600;
            margin: 10px 0;
        }

        .vehicle-card .view-details {
            display: inline-block;
            background-color: #ff5722;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 8px;
            margin-top: 15px;
            transition: background-color 0.3s ease;
        }

        .vehicle-card .view-details:hover {
            background-color: #34495e;
        }

        @media (max-width: 768px) {
            .other-vehicles {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                padding: 0 15px;
            }
        }

        .section-title {
            text-align: center;
            font-size: 28px;
            color: #2c3e50;
            margin: 40px 0 30px;
            font-weight: 600;
        }

        .other-vehicles-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto 40px;
            padding: 0 15px;
        }
        
        .other-vehicle-card {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .other-vehicle-image {
            width: 100%;
            height: 220px;
            background-color: #f8f8f8;
            overflow: hidden;
        }
        
        .other-vehicle-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .other-vehicle-details {
            padding: 15px;
        }
        
        .other-vehicle-title {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 10px;
            color: #333;
        }
        
        .other-vehicle-price {
            font-size: 18px;
            font-weight: 600;
            color: #ff5722;
            margin-bottom: 15px;
        }
        
        .view-details-btn {
            display: inline-block;
            background-color: #ff5722;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 400;
            font-size: 15px;
            border: none;
            cursor: pointer;
        }
        
        .no-vehicles {
            text-align: center;
            padding: 20px;
            grid-column: 1 / -1;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .other-vehicles-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .other-vehicles-container {
                grid-template-columns: 1fr;
            }
        }

        /* Specifications & Features Table Styles */
        .specifications-container {
            margin-top: 30px;
            margin-bottom: 30px;
        }
        
        .specs-heading {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 500;
            position: relative;
            padding-bottom: 8px;
            border-bottom: 2px solid #ff5722;
            display: inline-block;
        }
        
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        
        .specs-table tr {
            border-bottom: 1px solid #eee;
        }
        
        .specs-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .specs-table td {
            padding: 12px 15px;
            font-size: 16px;
            color: #333;
        }
        
        .specs-table td:first-child {
            font-weight: 500;
            color: #2c3e50;
            width: 40%;
        }
        
        .specs-table td:last-child {
            color: #555;
        }
        
        @media (max-width: 767px) {
            .specs-table td {
                padding: 10px;
                font-size: 14px;
            }
            
            .specs-heading {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="images/logo3.png" alt="Logo">
            <h1>WheeledDeal</h1>
        </div>
        <nav>
            <div class="icons">
                <!-- <?php if (isset($_SESSION['name'])): ?>
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
                <?php endif; ?> -->
                <div class="nav-links">
                    <a href="buyer_dashboard.php">Back to Dashboard</a>
                    <a href="my_wishlist.php">My Wishlist</a>
                    <a href="view_test_drives.php">My Test Drives</a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="vehicle-details-wrapper">
            <!-- Updated Vehicle Images Section -->
            <div class="vehicle-gallery">
                <div class="gallery-main" id="galleryMain">
                    <img src="<?php echo htmlspecialchars($main_photo); ?>" alt="<?php echo htmlspecialchars($vehicle['model']); ?>" id="mainImage">
                    
                    <!-- Add the arrows directly in the gallery-main div -->
                    <div class="gallery-arrow prev" onclick="prevPhoto()">
                        <i class="fas fa-chevron-left"></i>
                    </div>
                    <div class="gallery-arrow next" onclick="nextPhoto()">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    
                    <!-- Add a brand badge -->
                    <div class="gallery-brand">
                        <span class="brand-badge"><?php echo htmlspecialchars($vehicle['brand']); ?> ASSURED</span>
                    </div>
                </div>
                
                <div class="gallery-categories">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-item <?php echo ($category === $active_category) ? 'active' : ''; ?>" 
                             data-category="<?php echo $category; ?>" onclick="changeCategory('<?php echo $category; ?>')">
                            <div class="category-icon">
                                <?php if (!empty($photos[$category])): ?>
                                    <img src="<?php echo htmlspecialchars($photos[$category][0]['photo_file_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($category_names[$category]); ?>">
                                <?php else: ?>
                                    <i class="fas <?php echo $category_icons[$category]; ?>"></i>
                                <?php endif; ?>
                            </div>
                            <div class="category-name"><?php echo htmlspecialchars($category_names[$category]); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Specifications & Features Section -->
            <div class="specifications-container">
                <h2 class="specs-heading">Specifications & Features</h2>
                
                <table class="specs-table">
                    <!-- Engine from vehicle_specifications table -->
                    <?php if (!empty($vehicle['engine'])): ?>
                    <tr>
                        <td>Engine</td>
                        <td><?php echo htmlspecialchars($vehicle['engine']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Engine Type from vehicle_specifications table -->
                    <?php if (!empty($vehicle['engine_type'])): ?>
                    <tr>
                        <td>Engine Type</td>
                        <td><?php echo htmlspecialchars($vehicle['engine_type']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['max_power'])): ?>
                    <tr>
                        <td>Max Power</td>
                        <td><?php echo htmlspecialchars($vehicle['max_power']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['max_torque'])): ?>
                    <tr>
                        <td>Max Torque</td>
                        <td><?php echo htmlspecialchars($vehicle['max_torque']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['transmission'])): ?>
                    <tr>
                        <td>Transmission</td>
                        <td><?php echo htmlspecialchars($vehicle['transmission']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['length'])): ?>
                    <tr>
                        <td>Length</td>
                        <td><?php echo htmlspecialchars($vehicle['length']); ?> mm</td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['width'])): ?>
                    <tr>
                        <td>Width</td>
                        <td><?php echo htmlspecialchars($vehicle['width']); ?> mm</td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['height'])): ?>
                    <tr>
                        <td>Height</td>
                        <td><?php echo htmlspecialchars($vehicle['height']); ?> mm</td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['wheelbase'])): ?>
                    <tr>
                        <td>Wheelbase</td>
                        <td><?php echo htmlspecialchars($vehicle['wheelbase']); ?> mm</td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['ground_clearance'])): ?>
                    <tr>
                        <td>Ground Clearance</td>
                        <td><?php echo htmlspecialchars($vehicle['ground_clearance']); ?> mm</td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['kerb_weight'])): ?>
                    <tr>
                        <td>Kerb Weight</td>
                        <td><?php echo htmlspecialchars($vehicle['kerb_weight']); ?> kg</td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['seating_capacity'])): ?>
                    <tr>
                        <td>Seating Capacity</td>
                        <td><?php echo htmlspecialchars($vehicle['seating_capacity']); ?> Persons</td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['boot_space'])): ?>
                    <tr>
                        <td>Boot Space</td>
                        <td><?php echo htmlspecialchars($vehicle['boot_space']); ?> litres</td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['fuel_tank_capacity'])): ?>
                    <tr>
                        <td>Fuel Tank Capacity</td>
                        <td><?php echo htmlspecialchars($vehicle['fuel_tank_capacity']); ?> litres</td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Include other specifications as needed -->
                    
                    <?php if (!empty($vehicle['front_suspension'])): ?>
                    <tr>
                        <td>Front Suspension</td>
                        <td><?php echo htmlspecialchars($vehicle['front_suspension']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['rear_suspension'])): ?>
                    <tr>
                        <td>Rear Suspension</td>
                        <td><?php echo htmlspecialchars($vehicle['rear_suspension']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['front_brake_type'])): ?>
                    <tr>
                        <td>Front Brake Type</td>
                        <td><?php echo htmlspecialchars($vehicle['front_brake_type']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['rear_brake_type'])): ?>
                    <tr>
                        <td>Rear Brake Type</td>
                        <td><?php echo htmlspecialchars($vehicle['rear_brake_type']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['minimum_turning_radius'])): ?>
                    <tr>
                        <td>Minimum Turning Radius</td>
                        <td><?php echo htmlspecialchars($vehicle['minimum_turning_radius']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['wheels'])): ?>
                    <tr>
                        <td>Wheels</td>
                        <td><?php echo htmlspecialchars($vehicle['wheels']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['spare_wheel'])): ?>
                    <tr>
                        <td>Spare Wheel</td>
                        <td><?php echo htmlspecialchars($vehicle['spare_wheel']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['front_tyres'])): ?>
                    <tr>
                        <td>Front Tyres</td>
                        <td><?php echo htmlspecialchars($vehicle['front_tyres']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['rear_tyres'])): ?>
                    <tr>
                        <td>Rear Tyres</td>
                        <td><?php echo htmlspecialchars($vehicle['rear_tyres']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Vehicle Information Section -->
            <div class="vehicle-info">
                <h1 class="vehicle-title"><?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['model']); ?></h1>
                <div class="price-tag">₹<?php echo number_format($vehicle['price']); ?></div>

                <!-- Key Details -->
                <div class="key-details">
                    <div class="detail-item">
                        <i class="fas fa-calendar"></i>
                        <span>Year: <?php echo htmlspecialchars($vehicle['year']); ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Mileage: <?php echo htmlspecialchars($vehicle['mileage']); ?> km</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-road"></i>
                        <span>Kilometers: <?php echo htmlspecialchars($vehicle['kilometer']); ?> km</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-gas-pump"></i>
                        <span>Fuel Type: <?php echo htmlspecialchars($vehicle['fuel_type']); ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-car"></i>
                        <span>Brand: <?php echo htmlspecialchars($vehicle['brand']); ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-palette"></i>
                        <span>Color: <?php echo htmlspecialchars($vehicle['color']); ?></span>
                    </div>
                    <div class="detail-item">
                       <i class="fas fa-id-card"></i>
                       <span>Registration: <?php echo htmlspecialchars($vehicle['registration_type']); ?></span>
                   </div>
                   <div class="detail-item">
                       <i class="fas fa-users"></i>
                       <span>Owners: <?php echo htmlspecialchars($vehicle['number_of_owners']); ?></span>
                   </div>
                   <div class="detail-item">
                       <i class="fas fa-cog"></i>
                       <span>Transmission: <?php echo htmlspecialchars($vehicle['transmission']); ?></span>
                   </div>
                   <div class="detail-item">
                       <i class="fas fa-map-marker-alt"></i>
                       <span>Address: <?php echo htmlspecialchars($vehicle['Address']); ?></span>
                   </div>
                   <div class="detail-item">
                       <span class="detail-label"><i class="fas fa-shield-alt"></i> Guarantee:</span>
                       <span class="detail-value"><?php echo !empty($vehicle['guarantee']) ? htmlspecialchars($vehicle['guarantee']) : 'Not specified'; ?></span>
                   </div>
               </div>

               <!-- Action Buttons -->
               <div class="action-buttons">
                   <a href="test_drive.php?vehicle_id=<?php echo $vehicle['vehicle_id']; ?>" class="btn test-drive-btn">
                       <i class="fas fa-car"></i> Request Test Drive
                   </a>
                   <a href="transaction.php?vehicle_id=<?php echo $vehicle['vehicle_id']; ?>" class="btn buy-now-btn">
                       <i class="fas fa-shopping-cart"></i> Make Your Payment
                   </a>
               </div>

               <!-- Seller Information -->
               <div class="seller-info">
                   <h3>Seller Information</h3>
                   <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($vehicle['seller_name']); ?></p>
                   <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($vehicle['seller_phone']); ?></p>
                   <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($vehicle['seller_email']); ?></p>
               </div>

               <!-- Vehicle Description -->
               <div class="vehicle-description">
                   <h3>Vehicle Description</h3>
                   <p><?php echo nl2br(htmlspecialchars($vehicle['description'] ?? 'No description available.')); ?></p>
               </div>

               <?php if (!empty($vehicle['guarantee'])): ?>
               <div class="guarantee-badge">
                   <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($vehicle['guarantee']); ?> guarantee
               </div>
               <?php endif; ?>
           </div>
       </div>
   </div>

   <!-- Other Vehicles Section -->
   <h2 class="section-title">Other Vehicles You Might Like</h2>

   <div class="other-vehicles-container">
       <?php 
       // Updated query to exclude vehicles with completed transactions
       $other_vehicles_query = "SELECT v.*, p.photo_file_path 
                                FROM tbl_vehicles v 
                                LEFT JOIN tbl_photos p ON v.vehicle_id = p.vehicle_id AND p.is_primary = 1
                                WHERE v.vehicle_id != {$vehicle_id} 
                                AND v.status = 'Available'
                                AND v.vehicle_id NOT IN (
                                    SELECT vehicle_id FROM tbl_transactions 
                                    WHERE status = 'Completed'
                                )
                                ORDER BY v.created_at DESC 
                                LIMIT 6";
       
       $result_other_vehicles = $conn->query($other_vehicles_query);
       
       if ($result_other_vehicles && $result_other_vehicles->num_rows > 0): 
           while ($other_vehicle = $result_other_vehicles->fetch_assoc()): ?>
           <div class="other-vehicle-card">
               <div class="other-vehicle-image">
                   <img src="<?php echo htmlspecialchars($other_vehicle['photo_file_path'] ?? 'images/default_car_image.jpg'); ?>" 
                        alt="<?php echo htmlspecialchars($other_vehicle['model']); ?>">
               </div>
               <div class="other-vehicle-details">
                   <h3 class="other-vehicle-title"><?php echo htmlspecialchars($other_vehicle['model']); ?></h3>
                   <p class="other-vehicle-price">₹<?php echo number_format($other_vehicle['price']); ?></p>
                   <a href="vehicle_details.php?id=<?php echo $other_vehicle['vehicle_id']; ?>" class="view-details-btn">
                       View Details
                   </a>
               </div>
           </div>
           <?php endwhile; ?>
       <?php else: ?>
           <p class="no-vehicles">No similar vehicles found.</p>
       <?php endif; ?>
   </div>

   <script>
       // Store photos by category
       const photosByCategory = {
           <?php foreach ($categories as $category): ?>
               '<?php echo $category; ?>': [
                   <?php foreach ($photos[$category] as $photo): ?>
                       {
                           path: '<?php echo htmlspecialchars($photo['photo_file_path']); ?>',
                           id: <?php echo $photo['photo_id']; ?>
                       },
                   <?php endforeach; ?>
               ],
           <?php endforeach; ?>
       };
       
       let currentCategory = '<?php echo $active_category; ?>';
       let currentPhotoIndex = 0;
       
       function changeCategory(category) {
           // Update active category
           document.querySelectorAll('.category-item').forEach(item => {
               item.classList.remove('active');
           });
           document.querySelector(`.category-item[data-category="${category}"]`).classList.add('active');
           
           // Change to the new category
           currentCategory = category;
           currentPhotoIndex = 0;
           
           // Update the main image
           updateMainImage();
       }
       
       function nextPhoto() {
           const photos = photosByCategory[currentCategory];
           if (!photos || photos.length === 0) return;
           
           currentPhotoIndex = (currentPhotoIndex + 1) % photos.length;
           updateMainImage();
       }
       
       function prevPhoto() {
           const photos = photosByCategory[currentCategory];
           if (!photos || photos.length === 0) return;
           
           currentPhotoIndex = (currentPhotoIndex - 1 + photos.length) % photos.length;
           updateMainImage();
       }
       
       function updateMainImage() {
           const photos = photosByCategory[currentCategory];
           if (!photos || photos.length === 0) {
               document.getElementById('mainImage').src = 'images/default_car_image.jpg';
               return;
           }
           
           document.getElementById('mainImage').src = photos[currentPhotoIndex].path;
       }
       
       // Handle keyboard navigation
       document.addEventListener('keydown', function(e) {
           if (e.key === 'ArrowRight') {
               nextPhoto();
           } else if (e.key === 'ArrowLeft') {
               prevPhoto();
           }
       });
       
       // Swipe handling for mobile
       let touchStartX = 0;
       let touchEndX = 0;
       
       document.getElementById('galleryMain').addEventListener('touchstart', function(e) {
           touchStartX = e.changedTouches[0].screenX;
       }, false);
       
       document.getElementById('galleryMain').addEventListener('touchend', function(e) {
           touchEndX = e.changedTouches[0].screenX;
           handleSwipe();
       }, false);
       
       function handleSwipe() {
           if (touchEndX < touchStartX) {
               // Swipe left - next
               nextPhoto();
           } else if (touchEndX > touchStartX) {
               // Swipe right - previous
               prevPhoto();
           }
       }
   </script>
</body>
</html>