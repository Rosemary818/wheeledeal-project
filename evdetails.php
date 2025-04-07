<?php
session_start();
require_once 'db_connect.php';

// Check if ev_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: browse_ev.php');
    exit;
}

$vehicle_id = $_GET['id'];

// Debug connection
if (!$conn) {
    die("Connection is null. Check your db.php file.");
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query for vehicle details with EV information
$sql = "SELECT v.*, e.*, u.name as seller_name, u.email as seller_email, u.phone as seller_phone 
        FROM tbl_vehicles v
        LEFT JOIN tbl_ev e ON v.vehicle_id = e.vehicle_id
        LEFT JOIN tbl_users u ON v.seller_id = u.user_id
        WHERE v.vehicle_id = ?";

$stmt = $conn->prepare($sql);

// Check if prepare statement was successful
if ($stmt === false) {
    die("Prepare failed for query '$sql': " . $conn->error);
}

// If we get here, the prepare was successful
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: browse_ev.php');
    exit;
}

$vehicle = $result->fetch_assoc();

// Fetch photos from tbl_photos table
$photos_sql = "SELECT * FROM tbl_photos WHERE vehicle_id = ? ORDER BY photo_id";
$photos_stmt = $conn->prepare($photos_sql);

// Set default photo in case query fails
$photos = [];
$default_photo = [
    'photo_file_path' => 'images/default_car.jpg',
    'photo_file_name' => 'Default Vehicle Image'
];

if ($photos_stmt === false) {
    error_log("Prepare failed for photos query: " . $conn->error);
    $photos[] = $default_photo;
} else {
    $photos_stmt->bind_param("i", $vehicle_id);
    $photos_stmt->execute();
    $photos_result = $photos_stmt->get_result();
    
    if ($photos_result && $photos_result->num_rows > 0) {
        while ($photo = $photos_result->fetch_assoc()) {
            $photos[] = $photo;
        }
    } else {
        // No photos found, use default
        $photos[] = $default_photo;
    }
}

