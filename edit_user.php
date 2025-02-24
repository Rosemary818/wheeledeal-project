<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['user_id'], $_POST['name'], $_POST['email'], $_POST['number'])) {

        $user_id = $_POST['user_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $number = $_POST['number']; // Correct


        try {
            // Check if user exists before updating
            $stmtCheck = $db->prepare("SELECT * FROM automobileusers WHERE user_id = ?");
            $stmtCheck->execute([$user_id]);
            if ($stmtCheck->rowCount() === 0) {
                echo json_encode(["success" => false, "message" => "User not found."]);
                exit;
            }

            // Update user details
            $stmt = $db->prepare("UPDATE automobileusers SET name = ?, email = ?, number = ? WHERE user_id = ?");
            $stmt->execute([$name, $email, $number, $user_id]); // Use $number instead of $phone
            

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["success" => false, "message" => "No changes made."]);
            }
        } catch (Exception $e) {
            echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Invalid request. Missing parameters."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>
