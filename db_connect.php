<?php
$host = "dpg-cvpvbphr0fns73894490-a.oregon-postgres.render.com";
$dbname = "wheeledeal_db";
$username = "wheeledeal_db_user";
$password = "5F5ibmaatqHGXob4HrQu2yr3QLaVzCVt";
$port = "5432";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "✅ PDO loaded"; // Temporary debug line
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
