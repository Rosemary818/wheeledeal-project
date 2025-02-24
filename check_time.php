<?php
session_start();
date_default_timezone_set('Asia/Kolkata'); // Set correct timezone

include 'db.php';

// Get PHP time
echo "PHP Time: " . date('Y-m-d H:i:s') . "<br>";

// Get MySQL time
$result = $conn->query("SELECT NOW() AS mysql_time");
$row = $result->fetch_assoc();
echo "MySQL Time: " . $row['mysql_time'] . "<br>";
?>
