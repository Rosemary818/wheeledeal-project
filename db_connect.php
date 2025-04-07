<?php
$host = "localhost"; // Change if using a different host
$dbname = "wheeledeal_db"; // Your database name
$username = "root"; // Change if using a different MySQL user
$password = ""; // Change if using a password

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8 (optional, but recommended)
$conn->set_charset("utf8");

?>
