<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['number']);
    $dob = trim($_POST['dob']);
    $gender = trim($_POST['gender']);

    // Start transaction to ensure both updates succeed or none does
    $conn->begin_transaction();

    try {
        // Update users table
        $update_users_sql = "UPDATE tbl_users SET 
                            name = ?, 
                            email = ?, 
                            phone = ?,
                            dob = ?,
                            gender = ?
                            WHERE user_id = ?";

        $stmt = $conn->prepare($update_users_sql);
        if (!$stmt) {
            throw new Exception("Error preparing users update: " . $conn->error);
        }

        $stmt->bind_param("sssssi", $name, $email, $phone, $dob, $gender, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Error updating user profile: " . $stmt->error);
        }
        $stmt->close();

      

        // If we get here, both updates succeeded
        $conn->commit();

        $_SESSION['name'] = $name; // Update session name
        $success_message = "Profile updated successfully!";

    } catch (Exception $e) {
        // If any error occurred, roll back the changes
        $conn->rollback();
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}

// Fetch current user data from automobileusers table
$sql = "SELECT * FROM tbl_users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - WheeleDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <div class="logo">
            <img src="images/logo3.png" alt="Logo">
            <h1>WheeledDeal</h1>
        </div>

        <nav>
            <div class="icons">
                <!-- <?php if (isset($_SESSION['name'])): ?>
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
                <?php endif; ?> -->
                <div class="nav-links">
                    <a href="index.php">Back to Home</a>
                    <a href="buyer_dashboard.php">Back to Dashboard</a>
                    <!-- <a href="logout.php">Logout</a> -->
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="profile-card">
            <h2>My Profile</h2>

            <?php if ($success_message): ?>
                <div class="alert success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="POST" action="profile.php" class="profile-form">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" 
                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="number">Phone Number</label>
                    <input type="tel" id="number" name="number" 
                           value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" 
                           value="<?php echo htmlspecialchars($user['dob']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" required>
                        <option value="male" <?php echo ($user['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($user['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo ($user['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <button type="submit" class="update-btn">Update Profile</button>
            </form>
        </div>
    </div>

    <style>
        /* ... keeping the same styles as before ... */
        body {
            margin: 0;
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f7f4f1;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: white;
            border-bottom: 1px solid #ddd;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo img {
            height: 24px;
        }

        .logo h1 {
            font-size: 24px;
            margin: 0;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .profile-card h2 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .update-btn {
            background-color: #ff5722;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-top: 20px;
        }

        .update-btn:hover {
            background-color: #e64a19;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }

        .nav-links a:hover {
            color: #ff5722;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .profile-card {
                padding: 20px;
            }
        }

        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background-color: white;
        }
    </style>

</body>
</html>