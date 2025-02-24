<?php
require_once 'db.php'; // Ensure the database connection is included

// Fetch vehicle listings from the database
$stmt = $db->prepare("SELECT * FROM vehicle");
$stmt->execute();
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return data as JSON
header('Content-Type: application/json');
echo json_encode($vehicles);
?>
