<?php
session_start();
include 'db_connect.php'; // Database connection file

// At the top of your file, after session_start()
$where_conditions = [];
$params = [];
$types = '';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Store user_id in a variable for use throughout the file
$user_id = $_SESSION['user_id'];

// Handle search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_conditions[] = "(v.model LIKE ? OR v.brand LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
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

// Handle fuel type filter
if (isset($_GET['fuel_types']) && !empty($_GET['fuel_types'])) {
    $fuel_placeholders = str_repeat('?,', count($_GET['fuel_types']) - 1) . '?';
    $where_conditions[] = "v.fuel_type IN ($fuel_placeholders)";
    foreach ($_GET['fuel_types'] as $fuel_type) {
        $params[] = $fuel_type;
        $types .= 's';
    }
}

// Handle transmission type filter
if (isset($_GET['transmission_types']) && !empty($_GET['transmission_types'])) {
    $transmission_placeholders = str_repeat('?,', count($_GET['transmission_types']) - 1) . '?';
    $where_conditions[] = "v.transmission IN ($transmission_placeholders)";
    foreach ($_GET['transmission_types'] as $transmission) {
        $params[] = $transmission;
        $types .= 's';
    }
}

// Handle mileage filter
if (isset($_GET['min_mileage']) && isset($_GET['max_mileage'])) {
    $where_conditions[] = "v.mileage BETWEEN ? AND ?";
    $params[] = (int)$_GET['min_mileage'];
    $params[] = (int)$_GET['max_mileage'];
    $types .= 'ii';
}

// Handle color filter
if (isset($_GET['colors']) && !empty($_GET['colors'])) {
    $color_placeholders = str_repeat('?,', count($_GET['colors']) - 1) . '?';
    $where_conditions[] = "v.color IN ($color_placeholders)";
    foreach ($_GET['colors'] as $color) {
        $params[] = $color;
        $types .= 's';
    }
}

// Always add the base conditions
$where_conditions[] = "v.vehicle_type = 'ICE'";
$where_conditions[] = "v.seller_id != ?";
$params[] = $_SESSION['user_id'];
$types .= 'i';

// Handle sorting
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$order_by = "ORDER BY ";
switch ($sort_order) {
    case 'price_low':
        $order_by .= "v.price ASC";
        break;
    case 'price_high':
        $order_by .= "v.price DESC";
        break;
    case 'year_new':
        $order_by .= "v.year DESC";
        break;
    case 'year_old':
        $order_by .= "v.year ASC";
        break;
    default:
        $order_by .= "v.created_at DESC";
}

// Build the WHERE clause
$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Build the final query - updated to exclude vehicles with completed transactions
$vehicles_query = "SELECT v.*, 
                  CASE WHEN w.wishlist_id IS NOT NULL THEN 1 ELSE 0 END as is_wishlisted 
                  FROM tbl_vehicles v 
                  LEFT JOIN tbl_wishlist w ON v.vehicle_id = w.vehicle_id AND w.user_id = ? 
                  LEFT JOIN tbl_transactions t ON v.vehicle_id = t.vehicle_id AND LOWER(t.status) = 'completed'
                  $where_clause 
                  AND t.transaction_id IS NULL
                  $order_by";

// Add user_id for wishlist join to the beginning of params array
array_unshift($params, $_SESSION['user_id']);
$types = 'i' . $types;

// Debug the query and parameters
// echo "Query: $vehicles_query <br>";
// echo "Types: $types <br>";
// echo "Params: " . print_r($params, true) . "<br>";

// Prepare and execute the query with error handling
$stmt = $conn->prepare($vehicles_query);

// Check if prepare was successful
if ($stmt === false) {
    echo "Error in prepare statement: " . $conn->error;
    exit(); // Stop execution to avoid the fatal error
}

// Now it's safe to bind parameters
try {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $vehicles_result = $stmt->get_result();
} catch (Exception $e) {
    echo "Error in execution: " . $e->getMessage();
    exit();
}

// Fetch all photos separately to avoid complex joins
$vehicle_photos = [];
if ($vehicles_result->num_rows > 0) {
    $temp_vehicles = $vehicles_result->fetch_all(MYSQLI_ASSOC);
    
    // Get vehicle IDs
    $vehicle_ids = array_column($temp_vehicles, 'vehicle_id');
    
    if (!empty($vehicle_ids)) {
        $ids_str = implode(',', $vehicle_ids);
        $photos_query = "SELECT * FROM tbl_photos WHERE vehicle_id IN ($ids_str) AND category = 'exterior' ORDER BY vehicle_id, photo_id";
        $photos_result = $conn->query($photos_query);
        
        if ($photos_result && $photos_result->num_rows > 0) {
            while ($photo = $photos_result->fetch_assoc()) {
                $vehicle_photos[$photo['vehicle_id']][] = $photo;
            }
        }
    }
    
    // Reset vehicles result
    $vehicles_result->data_seek(0);
}

