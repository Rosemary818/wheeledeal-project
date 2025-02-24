<?php
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['user_id'];

    $stmt = $db->prepare("DELETE FROM automobileusers WHERE user_id = ?");
    if ($stmt->execute([$user_id])) {
        echo json_encode(["success" => true, "message" => "User deleted successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error deleting user."]);
    }
}
?>
