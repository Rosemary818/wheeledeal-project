<?php
session_start();
include 'db_connect.php'; // Make sure this matches your connection file name

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Log the incoming request
$log_message = "Wishlist update requested at " . date('Y-m-d H:i:s') . "\n";
$log_message .= "POST data: " . print_r($_POST, true) . "\n";
$log_message .= "Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') . "\n";
file_put_contents('wishlist_debug.log', $log_message, FILE_APPEND);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get variables from POST
$user_id = $_SESSION['user_id'];
$vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Validate inputs
if (!$vehicle_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle ID']);
    exit;
}

// Process the wishlist action
try {
    // First check if this wishlist entry already exists
    $check_sql = "SELECT wishlist_id FROM tbl_wishlist WHERE user_id = ? AND vehicle_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $vehicle_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    // Log database query results
    $log_message = "Database check at " . date('Y-m-d H:i:s') . "\n";
    $log_message .= "SQL check: " . $check_sql . "\n";
    $log_message .= "Params: user_id=$user_id, vehicle_id=$vehicle_id\n";
    $log_message .= "Result rows: " . $check_result->num_rows . "\n";
    file_put_contents('wishlist_debug.log', $log_message, FILE_APPEND);
    
    // Add or remove based on action and current state
    if ($action == 'add') {
        // Only add if it doesn't exist already
        if ($check_result->num_rows == 0) {
            // Create a timestamp for the created_at field
            $timestamp = date('Y-m-d H:i:s');
            
            // Add to wishlist
            $insert_sql = "INSERT INTO tbl_wishlist (user_id, vehicle_id, added_at) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iis", $user_id, $vehicle_id, $timestamp);
            $success = $insert_stmt->execute();
            
            // Log insertion attempt
            $log_message = "Insert attempt at " . date('Y-m-d H:i:s') . "\n";
            $log_message .= "SQL: " . $insert_sql . "\n";
            $log_message .= "Params: user_id=$user_id, vehicle_id=$vehicle_id, timestamp=$timestamp\n";
            $log_message .= "Success: " . ($success ? 'true' : 'false') . "\n";
            if (!$success) {
                $log_message .= "Error: " . $conn->error . "\n";
            } else {
                $log_message .= "Inserted ID: " . $conn->insert_id . "\n";
            }
            file_put_contents('wishlist_debug.log', $log_message, FILE_APPEND);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Added to wishlist']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
        } else {
            echo json_encode(['success' => true, 'message' => 'Already in wishlist']);
        }
    } elseif ($action == 'remove') {
        // Only try to remove if it exists
        if ($check_result->num_rows > 0) {
            $delete_sql = "DELETE FROM tbl_wishlist WHERE user_id = ? AND vehicle_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $user_id, $vehicle_id);
            $success = $delete_stmt->execute();
            
            // Log deletion attempt
            $log_message = "Delete attempt at " . date('Y-m-d H:i:s') . "\n";
            $log_message .= "SQL: " . $delete_sql . "\n";
            $log_message .= "Params: user_id=$user_id, vehicle_id=$vehicle_id\n";
            $log_message .= "Success: " . ($success ? 'true' : 'false') . "\n";
            if (!$success) {
                $log_message .= "Error: " . $conn->error . "\n";
            }
            file_put_contents('wishlist_debug.log', $log_message, FILE_APPEND);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Removed from wishlist']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
        } else {
            echo json_encode(['success' => true, 'message' => 'Not in wishlist']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    $log_message = "Exception at " . date('Y-m-d H:i:s') . "\n";
    $log_message .= "Message: " . $e->getMessage() . "\n";
    $log_message .= "Trace: " . $e->getTraceAsString() . "\n";
    file_put_contents('wishlist_debug.log', $log_message, FILE_APPEND);
    
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?> 