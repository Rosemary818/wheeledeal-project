<?php
session_start();
include 'db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Log the received data for debugging
error_log("update_payment.php received: " . $json);

// Verify required data
if (!isset($data['payment_id']) || !isset($data['transaction_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$payment_id = $data['payment_id'];
$transaction_id = $data['transaction_id'];
$method = $data['method'] ?? '';
$vehicle_id = $data['vehicle_id'] ?? null;

try {
    // Start transaction
    $conn->begin_transaction();

    // 1. Update the transaction with the payment ID and set status to Completed
    $update_sql = "UPDATE tbl_transactions 
                  SET status = 'Completed', 
                      razorpay_payment_id = ? 
                  WHERE transaction_id = ?";
    
    $stmt = $conn->prepare($update_sql);
    if (!$stmt) {
        throw new Exception("Prepare failed for transaction update: " . $conn->error);
    }
    
    $stmt->bind_param("si", $payment_id, $transaction_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for transaction update: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("No transaction found with ID: $transaction_id");
    }
    
    // Get the vehicle_id from the transaction if not provided
    if (!$vehicle_id) {
        $vehicle_sql = "SELECT vehicle_id FROM tbl_transactions WHERE transaction_id = ?";
        $vehicle_stmt = $conn->prepare($vehicle_sql);
        $vehicle_stmt->bind_param("i", $transaction_id);
        $vehicle_stmt->execute();
        $vehicle_result = $vehicle_stmt->get_result();
        if ($row = $vehicle_result->fetch_assoc()) {
            $vehicle_id = $row['vehicle_id'];
        } else {
            throw new Exception("Could not find vehicle_id for transaction: $transaction_id");
        }
    }
    
    // Make sure we have a vehicle_id at this point
    if (!$vehicle_id) {
        throw new Exception("No vehicle_id available to update");
    }
    
    // 2. Update the vehicle status to Inactive
    $vehicle_update = "UPDATE tbl_vehicles SET status = 'Inactive' WHERE vehicle_id = ?";
    $vehicle_stmt = $conn->prepare($vehicle_update);
    if (!$vehicle_stmt) {
        throw new Exception("Prepare failed for vehicle update: " . $conn->error);
    }
    
    $vehicle_stmt->bind_param("i", $vehicle_id);
    if (!$vehicle_stmt->execute()) {
        throw new Exception("Execute failed for vehicle update: " . $vehicle_stmt->error);
    }
    
    error_log("Updated vehicle $vehicle_id to Inactive status");
    
    // Verify the update was successful
    $verify_sql = "SELECT status FROM tbl_vehicles WHERE vehicle_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("i", $vehicle_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $vehicle_data = $verify_result->fetch_assoc();
    
    if ($vehicle_data['status'] !== 'Inactive') {
        throw new Exception("Failed to update vehicle status to Inactive. Current status: " . $vehicle_data['status']);
    }
    
    // Everything successful, commit the transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Payment processed successfully',
        'vehicle_id' => $vehicle_id,
        'new_status' => 'Inactive'
    ]);
    
} catch (Exception $e) {
    // Roll back the transaction on error
    $conn->rollback();
    error_log("Payment update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 