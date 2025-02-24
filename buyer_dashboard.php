<?php
session_start();
include 'db.php'; // Database connection file

// Check if wishlist action is performed
if (isset($_POST['toggle_wishlist'])) {
    $vehicle_id = $_POST['vehicle_id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if item already exists in tbl_wishlist
    $check_sql = "SELECT * FROM tbl_wishlist WHERE user_id = ? AND vehicle_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $vehicle_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Remove from tbl_wishlist
        $sql = "DELETE FROM tbl_wishlist WHERE user_id = ? AND vehicle_id = ?";
    } else {
        // Add to tbl_wishlist
        $sql = "INSERT INTO tbl_wishlist (user_id, vehicle_id) VALUES (?, ?)";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $vehicle_id);
    $stmt->execute();
}

// Fetch vehicles from the database with their photos
$sql = "SELECT v.vehicle_id, v.model, v.year, v.price, vp.photo_file_name, vp.photo_file_path,
        CASE WHEN w.wishlist_id IS NOT NULL THEN 1 ELSE 0 END as is_wishlisted
        FROM vehicle v
        LEFT JOIN vehicle_photos vp ON v.vehicle_id = vp.vehicle_id
        LEFT JOIN tbl_wishlist w ON v.vehicle_id = w.vehicle_id AND w.user_id = ?
        ORDER BY v.created_at DESC";
$stmt = $conn->prepare($sql);

// Add error handling
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

