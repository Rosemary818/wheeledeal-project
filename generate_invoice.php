<?php
// Turn off output buffering and clear any previous output
ob_end_clean();
// Start output buffering to prevent any accidental output
ob_start();

session_start();
require 'db_connect.php';
require 'fpdf/fpdf.php'; // Update this path to where your FPDF is installed

// Turn off error display for production, but enable for debugging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Debug output
echo "Debug Information:<br>";
echo "GET parameters: <pre>" . print_r($_GET, true) . "</pre><br>";
echo "SESSION data: <pre>" . print_r($_SESSION, true) . "</pre><br>";

// Get transaction ID from either GET or SESSION
$transaction_id = isset($_GET['transaction_id']) ? intval($_GET['transaction_id']) : 
                (isset($_SESSION['current_transaction_id']) ? intval($_SESSION['current_transaction_id']) : 0);

// Get vehicle ID from either GET or SESSION
$vehicle_id = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 
             (isset($_SESSION['vehicle_id']) ? intval($_SESSION['vehicle_id']) : 0);

// Debug information
echo "Processed IDs:<br>";
echo "Transaction ID: " . $transaction_id . "<br>";
echo "Vehicle ID: " . $vehicle_id . "<br>";
echo "User ID: " . $_SESSION['user_id'] . "<br>";

// Validate IDs
if ($transaction_id <= 0 && !$vehicle_id) {
    die("Invalid or missing transaction/vehicle ID. Please provide valid IDs.<br>
         Transaction ID: $transaction_id<br>
         Vehicle ID: $vehicle_id<br>");
}

// Check if FPDF is available before trying to use it
if (!file_exists('fpdf/fpdf.php')) {
    die("Error: FPDF library not found. Please install FPDF library in the 'fpdf' folder.");
}

// DEBUGGING: Check what's being passed in the URL
echo "<div style='background: #f8f8f8; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px;'>";
echo "<h3>Debug Information:</h3>";
echo "Raw transaction_id from URL: " . (isset($_GET['transaction_id']) ? $_GET['transaction_id'] : 'Not set') . "<br>";
echo "SESSION user_id: " . $_SESSION['user_id'] . "<br>";
echo "</div>";

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// First, verify the transaction exists and belongs to the user
$check_sql = "SELECT vehicle_id FROM tbl_transactions 
              WHERE transaction_id = ? AND buyer_id = ?";
$check_stmt = $conn->prepare($check_sql);
if (!$check_stmt) {
    die("Database error: " . $conn->error);
}

$check_stmt->bind_param("ii", $transaction_id, $_SESSION['user_id']);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    die("Transaction not found or unauthorized access.");
}

