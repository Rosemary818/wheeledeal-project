<?php
session_start();
include 'db_connect.php';

// Initialize form variables
$name = '';
$email = '';
$phone = '';
$dob = '';
$gender = '';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
    $dob = $_POST['dob'];
    $gender = filter_var($_POST['gender'], FILTER_SANITIZE_STRING);

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($phone) || empty($dob) || empty($gender)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM tbl_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO tbl_users (name, email, password, phone, dob, gender, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'user', NOW(), NOW())");
            $stmt->bind_param("ssssss", $name, $email, $hashed_password, $phone, $dob, $gender);
            
            if ($stmt->execute()) {
                $success = "Registration successful! Please login.";
                // Redirect to login page after 2 seconds
                header("refresh:2;url=login.php");
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
}

$conn->close();
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

        function validateName() {
            const nameField = document.querySelector('input[name="name"]');
            const errorElement = document.getElementById('name-error');
            const nameValue = nameField.value;

            // Regular expression to validate the name
            const nameRegex = /^[A-Za-z][A-Za-z\s]*$/; // Starts with a letter and allows letters and spaces only

            if (nameValue.trim() === '') {
                errorElement.textContent = "Name cannot be empty.";
            } else if (!nameRegex.test(nameValue)) {
                errorElement.textContent = "Name must start with a letter and can only contain letters and spaces.";
            } else {
                errorElement.textContent = ''; // Clear error message
            }
        }

        function validateEmail() {
            const emailField = document.querySelector('input[name="email"]');
            const errorElement = document.getElementById('email-error');
            const emailValue = emailField.value.toLowerCase(); // Convert to lowercase

            // Regular expression to validate the email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (emailValue.trim() === '') {
                errorElement.textContent = "Email cannot be empty.";
            } else if (!emailRegex.test(emailValue)) {
                errorElement.textContent = "Please enter a valid email address.";
            } else {
                errorElement.textContent = ''; // Clear error message
            }

            // Update the email field to be lowercase
            emailField.value = emailValue;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const nameField = document.querySelector('input[name="name"]');
            const emailField = document.querySelector('input[name="email"]');
            const passwordField = document.querySelector('input[name="password"]');
            const confirmPasswordField = document.querySelector('input[name="confirm_password"]');
            const phoneField = document.querySelector('input[name="phone"]');
            const dobField = document.querySelector('input[name="dob"]');
            const genderField = document.querySelector('select[name="gender"]');

            nameField.addEventListener('input', validateName);
            emailField.addEventListener('input', validateEmail);

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
                <input type="text" name="name" placeholder="Full Name" required>
                <div id="name-error" class="error-message"></div>
                <input type="email" name="email" placeholder="Email Address" required>
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