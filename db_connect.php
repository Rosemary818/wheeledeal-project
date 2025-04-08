<?php
$host = "dpg-cvpvbphr0fns73894490-a.oregon-postgres.render.com";
$dbname = "wheeledeal_db";
$username = "wheeledeal_db_user";
$password = "5F5ibmaatqHGXob4HrQu2yr3QLaVzCVt";
$port = "5432";

try {
    // ✅ Create the PDO connection
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);

    // ✅ Set error and fetch modes
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Optional debug message
    // echo "✅ Connected to PostgreSQL successfully";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
