<?php
include 'db.php';
$database_name = "automobilereselling";
mysqli_select_db($conn, $database_name);
$sql = "CREATE TABLE automobileusers (
    user_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    number VARCHAR(255) NOT NULL,
    dob date,
    gender ENUM('male', 'female', 'others') NOT NULL,
    role ENUM('admin', 'buyer', 'seller') NOT NULL DEFAULT 'buyer',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";
if (mysqli_query($conn, $sql)) {
echo "Table created successfully";
} else {
echo "Error creating table: " . mysqli_error($conn);
}
mysqli_close($conn);