<?php
session_start();
include 'db.php'; // Database connection file

// Check if seller is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $model = $_POST['model'];
    $year = $_POST['year'];
    $price = trim($_POST['price']); // Get the price directly
    $mileage = $_POST['mileage'];
    $description = $_POST['description'];
    $fuel_type = $_POST['fuel_type'];
    $transmission = $_POST['transmission'];
    $address = $_POST['address']; // Get the address
    $brand = $_POST['brand']; // Get the brand
    $status = 'Active';
    
    // Insert vehicle details into the database
    $sql = "INSERT INTO vehicle (seller_id, model, year, price, mileage, description, fuel_type, transmission, address, brand, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo "Error preparing statement: " . $conn->error;
        exit();
    }

    $stmt->bind_param("isisdssssss", $seller_id, $model, $year, $price, $mileage, $description, $fuel_type, $transmission, $address, $brand, $status);

    if ($stmt->execute()) {
        $vehicle_id = $stmt->insert_id;
        
        // Check if photos are uploaded
        if (isset($_FILES['vehicle_photo']) && !empty($_FILES['vehicle_photo']['name'][0])) {
            $photos = $_FILES['vehicle_photo'];

            foreach ($photos['tmp_name'] as $key => $tmp_name) {
                $photo_name = $photos['name'][$key];
                $photo_tmp = $photos['tmp_name'][$key];
                $photo_path = "uploads/" . basename($photo_name);

                $check = getimagesize($photo_tmp);
                if ($check !== false) {
                    if (move_uploaded_file($photo_tmp, $photo_path)) {
                        $sql_photo = "INSERT INTO vehicle_photos (vehicle_id, photo_file_path) VALUES (?, ?)";
                        $stmt_photo = $conn->prepare($sql_photo);
                        if ($stmt_photo === false) {
                            echo "Error preparing photo insert statement: " . $conn->error;
                            exit();
                        }
                        $stmt_photo->bind_param("is", $vehicle_id, $photo_path);
                        $stmt_photo->execute();
                        $stmt_photo->close();
                    }
                }
            }
            echo "<script>alert('Vehicle listed successfully with photos!');</script>";
        }
    } else {
        echo "<script>alert('Error listing vehicle: " . $stmt->error . "');</script>";
    }
    
    $stmt->close();
}

// Fetch vehicles for the seller
$sql = "SELECT * FROM vehicle WHERE seller_id = ?"; // Assuming you have a seller_id to filter vehicles
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']); // Assuming user_id is stored in session
$stmt->execute();
$result = $stmt->get_result();

