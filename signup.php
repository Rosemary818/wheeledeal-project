<?php
// Database connection
include 'db.php';
$database_name = "automobilereselling";

// Validate database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

// Initialize variables
$errors = [];
$name = $email = $dob = $gender = $phone = "";

// Form submission handling
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $dob = trim($_POST['dob']);
    $gender = trim($_POST['gender']);
    $phone = trim($_POST['phone']);

    // Validation rules
    if (!preg_match("/^[a-zA-Z ]*$/", $name)) {
        $errors[] = "Name can only contain letters and white spaces.";
    }
    if (strlen($name) < 3) {
        $errors[] = "Name must be at least 3 characters long.";
    }
    if (preg_match("/\d/", $name)) {
        $errors[] = "Name cannot contain numbers.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (empty($dob)) {
        $errors[] = "Date of birth is required.";
    }
    if (empty($gender)) {
        $errors[] = "Gender is required.";
    }
    if (!preg_match("/^[6-9][0-9]{9}$/", $phone) || $phone === "0000000000" || preg_match("/^(\d)\1{9}$/", $phone)) {
        $errors[] = "Phone number must be exactly 10 digits, start with 6, 7, 8, or 9, and cannot be all zeros or repeated digits.";
    }

    // Check if email already exists
    if (empty($errors)) {
        $email_check_query = "SELECT * FROM automobilelogin WHERE email = ?";
        $stmt = mysqli_prepare($conn, $email_check_query);

        if ($stmt === false) {
            $errors[] = "Database preparation error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) > 0) {
                $errors[] = "Email already exists.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // If no errors, proceed with database insertion
    if (empty($errors)) {
        // Hash the password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // Insert data into `automobileusers` table
            $sql_automobileusers = "INSERT INTO automobileusers (name, email, number, password, dob, gender, role) 
            VALUES (?, ?, ?, ?, ?, ?, 'buyer')";

            $stmt_automobileusers = mysqli_prepare($conn, $sql_automobileusers);
            mysqli_stmt_bind_param($stmt_automobileusers, "ssssss", $name, $email, $phone, $hashed_password, $dob, $gender);
            mysqli_stmt_execute($stmt_automobileusers);
            mysqli_stmt_close($stmt_automobileusers);

            // Insert email and password into `automobilelogin` table
            $sql_automobilelogin = "INSERT INTO automobilelogin (email, password) VALUES (?, ?)";
            $stmt_automobilelogin = mysqli_prepare($conn, $sql_automobilelogin);
            mysqli_stmt_bind_param($stmt_automobilelogin, "ss", $email, $hashed_password);
            mysqli_stmt_execute($stmt_automobilelogin);
            mysqli_stmt_close($stmt_automobilelogin);

            // Commit transaction
            mysqli_commit($conn);

            // Redirect to login page on successful registration
            header("Location: login.php");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Registration failed. Please try again.";
        }
    }
}