// Check if the query executed successfully
if (!$result) {
    die("Error fetching vehicles: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Listings - WheeleDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <img src="images/logo3.png" alt="Logo">
            <h1>WheeledDeal</h1>
        </div>

        <div class="search-container">
            <input type="text" placeholder="Search vehicles...">
            <button>
                <img src="images/search.png" alt="Search">
            </button>
        </div>

        <nav>
            <div class="icons">
                <?php if (isset($_SESSION['name'])): ?>
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
                <?php endif; ?>
                <div class="nav-links">
                    <a href="index.php">Back to Home</a>
                    <a href="my_wishlist.php">My Wishlist</a>
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

    <div class="container">
        <div class="content-wrapper">
            <!-- Sidebar Filters -->
            <div class="sidebar">
                <!-- Budget Filter -->
                <div class="filter-box">
                    <h6>Budget:</h6>
                    <input type="range" class="form-range" min="100000" max="5000000" id="budgetRange" oninput="updateBudget()">
                    <div class="range-values">
                        <span id="minPrice">₹1,00,000</span>
                        <span id="maxPrice">₹50,00,000</span>
                    </div>
                </div>

                <!-- Brand + Model Filter -->
                <div class="filter-box">
                    <h6>Brand + Model:</h6>
                    <input type="text" placeholder="Search Brand or Model">
                    <div class="brand-list">
                        <strong>Popular</strong>
                        <div class="checkbox-group">
                            <label><input type="checkbox"> Maruti</label>
                            <label><input type="checkbox"> Hyundai </label>
                            <label><input type="checkbox"> Honda </label>
                            <label><input type="checkbox"> Toyota </label>
                        </div>
                    </div>
                </div>

                <!-- Model Year Filter -->
                <div class="filter-box">
                    <h6>Model Year:</h6>
                    <input type="range" class="form-range" min="2002" max="2025" id="modelYearRange" oninput="updateModelYear()">
                    <div class="range-values">
                        <span id="minYear">2002</span>
                        <span id="maxYear">2025</span>
                    </div>
                </div>

                <!-- Fuel Type Filter -->
                <div class="filter-box">
                    <h6>Fuel Type:</h6>
                    <div class="checkbox-group">
                        <label><input type="checkbox"> Petrol </label>
                        <label><input type="checkbox"> Diesel </label>
                        <label><input type="checkbox"> CNG </label>
                        <label><input type="checkbox"> Electric</label>
                    </div>
                </div>

                <!-- Body Type Filter -->
                <div class="filter-box">
                    <h6>Body Type:</h6>
                    <div class="checkbox-group">
                        <label><input type="checkbox"> SUV (2253)</label>
                        <label><input type="checkbox"> Sedan (1482)</label>
                        <label><input type="checkbox"> Hatchback (1475)</label>
                        <label><input type="checkbox"> MUV (387)</label>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <h2 class="section-title">Available Cars</h2>
                <div class="car-grid">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $imageSrc = $row['photo_file_path'] ? $row['photo_file_path'] : 'uploads/default_car_image.jpg';
                            $wishlistClass = $row['is_wishlisted'] ? 'wishlisted' : '';
                            
                            echo '<div class="car-card">
                                    <div class="car-image-container">
                                        <img src="' . htmlspecialchars($imageSrc) . '" alt="Car Image">
                                        <form class="wishlist-form" method="POST">
                                            <input type="hidden" name="vehicle_id" value="' . $row['vehicle_id'] . '">
                                            <button type="submit" name="toggle_wishlist" class="wishlist-btn ' . $wishlistClass . '">
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
                        echo '<p class="no-cars">No cars available right now.</p>';
                    }
                    ?>
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
    }

    .search-container input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 20px;
        outline: none;
        font-size: 14px;
    }

    .search-container button {
        margin-left: -35px;
        padding: 6px;
        border: none;
        background: none;
        cursor: pointer;
    }

    .search-container button img {
        width: 18px;
        height: 18px;
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

    .car-card img {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .car-details {
        padding: 15px;
        text-align: center;
    }

    .car-title {
        margin: 0 0 10px 0;
        font-size: 18px;
        color: #333;
    }

    .price {
        color: #ff5722;
        font-size: 20px;
        font-weight: bold;
        margin: 0 0 15px 0;
    }

    .view-details-btn {
        display: inline-block;
        padding: 8px 20px;
        background-color: #ff5722;
        color: white;
        text-decoration: none;
        border-radius: 20px;
        transition: background-color 0.3s;
    }

    .view-details-btn:hover {
        background-color: #e64a19;
    }

    .no-cars {
        text-align: center;
        color: #666;
        grid-column: 1 / -1;
    }

    .sidebar {
        width: 280px;
        flex-shrink: 0;
    }

    .filter-box {
        background: white;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .filter-box h6 {
        font-size: 15px;
        font-weight: 600;
        margin-bottom: 12px;
        color: #333;
    }

    .range-values {
        display: flex;
        justify-content: space-between;
        margin-top: 8px;
        font-size: 14px;
        color: #666;
    }

    .checkbox-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .checkbox-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #333;
        cursor: pointer;
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

    @media (max-width: 768px) {
        header {
            flex-direction: column;
            padding: 15px;
        }

        nav {
            flex-direction: column;
            width: 100%;
            gap: 15px;
        }

        .icons {
            align-items: center;
            width: 100%;
        }

        .nav-links {
            flex-wrap: wrap;
            justify-content: center;
        }

        nav form {
            width: 100%;
        }

        nav button {
            width: 100%;
        }

        .content-wrapper {
            flex-direction: column;
        }

        .sidebar {
            width: 100%;
        }
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

    .test-drive-btn:hover {
        background-color: #e64a19;
    }

    .details-btn {
        background-color: #f0f0f0;
        color: #333;
        border: 1px solid #ddd;
    }

    .details-btn:hover {
        background-color: #e0e0e0;
    }

    .car-image-container {
        position: relative;
        width: 100%;
    }

    .wishlist-form {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1;
    }

    .wishlist-btn {
        background: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .wishlist-btn i {
        color: #666;
        font-size: 20px;
        transition: all 0.3s ease;
    }

    .wishlist-btn.wishlisted i {
        color: #ff5722;
    }

    .wishlist-btn:hover {
        background: rgba(255, 255, 255, 1);
        transform: scale(1.1);
    }

    .wishlist-btn:hover i {
        color: #ff5722;
    }
</style>

<script>
    function updateBudget() {
        let budgetValue = document.getElementById("budgetRange").value;
        document.getElementById("maxPrice").innerText = `₹${parseInt(budgetValue).toLocaleString()}`;
    }

    function updateModelYear() {
        let yearValue = document.getElementById("modelYearRange").value;
        document.getElementById("maxYear").innerText = yearValue;
    }
</script>

</body>
</html>