<?php
session_start(); // Start the session to access user info

// Assuming the user is already logged in and the user ID is stored in the session
$user_id = $_SESSION['user_id']; // This should be set during login

// Check if the form is submitted and the role is 'seller' or 'buyer'
if (isset($_POST['role'])) {
    // Include your database connection file
    require_once 'db.php';

    // Determine if the role is 'seller' or 'buyer' and set the appropriate query
    if ($_POST['role'] == 'seller') {
        // Update the user's role to 'seller'
        $query = "UPDATE automobileusers SET role = 'seller' WHERE user_id = ?";
        $stmt = $conn->prepare($query);

        if ($stmt === false) {
            echo "Error preparing the query: " . $conn->error;
        } else {
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                // Redirect to the seller dashboard after switching to 'seller' role
                header("Location: seller_dashboard.php");
                exit(); // Don't forget to call exit to stop further code execution
            } else {
                echo "Error executing query: " . $stmt->error;
            }
        }
    }
    elseif ($_POST['role'] == 'buyer') {
        // Update the user's role to 'buyer'
        $query = "UPDATE automobileusers SET role = 'buyer' WHERE user_id = ?";
        $stmt = $conn->prepare($query);

        if ($stmt === false) {
            echo "Error preparing the query: " . $conn->error;
        } else {
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                // Redirect to the buyer dashboard after switching to 'buyer' role
                header("Location: buyer_dashboard.php");
                exit(); // Stop further code execution
            } else {
                echo "Error executing query: " . $stmt->error;
            }
        }
    }
}
?>
