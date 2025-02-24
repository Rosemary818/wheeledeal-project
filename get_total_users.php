<?php
require_once 'db.php';

header('Content-Type: application/json');

try {
    $stmt = $conn->query("SELECT COUNT(*) AS total_users FROM automobileusers");

    if ($stmt) {
        $result = $stmt->fetch_assoc(); // Use fetch_assoc() instead of fetch()
        echo json_encode(["total_users" => $result['total_users']]);
    } else {
        echo json_encode(["error" => "Query failed."]);
    }
} catch (Exception $e) {
    echo json_encode(["error" => "Error fetching user count."]);
}
?>