try {
    // Fetch transaction details with vehicle and user information
    $sql = "SELECT t.*, 
            v.brand, v.model, v.year, v.description,
            seller.name as seller_name, seller.email as seller_email, 
            buyer.name as buyer_name, buyer.email as buyer_email, buyer.phone as buyer_phone
            FROM tbl_transactions t
            LEFT JOIN tbl_vehicles v ON t.vehicle_id = v.vehicle_id
            LEFT JOIN tbl_users seller ON v.seller_id = seller.user_id
            LEFT JOIN tbl_users buyer ON t.buyer_id = buyer.user_id
            WHERE t.transaction_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }
    
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Transaction not found or unauthorized access.");
    }
    
    $transaction = $result->fetch_assoc();
    
    // Fetch buyer information if not in session
    $buyer_sql = "SELECT name, email, phone FROM tbl_users WHERE user_id = ?";
    $buyer_stmt = $conn->prepare($buyer_sql);
    $buyer_id = $_SESSION['user_id'];
    $buyer_stmt->bind_param("i", $buyer_id);
    $buyer_stmt->execute();
    $buyer_result = $buyer_stmt->get_result();
    $buyer = $buyer_result->fetch_assoc();
    
    // Use data from database instead of session for buyer details
    $buyer_name = $buyer['name'] ?? $_SESSION['name'] ?? 'N/A';
    $buyer_email = $buyer['email'] ?? $_SESSION['email'] ?? 'N/A';
    $buyer_phone = $buyer['phone'] ?? $_SESSION['phone'] ?? 'N/A';
    
    // Generate invoice number with prefix and leading zeros
    $invoice_number = 'WD-' . str_pad($transaction_id, 6, '0', STR_PAD_LEFT);
    
    // Now create PDF - clear output buffer first
    ob_end_clean();
    
    // Create custom PDF class with header/footer
    class InvoicePDF extends FPDF {
        function Header() {
            // Empty header - we'll create our own in the main code
        }
        
        function Footer() {
            // Position at 15 mm from bottom
            $this->SetY(-15);
            // Set font
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(128);
            // Page number
            $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
        }
        
        // Add the RoundedRect method to the class
        function RoundedRect($x, $y, $w, $h, $r, $style = '') {
            $k = $this->k;
            $hp = $this->h;
            if($style=='F')
                $op='f';
            elseif($style=='FD' || $style=='DF')
                $op='B';
            else
                $op='S';
            $MyArc = 4/3 * (sqrt(2) - 1);
            $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
            $xc = $x+$w-$r;
            $yc = $y+$r;
            $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));

            $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
            $xc = $x+$w-$r;
            $yc = $y+$h-$r;
            $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
            $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
            $xc = $x+$r;
            $yc = $y+$h-$r;
            $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
            $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
            $xc = $x+$r ;
            $yc = $y+$r;
            $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
            $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
            $this->_out($op);
        }

        function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
            $h = $this->h;
            $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
                $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
        }
    }
    
    // Create new PDF document
    $pdf = new InvoicePDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Set document properties
    $pdf->SetTitle('Vehicle Purchase Invoice');
    $pdf->SetAuthor('WheeledDeal');
    $pdf->SetCreator('WheeledDeal Invoice System');
    
    // Set color for headings and accents (WheeledDeal orange)
    $pdf->SetDrawColor(255, 87, 34);
    $pdf->SetFillColor(255, 87, 34);
    
    // Header Section
    // Logo & Company Name
    $pdf->Image('images/logo3.png', 10, 10, 30);
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Cell(0, 10, 'WheeledDeal', 0, 1, 'R');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, 'Premium Automotive Marketplace', 0, 1, 'R');
    $pdf->Cell(0, 5, 'contact@wheeleddeal.com | +91 123-456-7890', 0, 1, 'R');
    $pdf->Cell(0, 5, 'www.wheeleddeal.com', 0, 1, 'R');
    
    // Invoice Title & Information
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->SetTextColor(255, 87, 34);
    $pdf->Cell(0, 20, 'INVOICE', 0, 1, 'C');
    
    $pdf->SetLineWidth(0.5);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(5);
    
    // Invoice Details Box
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(51, 51, 51);
    
    $pdf->RoundedRect(140, $pdf->GetY(), 60, 25, 2, 'F');
    $pdf->SetXY(145, $pdf->GetY() + 2);
    $pdf->Cell(50, 5, 'Invoice Number:', 0, 1);
    $pdf->SetXY(145, $pdf->GetY());
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 5, $invoice_number, 0, 1);
    
    $pdf->SetXY(145, $pdf->GetY() + 2);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(50, 5, 'Date:', 0, 1);
    $pdf->SetXY(145, $pdf->GetY());
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 5, date('d M Y', strtotime($transaction['transaction_date'])), 0, 1);
    
    // Buyer and Seller Information
    $pdf->SetY($pdf->GetY() + 10);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(255, 87, 34);
    $pdf->Cell(95, 8, 'Bill To:', 0, 0);
    $pdf->Cell(95, 8, 'Seller Details:', 0, 1);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(51, 51, 51);
    
    // Buyer Box
    $pdf->RoundedRect(10, $pdf->GetY(), 90, 25, 2, 'F');
    $pdf->SetXY(15, $pdf->GetY() + 2);
    $pdf->Cell(80, 5, $transaction['buyer_name'], 0, 1);
    $pdf->SetXY(15, $pdf->GetY() + 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(80, 5, 'Email: ' . $transaction['buyer_email'], 0, 1);
    $pdf->SetXY(15, $pdf->GetY());
    $pdf->Cell(80, 5, 'Phone: ' . $transaction['buyer_phone'], 0, 1);
    
    // Seller Box
    $pdf->SetXY(110, $pdf->GetY() - 11);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(80, 5, $transaction['seller_name'], 0, 1);
    $pdf->SetXY(110, $pdf->GetY() + 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(80, 5, 'Email: ' . $transaction['seller_email'], 0, 1);
    
    // Vehicle Details Section
    $pdf->SetY($pdf->GetY() + 15);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(255, 87, 34);
    $pdf->Cell(0, 8, 'VEHICLE DETAILS', 0, 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->SetFillColor(245, 245, 245);
    
    // Table header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(232, 232, 232);
    $pdf->Cell(40, 8, 'Description', 1, 0, 'C', true);
    $pdf->Cell(150, 8, 'Value', 1, 1, 'C', true);
    
    // Vehicle info as table
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetFillColor(248, 248, 248);
    
    $pdf->Cell(40, 8, 'Brand', 1, 0, 'L', true);
    $pdf->Cell(150, 8, $transaction['brand'], 1, 1, 'L');
    
    $pdf->Cell(40, 8, 'Model', 1, 0, 'L', true);
    $pdf->Cell(150, 8, $transaction['model'], 1, 1, 'L');
    
    $pdf->Cell(40, 8, 'Year', 1, 0, 'L', true);
    $pdf->Cell(150, 8, $transaction['year'], 1, 1, 'L');
    
    // Vehicle description (if available)
    if (!empty($transaction['description'])) {
        $pdf->Cell(40, 8, 'Description', 1, 0, 'L', true);
        $pdf->Cell(150, 8, substr($transaction['description'], 0, 80) . (strlen($transaction['description']) > 80 ? '...' : ''), 1, 1, 'L');
    }
    
    // Payment Details Section
    $pdf->SetY($pdf->GetY() + 10);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(255, 87, 34);
    $pdf->Cell(0, 8, 'PAYMENT DETAILS', 0, 1, 'L', true);
    
    // Payment info table
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->SetFillColor(232, 232, 232);
    $pdf->Cell(40, 8, 'Description', 1, 0, 'C', true);
    $pdf->Cell(60, 8, 'Details', 1, 0, 'C', true);
    $pdf->Cell(90, 8, 'Amount', 1, 1, 'C', true);
    
    // Vehicle amount
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetFillColor(248, 248, 248);
    $pdf->Cell(40, 8, 'Vehicle Purchase', 1, 0, 'L', true);
    $pdf->Cell(60, 8, $transaction['brand'] . ' ' . $transaction['model'] . ' ' . $transaction['year'], 1, 0, 'L');
    $pdf->Cell(90, 8, 'Rs. ' . number_format($transaction['amount'], 2), 1, 1, 'R');
    
    // Add taxes, fees if needed (example)
    $pdf->Cell(40, 8, 'Processing Fee', 1, 0, 'L', true);
    $pdf->Cell(60, 8, 'Transaction Processing', 1, 0, 'L');
    $pdf->Cell(90, 8, 'Rs. 0.00', 1, 1, 'R');
    
    // Total row
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(232, 232, 232);
    $pdf->Cell(100, 8, 'TOTAL', 1, 0, 'L', true);
    $pdf->Cell(90, 8, 'Rs. ' . number_format($transaction['amount'], 2), 1, 1, 'R', true);
    
    // Payment method
    $pdf->SetY($pdf->GetY() + 5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'Payment Method:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 8, $transaction['method'], 0, 1);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'Transaction ID:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 8, $transaction['transaction_id'], 0, 1);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'Payment Status:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $color = ($transaction['status'] == 'Completed') ? '008800' : 'FF0000';
    $pdf->SetTextColor(hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2)));
    $pdf->Cell(60, 8, $transaction['status'], 0, 1);
    $pdf->SetTextColor(0);
    
    // Terms & Conditions
    $pdf->SetY($pdf->GetY() + 10);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Cell(0, 8, 'Terms and Conditions:', 0, 1);
    
    $pdf->SetFont('Arial', '', 9);
    $terms = "1. This invoice confirms the sale of the above vehicle between the buyer and seller.
2. WheeledDeal acts as a marketplace facilitator and is not responsible for any disputes between buyer and seller.
3. The vehicle is sold as-is with any applicable guarantees as agreed upon during purchase.
4. Any warranty claims must be addressed directly with the seller.
5. Please retain this invoice for your records and future reference.";
    
    $pdf->MultiCell(0, 5, $terms, 0, 'L');
    
    // Thank you note
    $pdf->SetY($pdf->GetY() + 5);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(255, 87, 34);
    $pdf->Cell(0, 8, 'Thank you for your business!', 0, 1, 'C');
    
    // Add QR code or barcode if needed
    // $pdf->Image('qrcode.png', 160, 240, 30);
    
    // Add signature
    $pdf->SetY($pdf->GetY() + 15);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Cell(95, 5, 'WheeledDeal Authorized Signature', 0, 0, 'C');
    $pdf->Cell(95, 5, 'Customer Signature', 0, 1, 'C');
    
    $pdf->Line(30, $pdf->GetY() + 15, 80, $pdf->GetY() + 15);
    $pdf->Line(130, $pdf->GetY() + 15, 180, $pdf->GetY() + 15);
    
    // Output the PDF
    $pdf->Output('D', 'WheeledDeal_Invoice_' . $invoice_number . '.pdf');
    exit();
    
} catch (Exception $e) {
    // Clear any output buffers
    ob_end_clean();
    die("Error generating invoice: " . $e->getMessage());
}
?> 