// Fetch all brands from the brands table
$brand_sql = "SELECT name FROM brands"; // Fetch all brands
$brand_result = $conn->query($brand_sql);
$brands = [];
if ($brand_result && $brand_result->num_rows > 0) {
    while ($row = $brand_result->fetch_assoc()) {
        $brands[] = htmlspecialchars($row['name']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WheeledDeal - Seller Dashboard</title>
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
                    <a href="profile.php">
                        <img src="images/login.png" alt="Profile">
                        My Profile
                    </a>
                    <a href="manage_test_drives.php" class="nav-link">Manage Test Drives</a>
                    <!-- <a href="logout.php">Logout</a> -->
                </div>
            </div>
            <form action="switch_role.php" method="POST">
                <button type="submit" name="role" value="buyer">Back to Buying</button>
            </form>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="stats-section">
           <div class="stat-card">
    <img src="images/carbuyers.svg" alt="Active Listings">
    <div class="stat-info">
        <h3>Active Listings</h3>
        <?php
        // Fetch total active listings count for the seller
        $sql_count = "SELECT COUNT(*) AS total FROM vehicle WHERE seller_id = ?";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bind_param("i", $seller_id);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $row_count = $result_count->fetch_assoc();
        $total_listings = $row_count['total'];
        echo '<p class="stat-number">' . $total_listings . '</p>';
        ?>
    </div>
</div>

            <div class="stat-card">
                <img src="images/zero.svg" alt="Views">
                <div class="stat-info">
                    <h3>Total Views</h3>
                    <!-- <p class="stat-number">1,234</p> -->
                </div>
            </div>
            <div class="stat-card">
                <img src="images/Nounwanted.svg" alt="Sales">
                <div class="stat-info">
                    <h3>Completed Sales</h3>
                    <!-- <p class="stat-number">8</p> -->
                </div>
            </div>
        </div>

        <div class="main-content">
            <div class="action-section">
                <h2>List Your Vehicle</h2>
                <form class="vehicle-form" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
    <div class="input-group">
        <input type="text" name="model" placeholder="Vehicle Model" required>
    </div>
    <div class="input-group">
        <input type="number" name="year" placeholder="Year" required>
    </div>
    <div class="input-group">
        <input type="text" name="price" placeholder="Price (e.g., 695000)" required pattern="\d*" title="Please enter a valid number">
    </div>
    <div class="input-group">
        <input type="number" name="mileage" placeholder="Mileage" required>
    </div>
    <div class="input-group">
        <select name="fuel_type" required>
            <option value="Petrol">Petrol</option>
            <option value="Diesel">Diesel</option>
            <option value="Electric">Electric</option>
            <option value="Hybrid">Hybrid</option>
        </select>
    </div>
    <div class="input-group">
        <select name="transmission" required>
            <option value="Automatic">Automatic</option>
            <option value="Manual">Manual</option>
        </select>
    </div>
    <div class="input-group">
                        <textarea name="description" placeholder="Vehicle Description" required></textarea>
                    </div>
    <div class="input-group">
        <input type="text" name="address" placeholder="Address" required>
    </div>
    <div class="input-group">
        <select name="brand" required>
            <option value="">Select Brand</option>
            <?php foreach ($brands as $brand): ?>
                <option value="<?php echo htmlspecialchars($brand); ?>"><?php echo htmlspecialchars($brand); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
                    <div class="input-group">
                        <label>Upload Vehicle Photos:</label>
                        <input type="file" name="vehicle_photo[]" multiple required>
                    </div>
                    <button type="submit">List Vehicle</button>
                </form>
            </div>

            <div class="listings-section">
                <h2>Your Current Listings</h2>
                <div class="listings-grid">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($vehicle = $result->fetch_assoc()) {
                            $vehicle_id = $vehicle['vehicle_id'];

                            // Fetch associated photos
                            $photo_sql = "SELECT photo_file_path FROM vehicle_photos WHERE vehicle_id = ?";
                            $photo_stmt = $conn->prepare($photo_sql);
                            $photo_stmt->bind_param("i", $vehicle_id);
                            $photo_stmt->execute();
                            $photo_result = $photo_stmt->get_result();

                            // Store photos in an array
                            $photos = [];
                            while ($photo = $photo_result->fetch_assoc()) {
                                $photos[] = $photo['photo_file_path'];
                            }
                            $photo_stmt->close();

                            // Output vehicle details
                            echo '
                            <div class="listing-card" data-vehicle-id="' . $vehicle_id . '">
                                <img src="' . htmlspecialchars($photos[0]) . '" alt="Vehicle">
                                <div class="listing-details">
                                    <h3>' . htmlspecialchars($vehicle['model']) . '</h3>
                                    <p>Brand: ' . htmlspecialchars($vehicle['brand']) . '</p>
                                    <p>Year: ' . htmlspecialchars($vehicle['year']) . '</p>
                                    <p>Price: ' . '₹' . number_format($vehicle['price'], 0, '.', ',') . '</p>
                                    <p>Mileage: ' . htmlspecialchars($vehicle['mileage']) . '</p>
                                    <p>Description: ' . htmlspecialchars($vehicle['description']) . '</p>
                                    <p>Fuel Type: ' . htmlspecialchars($vehicle['fuel_type']) . '</p>
                                    <p>Transmission: ' . htmlspecialchars($vehicle['transmission']) . '</p>
                                    <p>Address: ' . htmlspecialchars($vehicle['address']) . '</p>
                                    <div class="listing-actions">
                                        <button class="edit-btn" onclick="editVehicle(' . $vehicle_id . ')">Edit</button>
                                        <button class="delete-btn" onclick="deleteVehicle(' . $vehicle_id . ')">Delete</button>
                                    </div>
                                </div>
                            </div>';
                        }
                    } else {
                        echo "<p>No listings found.</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

<style>

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        font-size: 18px;
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

    .logo, .search-container, .icons {
        /* Keep your existing styles */
    }

    .dashboard-container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 0 20px;
    }

    .stats-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 15px;
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-card img {
        width: 30px;
        height: 30px;
    }

    .stat-info h3 {
        margin: 0;
        color: #333;
        font-size: 16px;
    }

    .stat-number {
        margin: 5px 0 0;
        font-size: 20px;
        font-weight: bold;
        color: #ff5722;
    }

    .action-section {
        background: white;
        padding: 40px 20px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 35px;
        margin-bottom: 35px;
    }

    .input-group {
        margin-bottom: 25px;
        width: 100%;
        box-sizing: border-box;
    }

    .input-group input, .input-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 20px;
        outline: none;
        font-size: 14px;
        box-sizing: border-box;
    }

    /* Ensure consistent styling for dropdowns */
.form-grid .input-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 20px;
    outline: none;
    font-size: 14px;
    box-sizing: border-box;
}

