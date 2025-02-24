<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

// Fetch all test drive requests for this buyer
$sql = "SELECT td.*, 
        v.model as vehicle_model, v.year as vehicle_year, v.price as vehicle_price,
        s.name as seller_name, s.number as seller_phone, s.email as seller_email,
        td.testdrive_id
        FROM tbl_testdrive td
        JOIN vehicle v ON td.vehicle_id = v.vehicle_id
        JOIN automobileusers s ON td.seller_id = s.user_id
        WHERE td.buyer_id = ?
        ORDER BY td.requested_date DESC, td.requested_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Test Drive Requests - WheeleDeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <div class="logo">
            <img src="images/logo3.png" alt="Logo">
            <h1>WheeledDeal</h1>
        </div>
        <nav>
            <div class="nav-links">
                <a href="buyer_dashboard.php" class="nav-link">Back to Dashboard</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <h2>My Test Drive Requests</h2>

        <div class="requests-container">
            <?php if ($result->num_rows > 0): ?>
                <?php while($request = $result->fetch_assoc()): ?>
                    <div class="request-card <?php echo strtolower($request['status']); ?>">
                        <div class="vehicle-info">
                            <h3><?php echo htmlspecialchars($request['vehicle_model']); ?> (<?php echo $request['vehicle_year']; ?>)</h3>
                            <p class="price">₹<?php echo number_format($request['vehicle_price']); ?></p>
                        </div>
                        
                        <div class="request-details">
                            <div class="detail-group">
                                <p><strong>Original Request:</strong></p>
                                <p>Date: <?php echo date('d M Y', strtotime($request['requested_date'])); ?></p>
                                <p>Time: <?php echo date('h:i A', strtotime($request['requested_time'])); ?></p>
                            </div>
                            
                            <div class="status-group">
                                <p><strong>Status:</strong> 
                                    <span class="status-badge <?php echo strtolower($request['status']); ?>">
                                        <?php echo $request['status']; ?>
                                    </span>
                                </p>
                                
                                <?php if ($request['status'] == 'Rescheduled'): ?>
                                    <div class="reschedule-info">
                                        <h4>Seller's Suggested Schedule:</h4>
                                        <p>Date: <?php echo date('d M Y', strtotime($request['suggested_date'])); ?></p>
                                        <p>Time: <?php echo date('h:i A', strtotime($request['suggested_time'])); ?></p>
                                        <p class="seller-message">
                                            <strong>Message from Seller:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($request['seller_message'])); ?>
                                        </p>
                                        <div class="reschedule-actions">
                                            <button onclick="acceptReschedule(<?php echo $request['testdrive_id']; ?>)" class="accept-btn">
                                                <i class="fas fa-check"></i> Accept New Schedule
                                            </button>
                                            <button onclick="declineReschedule(<?php echo $request['testdrive_id']; ?>)" class="decline-btn">
                                                <i class="fas fa-times"></i> Decline
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($request['status'] == 'Confirmed'): ?>
                                    <div class="seller-info">
                                        <p><strong>Seller Name:</strong> <?php echo htmlspecialchars($request['seller_name']); ?></p>
                                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($request['seller_phone']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($request['seller_email']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="vehicle_details.php?id=<?php echo $request['vehicle_id']; ?>" class="view-details-btn">View Vehicle Details</a>
                            <?php if ($request['status'] == 'Pending'): ?>
                                <?php echo "<!-- Debug: testdrive_id = " . $request['testdrive_id'] . " -->"; ?>
                                <button onclick="confirmCancel(<?php echo $request['testdrive_id']; ?>)" class="cancel-btn">
                                    <i class="fas fa-times"></i> Cancel Request
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-requests">You haven't made any test drive requests yet.</p>
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

    .container {
        max-width: 1000px;
        margin: 30px auto;
        padding: 0 20px;
    }

    .request-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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

    .vehicle-info h3 {
        margin: 0 0 10px 0;
        color: #333;
    }

    .price {
        color: #ff5722;
        font-weight: 600;
        font-size: 18px;
        margin: 5px 0;
    }

    .request-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 15px;
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

    .seller-info {
        margin-top: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 5px;
    }

    .seller-info p {
        margin: 5px 0;
    }

    .no-requests {
        text-align: center;
        color: #666;
        padding: 30px;
        background: white;
        border-radius: 10px;
    }

    @media (max-width: 768px) {
        .request-details {
            grid-template-columns: 1fr;
        }
    }

    .nav-links {
        display: flex;
        align-items: center;
    }

    .nav-link {
        text-decoration: none;  /* Removes underline */
        color: #333;
        font-weight: 500;
        padding: 8px 16px;
        transition: all 0.3s ease;
    }

    .nav-link:hover {
        color: #ff5722;  /* Orange color on hover */
    }

    .nav-link.active {
        color: #ff5722;  /* Orange color when active */
    }

    .cancel-btn {
        padding: 8px 16px;
        background-color: #dc3545;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .cancel-btn:hover {
        background-color: #c82333;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }

    .reschedule-info {
        background: #fff3cd;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
    }

    .reschedule-info h4 {
        color: #856404;
        margin: 0 0 10px 0;
    }

    .seller-message {
        background: white;
        padding: 10px;
        border-radius: 5px;
        margin: 10px 0;
    }

    .reschedule-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }

    .accept-btn, .decline-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .accept-btn {
        background-color: #28a745;
        color: white;
    }

    .accept-btn:hover {
        background-color: #218838;
    }

    .decline-btn {
        background-color: #dc3545;
        color: white;
    }

    .decline-btn:hover {
        background-color: #c82333;
    }

    .status-badge.rescheduled {
        background-color: #fff3cd;
        color: #856404;
    }
</style>

<script>
    // Get current page URL
    const currentPage = window.location.pathname.split('/').pop();
    
    // Add active class to current page link
    document.querySelectorAll('.nav-link').forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });

    function confirmCancel(testDriveId) {
        if (confirm('Are you sure you want to cancel this test drive request?')) {
            window.location.href = 'cancel_test_drive.php?id=' + testDriveId;
        }
    }

    function acceptReschedule(testDriveId) {
        if (confirm('Accept the new schedule?')) {
            window.location.href = 'handle_reschedule.php?action=accept&id=' + testDriveId;
        }
    }

    function declineReschedule(testDriveId) {
        if (confirm('Decline the new schedule? This will cancel the test drive request.')) {
            window.location.href = 'handle_reschedule.php?action=decline&id=' + testDriveId;
        }
    }
</script>

</body>
</html> 