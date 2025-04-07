<?php
$host = getenv("dpg-cvpvbphr0fns73894490-a");
$dbname = getenv("wheeledeal_db");
$username = getenv("wheeledeal_db_user");
$password = getenv("5F5ibmaatqHGXob4HrQu2yr3QLaVzCVt");

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");
?>
