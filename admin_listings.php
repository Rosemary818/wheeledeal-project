<?php
session_start();
require_once 'db_connect.php';

// Simple admin authentication
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

// Get the user's role from the database
$user_id = $_SESSION['user_id'];
$admin_check = $conn->query("SELECT role FROM tbl_users WHERE user_id = $user_id");
$is_admin = false;

if ($admin_check && $admin_check->num_rows > 0) {
    $user_data = $admin_check->fetch_assoc();
    $is_admin = ($user_data['role'] == 'admin');
}

if (!$is_admin) {
    header("Location: login.php");
    exit;
}

// Fetch all vehicles
$vehicles_query = "SELECT * FROM tbl_vehicles ORDER BY created_at DESC";
$vehicles_result = $conn->query($vehicles_query);

// Handle AJAX request for vehicle details
if(isset($_GET['fetch_vehicle']) && isset($_GET['id'])) {
    $vehicle_id = intval($_GET['id']);
    $vehicle_data = array();
    
    // Fetch vehicle details
    $vehicle_query = "SELECT * FROM tbl_vehicles WHERE vehicle_id = $vehicle_id";
    $vehicle_result = $conn->query($vehicle_query);
    
    if($vehicle_result && $vehicle_result->num_rows > 0) {
        $vehicle = $vehicle_result->fetch_assoc();
        
        // Check if vehicle has been sold
        $sold_query = "SELECT 1 FROM tbl_transactions WHERE vehicle_id = $vehicle_id AND status = 'Completed' LIMIT 1";
        $sold_result = $conn->query($sold_query);
        $is_sold = ($sold_result && $sold_result->num_rows > 0);
        
        // Fetch photos
        $photos_query = "SELECT * FROM tbl_photos WHERE vehicle_id = $vehicle_id";
        $photos_result = $conn->query($photos_query);
        $photos = array();
        
        if($photos_result && $photos_result->num_rows > 0) {
            while($photo = $photos_result->fetch_assoc()) {
                if(!empty($photo['photo_file_name'])) {
                    $image_path = $photo['photo_file_name'];
                    
                    // Check if file exists locally
                    if (!file_exists($image_path)) {
                        // Try various paths
                        $folder_path = dirname($photo['photo_file_path']);
                        if (file_exists($folder_path.'/'.$photo['photo_file_name'])) {
                            $image_path = $folder_path.'/'.$photo['photo_file_name'];
                        } 
                        else if (file_exists($photo['photo_file_path'])) {
                            $image_path = $photo['photo_file_path'];
                        }
                        else {
                            $image_path = 'images/placeholder-car.jpg';
                        }
                    }
                    
                    $photos[] = $image_path;
                }
            }
        }
        
        // Fetch seller info
        $seller_query = "SELECT * FROM tbl_users WHERE user_id = {$vehicle['seller_id']}";
        $seller_result = $conn->query($seller_query);
        $seller = ($seller_result && $seller_result->num_rows > 0) ? $seller_result->fetch_assoc() : null;
        
        $vehicle_data = array(
            'vehicle' => $vehicle,
            'photos' => $photos,
            'seller' => $seller,
            'is_sold' => $is_sold
        );
        
        header('Content-Type: application/json');
        echo json_encode($vehicle_data);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'Vehicle not found'));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Listings - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            display: flex;
            background-color: #f5f7fa;
        }
        
        .sidebar {
            width: 126px;
            height: 100vh;
            background-color: #2c3e50;
            color: white;
            position: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 20px;
        }
        
        .sidebar-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .sidebar-logo img {
            width: 60px;
            height: 60px;
        }
        
        .sidebar-logo h2 {
            font-size: 14px;
            text-align: center;
            margin-top: 5px;
        }
        
        .nav-menu {
            list-style: none;
            width: 100%;
        }
        
        .nav-menu li {
            margin-bottom: 5px;
        }
        
        .nav-menu a {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #b8c7ce;
            text-decoration: none;
            padding: 15px 0;
            transition: all 0.3s;
            font-size: 12px;
        }
        
        .nav-menu a:hover, .nav-menu a.active {
            background-color: #1a2530;
            color: white;
        }
        
        .nav-menu i {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .main-content {
            margin-left: 126px;
            padding: 20px;
            width: calc(100% - 126px);
        }
        
        .header {
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
        }
        
        .listings-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .car-card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 400px;
        }
        
        .car-image {
            width: 100%;
            height: 220px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            overflow: hidden;
            position: relative;
            background-color: #f9f9f9;
        }
        
        .car-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
        }
        
        /* EV Badge Style */
        .ev-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            display: flex;
            align-items: center;
            z-index: 2;
        }
        
        .ev-badge i {
            margin-right: 5px;
        }
        
        /* Sold Badge Style - matching EV badge style but red */
        .sold-badge {
            position: absolute;
            top: 10px;
            right: 10px; /* Position on the right side instead of beneath EV badge */
            background-color: #e53935; /* Red color */
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            display: flex;
            align-items: center;
            z-index: 2;
        }
        
        .sold-badge i {
            margin-right: 5px;
        }
        
        /* Large badge for detail view */
        .sold-badge-large {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: #e53935;
            color: white;
            padding: 8px 15px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            z-index: 10;
        }
        
        .sold-badge-large i {
            margin-right: 8px;
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 5px;
            font-size: 10px;
            text-align: center;
            position: absolute;
            bottom: 0;
            width: 100%;
        }
        
        .car-details {
            padding: 15px;
            flex-grow: 1;
        }
        
        .car-title {
            font-weight: bold;
            font-size: 14px;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .car-info {
            display: grid;
            grid-template-columns: auto auto;
            gap: 5px 10px;
            font-size: 12px;
        }
        
        .car-info-label {
            color: #777;
            font-weight: normal;
        }
        
        .car-info-value {
            text-align: right;
            color: #333;
        }
        
        .price {
            color: #4CAF50 !important;
            font-weight: bold;
        }
        
        .view-more {
            display: block;
            width: 100%;
            padding: 10px;
            text-align: center;
            background-color: #ff5722;
            color: white;
            text-decoration: none;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .view-more:hover {
            background-color: #e64a19;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5vh auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 80%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: modalopen 0.4s;
        }
        
        @keyframes modalopen {
            from {opacity: 0; transform: translateY(-60px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .close-modal {
            position: absolute;
            top: 10px;
            right: 20px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #555;
        }
        
        .vehicle-detail-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .vehicle-detail-title {
            font-size: 24px;
            color: #333;
        }
        
        .vehicle-price {
            font-size: 24px;
            color: #4CAF50;
            font-weight: bold;
        }
        
        .vehicle-detail-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .vehicle-gallery {
            position: relative;
            height: 300px;
            background: #f9f9f9;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .gallery-main-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .vehicle-thumbnails {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        
        .vehicle-thumbnail {
            width: 60px;
            height: 60px;
            border-radius: 5px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.2s;
        }
        
        .vehicle-thumbnail.active {
            border-color: #ff5722;
        }
        
        .vehicle-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-item {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .info-label {
            color: #777;
            font-size: 14px;
        }
        
        .info-value {
            color: #333;
            font-weight: bold;
            font-size: 16px;
        }
        
        .vehicle-description {
            grid-column: span 2;
            margin-top: 20px;
        }
        
        .vehicle-description h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .vehicle-description p {
            line-height: 1.6;
            color: #555;
        }
        
        .seller-info {
            grid-column: span 2;
            margin-top: 20px;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }
        
        .seller-info h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .seller-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            font-size: 18px;
            color: #777;
        }
        
        .loading i {
            animation: spin 1s infinite linear;
            margin-right: 10px;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="images/logo3.png" alt="WheeledDeal Logo">
            <h2>WheeledDeal<br>Admin</h2>
        </div>
        <ul class="nav-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="admin_listings.php" class="active"><i class="fas fa-car"></i> Listings</a></li>
            <li><a href="admin_transactions.php"><i class="fas fa-money-bill-wave"></i> Transactions</a></li>
            <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Car Listings</h1>
        </div>
        
        <div class="listings-grid">
            <?php
            if ($vehicles_result && $vehicles_result->num_rows > 0) {
                while ($vehicle = $vehicles_result->fetch_assoc()) {
                    // Get the first photo for this vehicle
                    $photo_query = "SELECT * FROM tbl_photos WHERE vehicle_id = {$vehicle['vehicle_id']} LIMIT 1";
                    $photo_result = $conn->query($photo_query);
                    
                    // Set default image path
                    $image_path = 'images/placeholder-car.jpg';
                    $image_error = '';
                    
                    // If photo exists, use it
                    if ($photo_result && $photo_result->num_rows > 0) {
                        $photo = $photo_result->fetch_assoc();
                        
                        // Special fix for your database - just use the file directly
                        if (!empty($photo['photo_file_name'])) {
                            // For your data structure, simply using the filename directly works best
                            $image_path = $photo['photo_file_name'];
                            
                            // Check if file exists locally
                            if (!file_exists($image_path)) {
                                // Try direct path from the filenames in your DB - your file paths look clean
                                $folder_path = dirname($photo['photo_file_path']);
                                if (file_exists($folder_path.'/'.$photo['photo_file_name'])) {
                                    $image_path = $folder_path.'/'.$photo['photo_file_name'];
                                } 
                                // Try using the path directly
                                else if (file_exists($photo['photo_file_path'])) {
                                    $image_path = $photo['photo_file_path'];
                                }
                                // If none of these work, consider it missing
                                else {
                                    $image_error = "Image not found. Please check file paths.";
                                    $image_path = 'images/placeholder-car.jpg';
                                }
                            }
                        }
                    }
                    
                    // Check if vehicle has been sold (has a completed transaction)
                    $sold_query = "SELECT 1 FROM tbl_transactions WHERE vehicle_id = {$vehicle['vehicle_id']} AND status = 'Completed' LIMIT 1";
                    $sold_result = $conn->query($sold_query);
                    $is_sold = ($sold_result && $sold_result->num_rows > 0);
                    
                    // Format vehicle title and price
                    $title = $vehicle['year'] . ' ' . $vehicle['brand'] . ' ' . $vehicle['model'];
                    $price = "₹" . number_format($vehicle['price']);
                    
                    // Check if this is an electric vehicle based on vehicle_type column
                    $isElectric = false;
                    if (isset($vehicle['vehicle_type']) && strtolower($vehicle['vehicle_type']) == 'ev') {
                        $isElectric = true;
                    }
                    
                    echo "<div class='car-card'>
                        <div class='car-image'>
                            " . ($isElectric ? "<div class='ev-badge'><i class='fas fa-bolt'></i> Electric Vehicle</div>" : "") . "
                            " . ($is_sold ? "<div class='sold-badge'><i class='fas fa-check-circle'></i> Sold</div>" : "") . "
                            <img src='{$image_path}' alt='{$title}' onerror=\"this.onerror=null; this.src='images/placeholder-car.jpg';\">
                            " . ($image_error ? "<div class='error-message'>{$image_error}</div>" : "") . "
                        </div>
                        <div class='car-details'>
                            <div class='car-title'>{$title}</div>
                            <div class='car-info'>
                                <div class='car-info-label'>Price:</div>
                                <div class='car-info-value price'>{$price}</div>
                                
                                <div class='car-info-label'>Color:</div>
                                <div class='car-info-value'>{$vehicle['color']}</div>";
                                
                                // Only show fuel type for non-electric vehicles
                                if (!$isElectric) {
                                    echo "
                                    <div class='car-info-label'>Fuel Type:</div>
                                    <div class='car-info-value'>{$vehicle['fuel_type']}</div>";
                                }
                                
                                echo "
                                <div class='car-info-label'>Transmission:</div>
                                <div class='car-info-value'>{$vehicle['transmission']}</div>
                                
                                <div class='car-info-label'>Status:</div>
                                <div class='car-info-value'>" . ($is_sold ? "Sold" : $vehicle['status']) . "</div>
                            </div>
                        </div>
                        <button class='view-more' data-vehicle-id='{$vehicle['vehicle_id']}'>View Details</button>
                    </div>";
                }
            } else {
                echo "<div style='grid-column: span 3; text-align: center; padding: 30px;'>No vehicles found</div>";
            }
            ?>
        </div>
    </div>
    
    <!-- Vehicle Details Modal -->
    <div id="vehicleModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div id="vehicleDetailContent">
                <div class="loading">
                    <i class="fas fa-spinner"></i> Loading vehicle details...
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('vehicleModal');
        const modalContent = document.getElementById('vehicleDetailContent');
        const closeBtn = document.querySelector('.close-modal');
        const viewButtons = document.querySelectorAll('.view-more');
        
        // Close modal when clicking the X
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Handle view details button clicks
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const vehicleId = this.getAttribute('data-vehicle-id');
                openVehicleDetails(vehicleId);
            });
        });
        
        function openVehicleDetails(vehicleId) {
            // Show modal with loading state
            modal.style.display = 'block';
            modalContent.innerHTML = '<div class="loading"><i class="fas fa-spinner"></i> Loading vehicle details...</div>';
            
            // Fetch vehicle details
            fetch(`admin_listings.php?fetch_vehicle=1&id=${vehicleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalContent.innerHTML = `<div class="error-message">${data.error}</div>`;
                        return;
                    }
                    
                    const vehicle = data.vehicle;
                    const photos = data.photos;
                    const seller = data.seller;
                    const is_sold = data.is_sold;
                    
                    // Check if this is an electric vehicle
                    const isElectric = vehicle.vehicle_type && vehicle.vehicle_type.toLowerCase() === 'ev';
                    
                    // Format price and title
                    const title = `${vehicle.year} ${vehicle.brand} ${vehicle.model}`;
                    const price = "₹" + new Intl.NumberFormat('en-IN').format(vehicle.price);
                    
                    // Build vehicle info HTML
                    let html = `
                        <div class="vehicle-detail-header">
                            <h2 class="vehicle-detail-title">${title}</h2>
                            <div class="vehicle-price">${price}</div>
                        </div>
                        <div class="vehicle-detail-content">
                            <div class="vehicle-gallery">
                                <img src="${photos[0] || 'images/placeholder-car.jpg'}" alt="${title}" class="gallery-main-image">
                                ${is_sold ? '<div class="sold-badge-large"><i class="fas fa-check-circle"></i> Sold</div>' : ''}
                            </div>
                            
                            <div class="vehicle-thumbnails">`;
                    
                    // Add thumbnails
                    photos.forEach((photo, index) => {
                        html += `<img src="${photo}" class="vehicle-thumbnail ${index === 0 ? 'active' : ''}" 
                                      onclick="changeMainImage(this.src)" alt="Vehicle photo ${index + 1}">`;
                    });
                    
                    html += `</div>
                            
                            <div class="vehicle-info-grid">
                                <div class="info-item">
                                    <div class="info-label">Brand</div>
                                    <div class="info-value">${vehicle.brand}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Model</div>
                                    <div class="info-value">${vehicle.model}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Year</div>
                                    <div class="info-value">${vehicle.year}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Mileage</div>
                                    <div class="info-value">${vehicle.mileage} km</div>
                                </div>`;
                    
                    // Only show fuel type for non-electric vehicles
                    if (!isElectric) {
                        html += `
                            <div class="info-item">
                                <div class="info-label">Fuel Type</div>
                                <div class="info-value">${vehicle.fuel_type}</div>
                            </div>`;
                    }
                    
                    html += `
                                <div class="info-item">
                                    <div class="info-label">Transmission</div>
                                    <div class="info-value">${vehicle.transmission}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Color</div>
                                    <div class="info-value">${vehicle.color}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Status</div>
                                    <div class="info-value">${vehicle.status}</div>
                                </div>`;
                                
                    // Add electric vehicle badge if it's an EV
                    if (isElectric) {
                        html += `
                        <div class="info-item">
                            <div class="info-label">Vehicle Type</div>
                            <div class="info-value"><span class="ev-badge-small"><i class="fas fa-bolt"></i> Electric</span></div>
                        </div>`;
                    }
                    
                    html += `    
                                <div class="vehicle-description">
                                    <h3>Description</h3>
                                    <p>${vehicle.description || 'No description available.'}</p>
                                </div>
                                
                                <div class="seller-info">
                                    <h3>Seller Information</h3>
                                    <div class="seller-info-grid">
                                        <div class="info-item">
                                            <div class="info-label">Name</div>
                                            <div class="info-value">${seller.name}</div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Email</div>
                                            <div class="info-value">${seller.email}</div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Phone</div>
                                            <div class="info-value">${seller.phone}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    
                    modalContent.innerHTML = html;
                })
                .catch(error => {
                    modalContent.innerHTML = `<div class="error-message">Error fetching vehicle details: ${error.message}</div>`;
                });
        }
        
        // Function to change the main image when clicking thumbnails
        window.changeMainImage = function(src) {
            const mainImage = document.querySelector('.gallery-main-image');
            mainImage.src = src;
            
            // Update active thumbnail
            const thumbnails = document.querySelectorAll('.vehicle-thumbnail');
            thumbnails.forEach(thumb => {
                if (thumb.src === src) {
                    thumb.classList.add('active');
                } else {
                    thumb.classList.remove('active');
                }
            });
        };
    });
    </script>
</body>
</html>