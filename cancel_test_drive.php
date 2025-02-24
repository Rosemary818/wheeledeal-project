<?php
session_start();
include 'db.php';

// Add error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug output
echo "Starting cancellation process...<br>";
echo "Test Drive ID: " . $_GET['id'] . "<br>";
echo "User ID: " . $_SESSION['user_id'] . "<br>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

// Check if test drive ID is provided
if (!isset($_GET['id'])) {
    die("No test drive ID provided");
}

$test_drive_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Debug the SQL query
$sql = "SELECT * FROM tbl_testdrive WHERE testdrive_id = ? AND buyer_id = ? AND status = 'Pending'";
echo "SQL Query: " . $sql . "<br>";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("ii", $test_drive_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

echo "Found rows: " . $result->num_rows . "<br>";

if ($result->num_rows === 0) {
    die("No matching test drive found");
}

// Update the test drive status to Cancelled
$update_sql = "UPDATE tbl_testdrive SET status = 'Cancelled' WHERE testdrive_id = ?";
$update_stmt = $conn->prepare($update_sql);
if (!$update_stmt) {
    die("Update prepare failed: " . $conn->error);
}

$update_stmt->bind_param("i", $test_drive_id);

if ($update_stmt->execute()) {
    echo "Successfully cancelled!";
    header('Location: view_test_drives.php?msg=cancelled');
} else {
    echo "Error updating: " . $update_stmt->error;
    header('Location: view_test_drives.php?error=1');
}
exit(); 