/* Optional: Add margins to the input fields to match the design */
.input-group {
    margin-bottom: 25px;
}


    .input-group textarea {
        height: 80px;
        resize: horizontal;
        margin-bottom: 25px;
        width: 100%;
    }
    .input-group.textarea-group {
    grid-column: span 2; /* Makes it take up two columns in the grid */
}

.input-group.textarea-group textarea {
    width: 100%; 
}


    button {
        padding: 12px 25px;
        background-color: #ff5722;
        color: white;
        border: none;
        border-radius: 20px;
        cursor: pointer;
        font-size: 16px;
        transition: background-color 0.3s;
    }

    button:hover {
        background-color: #e64a19;
    }

    .listings-section {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .listings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .listing-card {
        border: 1px solid #ddd;
        border-radius: 10px;
        overflow: hidden;
    }

    .listing-card img {
        /* width: 100%; */
        height: 200px;
        object-fit: cover;
    }

    .listing-details {
        padding: 15px;
    }

    .listing-details h3 {
        margin: 0 0 10px 0;
        color: #333;
    }

    .listing-details p {
        margin: 0 0 15px 0;
        color: #ff5722;
        font-weight: bold;
    }

    .listing-actions {
        display: flex;
        gap: 10px;
    }

    .edit-btn, .delete-btn {
        padding: 8px 15px;
        border-radius: 15px;
        font-size: 14px;
    }

    .edit-btn {
        background-color: #4CAF50;
    }

    .delete-btn {
        background-color: #f44336;
    }

    @media (max-width: 768px) {
        .stats-section {
            grid-template-columns: 1fr;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .listings-grid {
            grid-template-columns: 1fr;
        }
    }

    .logo {
        display: flex;
        align-items: center;
    }

    .logo img {
        height: 24px;
        margin-right: 10px;
    }

    .logo h1 {
        font-size: 24px;
        margin: 0;
        color: #333;
    }

    /* Header icons */
    .icons img {
        width: 24px;
        height: 24px;
        margin-right: 5px;
    }

    /* Search container and button adjustments */
    .search-container {
        display: flex;
        align-items: center;
        width: 40%;
    }

    .search-container input {
        width: 100%;
        padding: 8px 12px; /* Reduced from 10px 15px */
        border: 1px solid #ddd;
        border-radius: 20px;
        outline: none;
        font-size: 16px; /* Slightly reduced from 18px */
    }

    .search-container button {
        margin-left: -35px; /* Reduced from -40px */
        padding: 6px; /* Added specific padding for button */
        border: none;
        background: none;
        cursor: pointer;
    }

    .search-container button img {
        width: 18px;  /* Slightly reduced from 20px */
        height: 18px; /* Slightly reduced from 20px */
    }

    nav {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .icons {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .nav-links {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .user-name {
        color: #333;
        font-size: 14px;
        margin-right: 15px;
        white-space: nowrap;
    }

    .icons a {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: #333;
        font-size: 14px;
        gap: 5px;
        white-space: nowrap;
    }

    .icons a:hover {
        color: #ff5722;
    }

    .icons img {
        width: 12px;
        height: 12px;
    }

    nav button {
        padding: 8px 16px;
        background-color: #ff5722;
        color: white;
        border: none;
        border-radius: 20px;
        cursor: pointer;
        font-size: 14px;
        white-space: nowrap;
    }

    nav button:hover {
        background-color: #e64a19;
    }

    @media (max-width: 768px) {
        header {
            flex-direction: column;
            gap: 15px;
            padding: 15px;
        }

        .nav-links {
            flex-wrap: wrap;
            justify-content: center;
        }

        .icons {
            flex-direction: column;
            align-items: center;
        }

        nav {
            flex-direction: column;
            width: 100%;
        }

        .search-container {
            width: 100%;
        }
    }

    .nav-link {
        text-decoration: none;
        color: #333;
        font-size: 14px;
        padding: 5px 10px;
        transition: color 0.3s;
    }

    .nav-link:hover {
        color: #ff5722;
    }
</style>

<script>
function editVehicle(vehicleId) {
    if (confirm('Do you want to edit this vehicle?')) {
        window.location.href = 'edit_vehicle.php?id=' + vehicleId;
    }
}

function deleteVehicle(vehicleId) {
    if (confirm('Are you sure you want to delete this vehicle? This action cannot be undone.')) {
        fetch('delete_vehicle.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'vehicle_id=' + vehicleId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Vehicle deleted successfully!');
                // Remove the vehicle card from the DOM
                document.querySelector(`.listing-card[data-vehicle-id="${vehicleId}"]`).remove();
            } else {
                alert('Error deleting vehicle: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the vehicle.');
        });
    }
}
</script>
</body>
</html>
