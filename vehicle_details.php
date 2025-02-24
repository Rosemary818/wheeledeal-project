<?php
session_start();
include 'db.php';

if (!isset($_GET['id'])) {
    header('Location: buyer_dashboard.php');
    exit();
}

$vehicle_id = $_GET['id'];

// Fetch vehicle details with seller information
$sql = "SELECT v.*, vp.photo_file_path, u.name as seller_name, u.email as seller_email, u.number as seller_phone
        FROM vehicle v
        LEFT JOIN vehicle_photos vp ON v.vehicle_id = vp.vehicle_id
        LEFT JOIN automobileusers u ON v.seller_id = u.user_id
        WHERE v.vehicle_id = ?";

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['model']); ?> - WheeleDeal</title>
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
            <!-- Vehicle Images Section -->
            <div class="vehicle-images">
                <img src="<?php echo htmlspecialchars($vehicle['photo_file_path'] ?? 'uploads/default_car_image.jpg'); ?>" 
                     alt="Vehicle Image" class="main-image">
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
                        <i class="fas fa-gas-pump"></i>
                        <span>Fuel Type: <?php echo htmlspecialchars($vehicle['fuel_type']); ?></span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="test_drive.php?vehicle_id=<?php echo $vehicle['vehicle_id']; ?>" class="btn test-drive-btn">
                        <i class="fas fa-car"></i> Request Test Drive
                    </a>
                    <a href="transaction.php?vehicle_id=<?php echo $vehicle['vehicle_id']; ?>" class="btn buy-now-btn">
                        <i class="fas fa-shopping-cart"></i> Buy Now
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
            gap: 30px;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .vehicle-images {
            position: relative;
        }

        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
        }

        .vehicle-title {
            font-size: 28px;
            margin: 0 0 15px 0;
            color: #333;
        }

        .price-tag {
            font-size: 32px;
            font-weight: 600;
            color: #ff5722;
            margin-bottom: 20px;
        }

        .key-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
        }

        .detail-item i {
            color: #ff5722;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
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
        }

        .seller-info p {
            margin: 10px 0;
            color: #666;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .vehicle-description {
            color: #666;
            line-height: 1.6;
        }

        .vehicle-description h3 {
            color: #333;
            margin: 0 0 15px 0;
        }

        @media (max-width: 768px) {
            .vehicle-details-wrapper {
                grid-template-columns: 1fr;
            }

            .main-image {
                height: 300px;
            }

            .key-details {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>

    <script>
        // Get current page URL
        const currentPage = window.location.pathname.split('/').pop();
        
        // Add active class to current page link
        document.querySelectorAll('.nav-links a').forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    </script>
</body>
</html> 