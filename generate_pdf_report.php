<?php
// Prevent any output before PDF generation
ob_start();

session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}
require_once 'db_connect.php';
require_once 'fpdf/fpdf.php'; // Direct path to FPDF library

// Check if user is admin
$user_id = $_SESSION['user_id'];
$admin_check = $conn->prepare("SELECT role FROM tbl_users WHERE user_id = ?");
$admin_check->bind_param("i", $user_id);
$admin_check->execute();
$result = $admin_check->get_result();
$user = $result->fetch_assoc();

if(!$result || $user['role'] != 'admin'){
    header("Location: login.php");
    exit;
}

// Get report type from URL
$report_type = isset($_GET['type']) ? $_GET['type'] : '';

// Create new PDF document
$pdf = new FPDF('P', 'mm', 'A4');

// Add a page
$pdf->AddPage();

// Set document information
$pdf->SetTitle('WheeledDeal ' . ucfirst($report_type) . ' Report');
$pdf->SetAuthor('Admin');
$pdf->SetCreator('WheeledDeal Admin');

// Add custom header
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'WheeledDeal ' . ucfirst($report_type) . ' Report', 0, 1, 'C');
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
$pdf->Ln(10);

// Generate report based on type
switch($report_type) {
    case 'users':
        generateUsersReport($pdf, $conn);
        break;
    case 'sales':
        generateSalesReport($pdf, $conn);
        break;
    case 'vehicles':
        generateVehiclesReport($pdf, $conn);
        break;
    default:
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Invalid report type specified.', 0, 1, 'C');
}

// Clear any buffered output
ob_end_clean();

// Close and output PDF document
$pdf->Output('D', 'wheeleddeal_' . $report_type . '_report.pdf');
exit;

// Function to generate users report
function generateUsersReport($pdf, $conn) {
    // Title
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 15, 'User Registrations Report', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    
    // Get user data - exclude admin users
    $sql = "SELECT user_id, name, email, phone, role, created_at 
            FROM tbl_users 
            WHERE role != 'admin'
            ORDER BY created_at DESC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        // Table header
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(20, 10, 'ID', 1, 0, 'C', true);
        $pdf->Cell(40, 10, 'Name', 1, 0, 'C', true);
        $pdf->Cell(50, 10, 'Email', 1, 0, 'C', true);
        $pdf->Cell(30, 10, 'Phone', 1, 0, 'C', true);
        $pdf->Cell(20, 10, 'Role', 1, 0, 'C', true);
        $pdf->Cell(30, 10, 'Registered Date', 1, 1, 'C', true);
        
        // Table data
        $pdf->SetFont('Arial', '', 9);
        while($row = $result->fetch_assoc()) {
            $pdf->Cell(20, 8, $row['user_id'], 1, 0, 'C');
            $pdf->Cell(40, 8, $row['name'], 1, 0, 'L');
            $pdf->Cell(50, 8, $row['email'], 1, 0, 'L');
            $pdf->Cell(30, 8, $row['phone'], 1, 0, 'C');
            $pdf->Cell(20, 8, $row['role'], 1, 0, 'C');
            $pdf->Cell(30, 8, date('Y-m-d', strtotime($row['created_at'])), 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, 'No users found in the database.', 0, 1, 'C');
    }
}

