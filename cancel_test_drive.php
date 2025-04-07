<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

// Check if test drive ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $testdrive_id = $_GET['id'];
    
    // Verify this test drive belongs to the logged-in buyer
    $verify_sql = "SELECT * FROM tbl_test_drives WHERE testdrive_id = ? AND buyer_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $testdrive_id, $buyer_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 1) {
        // Update the test drive status to "Cancelled"
        $update_sql = "UPDATE tbl_test_drives SET status = 'Cancelled' WHERE testdrive_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $testdrive_id);
        
        if ($update_stmt->execute()) {
            // Success message
            $_SESSION['success_message'] = "Test drive cancelled successfully.";
        } else {
            // Error message
            $_SESSION['error_message'] = "Error: Unable to cancel test drive.";
        }
    } else {
        // Test drive doesn't belong to this user
        $_SESSION['error_message'] = "Error: You are not authorized to cancel this test drive.";
    }
} else {
    // No test drive ID provided
    $_SESSION['error_message'] = "Error: Invalid test drive ID.";
}

// Redirect back to the test drives page
header("Location: view_test_drives.php");
exit();
?> 