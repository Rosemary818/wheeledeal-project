<?php
// PostgreSQL connection details
$host = "dpg-cvpvbphr0fns73894490-a.oregon-postgres.render.com";
$dbname = "wheeledeal_db";
$username = "wheeledeal_db_user";
$password = "5F5ibmaatqHGXob4HrQu2yr3QLaVzCVt";
$port = "5432";

// Create connection using PDO
try {
    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Set connection attributes
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Optional: Set schema if needed
    // $conn->exec("SET search_path TO my_schema");
    
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
