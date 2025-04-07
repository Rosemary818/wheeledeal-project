<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Debug the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch wishlisted vehicles with their specifications
$sql = "SELECT DISTINCT 
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
        (SELECT photo_file_path FROM tbl_photos 
         WHERE vehicle_id = v.vehicle_id 
         LIMIT 1) as photo_file_path
        FROM tbl_wishlist w
        JOIN tbl_vehicles v ON w.vehicle_id = v.vehicle_id
        LEFT JOIN tbl_vehicle_specifications vs ON v.vehicle_id = vs.vehicle_id
        WHERE w.user_id = ?
        AND (v.status = 'active' OR v.status IS NULL)";

// Debug information
error_log("SQL Query: " . $sql);
error_log("User ID: " . $_SESSION['user_id']);

// Execute the query
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Error in prepare statement: " . $conn->error);
}

// Handle wishlist removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_vehicle'])) {
    $vehicle_id = $_POST['vehicle_id'];
    $delete_sql = "DELETE FROM tbl_wishlist WHERE user_id = ? AND vehicle_id = ?";
    if ($delete_stmt = $conn->prepare($delete_sql)) {
        $delete_stmt->bind_param("ii", $_SESSION['user_id'], $vehicle_id);
        $delete_stmt->execute();
        header("Location: my_wishlist.php");
        exit();
    }
}

// Function to get all photos for a vehicle
function getVehiclePhotos($conn, $vehicle_id) {
    $sql = "SELECT photo_file_path FROM tbl_photos WHERE vehicle_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    return $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - WheeleDeal</title>
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
                    <a href="profile.php">My Profile</a>
                    <a href="view_test_drives.php">My Test Drives</a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <h2 class="section-title">My Wishlist</h2>
        <div class="car-grid">
            <?php
            if ($result->num_rows > 0) {
                while ($vehicle = $result->fetch_assoc()) {
                    // Get all photos for this vehicle
                    $photos = getVehiclePhotos($conn, $vehicle['vehicle_id']);
                    $photo_paths = [];
                    while ($photo = $photos->fetch_assoc()) {
                        $photo_paths[] = $photo['photo_file_path'];
                    }
                    ?>
                    <div class="car-card">
                        <div class="car-image-container">
                            <?php if (!empty($photo_paths)): ?>
                                <?php foreach($photo_paths as $index => $path): ?>
                                    <img src="<?php echo htmlspecialchars($path); ?>" 
                                         class="vehicle-image <?php echo $index === 0 ? 'active' : ''; ?>"
                                         alt="<?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?>">
                                <?php endforeach; ?>
                                
                                <?php if (count($photo_paths) > 1): ?>
                                    <button class="nav-btn prev">❮</button>
                                    <button class="nav-btn next">❯</button>
                                    <div class="image-counter">1/<?php echo count($photo_paths); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="no-image">No Image Available</div>
                            <?php endif; ?>

                            <!-- Add EV badge here -->
                            <?php if (isset($vehicle['vehicle_type']) && $vehicle['vehicle_type'] === 'EV'): ?>
                                <div class="ev-badge">EV</div>
                            <?php endif; ?>

                            <form class="wishlist-form" method="POST">
                                <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['vehicle_id']; ?>">
                                <button type="submit" name="remove_vehicle" class="wishlist-btn wishlisted">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </form>
                        </div>

                        <div class="car-details">
                            <h3 class="car-title">
                                <?php echo htmlspecialchars($vehicle['year']) . ' ' . 
                                         htmlspecialchars($vehicle['brand']) . ' ' . 
                                         htmlspecialchars($vehicle['model']); ?>
                            </h3>
                            <p class="price">₹<?php echo number_format($vehicle['price']); ?></p>
                            
                            <div class="specs-grid">
                                <?php if (!isset($vehicle['vehicle_type']) || $vehicle['vehicle_type'] !== 'EV'): ?>
                                    <div class="spec-item">
                                        <span class="spec-label">Fuel Type:</span>
                                        <span class="spec-value"><?php echo htmlspecialchars($vehicle['fuel_type']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="spec-item">
                                    <span class="spec-label">Transmission:</span>
                                    <span class="spec-value"><?php echo htmlspecialchars($vehicle['transmission']); ?></span>
                                </div>
                                <div class="spec-item">
                                    <span class="spec-label">Mileage:</span>
                                    <span class="spec-value"><?php echo htmlspecialchars($vehicle['mileage']); ?> km/l</span>
                                </div>
                                <div class="spec-item">
                                    <span class="spec-label">Seating Capacity:</span>
                                    <span class="spec-value"><?php echo htmlspecialchars($vehicle['seating_capacity']); ?></span>
                                </div>
                            </div>

                            <div class="button-stack">
                                <?php if (isset($vehicle['vehicle_type']) && $vehicle['vehicle_type'] === 'EV'): ?>
                                    <a href="evdetails.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="details-btn">View Details</a>
                                <?php else: ?>
                                    <a href="vehicle_details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="details-btn">View Details</a>
                                <?php endif; ?>
                                <a href="test_drive.php?vehicle_id=<?php echo $vehicle['vehicle_id']; ?>" class="test-drive-btn">Request Test Drive</a>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<p class="no-cars">No vehicles in your wishlist yet.</p>';
            }
            ?>
        </div>
    </div>

    <style>
        /* Copy the same styles from buyer_dashboard.php */
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

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .car-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .car-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease-in-out;
        }

        .car-card:hover {
            transform: translateY(-5px);
        }

        .car-image-container {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
            background-color: #f5f5f5;
        }

        .car-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.3s ease;
        }

        .car-image-container:hover img {
            transform: scale(1.05);
        }

        .car-details {
            padding: 15px;
        }

        .button-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
        }

        .test-drive-btn, .details-btn {
            display: block;
            padding: 8px 15px;
            text-align: center;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .test-drive-btn {
            background-color: #ff5722;
            color: white;
        }

        .details-btn {
            background-color: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
        }

        .wishlist-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            transition: transform 0.2s;
            color: #FF6B35;
        }

        .wishlist-btn:hover {
            transform: scale(1.1);
        }

        .wishlist-btn.wishlisted {
            color: #FF6B35;
        }

        .no-cars {
            text-align: center;
            grid-column: 1 / -1;
            padding: 20px;
            color: #666;
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

        .ev-details {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .ev-details p {
            margin: 5px 0;
            font-size: 0.9em;
            color: #666;
        }

        .ev-details i {
            width: 20px;
            color: #FF6B35;
            margin-right: 5px;
        }

        .wishlist-btn.wishlisted i {
            color: #FF6B35;
        }

        .wishlist-form {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 2;
        }

        .car-image-container {
            position: relative;
        }

        .ev-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #00bfa5;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.8rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
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

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.car-card').forEach(card => {
                const images = card.querySelectorAll('.vehicle-image');
                const prevBtn = card.querySelector('.prev');
                const nextBtn = card.querySelector('.next');
                const counter = card.querySelector('.image-counter');
                let currentIndex = 0;

                if (!images.length) return;

                function updateImage(index) {
                    images.forEach(img => img.classList.remove('active'));
                    images[index].classList.add('active');
                    if (counter) {
                        counter.textContent = `${index + 1}/${images.length}`;
                    }
                }

                prevBtn?.addEventListener('click', () => {
                    currentIndex = (currentIndex - 1 + images.length) % images.length;
                    updateImage(currentIndex);
                });

                nextBtn?.addEventListener('click', () => {
                    currentIndex = (currentIndex + 1) % images.length;
                    updateImage(currentIndex);
                });
            });
        });
    </script>
</body>
</html> 