// Function to generate sales report
function generateSalesReport($pdf, $conn) {
    // Title
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 15, 'Sales Transactions Report', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    
    // Get transactions data - only completed transactions
    $sql = "SELECT t.transaction_id, t.buyer_id, u.name as buyer_name, t.vehicle_id, v.brand, v.model, 
                  t.amount, t.method, t.status, t.transaction_date, t.razorpay_payment_id
            FROM tbl_transactions t
            LEFT JOIN tbl_users u ON t.buyer_id = u.user_id
            LEFT JOIN tbl_vehicles v ON t.vehicle_id = v.vehicle_id
            WHERE t.status = 'Completed'
            ORDER BY t.transaction_date DESC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        // Table header
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(12, 10, 'ID', 1, 0, 'C', true);
        $pdf->Cell(25, 10, 'Buyer', 1, 0, 'C', true);
        $pdf->Cell(50, 10, 'Vehicle', 1, 0, 'C', true);
        $pdf->Cell(23, 10, 'Amount', 1, 0, 'C', true);
        $pdf->Cell(18, 10, 'Method', 1, 0, 'C', true);
        $pdf->Cell(18, 10, 'Status', 1, 0, 'C', true);
        $pdf->Cell(30, 10, 'Date', 1, 1, 'C', true);
        
        // Table data
        $pdf->SetFont('Arial', '', 9);
        while($row = $result->fetch_assoc()) {
            $pdf->Cell(12, 8, $row['transaction_id'], 1, 0, 'C');
            $pdf->Cell(25, 8, isset($row['buyer_name']) ? $row['buyer_name'] : 'N/A', 1, 0, 'L');
            $vehicle_name = isset($row['brand']) && isset($row['model']) ? $row['brand'] . ' ' . $row['model'] : 'N/A';
            $pdf->Cell(50, 8, $vehicle_name, 1, 0, 'L');
            $pdf->Cell(23, 8, '₹' . number_format($row['amount']), 1, 0, 'R');
            $pdf->Cell(18, 8, $row['method'], 1, 0, 'C');
            $pdf->Cell(18, 8, $row['status'], 1, 0, 'C');
            $pdf->Cell(30, 8, date('Y-m-d', strtotime($row['transaction_date'])), 1, 1, 'C');
        }
        
        // Add summary at the end
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 10, 'Transaction Summary', 0, 1, 'L');
        
        // Calculate total sales
        $total_query = "SELECT SUM(amount) as total FROM tbl_transactions WHERE status = 'Completed'";
        $total_result = $conn->query($total_query);
        $total = ($total_result && $total_result->num_rows > 0) ? 
                 $total_result->fetch_assoc()['total'] : 0;
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(100, 8, 'Total Sales Amount:', 0, 0, 'R');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 8, '₹' . number_format($total, 2), 0, 1, 'L');
    } else {
        $pdf->Cell(0, 10, 'No completed transactions found in the database.', 0, 1, 'C');
    }
}

// Function to generate vehicles report
function generateVehiclesReport($pdf, $conn) {
    // Title
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 15, 'Vehicle Listings Report', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    
    // Get vehicles data
    $sql = "SELECT v.vehicle_id, v.brand, v.model, v.year, v.price, v.vehicle_type, 
                   v.fuel_type, v.transmission, v.status, u.name as seller_name, v.created_at
            FROM tbl_vehicles v
            LEFT JOIN tbl_users u ON v.seller_id = u.user_id
            ORDER BY v.created_at DESC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        // Table header
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(10, 10, 'ID', 1, 0, 'C', true);
        $pdf->Cell(70, 10, 'Vehicle', 1, 0, 'C', true);
        $pdf->Cell(9, 10, 'Year', 1, 0, 'C', true);
        $pdf->Cell(18, 10, 'Price', 1, 0, 'C', true);
        $pdf->Cell(10, 10, 'Type', 1, 0, 'C', true);
        $pdf->Cell(25, 10, 'Seller', 1, 0, 'C', true);
        $pdf->Cell(15, 10, 'Status', 1, 0, 'C', true);
        $pdf->Cell(22, 10, 'Listed Date', 1, 1, 'C', true);
        
        // Table data
        $pdf->SetFont('Arial', '', 8);
        while($row = $result->fetch_assoc()) {
            $vehicle_name = $row['brand'] . ' ' . $row['model'];
            $pdf->Cell(10, 8, $row['vehicle_id'], 1, 0, 'C');
            
            // Use smaller font for long vehicle names
            if (strlen($vehicle_name) > 35) {
                $pdf->SetFont('Arial', '', 7);
                $pdf->Cell(70, 8, $vehicle_name, 1, 0, 'L');
                $pdf->SetFont('Arial', '', 8); // Reset font
            } else {
                $pdf->Cell(70, 8, $vehicle_name, 1, 0, 'L');
            }
            
            $pdf->Cell(9, 8, $row['year'], 1, 0, 'C');
            $pdf->Cell(18, 8, '₹' . number_format($row['price']), 1, 0, 'R');
            $pdf->Cell(10, 8, isset($row['vehicle_type']) ? $row['vehicle_type'] : 'N/A', 1, 0, 'C');
            $pdf->Cell(25, 8, isset($row['seller_name']) ? $row['seller_name'] : 'N/A', 1, 0, 'L');
            $pdf->Cell(15, 8, $row['status'], 1, 0, 'C');
            $pdf->Cell(22, 8, date('Y-m-d', strtotime($row['created_at'])), 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, 'No vehicles found in the database.', 0, 1, 'C');
    }
}
?> 