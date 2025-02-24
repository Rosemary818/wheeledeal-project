<?php
require 'db.php'; // Include your database connection file
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']); // Trim input

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email address.");
    }

    // Check if email exists in the database
    $query = "SELECT * FROM automobilelogin WHERE email = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // Generate a secure reset token
        $token = bin2hex(random_bytes(32)); // 64-character token

        // Use DATE_ADD to ensure expiration is 1 hour ahead of NOW()
        $updateQuery = "UPDATE automobilelogin SET reset_token = ?, token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?";
        $updateStmt = $conn->prepare($updateQuery);

        // Debugging: Check if query preparation failed
        if (!$updateStmt) {
            die("Database error in UPDATE statement: " . $conn->error);
        }

        $updateStmt->bind_param("ss", $token, $email);
        $updateStmt->execute();

        // Send email with reset link
        $resetLink = "http://localhost/runmain/resetpassword.php?token=$token";
        $subject = "Password Reset Request";
        $message = "Click <a href='$resetLink'>here</a> to reset your password. This link will expire in 1 hour.";

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'wheeleddeal@gmail.com'; // Your email
            $mail->Password = 'aeyx tgob nqim wivb'; // Your email password (use app password if 2FA is enabled)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('wheeleddeal@gmail.com', 'WheeledDeal');
            $mail->addAddress($email);

            // Email content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->send();
            echo "A reset link has been sent to your email.";
        } catch (Exception $e) {
            echo "Failed to send email. Error: " . $mail->ErrorInfo;
        }
    } else {
        echo "No account found with this email.";
    }
}
?>
