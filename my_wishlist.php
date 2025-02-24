<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch wishlisted vehicles
$sql = "SELECT v.vehicle_id, v.model, v.year, v.price, vp.photo_file_name, vp.photo_file_path
        FROM tbl_wishlist w
        JOIN vehicle v ON w.vehicle_id = v.vehicle_id
        LEFT JOIN vehicle_photos vp ON v.vehicle_id = vp.vehicle_id
        WHERE w.user_id = ?
        ORDER BY v.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
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
                while ($row = $result->fetch_assoc()) {
                    $imageSrc = $row['photo_file_path'] ? $row['photo_file_path'] : 'uploads/default_car_image.jpg';
                    
                    echo '<div class="car-card">
                            <div class="car-image-container">
                                <img src="' . htmlspecialchars($imageSrc) . '" alt="Car Image">
                                <form class="wishlist-form" method="POST" action="buyer_dashboard.php">
                                    <input type="hidden" name="vehicle_id" value="' . $row['vehicle_id'] . '">
                                    <button type="submit" name="toggle_wishlist" class="wishlist-btn wishlisted">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="car-details">
                                <h3 class="car-title">' . htmlspecialchars($row['year']) . ' ' . htmlspecialchars($row['model']) . '</h3>
                                <p class="price">₹' . number_format($row['price']) . '</p>
                                <div class="button-group">
                                    <a href="test_drive.php?vehicle_id=' . $row['vehicle_id'] . '" class="test-drive-btn">Request Test Drive</a>
                                    <a href="vehicle_details.php?id=' . $row['vehicle_id'] . '" class="details-btn">View Details</a>
                                </div>
                            </div>
                          </div>';
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
        }

        .car-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
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
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .wishlist-btn.wishlisted i {
            color: #ff5722;
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