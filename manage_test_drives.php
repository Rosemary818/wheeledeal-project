<?php
session_start();
include 'db.php';

// Check if seller is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['testdrive_id']) && isset($_POST['status'])) {
    $testdrive_id = $_POST['testdrive_id'];
    $new_status = $_POST['status'];
    
    $update_sql = "UPDATE tbl_testdrive SET status = ? WHERE testdrive_id = ? AND seller_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sii", $new_status, $testdrive_id, $seller_id);
    
    if ($stmt->execute()) {
        $success_message = "Test drive request updated successfully!";
    } else {
        $error_message = "Error updating request: " . $conn->error;
    }
}

// Fetch test drive requests for this seller
$sql = "SELECT td.*, 
        v.model as vehicle_model, v.year as vehicle_year,
        b.name as buyer_name, b.email as buyer_email, b.number as buyer_phone
        FROM tbl_testdrive td
        JOIN vehicle v ON td.vehicle_id = v.vehicle_id
        JOIN automobileusers b ON td.buyer_id = b.user_id
        WHERE td.seller_id = ?
        ORDER BY td.requested_date ASC, td.requested_time ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Test Drive Requests - WheeleDeal</title>
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

        <div class="requests-container">
            <?php if ($result->num_rows > 0): ?>
                <?php while($request = $result->fetch_assoc()): ?>
                    <div class="request-card <?php echo strtolower($request['status']); ?>">
                        <div class="request-info">
                            <h3><?php echo htmlspecialchars($request['vehicle_model']); ?> (<?php echo $request['vehicle_year']; ?>)</h3>
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
                                    <button type="submit" name="status" value="Cancelled" class="cancel-btn">Cancel</button>
                                    <button type="button" onclick="openRescheduleModal(<?php echo $request['testdrive_id']; ?>)" class="reschedule-btn">
                                        <i class="fas fa-calendar-alt"></i> Reschedule
                                    </button>
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

    <!-- Add this modal HTML at the bottom of your body tag -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Reschedule Test Drive</h3>
            <form method="POST" id="rescheduleForm">
                <input type="hidden" name="testdrive_id" id="modal_testdrive_id">
                <div class="form-group">
                    <label>Suggested Date:</label>
                    <input type="date" name="suggested_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Suggested Time:</label>
                    <input type="time" name="suggested_time" required>
                </div>
                <div class="form-group">
                    <label>Message to Buyer:</label>
                    <textarea name="message" required placeholder="Explain why you need to reschedule..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="action" value="reschedule" class="submit-btn">Send Reschedule Request</button>
                </div>
            </form>
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

    .cancel-btn {
        background-color: #dc3545;
        color: white;
    }

    .cancel-btn:hover {
        background-color: #c82333;
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

    .reschedule-btn {
        background-color: #ffc107;
        color: #000;
        border: none;
        padding: 8px 16px;
        border-radius: 5px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .reschedule-btn:hover {
        background-color: #e0a800;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border-radius: 10px;
        width: 80%;
        max-width: 500px;
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover {
        color: #000;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 5px;
    }

    .form-group textarea {
        height: 100px;
        resize: vertical;
    }

    .submit-btn {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .submit-btn:hover {
        background-color: #218838;
    }
</style>

<!-- Add this JavaScript -->
<script>
    const modal = document.getElementById('rescheduleModal');
    const span = document.getElementsByClassName('close')[0];

    function openRescheduleModal(testdriveId) {
        document.getElementById('modal_testdrive_id').value = testdriveId;
        modal.style.display = "block";
    }

    span.onclick = function() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>

<?php
// Add this PHP code at the top of your file to handle the reschedule request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reschedule') {
    $testdrive_id = $_POST['testdrive_id'];
    $suggested_date = $_POST['suggested_date'];
    $suggested_time = $_POST['suggested_time'];
    $message = $_POST['message'];
    
    // Update the test drive with suggested date/time and set status to 'Rescheduled'
    $update_sql = "UPDATE tbl_testdrive 
                   SET status = 'Rescheduled',
                       suggested_date = ?,
                       suggested_time = ?,
                       seller_message = ?
                   WHERE testdrive_id = ? AND seller_id = ?";
    
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssii", $suggested_date, $suggested_time, $message, $testdrive_id, $seller_id);
    
    if ($stmt->execute()) {
        $success_message = "Reschedule request sent successfully!";
    } else {
        $error_message = "Error sending reschedule request: " . $conn->error;
    }
}
?>

</body>
</html> 