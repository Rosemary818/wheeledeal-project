<?php
session_start();
include 'db.php'; // Database connection file

// Fetch vehicles
$sql = "SELECT * FROM vehicle";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($vehicle = $result->fetch_assoc()) {
        // Fetch associated photos
        $vehicle_id = $vehicle['id'];
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
?>
        <div class="vehicle-card">
            <h3><?php echo htmlspecialchars($vehicle['model']); ?></h3>
            <p>Year: <?php echo htmlspecialchars($vehicle['year']); ?></p>
            <p>Price: <?php echo htmlspecialchars($vehicle['price']); ?></p>
            <p>Mileage: <?php echo htmlspecialchars($vehicle['mileage']); ?></p>
            <p>Description: <?php echo htmlspecialchars($vehicle['description']); ?></p>
            <p>Fuel Type: <?php echo htmlspecialchars($vehicle['fuel_type']); ?></p>
            <p>Transmission: <?php echo htmlspecialchars($vehicle['transmission']); ?></p>
            <p>Address: <?php echo htmlspecialchars($vehicle['address']); ?></p>

            <div class="vehicle-photos">
                <?php foreach ($photos as $photo): ?>
                    <img src="<?php echo htmlspecialchars($photo); ?>" alt="Vehicle Photo" style="width: 100px; height: auto;">
                <?php endforeach; ?>
            </div>
        </div>
<?php
    }
} else {
    echo "No vehicles found.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ... other code for vehicle details ...

    if ($stmt->execute()) {
        $vehicle_id = $stmt->insert_id; // Get the last inserted vehicle ID
        
        // Check if photos are uploaded
        if (isset($_FILES['vehicle_photo']) && !empty($_FILES['vehicle_photo']['name'][0])) {
            $photos = $_FILES['vehicle_photo'];

            foreach ($photos['tmp_name'] as $key => $tmp_name) {
                $photo_name = $photos['name'][$key];
                $photo_tmp = $photos['tmp_name'][$key];
                $photo_path = "uploads/" . basename($photo_name);

                // Check if the uploaded file is an image
                $check = getimagesize($photo_tmp);
                if ($check !== false) {
                    if (move_uploaded_file($photo_tmp, $photo_path)) {
                        // Insert photo details into the database
                        $sql_photo = "INSERT INTO vehicle_photos (vehicle_id, photo_file_name, photo_file_path, created_at) VALUES (?, ?, ?, NOW())";
                        $stmt_photo = $conn->prepare($sql_photo);
                        $stmt_photo->bind_param("iss", $vehicle_id, $photo_name, $photo_path);
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
}
?> 