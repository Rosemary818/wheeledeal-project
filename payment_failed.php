<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - WheeleDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f7f9fc;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .error-icon {
            font-size: 80px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .error-message {
            color: #dc3545;
            margin: 20px 0;
            padding: 15px;
            background: #f8d7da;
            border-radius: 5px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #ff5722;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            margin: 0 10px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #e64a19;
        }
    </style>
</head>
<body>
    <div class="container">
        <i class="fas fa-times-circle error-icon"></i>
        <h1>Payment Failed</h1>
        <p>We're sorry, but there was a problem processing your payment.</p>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                <?php 
                echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="buttons">
            <a href="buyer_dashboard.php" class="btn">Back to Dashboard</a>
            <a href="javascript:history.back()" class="btn">Try Again</a>
        </div>
    </div>
</body>
</html> 