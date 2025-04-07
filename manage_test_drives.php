<?php
session_start();
include 'db_connect.php';

// Check if seller is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

// Track cancelled test drives
if (!isset($_SESSION['seen_cancellations'])) {
    $_SESSION['seen_cancellations'] = [];
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['testdrive_id']) && isset($_POST['status'])) {
    $testdrive_id = $_POST['testdrive_id'];
    $new_status = $_POST['status'];
    
    $update_sql = "UPDATE tbl_test_drives SET status = ? WHERE testdrive_id = ? AND seller_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sii", $new_status, $testdrive_id, $seller_id);
    
    if ($stmt->execute()) {
        $success_message = "Test drive request updated successfully!";
    } else {
        $error_message = "Error updating request: " . $conn->error;
    }
}

// Mark a cancellation as seen
if (isset($_GET['seen']) && is_numeric($_GET['seen'])) {
    $_SESSION['seen_cancellations'][] = (int)$_GET['seen'];
    header("Location: manage_test_drives.php");
    exit();
}

// Update SQL query to include all status types and order cancelled requests first
$sql = "SELECT td.*,
        v.model as vehicle_model,
        v.year as vehicle_year,
        v.vehicle_type,
        b.name as buyer_name, 
        b.email as buyer_email, 
        b.phone as buyer_phone,
        td.status,
        CASE WHEN td.status = 'Cancelled' THEN 1 ELSE 2 END as sort_order
        FROM tbl_test_drives td
        JOIN tbl_vehicles v ON td.vehicle_id = v.vehicle_id
        JOIN tbl_users b ON td.buyer_id = b.user_id
        WHERE td.seller_id = ?
        ORDER BY sort_order, td.updated_at DESC, td.requested_date ASC";

// Fallback to a simpler query if updated_at doesn't exist
if ($conn->query("SHOW COLUMNS FROM tbl_test_drives LIKE 'updated_at'")->num_rows == 0) {
    $sql = "SELECT td.*,
            v.model as vehicle_model,
            v.year as vehicle_year,
            v.vehicle_type,
            b.name as buyer_name, 
            b.email as buyer_email, 
            b.phone as buyer_phone,
            td.status,
            CASE WHEN td.status = 'Cancelled' THEN 1 ELSE 2 END as sort_order
            FROM tbl_test_drives td
            JOIN tbl_vehicles v ON td.vehicle_id = v.vehicle_id
            JOIN tbl_users b ON td.buyer_id = b.user_id
            WHERE td.seller_id = ?
            ORDER BY sort_order, td.requested_date DESC";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $seller_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Test Drive Requests - WheeleDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <img src="images/logo3.png" alt="Logo">
            <h1>WheeledDeal</h1>
        </div>
        <nav>
            <div class="nav-links">
                <a href="seller_dashboard.php">Back to Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <h2>Manage Test Drive Requests</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="filter-tabs">
            <button class="filter-btn active" data-status="all">All</button>
            <button class="filter-btn" data-status="pending">Pending</button>
            <button class="filter-btn" data-status="confirmed">Confirmed</button>
            <button class="filter-btn" data-status="cancelled">Cancelled</button>
        </div>

        <div class="requests-container">
            <?php if ($result->num_rows > 0): ?>
                <?php while($request = $result->fetch_assoc()): ?>
                    <?php 
                    // Check if this is a new cancellation
                    $is_new_cancellation = $request['status'] == 'Cancelled' && 
                                           !in_array($request['testdrive_id'], $_SESSION['seen_cancellations']);
                    ?>
                    <div class="request-card <?php echo strtolower($request['status']); ?> <?php echo $is_new_cancellation ? 'new-cancellation' : ''; ?>" 
                         data-status="<?php echo strtolower($request['status']); ?>">
                        
                        <?php if ($is_new_cancellation): ?>
                            <div class="new-banner">
                                <span>CANCELLED</span>
                                <a href="?seen=<?php echo $request['testdrive_id']; ?>" class="dismiss-btn">
                                    <i class="fas fa-check"></i> Mark as seen
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="request-info">
                            <h3>
                                <?php echo htmlspecialchars($request['vehicle_model']); ?> 
                                (<?php echo $request['vehicle_year']; ?>)
                                <?php echo $request['vehicle_type'] == 'Electric' ? ' <span class="ev-badge">EV</span>' : ''; ?>
                            </h3>
                            <p><strong>Buyer:</strong> <?php echo htmlspecialchars($request['buyer_name']); ?></p>
                            <p><strong>Contact:</strong> <?php echo htmlspecialchars($request['buyer_phone']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($request['buyer_email']); ?></p>
                            <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($request['requested_date'])); ?></p>
                            <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($request['requested_time'])); ?></p>
                            <p><strong>Status:</strong> <span class="status-badge <?php echo strtolower($request['status']); ?>"><?php echo $request['status']; ?></span></p>
                        </div>
                        
                        <?php if ($request['status'] == 'Pending'): ?>
                            <div class="request-actions">
                                <form method="POST" class="status-form">
                                    <input type="hidden" name="testdrive_id" value="<?php echo $request['testdrive_id']; ?>">
                                    <button type="submit" name="status" value="Confirmed" class="confirm-btn">Confirm</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-requests">No test drive requests at the moment.</p>
            <?php endif; ?>
        </div>
    </div>

    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: #f7f4f1;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .request-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .request-card.confirmed {
            border-left: 4px solid #28a745;
        }

        .request-card.cancelled {
            border-left: 4px solid #dc3545;
        }

        .request-card.pending {
            border-left: 4px solid #ffc107;
        }
        
        .request-card.new-cancellation {
            border: 2px solid #dc3545;
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.3);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        
        .new-banner {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #dc3545;
            color: white;
            padding: 5px 15px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dismiss-btn {
            background-color: rgba(255, 255, 255, 0.3);
            color: white;
            border: none;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .dismiss-btn:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            background-color: white;
            border: 1px solid #ddd;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
        }
        
        .filter-btn.active {
            background-color: #ff5722;
            color: white;
            border-color: #ff5722;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.confirmed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .request-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        .confirm-btn, .cancel-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .confirm-btn {
            background-color: #28a745;
            color: white;
        }

        .confirm-btn:hover {
            background-color: #218838;
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

        .no-requests {
            text-align: center;
            color: #666;
            padding: 30px;
            background: white;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .request-actions {
                flex-direction: column;
            }
            
            .confirm-btn, .cancel-btn {
                width: 100%;
            }
            
            .filter-tabs {
                justify-content: center;
            }
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo img {
            height: 30px;
            width: auto;
        }

        .logo h1 {
            font-size: 20px;
            margin: 0;
            color: #333;
        }

        .nav-links {
            display: flex;
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

        .ev-badge {
            background-color: #4CAF50;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 8px;
            font-weight: normal;
        }

        .request-card {
            position: relative;
        }

        .vehicle-type {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.8em;
            padding: 3px 8px;
            border-radius: 4px;
            background: #e9ecef;
        }
    </style>

    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const requestCards = document.querySelectorAll('.request-card');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const status = this.getAttribute('data-status');
                    
                    // Show/hide request cards based on status
                    requestCards.forEach(card => {
                        if (status === 'all' || card.getAttribute('data-status') === status) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html> 