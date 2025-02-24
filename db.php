<?php
$servername = "localhost";
$username = "root"; // Change if you have a different username
$password = ""; // Change if you set a database password
$database = "automobilereselling";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>
