<?php
session_start();
include 'db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['vehicle_id']) || empty($data['vehicle_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vehicle ID is required']);
    exit;
}

$vehicle_id = $data['vehicle_id'];
$user_id = $_SESSION['user_id'];

// First check if the vehicle belongs to the user
$check_sql = "SELECT seller_id FROM tbl_vehicles WHERE vehicle_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $vehicle_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
    $check_stmt->close();
    exit;
}

$vehicle_data = $result->fetch_assoc();
$check_stmt->close();

// Verify ownership (unless user is admin)
if ($vehicle_data['seller_id'] != $user_id && $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Not authorized to delete this vehicle']);
    exit;
}

// Begin transaction for safe deletion
$conn->begin_transaction();
try {
    // Delete related records first
    
    // 1. Delete from tbl_photos
    $photo_sql = "DELETE FROM tbl_photos WHERE vehicle_id = ?";
    $photo_stmt = $conn->prepare($photo_sql);
    $photo_stmt->bind_param("i", $vehicle_id);
    $photo_stmt->execute();
    $photo_stmt->close();
    
    // 2. Delete from tbl_vehicle_specifications
    $specs_sql = "DELETE FROM tbl_vehicle_specifications WHERE vehicle_id = ?";
    $specs_stmt = $conn->prepare($specs_sql);
    $specs_stmt->bind_param("i", $vehicle_id);
    $specs_stmt->execute();
    $specs_stmt->close();
    
    // 3. Delete any test drives
    $test_drive_sql = "DELETE FROM tbl_test_drives WHERE vehicle_id = ?";
    $test_drive_stmt = $conn->prepare($test_drive_sql);
    $test_drive_stmt->bind_param("i", $vehicle_id);
    $test_drive_stmt->execute();
    $test_drive_stmt->close();
    
    // 4. Delete from tbl_vehicles
    $vehicle_sql = "DELETE FROM tbl_vehicles WHERE vehicle_id = ?";
    $vehicle_stmt = $conn->prepare($vehicle_sql);
    $vehicle_stmt->bind_param("i", $vehicle_id);
    $vehicle_stmt->execute();
    $vehicle_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Vehicle deleted successfully']);
} catch (Exception $e) {
    // Rollback in case of error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error deleting vehicle: ' . $e->getMessage()]);
}

$conn->close();
?> 