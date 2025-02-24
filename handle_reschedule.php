<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header('Location: view_test_drives.php');
    exit();
}

$testdrive_id = $_GET['id'];
$action = $_GET['action'];
$buyer_id = $_SESSION['user_id'];

// Verify this test drive belongs to the current user
$verify_sql = "SELECT * FROM tbl_testdrive WHERE testdrive_id = ? AND buyer_id = ? AND status = 'Rescheduled'";
$stmt = $conn->prepare($verify_sql);
$stmt->bind_param("ii", $testdrive_id, $buyer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: view_test_drives.php?error=invalid');
    exit();
}

// Handle the action
if ($action === 'accept') {
    // Update the test drive with the suggested schedule
    $update_sql = "UPDATE tbl_testdrive 
                   SET status = 'Confirmed',
                       requested_date = suggested_date,
                       requested_time = suggested_time
                   WHERE testdrive_id = ?";
} else {
    // Decline - set status to cancelled
    $update_sql = "UPDATE tbl_testdrive SET status = 'Cancelled' WHERE testdrive_id = ?";
}

$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $testdrive_id);

if ($update_stmt->execute()) {
    $message = ($action === 'accept') ? 'accepted' : 'declined';
    header("Location: view_test_drives.php?msg=$message");
} else {
    header('Location: view_test_drives.php?error=1');
}
exit();
?> 
