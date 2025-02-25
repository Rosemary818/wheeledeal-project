<?php
session_start();
include 'db.php'; // Database connection file

// Check if seller is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$vehicle_id = isset($_GET['id']) ? $_GET['id'] : null;

// Fetch vehicle details
if ($vehicle_id) {
    $sql = "SELECT * FROM vehicle WHERE vehicle_id = ? AND seller_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $vehicle_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicle = $result->fetch_assoc();

    if (!$vehicle) {
        header("Location: seller_dashboard.php");
        exit();
    }
}

// Fetch all brands from the brands table
$brand_sql = "SELECT name FROM brands"; // Adjust the table name if necessary
$brand_result = $conn->query($brand_sql);
$brands = [];

if ($brand_result && $brand_result->num_rows > 0) {
    while ($row = $brand_result->fetch_assoc()) {
        $brands[] = $row['name']; // Store each brand in an array
    }
} else {
    echo "No brands found or query failed: " . $conn->error; // Debugging output
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $model = $_POST['model'];
    $year = $_POST['year'];
    $price = $_POST['price'];
    $mileage = $_POST['mileage'];
    $description = $_POST['description'];
    $fuel_type = $_POST['fuel_type'];
    $transmission = $_POST['transmission'];
    $address = $_POST['address'];
    $brand = $_POST['brand'];
    
    $sql = "UPDATE vehicle SET 
            model = ?, 
            year = ?, 
            price = ?, 
            mileage = ?, 
            description = ?, 
            fuel_type = ?, 
            transmission = ?, 
            address = ?, 
            brand = ? 
            WHERE vehicle_id = ? AND seller_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisissssssi", $model, $year, $price, $mileage, $description, $fuel_type, $transmission, $address, $brand, $vehicle_id, $seller_id);


    
    if ($stmt->execute()) {
        // Handle new photo uploads if any
        if (isset($_FILES['vehicle_photo']) && !empty($_FILES['vehicle_photo']['name'][0])) {
            // Similar photo upload logic as in seller_dashboard.php
            // ... (photo upload code)
        }
        header("Location: seller_dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vehicle - WheeledDeal</title>
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
                <a href="profile.php">
                    <img src="images/login.png" alt="Profile">
                    <?php if (isset($_SESSION['name'])): ?>
                        <?php echo htmlspecialchars($_SESSION['name']); ?>
                    <?php endif; ?>
                </a>
                <a href="logout.php">Logout</a>
            </div>
            <form action="switch_role.php" method="POST">
                <button type="submit" name="role" value="buyer">Back to Buying</button>
            </form>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="action-section">
            <h2>Edit Vehicle</h2>
            <form class="vehicle-form" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="input-group">
                        <input type="text" name="model" value="<?php echo htmlspecialchars($vehicle['model']); ?>" placeholder="Vehicle Model" required>
                    </div>
                    <div class="input-group">
                        <input type="number" name="year" value="<?php echo htmlspecialchars($vehicle['year']); ?>" placeholder="Year" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="price" value="<?php echo htmlspecialchars($vehicle['price']); ?>" placeholder="Price" required>
                    </div>
                    <div class="input-group">
                        <input type="number" name="mileage" value="<?php echo htmlspecialchars($vehicle['mileage']); ?>" placeholder="Mileage" required>
                    </div>
                    <div class="input-group textarea-group">
                        <textarea name="description" placeholder="Vehicle Description" required><?php echo htmlspecialchars($vehicle['description']); ?></textarea>
                    </div>
                    <div class="input-group">
                        <select name="fuel_type" required>
                            <option value="Petrol" <?php echo $vehicle['fuel_type'] == 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                            <option value="Diesel" <?php echo $vehicle['fuel_type'] == 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                            <option value="Electric" <?php echo $vehicle['fuel_type'] == 'Electric' ? 'selected' : ''; ?>>Electric</option>
                            <option value="Hybrid" <?php echo $vehicle['fuel_type'] == 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <select name="transmission" required>
                            <option value="Automatic" <?php echo $vehicle['transmission'] == 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                            <option value="Manual" <?php echo $vehicle['transmission'] == 'Manual' ? 'selected' : ''; ?>>Manual</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="brand">Select Brand:</label>
                        <select name="brand" id="brand" required>
                            <option value="">Select Brand</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo htmlspecialchars($brand); ?>">
                                    <?php echo htmlspecialchars($brand); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="address">Address:</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($vehicle['address']); ?>" placeholder="Enter Address" required>
                    </div>
                </div>
                <div class="input-group photo-upload">
                    <label>Upload New Photos (optional):</label>
                    <input type="file" name="vehicle_photo[]" multiple>
                </div>
                <div class="button-group">
                    <button type="submit" class="update-btn">Update Vehicle</button>
                    <a href="seller_dashboard.php" class="cancel-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>

<style>
    body {
        margin: 0;
        font-family: 'Poppins', Arial, sans-serif;
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

    .dashboard-container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 0 20px;
    }

    .action-section {
        background: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 35px;
        margin-bottom: 35px;
    }

    .input-group {
        margin-bottom: 25px;
    }

    .input-group input,
    .input-group select,
    .input-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 20px;
        outline: none;
        font-size: 14px;
        box-sizing: border-box;
    }

    .textarea-group {
        grid-column: 1 / -1;
    }

    .textarea-group textarea {
        height: 120px;
        resize: vertical;
    }

    .photo-upload {
        margin: 20px 0;
    }

    .photo-upload label {
        display: block;
        margin-bottom: 10px;
        color: #333;
    }

    .button-group {
        display: flex;
        gap: 15px;
        justify-content: flex-start;
        margin-top: 20px;
    }

    .update-btn, .cancel-btn {
        padding: 12px 25px;
        border-radius: 20px;
        font-size: 16px;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
    }

    .update-btn {
        background-color: #ff5722;
        color: white;
        border: none;
    }

    .cancel-btn {
        background-color: #f5f5f5;
        color: #333;
        border: 1px solid #ddd;
    }

    .update-btn:hover {
        background-color: #e64a19;
    }

    .cancel-btn:hover {
        background-color: #ebebeb;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .button-group {
            flex-direction: column;
        }

        .update-btn, .cancel-btn {
            width: 100%;
        }
    }

    /* Update these specific styles */
    .icons {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .icons a {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: #333;
        font-size: 14px;
    }

    .icons img {
        width: 12px;      /* Reduced from 16px */
        height: 12px;     /* Reduced from 16px */
        margin-right: 5px;
    }

    nav {
        display: flex;
        align-items: center;
        gap: 15px;
    }
</style>
</body>
</html>