// Handle test drive request
if (isset($_POST['request_test_drive']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $preferred_date = $_POST['preferred_date'] ?? NULL;
    $preferred_time = $_POST['preferred_time'] ?? NULL;
    
    $test_drive_sql = "INSERT INTO test_drives (user_id, vehicle_id, preferred_date, preferred_time, status) 
                      VALUES (?, ?, ?, ?, 'Pending')";
    $test_drive_stmt = $conn->prepare($test_drive_sql);
    $test_drive_stmt->bind_param("iiss", $user_id, $vehicle_id, $preferred_date, $preferred_time);
    $test_drive_stmt->execute();
    
    // Redirect to avoid form resubmission
    header("Location: evdetails.php?id=$vehicle_id&test_drive=requested");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $vehicle['brand'] . ' ' . $vehicle['model']; ?> - WheeleDeal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #343a40;
            background-color: #f5f5f5;
        }
        
        /* Header styles - Matching your design */
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
            height: 30px;
            margin-right: 5px;
        }
        
        .brand-name {
            font-size: 18px;
            font-weight: 700;
            color: #000000;
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
        
        /* Vehicle detail page styles */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .vehicle-detail-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            padding: 20px;
            overflow: hidden;
        }
        
        /* Image gallery styles */
        .vehicle-gallery {
            position: relative;
            margin-bottom: 20px;
        }
        
        .gallery-container {
            position: relative;
            height: 300px;
            border-radius: 8px;
            overflow: hidden;
        }image.png
        
        .gallery-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .gallery-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.8);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 5;
        }
        
        .gallery-prev {
            left: 10px;
        }
        
        .gallery-next {
            right: 10px;
        }
        
        .thumbnail-nav {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        
        .thumbnail {
            width: 80px;
            height: 60px;
            border-radius: 4px;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
            border: 2px solid transparent;
        }
        
        .thumbnail:hover, .thumbnail.active {
            opacity: 1;
            border-color: #FF6B35;
        }
        
        /* Vehicle header */
        .vehicle-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .vehicle-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .vehicle-price {
            font-size: 24px;
            font-weight: 700;
            color: #FF6B35;
            margin-bottom: 5px;
        }
        
        /* Vehicle info section */
        .vehicle-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 50%;
            color: #FF6B35;
            font-size: 18px;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 12px;
            color: #6c757d;
        }
        
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #212529;
        }
        
        /* Specifications section */
        .vehicle-specs {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #FF6B35;
            padding-bottom: 5px;
            display: inline-block;
        }
        
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .specs-table tr {
            border-bottom: 1px solid #eee;
        }
        
        .specs-table tr:last-child {
            border-bottom: none;
        }
        
        .specs-table td {
            padding: 10px 5px;
            font-size: 14px;
        }
        
        .specs-table td:first-child {
            color: #FF6B35;
            font-weight: 500;
            width: 40%;
        }
        
        /* Vehicle description */
        .vehicle-description {
            margin-bottom: 30px;
        }
        
        .description-text {
            font-size: 14px;
            line-height: 1.6;
            color: #495057;
        }
        
        /* Seller section */
        .seller-info {
            margin-bottom: 30px;
        }
        
        .seller-details {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 10px;
        }
        
        .seller-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .seller-avatar i {
            font-size: 24px;
            color: #adb5bd;
        }
        
        .seller-contact {
            flex: 1;
        }
        
        .seller-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .seller-data {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 14px;
        }
        
        .seller-data a {
            color: #0d6efd;
            text-decoration: none;
        }
        
        /* Action buttons */
        .vehicle-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .action-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        
        .test-drive-btn {
            background-color: #FF6B35;
            color: white;
            flex: 1;
        }
        
        .test-drive-btn:hover {
            background-color: #e55a2b;
        }
        
        .wishlist-btn {
            background-color: #f8f9fa;
            color: #495057;
            border: 1px solid #ddd;
            flex: 1;
        }
        
        .wishlist-btn:hover, .wishlist-btn.active {
            background-color: #f8f9fa;
            color: #FF6B35;
            border-color: #FF6B35;
        }
        
        .wishlist-btn.active i {
            color: #FF6B35;
        }
        
        /* Modal for test drive */
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-submit {
            width: 100%;
            padding: 10px;
            background-color: #FF6B35;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .form-submit:hover {
            background-color: #e55a2b;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .vehicle-header {
                flex-direction: column;
            }
            
            .vehicle-info {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .vehicle-actions {
                flex-direction: column;
            }
        }

        .photo-tabs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .photo-tab {
            padding: 10px 20px;
            background: #f8f9fa;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 14px;
            color: #333;
            transition: all 0.3s ease;
        }

        .photo-tab.active {
            background: #4CAF50;
            color: white;
        }

        .photo-tab i {
            margin-right: 8px;
        }

        .photo-section {
            display: none;
        }

        .photo-section.active {
            display: block;
        }

        /* Updated main photo styles to maintain aspect ratio */
        .main-photo-container {
            width: 100%;
            max-height: 600px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f8f9fa;
            margin-bottom: 20px;
            border-radius: 8px;
            overflow: hidden;
        }

        .main-photo {
            max-width: 100%;
            max-height: 600px;
            width: auto;
            height: auto;
            object-fit: contain; /* Changed from cover to contain */
        }

        .photo-thumbnails {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 10px 0;
        }

        /* Updated thumbnail styles to maintain aspect ratio */
        .thumbnail-container {
            min-width: 120px;
            height: 90px;
            background: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 4px;
        }

        .photo-thumbnail {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain; /* Changed from cover to contain */
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .photo-thumbnail:hover {
            transform: scale(1.05);
        }

        .photo-thumbnail.active {
            border: 2px solid #4CAF50;
        }

        /* Hide scrollbar but keep functionality */
        .photo-thumbnails {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }

        .photo-thumbnails::-webkit-scrollbar {
            display: none;  /* Chrome, Safari and Opera */
        }

        .payment-btn {
            background-color: #28a745;
            color: white;
            flex: 1;
        }

        .payment-btn:hover {
            background-color: #218838;
        }

        .payment-summary {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        .payment-summary h3 {
            margin-bottom: 10px;
            color: #333;
            font-size: 16px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
            color: #666;
        }

        .summary-row.total {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-weight: bold;
            color: #333;
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
            <a href="browse_ev.php" class="nav-link">Browse EVs</a>
            <a href="my_wishlist.php" class="nav-link">My Wishlist</a>
            <a href="profile.php" class="nav-link">My Profile</a>
            <a href="view_test_drives.php" class="nav-link">My Test Drives</a>
            <a href="seller_dashboard.php" class="switch-button">Switch to Selling</a>
        </div>
    </header>

    <div class="container">
        <div class="vehicle-detail-container">
            <!-- Photo Tabs and Gallery - Moved to top of content -->
            <div class="photo-tabs">
                <button class="photo-tab active" data-category="exterior">
                    <i class="fas fa-car"></i> Exterior
                </button>
                <button class="photo-tab" data-category="interior">
                    <i class="fas fa-car-side"></i> Interior
                </button>
                <button class="photo-tab" data-category="features">
                    <i class="fas fa-star"></i> Features
                </button>
                <button class="photo-tab" data-category="imperfections">
                    <i class="fas fa-exclamation-circle"></i> Imperfections
                </button>
                <button class="photo-tab" data-category="highlights">
                    <i class="fas fa-lightbulb"></i> Highlights
                </button>
                <button class="photo-tab" data-category="tyres">
                    <i class="fas fa-circle"></i> Tyres
                </button>
            </div>

            <?php
            // Fetch photos for each category
            $categories = ['exterior', 'interior', 'features', 'imperfections', 'highlights', 'tyres'];

            foreach ($categories as $category) {
                $photos_sql = "SELECT * FROM tbl_photos WHERE vehicle_id = ? AND category = ?";
                $photos_stmt = $conn->prepare($photos_sql);
                $photos_stmt->bind_param("is", $vehicle_id, $category);
                $photos_stmt->execute();
                $photos_result = $photos_stmt->get_result();
                
                $is_active = $category === 'exterior' ? ' active' : '';
                ?>
                <div class="photo-section<?php echo $is_active; ?>" id="<?php echo $category; ?>-photos">
                    <?php if ($photos_result->num_rows > 0): ?>
                        <?php 
                        $photos = [];
                        while ($photo = $photos_result->fetch_assoc()) {
                            $photos[] = $photo;
                        }
                        ?>
                        <div class="main-photo-container">
                            <img src="<?php echo htmlspecialchars($photos[0]['photo_file_path']); ?>" 
                                 alt="<?php echo ucfirst($category); ?> View" 
                                 class="main-photo" 
                                 id="<?php echo $category; ?>-main-photo">
                        </div>
                        
                        <div class="photo-thumbnails">
                            <?php foreach ($photos as $index => $photo): ?>
                                <div class="thumbnail-container">
                                    <img src="<?php echo htmlspecialchars($photo['photo_file_path']); ?>" 
                                         alt="<?php echo ucfirst($category); ?> Thumbnail" 
                                         class="photo-thumbnail<?php echo $index === 0 ? ' active' : ''; ?>"
                                         onclick="updateMainPhoto('<?php echo $category; ?>', '<?php echo htmlspecialchars($photo['photo_file_path']); ?>', this)">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No <?php echo ucfirst($category); ?> photos available</p>
                    <?php endif; ?>
                </div>
            <?php } ?>

            <!-- Vehicle Header -->
            <div class="vehicle-header">
                <div>
                    <h1 class="vehicle-title"><?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['brand'] . ' ' . $vehicle['model']); ?></h1>
                    <p><?php echo isset($vehicle['year']) ? htmlspecialchars($vehicle['year']) : ''; ?></p>
                </div>
                <div>
                    <p class="vehicle-price">₹<?php echo number_format($vehicle['price']); ?></p>
                </div>
            </div>

            <!-- Vehicle Info Section -->
            <div class="vehicle-info">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Year</div>
                        <div class="info-value"><?php echo htmlspecialchars($vehicle['year']); ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-road"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Range</div>
                        <div class="info-value"><?php echo isset($vehicle['range_km']) ? htmlspecialchars($vehicle['range_km']) . ' km' : '0 km'; ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Kilometers</div>
                        <div class="info-value"><?php echo htmlspecialchars($vehicle['range_km']); ?> km</div>
                    </div>
                </div>
            </div>

            <!-- Specifications Section -->
            <div class="vehicle-specs">
                <h2 class="section-title">Specifications & Features</h2>
                <table class="specs-table">
                   
                        <td>Seating Capacity</td>
                        <td><?php echo isset($vehicle['seating_capacity']) ? htmlspecialchars($vehicle['seating_capacity']) . ' Persons' : 'N/A'; ?></td>
                    </tr>
                    
                    <tr>
                        <td>Range</td>
                        <td><?php echo isset($vehicle['range_km']) ? htmlspecialchars($vehicle['range_km']) . ' km' : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Battery Capacity</td>
                        <td><?php echo isset($vehicle['battery_capacity']) ? htmlspecialchars($vehicle['battery_capacity']) . ' kWh' : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Charging Time (AC)</td>
                        <td><?php echo isset($vehicle['charging_time_ac']) ? htmlspecialchars($vehicle['charging_time_ac']) . ' hours' : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Charging Time (DC)</td>
                        <td><?php echo isset($vehicle['charging_time_dc']) ? htmlspecialchars($vehicle['charging_time_dc']) . ' hours' : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Electric Motor</td>
                        <td><?php echo isset($vehicle['electric_motor']) ? htmlspecialchars($vehicle['electric_motor']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Front Suspension</td>
                        <td><?php echo isset($vehicle['front_suspension']) ? htmlspecialchars($vehicle['front_suspension']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Rear Suspension</td>
                        <td><?php echo isset($vehicle['rear_suspension']) ? htmlspecialchars($vehicle['rear_suspension']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Front Brake Type</td>
                        <td><?php echo isset($vehicle['front_brake_type']) ? htmlspecialchars($vehicle['front_brake_type']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Rear Brake Type</td>
                        <td><?php echo isset($vehicle['rear_brake_type']) ? htmlspecialchars($vehicle['rear_brake_type']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Minimum Turning Radius</td>
                        <td><?php echo isset($vehicle['minimum_turning_radius']) ? htmlspecialchars($vehicle['minimum_turning_radius']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Wheels</td>
                        <td><?php echo isset($vehicle['wheels']) ? htmlspecialchars($vehicle['wheels']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Spare Wheel</td>
                        <td><?php echo isset($vehicle['spare_wheel']) ? htmlspecialchars($vehicle['spare_wheel']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Front Tyres</td>
                        <td><?php echo isset($vehicle['front_tyres']) ? htmlspecialchars($vehicle['front_tyres']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Rear Tyres</td>
                        <td><?php echo isset($vehicle['rear_tyres']) ? htmlspecialchars($vehicle['rear_tyres']) : 'N/A'; ?></td>
                    </tr>
                </table>
            </div>

            <!-- Vehicle Description -->
            <div class="vehicle-description">
                <h2 class="section-title">Vehicle Description</h2>
                <p class="description-text">
                    <?php 
                    if (!empty($vehicle['description'])) {
                        echo nl2br(htmlspecialchars($vehicle['description']));
                    } else {
                        echo "The " . htmlspecialchars($vehicle['brand']) . " " . htmlspecialchars($vehicle['model']) . " is a stylish and fuel-efficient electric vehicle designed for eco-conscious drivers. Powered by a " . htmlspecialchars($vehicle['battery_capacity']) . " kWh battery, it provides a range of " . htmlspecialchars($vehicle['range_km']) . " km on a single charge, paired with an efficient electric motor transmission. Features include regenerative braking, multiple driving modes, and a user-friendly infotainment system.";
                    }
                    ?>
                </p>
            </div>

            <!-- Seller Information -->
            <div class="seller-info">
                <h2 class="section-title">Seller Information</h2>
                <div class="seller-details">
                    <div class="seller-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="seller-contact">
                        <div class="seller-name"><?php echo htmlspecialchars($vehicle['seller_name'] ?? 'N/A'); ?></div>
                        <div class="seller-data">
                            <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($vehicle['seller_phone'] ?? 'N/A'); ?></span>
                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($vehicle['seller_email'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="vehicle-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="test_drive.php?vehicle_id=<?php echo $vehicle_id; ?>" class="action-btn test-drive-btn">
                        <i class="fas fa-id-card"></i> Request Test Drive
                    </a>
                    <a href="transaction.php?vehicle_id=<?php echo $vehicle_id; ?>&amount=<?php echo $vehicle['price']; ?>" class="action-btn payment-btn">
                        <i class="fas fa-credit-card"></i> Make Payment
                    </a>
                <?php else: ?>
                    <a href="login.php?redirect=evdetails.php?id=<?php echo $vehicle_id; ?>" class="primary-button">Login to Continue</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Test Drive Modal -->
    <!-- <div id="test-drive-modal" class="modal">...</div> -->

    <script>
        // Test drive modal functionality
        /*
        const modal = document.getElementById("test-drive-modal");
        const btn = document.getElementById("test-drive-button");
        const span = document.getElementsByClassName("close-modal")[0];
        
        btn.onclick = function() {
            modal.style.display = "block";
        }
        
        span.onclick = function() {
            modal.style.display = "none";
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        */
        
        // Image gallery functionality
        const mainImage = document.getElementById('main-image');
        const thumbnails = document.querySelectorAll('.thumbnail');
        const prevBtn = document.querySelector('.gallery-prev');
        const nextBtn = document.querySelector('.gallery-next');
        let currentIndex = 0;
        const maxIndex = thumbnails.length - 1;
        
        // Set active thumbnail
        function setActiveThumbnail(index) {
            thumbnails.forEach(thumb => thumb.classList.remove('active'));
            thumbnails[index].classList.add('active');
            mainImage.src = thumbnails[index].src;
            currentIndex = index;
        }
        
        // Add click event to thumbnails
        thumbnails.forEach((thumbnail, index) => {
            thumbnail.addEventListener('click', () => {
                setActiveThumbnail(index);
            });
        });
        
        // Previous button
        prevBtn.addEventListener('click', () => {
            let newIndex = currentIndex - 1;
            if (newIndex < 0) newIndex = maxIndex;
            setActiveThumbnail(newIndex);
        });
        
        // Next button
        nextBtn.addEventListener('click', () => {
            let newIndex = currentIndex + 1;
            if (newIndex > maxIndex) newIndex = 0;
            setActiveThumbnail(newIndex);
        });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching
        const tabs = document.querySelectorAll('.photo-tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and sections
                tabs.forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.photo-section').forEach(section => {
                    section.classList.remove('active');
                });
                
                // Add active class to clicked tab and corresponding section
                this.classList.add('active');
                const category = this.dataset.category;
                document.getElementById(`${category}-photos`).classList.add('active');
            });
        });
    });

    // Function to update main photo when thumbnail is clicked
    function updateMainPhoto(category, src, thumbnail) {
        // Update main photo
        document.getElementById(`${category}-main-photo`).src = src;
        
        // Update thumbnail active state
        const thumbnails = thumbnail.parentElement.querySelectorAll('.photo-thumbnail');
        thumbnails.forEach(t => t.classList.remove('active'));
        thumbnail.classList.add('active');
    }
    </script>
</body>
</html>