session_start(); // Start the session

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Query to check if the user exists
    $query = "SELECT * FROM automobilelogin WHERE email = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        // Verify the password
        if (password_verify($password, $user['password'])) {
            // Store user name in session
            $_SESSION['user_name'] = $user['name']; // Assuming 'name' is a column in your users table
            header("Location: index.php"); // Redirect to index page
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No user found with that email.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - WheeledDeal</title>
    <style>
     body {
            font-family: Arial, sans-serif;
            background-color: #f8f7f5;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            text-align: center;
            width: 100%;
            max-width: 400px;
            box-sizing: border-box;
        }

        .signup-container {
            width: 100%;
            max-width: 400px;
            background-color: #fff;
            padding: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            text-align: center;
        }

        .logo img {
            height: 60px;
        }

        .logo h1 {
            font-size: 24px;
            color: #333;
            margin: 10px 0;
        }

        h2 {
            font-size: 22px;
            color: #333;
            margin-bottom: 20px;
        }

        form {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        input, select, button {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 15px;
            font-size: 14px;
            border-radius: 5px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #ff5722;
        }

        button {
            background-color: #ff5722;
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #e64a19;
        }

        .login-link {
            margin-top: 10px;
            font-size: 14px;
        }

        .login-link a {
            color: #ff5722;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #721c24; /* Keep the text color for visibility */
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: left;
            width: 100%;
        }
       
 </style>
    <script>
        function validateField(field, regex, errorMessage) {
            const errorElement = document.getElementById(field + '-error');
            if (!regex.test(field.value)) {
                errorElement.textContent = errorMessage;
            } else {
                errorElement.textContent = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const nameField = document.querySelector('input[name="name"]');
            const emailField = document.querySelector('input[name="email"]');
            const passwordField = document.querySelector('input[name="password"]');
            const confirmPasswordField = document.querySelector('input[name="confirm_password"]');
            const phoneField = document.querySelector('input[name="phone"]');
            const dobField = document.querySelector('input[name="dob"]');
            const genderField = document.querySelector('select[name="gender"]');

            nameField.addEventListener('input', function() {
                if (nameField.value.length < 3) {
                    document.getElementById('name-error').textContent = "Name must be at least 3 characters long.";
                } else if (/\d/.test(nameField.value)) {
                    document.getElementById('name-error').textContent = "Name cannot contain numbers.";
                } else {
                    document.getElementById('name-error').textContent = '';
                }
            });

            emailField.addEventListener('input', function() {
                validateField(emailField, /^[^\s@]+@[^\s@]+\.[^\s@]+$/, "Invalid email format.");
            });

            passwordField.addEventListener('input', function() {
                if (passwordField.value.length < 6) {
                    document.getElementById('password-error').textContent = "Password must be at least 6 characters long.";
                } else {
                    document.getElementById('password-error').textContent = '';
                }
            });

            confirmPasswordField.addEventListener('input', function() {
                const errorElement = document.getElementById('confirm-password-error');
                if (confirmPasswordField.value !== passwordField.value) {
                    errorElement.textContent = "Passwords do not match.";
                } else {
                    errorElement.textContent = '';
                }
            });

            phoneField.addEventListener('input', function() {
                const phoneValue = phoneField.value;
                if (!/^[6-9][0-9]{9}$/.test(phoneValue) || phoneValue === "0000000000" || /^(\d)\1{9}$/.test(phoneValue)) {
                    document.getElementById('phone-error').textContent = "Phone number must be exactly 10 digits, start with 6, 7, 8, or 9, and cannot be all zeros or repeated digits.";
                } else {
                    document.getElementById('phone-error').textContent = '';
                }
            });

            dobField.addEventListener('input', function() {
                const errorElement = document.getElementById('dob-error');
                if (!dobField.value) {
                    errorElement.textContent = "Date of birth is required.";
                } else {
                    errorElement.textContent = '';
                }
            });

            genderField.addEventListener('change', function() {
                const errorElement = document.getElementById('gender-error');
                if (!genderField.value) {
                    errorElement.textContent = "Gender is required.";
                } else {
                    errorElement.textContent = '';
                }
            });
        });
    </script>
  </head>
<body>
    <div class="signup-container">
        <div class="signup-card">
            <div class="logo">
                <img src="images/logo3.png" alt="Logo">
                <h1>WheeledDeal</h1>
            </div>

            <h2>Create Your Account</h2>
            <?php
            if (!empty($errors)) {
                echo '<div class="error-message">';
                foreach ($errors as $error) {
                    echo "<p>$error</p>";
                }
                echo '</div>';
            }
            ?>
            <form action="" method="POST">
                <input type="text" name="name" placeholder="Full Name" required 
                       value="<?php echo htmlspecialchars($name); ?>">
                <div id="name-error" class="error-message"></div>
                <input type="email" name="email" placeholder="Email Address" required
                       value="<?php echo htmlspecialchars($email); ?>">
                <div id="email-error" class="error-message"></div>
                <input type="password" name="password" placeholder="Password" required>
                <div id="password-error" class="error-message"></div>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <div id="confirm-password-error" class="error-message"></div>
                <input type="text" name="phone" placeholder="Phone Number" required
                       value="<?php echo htmlspecialchars($phone); ?>">
                <div id="phone-error" class="error-message"></div>
                <input type="date" name="dob" placeholder="Date of Birth" required
                       value="<?php echo htmlspecialchars($dob); ?>">
                <div id="dob-error" class="error-message"></div>

                <select name="gender" required>
                    <option value="" disabled <?php echo empty($gender) ? 'selected' : ''; ?>>Select Gender</option>
                    <option value="Male" <?php echo ($gender == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($gender == 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($gender == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
                <div id="gender-error" class="error-message"></div>

                <button type="submit">Sign Up</button>
            </form>
            <p class="login-link">
                Already have an account? <a href="login.php">Log In</a>
            </p>
        </div>
    </div>
</body>
</html>