// Check if the query executed successfully
if (!$vehicles_result) {
    die("Error fetching vehicles: " . $conn->error);
}

// Function to get photos for a specific vehicle
function getVehiclePhotos($conn, $vehicle_id) {
    $photo_sql = "SELECT photo_file_path FROM vehicle_photos WHERE vehicle_id = ? LIMIT 1";
    $photo_stmt = $conn->prepare($photo_sql);
    $photo_stmt->bind_param("i", $vehicle_id);
    $photo_stmt->execute();
    $photos_result = $photo_stmt->get_result();
    
    $photos = [];
    while ($photo = $photos_result->fetch_assoc()) {
        $photos[] = $photo['photo_file_path'];
    }
    
    return $photos;
}

// Update the brand filter query
$brand_sql = "SELECT DISTINCT brand 
              FROM tbl_vehicles 
              WHERE vehicle_type = 'ICE' 
              AND seller_id != ? 
              AND status = 'Active'
              ORDER BY brand";

$brand_stmt = $conn->prepare($brand_sql);
$brand_stmt->bind_param("i", $user_id);
$brand_stmt->execute();
$brand_result = $brand_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Listings - WheeleDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Swiper CSS for image slider -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css" />
</head>
<body>
    <header>
        <div class="logo">
            <img src="images/logo3.png" alt="Logo">
            <h1>WheeledDeal</h1>
        </div>

        <div class="search-container">
            <form action="" method="GET" id="searchForm">
                <input type="text" name="search" placeholder="Search vehicles..." 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <nav>
            <div class="icons">
                <!-- <?php if (isset($_SESSION['name'])): ?>
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
                <?php endif; ?> -->
                <div class="nav-links">
                    <a href="index.php">Back to Home</a>
                    <a href="my_wishlist.php">My Wishlist</a>
                    <a href="purchase_history.php">Purchase History</a>
                    <a href="profile.php">My Profile</a>
                    <a href="view_test_drives.php">My Test Drives</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
            <form action="switch_role.php" method="POST">
                <button type="submit" name="role" value="seller">Switch to Selling</button>
            </form>
        </nav>
    </header>

    <!-- Header Section with Available Cars and Browse EV button -->
    <div class="header-section">
        <h2 class="section-title">Available Cars</h2>
        <a href="browse_ev.php" class="browse-ev-btn">
            <i class="fas fa-bolt"></i> Browse Electric Vehicles
        </a>
    </div>

    <div class="container">
        <div class="content-wrapper">
            <!-- Sidebar Filters -->
            <div class="sidebar">
                <form id="filterForm" action="" method="GET">
                    <!-- Budget Filter -->
                    <div class="filter-box">
                        <h6>Budget:</h6>
                        <input type="range" class="form-range" min="100000" max="5000000" 
                               id="minPrice" name="min_price" 
                               value="<?php echo isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : '100000'; ?>">
                        <input type="range" class="form-range" min="100000" max="5000000" 
                               id="maxPrice" name="max_price" 
                               value="<?php echo isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : '5000000'; ?>">
                        <div class="range-values">
                            <span id="minPriceValue">₹1,00,000</span>
                            <span id="maxPriceValue">₹50,00,000</span>
                        </div>
                    </div>

                    <!-- Brand Filter -->
                    <div class="filter-box">
                        <h6>Brand:</h6>
                        <?php
                        while ($brand = $brand_result->fetch_assoc()) {
                            $checked = isset($_GET['brands']) && in_array($brand['brand'], $_GET['brands']) ? 'checked' : '';
                            echo "<label><input type='checkbox' name='brands[]' value='" . htmlspecialchars($brand['brand']) . "' $checked> " . htmlspecialchars($brand['brand']) . "</label>";
                        }
                        ?>
                    </div>

                    <!-- Year Filter -->
                    <div class="filter-box">
                        <h6>Year:</h6>
                        <input type="range" class="form-range" min="2000" max="2024" 
                               id="minYear" name="min_year" 
                               value="<?php echo isset($_GET['min_year']) ? htmlspecialchars($_GET['min_year']) : '2000'; ?>">
                        <input type="range" class="form-range" min="2000" max="2024" 
                               id="maxYear" name="max_year" 
                               value="<?php echo isset($_GET['max_year']) ? htmlspecialchars($_GET['max_year']) : '2024'; ?>">
                        <div class="range-values">
                            <span id="minYearValue">2000</span>
                            <span id="maxYearValue">2024</span>
                        </div>
                    </div>

                    <!-- Fuel Type Filter -->
                    <div class="filter-box">
                        <h6>Fuel Type:</h6>
                        <?php
                        $fuel_types = ['Petrol', 'Diesel', 'CNG', 'Electric'];
                        foreach ($fuel_types as $type) {
                            $checked = isset($_GET['fuel_types']) && in_array($type, $_GET['fuel_types']) ? 'checked' : '';
                            echo "<label><input type='checkbox' name='fuel_types[]' value='$type' $checked> $type</label>";
                        }
                        ?>
                    </div>

                    <!-- Transmission Type Filter -->
                    <div class="filter-box">
                        <h6>Transmission Type:</h6>
                        <?php
                        $transmission_types = ['Manual', 'Automatic', 'AMT'];
                        foreach ($transmission_types as $type) {
                            $checked = isset($_GET['transmission_types']) && in_array($type, $_GET['transmission_types']) ? 'checked' : '';
                            echo "<label><input type='checkbox' name='transmission_types[]' value='$type' $checked> $type</label>";
                        }
                        ?>
                    </div>

                    <!-- Mileage Filter -->
                    <div class="filter-box">
                        <h6>Mileage (km/l):</h6>
                        <input type="range" class="form-range" min="0" max="50" 
                               id="minMileage" name="min_mileage" 
                               value="<?php echo isset($_GET['min_mileage']) ? htmlspecialchars($_GET['min_mileage']) : '0'; ?>">
                        <input type="range" class="form-range" min="0" max="50" 
                               id="maxMileage" name="max_mileage" 
                               value="<?php echo isset($_GET['max_mileage']) ? htmlspecialchars($_GET['max_mileage']) : '50'; ?>">
                        <div class="range-values">
                            <span id="minMileageValue">0 km/l</span>
                            <span id="maxMileageValue">50 km/l</span>
                        </div>
                    </div>

                    <!-- Color Filter -->
                    <div class="filter-box">
                        <h6>Color:</h6>
                        <?php
                        $colors = ['White', 'Black', 'Silver', 'Red', 'Blue', 'Grey', 'Brown', 'Other'];
                        foreach ($colors as $color) {
                            $checked = isset($_GET['colors']) && in_array($color, $_GET['colors']) ? 'checked' : '';
                            echo "<label class='color-option'><input type='checkbox' name='colors[]' value='$color' $checked> 
                                  <span class='color-box $color'></span> $color</label>";
                        }
                        ?>
                    </div>

                    <!-- Sort By -->
                    <div class="filter-box">
                        <h6>Sort By:</h6>
                        <select name="sort" class="sort-select">
                            <option value="newest" <?php echo (!isset($_GET['sort']) || $_GET['sort'] === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="price_low" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="year_new" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'year_new') ? 'selected' : ''; ?>>Year: Newest First</option>
                            <option value="year_old" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'year_old') ? 'selected' : ''; ?>>Year: Oldest First</option>
                        </select>
                    </div>

                    <button type="submit" class="apply-filters-btn">Apply Filters</button>
                    <button type="button" class="reset-filters-btn" onclick="resetFilters()">Reset Filters</button>
                </form>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="vehicles-grid">
                    <?php if ($vehicles_result->num_rows > 0): ?>
                        <?php while ($vehicle = $vehicles_result->fetch_assoc()): ?>
                            <div class="vehicle-card" data-vehicle-id="<?php echo $vehicle['vehicle_id']; ?>">
                                <!-- Wishlist Icon -->
                                <div class="wishlist-icon">
                                    <form action="update_wishlist.php" method="POST" target="wishlistFrame" style="margin:0; padding:0;">
                                        <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['vehicle_id']; ?>">
                                        <input type="hidden" name="action" value="<?php echo $vehicle['is_wishlisted'] ? 'remove' : 'add'; ?>">
                                        
                                        <button type="submit" style="background:none; border:none; padding:0; cursor:pointer;">
                                            <i class="<?php echo $vehicle['is_wishlisted'] ? 'fas' : 'far'; ?> fa-heart wishlist-heart" 
                                               data-vehicle-id="<?php echo $vehicle['vehicle_id']; ?>"
                                               style="color: <?php echo $vehicle['is_wishlisted'] ? '#ff5722' : '#777'; ?>; font-size:18px;"></i>
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- Vehicle Image Carousel -->
                                <div class="vehicle-image-container">
                                    <button class="nav-arrow left" onclick="prevImage(<?php echo $vehicle['vehicle_id']; ?>)">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    
                                    <div class="image-wrapper" id="image-wrapper-<?php echo $vehicle['vehicle_id']; ?>">
                                        <?php 
                                        // Get photos for this vehicle
                                        $photos = isset($vehicle_photos[$vehicle['vehicle_id']]) ? $vehicle_photos[$vehicle['vehicle_id']] : [];
                                        $photo_count = count($photos);
                                        ?>
                                        
                                        <?php if ($photo_count > 0): ?>
                                            <?php foreach($photos as $index => $photo): ?>
                                                <img src="<?php echo htmlspecialchars($photo['photo_file_path']); ?>" 
                                                    alt="<?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model'] . ' Photo ' . ($index+1)); ?>" 
                                                    class="vehicle-img <?php echo $index === 0 ? 'active' : ''; ?>"
                                                    data-index="<?php echo $index; ?>"
                                                    data-vehicle="<?php echo $vehicle['vehicle_id']; ?>">
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <img src="images/default-vehicle.jpg" alt="Default Vehicle Image" class="vehicle-img active" data-index="0" data-vehicle="<?php echo $vehicle['vehicle_id']; ?>">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($photo_count > 0): ?>
                                    <div class="photo-indicators">
                                        <?php for($i=0; $i<$photo_count; $i++): ?>
                                            <span class="indicator <?php echo $i === 0 ? 'active' : ''; ?>" 
                                                  data-index="<?php echo $i; ?>"
                                                  onclick="showImage(<?php echo $vehicle['vehicle_id']; ?>, <?php echo $i; ?>)"></span>
                                        <?php endfor; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <button class="nav-arrow right" onclick="nextImage(<?php echo $vehicle['vehicle_id']; ?>)">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                                
                                <!-- Vehicle Details -->
                                <div class="vehicle-details">
                                    <h3 class="vehicle-title"><?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['brand'] . ' ' . $vehicle['model']); ?></h3>
                                    <p class="vehicle-price">₹<?php echo number_format($vehicle['price']); ?></p>
                                    
                                    <div class="vehicle-actions">
                                        <?php 
                                        // Determine if vehicle is electric
                                        $is_ev = isset($vehicle['vehicle_type']) && $vehicle['vehicle_type'] === 'Electric' ? 1 : 0;
                                        ?>
                                        <a href="test_drive.php?vehicle_id=<?php echo $vehicle['vehicle_id']; ?>&is_ev=<?php echo $is_ev; ?>" 
                                           class="request-test-drive-btn">Request Test Drive</a>
                                        <a href="vehicle_details.php?id=<?php echo $vehicle['vehicle_id']; ?>" 
                                           class="view-details-btn">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-vehicles-found">
                            <p>No vehicles found matching your criteria.</p>
                            <a href="buyer_dashboard.php" class="reset-filters-btn">Reset all filters</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
        gap: 8px;
    }

    .logo img {
        height: 30px;
        width: auto;
    }

    .logo h1 {
        font-size: 20px;
        margin: 0;
        color: #333;
    }

    .search-container {
        display: flex;
        align-items: center;
        width: 40%;
        margin: 0 auto;
    }

    .search-container form {
        width: 100%;
        display: flex;
        align-items: center;
        position: relative;
    }

    .search-container input {
        width: 100%;
        padding: 12px 20px;
        border: 2px solid #ddd;
        border-radius: 30px;
        outline: none;
        font-size: 16px;
        transition: all 0.3s ease;
    }

    .search-container input:focus {
        border-color: #ff5722;
        box-shadow: 0 0 8px rgba(255,87,34,0.2);
    }

    .search-container button {
        position: absolute;
        right: 15px;
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
    }

    .search-container button:hover {
        color: #ff5722;
    }

    nav {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .icons {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
    }

    .user-name {
        color: #666;
        font-size: 14px;
    }

    .nav-links {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .nav-links a {
        text-decoration: none;
        color: #333;
        font-size: 14px;
        transition: color 0.3s;
    }

    .nav-links a:hover {
        color: #ff5722;
    }

    nav button {
        padding: 8px 16px;
        background-color: #ff5722;
        color: white;
        border: none;
        border-radius: 20px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.3s;
    }

    nav button:hover {
        background-color: #e64a19;
    }

    .container {
        max-width: 1400px;
        margin: 20px auto;
        padding: 0 20px;
    }

    .content-wrapper {
        display: flex;
        gap: 30px;
    }

    .section-title {
        text-align: center;
        color: #333;
        margin-bottom: 30px;
    }

    .vehicles-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 40px;
    }

    .vehicle-card {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        position: relative;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .vehicle-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    }

    .wishlist-icon {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 10;
        background: white;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        transition: all 0.2s ease;
    }

    .wishlist-icon:hover {
        transform: scale(1.1);
    }

    .wishlist-icon .fas.fa-heart {
        color: #ff5722 !important; /* Orange color */
    }

    .wishlist-icon .far.fa-heart {
        color: #777 !important; /* Gray color */
    }

    .vehicle-image-container {
        position: relative;
        height: 200px;
        background: #f5f5f5;
    }

    .image-wrapper {
        width: 100%;
        height: 100%;
        position: relative;
    }

    .vehicle-img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: none;
    }

    .vehicle-img.active {
        display: block;
    }

    .nav-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 30px;
        height: 30px;
        background: rgba(255,255,255,0.8);
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 5;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .nav-arrow.left {
        left: 10px;
    }

    .nav-arrow.right {
        right: 10px;
    }

    .nav-arrow i {
        color: #333;
        font-size: 12px;
    }

    .photo-indicators {
        position: absolute;
        bottom: 10px;
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        gap: 5px;
        z-index: 5;
    }

    .indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: rgba(255,255,255,0.5);
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .indicator.active {
        background: white;
    }

    .vehicle-details {
        padding: 15px;
        text-align: center;
    }
    
    .vehicle-title {
        font-size: 16px;
        font-weight: 600;
        color: #273240;
        margin: 0 0 10px;
        line-height: 1.3;
        text-align: center;
    }
    
    .vehicle-price {
        font-size: 18px;
        font-weight: 600;
        color: #ff5722;
        margin: 0 0 15px;
        text-align: center;
    }

    .vehicle-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .request-test-drive-btn, .view-details-btn {
        width: 100%;
        padding: 10px 0;
        border-radius: 4px;
        text-align: center;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .request-test-drive-btn {
        background: #ff5722;
        color: white;
    }

    .request-test-drive-btn:hover {
        background: #e64a19;
    }

    .view-details-btn {
        background: #f1f1f1;
        color: #333;
        border: 1px solid #ddd;
    }

    .view-details-btn:hover {
        background: #e5e5e5;
    }

    .no-vehicles-found {
        grid-column: 1 / -1;
        text-align: center;
        padding: 30px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .no-vehicles-found p {
        margin-bottom: 15px;
        color: #666;
    }

    .reset-filters-btn {
        display: inline-block;
        background: #ff5722;
        color: white;
        padding: 10px 20px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
    }

    @media (max-width: 992px) {
        .vehicles-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .vehicles-grid {
            grid-template-columns: 1fr;
        }
    }

    .filter-box {
        background: white;
        padding: 10px;  /* Reduced from 15px */
        margin-bottom: 10px;  /* Reduced from 15px */
        border-radius: 6px;  /* Slightly reduced */
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);  /* Lighter shadow */
    }

    .filter-box h6 {
        margin-bottom: 6px;  /* Reduced from 10px */
        color: #333;
        font-weight: 600;
        font-size: 14px;  /* Added smaller font size */
    }

    .range-values {
        display: flex;
        justify-content: space-between;
        margin-top: 3px;  /* Reduced from 5px */
        font-size: 11px;  /* Reduced from 12px */
        color: #666;
    }

    input[type="range"] {
        width: 100%;
        margin: 6px 0;  /* Reduced from 10px */
        height: 5px;  /* Added to make slider thinner */
    }

    input[type="text"] {
        width: 100%;
        padding: 6px;  /* Reduced from 8px */
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 8px;  /* Reduced from 10px */
        font-size: 13px;  /* Added smaller font size */
    }

    /* Make checkboxes more compact */
    .filter-box label {
        display: block;
        font-size: 13px;
        margin-bottom: 3px;  /* Reduced vertical spacing */
        color: #555;
    }

    /* Adjust color options spacing */
    .color-option {
        display: flex;
        align-items: center;
        margin-bottom: 3px;  /* Reduced from 5px */
    }

    .color-box {
        width: 16px;  /* Reduced from 20px */
        height: 16px;  /* Reduced from 20px */
        margin-right: 6px;  /* Reduced from 8px */
        border: 1px solid #ddd;
        border-radius: 3px;  /* Reduced from 4px */
    }

    /* Adjust sort select */
    .sort-select {
        width: 100%;
        padding: 6px;  /* Reduced from 8px */
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 3px;  /* Reduced from 5px */
        font-size: 13px;  /* Added smaller font size */
    }

    /* Make filter buttons smaller */
    .apply-filters-btn, .reset-filters-btn {
        width: 100%;
        padding: 8px;  /* Reduced from 10px */
        margin-top: 8px;  /* Reduced from 10px */
        border: none;
        border-radius: 4px;  /* Reduced from 5px */
        cursor: pointer;
        font-weight: 500;
        font-size: 13px;  /* Added smaller font size */
    }

    /* Adjust sidebar width if needed */
    .sidebar {
        width: 220px;  /* You can adjust this to your preference */
        flex-shrink: 0;
    }

    .range-values {
        display: flex;
        justify-content: space-between;
        margin-top: 5px;
        font-size: 12px;
        color: #666;
    }

    input[type="range"] {
        width: 100%;
        margin: 10px 0;
    }

    input[type="text"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-bottom: 10px;
    }

    .main-content {
        flex-grow: 1;
    }

    .apply-filters-btn, .reset-filters-btn {
        width: 100%;
        padding: 10px;
        margin-top: 10px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
    }

    .apply-filters-btn {
        background-color: #2c3e50;
        color: white;
    }

    .reset-filters-btn {
        background-color: #95a5a6;
        color: white;
    }

    .apply-filters-btn:hover {
        background-color: #34495e;
    }

    .reset-filters-btn:hover {
        background-color: #7f8c8d;
    }

    .ev-top-section {
        width: 100%;
        padding: 15px 30px;
        background: transparent;
    }

    .ev-container {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        justify-content: flex-end;  /* Aligns content to the right */
    }

    .ev-button {
        display: inline-flex;
        align-items: center;
        padding: 12px 25px;
        background: linear-gradient(135deg, #4CAF50, #2196F3);
        color: white;
        text-decoration: none;
        border-radius: 50px;
        font-size: 16px;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .ev-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .ev-button i {
        margin-right: 8px;
        font-size: 18px;
    }

    .color-option {
        display: flex;
        align-items: center;
        margin-bottom: 5px;
    }

    .color-box {
        width: 20px;
        height: 20px;
        margin-right: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .color-box.White { background-color: #ffffff; }
    .color-box.Black { background-color: #000000; }
    .color-box.Silver { background-color: #c0c0c0; }
    .color-box.Red { background-color: #ff0000; }
    .color-box.Blue { background-color: #0000ff; }
    .color-box.Grey { background-color: #808080; }
    .color-box.Brown { background-color: #8b4513; }
    .color-box.Other { background: linear-gradient(45deg, #ff0000, #00ff00, #0000ff); }

    .sort-select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 5px;
    }

    .specifications {
        margin: 10px 0;
        padding: 10px;
        background: #f8f8f8;
        border-radius: 5px;
    }

    .specifications h4 {
        margin: 0 0 5px 0;
        font-size: 14px;
        color: #333;
    }

    .specifications ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .specifications li {
        font-size: 13px;
        color: #666;
        margin: 3px 0;
    }

    /* Header section to align title and EV button */
    .header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 0 15px;
    }
    
    .section-title {
        font-size: 28px;
        color: #333;
        margin: 0;
        font-weight: 500;
    }
    
    .browse-ev-btn {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        background: linear-gradient(135deg, #4CAF50, #2196F3);
        color: white;
        text-decoration: none;
        border-radius: 50px;
        font-size: 16px;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    
    .browse-ev-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
    }
    
    .browse-ev-btn i {
        margin-right: 8px;
        font-size: 18px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .header-section {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        
        .browse-ev-btn {
            align-self: flex-start;
        }
    }
</style>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js"></script>

<script>
    // Initialize Swiper for each vehicle
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all vehicle swipers
        <?php 
        if ($vehicles_result->num_rows > 0) {
            // Reset result pointer to beginning
            $vehicles_result->data_seek(0);
            while ($vehicle = $vehicles_result->fetch_assoc()) {
                echo "new Swiper('.vehicle-swiper-" . $vehicle['vehicle_id'] . "', {
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

    // Reset filters
    function resetFilters() {
        document.getElementById('filterForm').reset();
        document.getElementById('minPrice').value = 100000;
        document.getElementById('maxPrice').value = 5000000;
        document.getElementById('minYear').value = 2000;
        document.getElementById('maxYear').value = 2024;
        updatePriceRange();
        updateYearRange();
        document.getElementById('filterForm').submit();
    }

    // Add event listeners
    document.getElementById('minPrice').addEventListener('input', updatePriceRange);
    document.getElementById('maxPrice').addEventListener('input', updatePriceRange);
    document.getElementById('minYear').addEventListener('input', updateYearRange);
    document.getElementById('maxYear').addEventListener('input', updateYearRange);

    // Initialize ranges
    updatePriceRange();
    updateYearRange();

    // Mileage range slider
    const minMileage = document.getElementById('minMileage');
    const maxMileage = document.getElementById('maxMileage');
    const minMileageValue = document.getElementById('minMileageValue');
    const maxMileageValue = document.getElementById('maxMileageValue');

    minMileage.addEventListener('input', function() {
        minMileageValue.textContent = this.value + ' km/l';
        if (parseInt(maxMileage.value) < parseInt(this.value)) {
            maxMileage.value = this.value;
            maxMileageValue.textContent = this.value + ' km/l';
        }
    });

    maxMileage.addEventListener('input', function() {
        maxMileageValue.textContent = this.value + ' km/l';
        if (parseInt(minMileage.value) > parseInt(this.value)) {
            minMileage.value = this.value;
            minMileageValue.textContent = this.value + ' km/l';
        }
    });

    // Auto-submit on sort change
    document.querySelector('.sort-select').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });

    function toggleWishlist(vehicleId, element) {
        // Get the heart icon
        const heartIcon = element.querySelector('i');
        
        // Toggle appearance immediately for responsive UI
        const isCurrentlyWishlisted = heartIcon.classList.contains('fas');
        if (isCurrentlyWishlisted) {
            // Remove from wishlist
            heartIcon.classList.remove('fas');
            heartIcon.classList.add('far');
            heartIcon.style.color = '#777';
            var action = 'remove';
        } else {
            // Add to wishlist
            heartIcon.classList.remove('far');
            heartIcon.classList.add('fas');
            heartIcon.style.color = '#ff5722';
            var action = 'add';
        }
        
        // Create AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'update_wishlist.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        // Send request
        xhr.send('vehicle_id=' + vehicleId + '&action=' + action);
        
        // Prevent navigation
        return false;
    }

    // Add this to your existing JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Handle image navigation
        const leftButtons = document.querySelectorAll('.nav-arrow.left');
        const rightButtons = document.querySelectorAll('.nav-arrow.right');
        
        // If you have multiple images per vehicle
        leftButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                // Add code to show previous image
                // This requires additional backend work to retrieve all images
            });
        });
        
        rightButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                // Add code to show next image
                // This requires additional backend work to retrieve all images
            });
        });
    });

    // Image navigation functions
    function showImage(vehicleId, index) {
        const container = document.getElementById(`vehicle-${vehicleId}`);
        const images = container.querySelectorAll('.vehicle-img');
        const indicators = container.querySelectorAll('.indicator');
        
        // Hide all images
        images.forEach(img => {
            img.classList.remove('active');
        });
        
        // Show the selected image
        if (images[index]) {
            images[index].classList.add('active');
        }
        
        // Update indicators
        indicators.forEach(ind => {
            ind.classList.remove('active');
        });
        
        if (indicators[index]) {
            indicators[index].classList.add('active');
        }
    }

    function nextImage(vehicleId) {
        const container = document.getElementById(`vehicle-${vehicleId}`);
        const images = container.querySelectorAll('.vehicle-img');
        const activeImage = container.querySelector('.vehicle-img.active');
        
        if (!activeImage) return;
        
        let currentIndex = parseInt(activeImage.getAttribute('data-index'));
        let nextIndex = (currentIndex + 1) % images.length;
        
        showImage(vehicleId, nextIndex);
    }

    function prevImage(vehicleId) {
        const container = document.getElementById(`vehicle-${vehicleId}`);
        const images = container.querySelectorAll('.vehicle-img');
        const activeImage = container.querySelector('.vehicle-img.active');
        
        if (!activeImage) return;
        
        let currentIndex = parseInt(activeImage.getAttribute('data-index'));
        let prevIndex = (currentIndex - 1 + images.length) % images.length;
        
        showImage(vehicleId, prevIndex);
    }
</script>

<!-- Add this script at the end of your file, before closing body tag -->
<script>
// Image Navigation Functions
function nextImage(vehicleId) {
    const wrapper = document.getElementById(`image-wrapper-${vehicleId}`);
    if (!wrapper) return;
    
    const images = wrapper.querySelectorAll('.vehicle-img');
    const indicators = document.querySelectorAll(`.vehicle-card[data-vehicle-id="${vehicleId}"] .indicator`);
    
    let activeIndex = 0;
    images.forEach((img, index) => {
        if (img.classList.contains('active')) {
            activeIndex = index;
            img.classList.remove('active');
        }
    });
    
    // Calculate next index with wrap-around
    const nextIndex = (activeIndex + 1) % images.length;
    images[nextIndex].classList.add('active');
    
    // Update indicators if they exist
    if (indicators.length > 0) {
        indicators.forEach(ind => ind.classList.remove('active'));
        indicators[nextIndex].classList.add('active');
    }
}

function prevImage(vehicleId) {
    const wrapper = document.getElementById(`image-wrapper-${vehicleId}`);
    if (!wrapper) return;
    
    const images = wrapper.querySelectorAll('.vehicle-img');
    const indicators = document.querySelectorAll(`.vehicle-card[data-vehicle-id="${vehicleId}"] .indicator`);
    
    let activeIndex = 0;
    images.forEach((img, index) => {
        if (img.classList.contains('active')) {
            activeIndex = index;
            img.classList.remove('active');
        }
    });
    
    // Calculate previous index with wrap-around
    const prevIndex = (activeIndex - 1 + images.length) % images.length;
    images[prevIndex].classList.add('active');
    
    // Update indicators if they exist
    if (indicators.length > 0) {
        indicators.forEach(ind => ind.classList.remove('active'));
        indicators[prevIndex].classList.add('active');
    }
}

function showImage(vehicleId, index) {
    const wrapper = document.getElementById(`image-wrapper-${vehicleId}`);
    if (!wrapper) return;
    
    const images = wrapper.querySelectorAll('.vehicle-img');
    const indicators = document.querySelectorAll(`.vehicle-card[data-vehicle-id="${vehicleId}"] .indicator`);
    
    // Hide all images
    images.forEach(img => img.classList.remove('active'));
    
    // Show selected image
    if (images[index]) {
        images[index].classList.add('active');
    }
    
    // Update indicators
    if (indicators.length > 0) {
        indicators.forEach(ind => ind.classList.remove('active'));
        if (indicators[index]) {
            indicators[index].classList.add('active');
        }
    }
}

// Wishlist Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to all wishlist icons
    document.querySelectorAll('.wishlist-icon').forEach(icon => {
        icon.addEventListener('click', function() {
            const vehicleId = this.closest('.vehicle-card').dataset.vehicleId;
            const heart = this.querySelector('i');
            
            // Toggle heart icon
            const isAdding = heart.classList.contains('far');
            if (isAdding) {
                heart.classList.remove('far');
                heart.classList.add('fas');
            } else {
                heart.classList.remove('fas');
                heart.classList.add('far');
            }
            
            // Send AJAX request to update wishlist
            fetch('update_wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `vehicle_id=${vehicleId}&action=${isAdding ? 'add' : 'remove'}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Optional: Show a success message
                    console.log('Wishlist updated successfully');
                } else {
                    // If error, revert the heart icon
                    if (isAdding) {
                        heart.classList.remove('fas');
                        heart.classList.add('far');
                    } else {
                        heart.classList.remove('far');
                        heart.classList.add('fas');
                    }
                    console.error('Failed to update wishlist:', data.message);
                }
            })
            .catch(error => {
                console.error('Error updating wishlist:', error);
                // Revert the heart icon on error
                if (isAdding) {
                    heart.classList.remove('fas');
                    heart.classList.add('far');
                } else {
                    heart.classList.remove('far');
                    heart.classList.add('fas');
                }
            });
        });
    });
});
</script>

<!-- Add this hidden iframe at the end of your body -->
<iframe id="wishlistFrame" name="wishlistFrame" style="display:none;"></iframe>

</body>
</html>