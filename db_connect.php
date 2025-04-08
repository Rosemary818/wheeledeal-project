<?php
// PostgreSQL connection details for Render
$host = "dpg-cvpvbphr0fns73894490-a.oregon-postgres.render.com"; // External host
$dbname = "wheeledeal_db";
$username = "wheeledeal_db_user";
$password = "5F5ibmaatqHGXob4HrQu2yr3QLaVzCVt";
$port = "5432";

try {
    // Create a PDO connection to PostgreSQL
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Connection successful
    // echo "Connected successfully to PostgreSQL";
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
