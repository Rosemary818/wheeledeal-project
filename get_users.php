<?php
require_once 'db.php'; // Ensure this file is included

$sql = "SELECT user_id,name, email, number, gender FROM automobileusers";
$result = $conn->query($sql);

$users = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

echo json_encode($users);
?>
