<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id'])) {
    $vehicle_id = $_POST['vehicle_id'];
    $seller_id = $_SESSION['user_id'];

    // First verify that this vehicle belongs to the logged-in seller
    $verify_sql = "SELECT seller_id FROM vehicle WHERE vehicle_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("i", $vehicle_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    $vehicle = $result->fetch_assoc();

    if ($vehicle && $vehicle['seller_id'] == $seller_id) {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Delete photos first (due to foreign key constraint)
            $delete_photos = "DELETE FROM vehicle_photos WHERE vehicle_id = ?";
            $photo_stmt = $conn->prepare($delete_photos);
            $photo_stmt->bind_param("i", $vehicle_id);
            $photo_stmt->execute();

            // Then delete the vehicle
            $delete_vehicle = "DELETE FROM vehicle WHERE vehicle_id = ? AND seller_id = ?";
            $vehicle_stmt = $conn->prepare($delete_vehicle);
            $vehicle_stmt->bind_param("ii", $vehicle_id, $seller_id);
            $vehicle_stmt->execute();

            // Commit transaction
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Not authorized to delete this vehicle']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
 