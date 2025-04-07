<?php
// This is a one-time cleanup script to fix your password reset system
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php';

echo "<h1>Database Cleanup Tool</h1>";

// Step 1: Clean up the duplicate tokens
$conn->query("DELETE FROM tbl_login WHERE reset_token IS NOT NULL");
echo "<p>Cleared all existing tokens</p>";

// Step 2: Create a new simple token for testing
$user_id = 1; // The user we want to test with
$new_token = bin2hex(random_bytes(16)); // Shorter, 32-character token
$expiry = date('Y-m-d H:i:s', strtotime('+1 year')); // Long expiry for testing

// Insert the new token
$stmt = $conn->prepare("INSERT INTO tbl_login (user_id, reset_token, token_expiry) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $new_token, $expiry);
$stmt->execute();
echo "<p>Created new token for User ID: $user_id</p>";

// Step 3: Show the reset link
$reset_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/resetpassword.php?token=$new_token";
echo "<p>New reset link: <a href='$reset_url'>$reset_url</a></p>";
echo "<p>Token value: $new_token</p>";

echo "<h2>Database Check</h2>";
$result = $conn->query("SELECT * FROM tbl_login WHERE user_id = $user_id");
if ($row = $result->fetch_assoc()) {
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}
?> 