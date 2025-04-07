<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['vehicle_id'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'];
$vehicle_id = $_POST['vehicle_id'];

// Check if already in wishlist
$check_sql = "SELECT wishlist_id FROM tbl_wishlist WHERE user_id = ? AND vehicle_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $user_id, $vehicle_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    // Remove from wishlist
    $delete_sql = "DELETE FROM tbl_wishlist WHERE user_id = ? AND vehicle_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $user_id, $vehicle_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['status' => 'removed']);
    } else {
        echo json_encode(['error' => 'Failed to remove from wishlist']);
    }
} else {
    // Add to wishlist
    $insert_sql = "INSERT INTO tbl_wishlist (user_id, vehicle_id) VALUES (?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ii", $user_id, $vehicle_id);
    
    if ($insert_stmt->execute()) {
        echo json_encode(['status' => 'added']);
    } else {
        echo json_encode(['error' => 'Failed to add to wishlist']);
    }
} 