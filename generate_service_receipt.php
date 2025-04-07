<?php
session_start();
include 'db_connect.php';
require('fpdf/fpdf.php');

if (!isset($_SESSION['user_id']) || !isset($_GET['transaction_id'])) {
    exit('Invalid request');
}

// Fetch transaction details
$sql = "SELECT t.*, 
        v.model as vehicle_model,
        v.brand as vehicle_brand,
        v.year as vehicle_year,
        v.fuel_type,
        u.name as seller_name,
        u.email as seller_email,
        u.phone as seller_phone
        FROM tbl_transactions t
        JOIN tbl_vehicles v ON t.vehicle_id = v.vehicle_id
        JOIN tbl_users u ON v.seller_id = u.user_id
        WHERE t.transaction_id = ? AND t.buyer_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $_GET['transaction_id'], $_SESSION['user_id']);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();

if (!$purchase) {
    exit('Transaction not found');
}

class PDF extends FPDF {
    function Header() {
        // Logo - reduced size
        $this->Image('images/logo3.png', 10, 10, 25);
        
        // Company Name - reduced font size
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(51, 51, 51);
        $this->Cell(0, 8, 'WheeledDeal', 0, 1, 'R');
        
        // Company Details - reduced spacing
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 4, 'Premium Auto Dealership', 0, 1, 'R');
        $this->Cell(0, 4, 'Contact: +91 1234567890', 0, 1, 'R');
        $this->Cell(0, 4, 'Email: service@wheeledeal.com', 0, 1, 'R');
        
        // Receipt Title - reduced spacing
        $this->Ln(10); // Reduced from 20 to 10
        $this->SetFont('Arial', 'B', 22);
        $this->SetTextColor(255, 87, 34); // Orange color
        $this->Cell(0, 10, 'Service Receipt', 0, 1, 'C');
        
        // Decorative Line
        $this->SetDrawColor(255, 87, 34);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5); // Reduced from 10 to 5
    }
    
    function Footer() {
        $this->SetY(-25); // Moved up from -30 to -25
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Create new PDF
$pdf = new PDF();
$pdf->AddPage();

// Vehicle Information Section - reduced spacing
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('Arial', 'B', 12); // Reduced from 14 to 12
$pdf->SetTextColor(51, 51, 51);
$pdf->Cell(0, 8, 'Vehicle Information', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10); // Reduced from 12 to 10
$pdf->Cell(50, 8, 'Vehicle:', 0); // Reduced width and height
$pdf->Cell(0, 8, $purchase['vehicle_brand'] . ' ' . $purchase['vehicle_model'] . ' ' . $purchase['vehicle_year'], 0, 1);
$pdf->Cell(50, 8, 'Transaction ID:', 0);
$pdf->Cell(0, 8, $purchase['transaction_id'], 0, 1);

// Add Fuel Type
$pdf->Cell(50, 8, 'Fuel Type:', 0);
$pdf->Cell(0, 8, $purchase['fuel_type'], 0, 1);

// Add Seller Information - reduced spacing
$pdf->SetFillColor(240, 248, 255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Seller Information', 0, 1, 'L', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 8, 'Name:', 0);
$pdf->Cell(0, 8, $purchase['seller_name'], 0, 1);
$pdf->Cell(50, 8, 'Email:', 0);
$pdf->Cell(0, 8, $purchase['seller_email'], 0, 1);
$pdf->Cell(50, 8, 'Phone:', 0);
$pdf->Cell(0, 8, $purchase['seller_phone'], 0, 1);

// Calculate service dates
$transaction_date = strtotime($purchase['transaction_date']);
$end_date = strtotime('+2 months', $transaction_date);

// Service Details Section - reduced spacing
$pdf->Ln(3); // Reduced from 5 to 3
$pdf->SetFillColor(255, 87, 34, 0.1);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Free Service Package Details', 0, 1, 'L', true);
$pdf->SetFont('Arial', '', 10);

// Service Period Details in a table format - reduced height
$pdf->SetFillColor(255, 255, 255);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Cell(50, 8, 'Service Period:', 1, 0, 'L', true);
$pdf->Cell(0, 8, '2 Months', 1, 1, 'L', true);

$pdf->Cell(50, 8, 'Valid From:', 1, 0, 'L', true);
$pdf->Cell(0, 8, date('d M Y', $transaction_date), 1, 1, 'L', true);

$pdf->Cell(50, 8, 'Valid Until:', 1, 0, 'L', true);
$pdf->Cell(0, 8, date('d M Y', $end_date), 1, 1, 'L', true);

// Service Inclusions with icons - reduced spacing
$pdf->Ln(5); // Reduced from 10 to 5
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Service Inclusions:', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);

$services = array(
    'Regular Maintenance Check',
    'Oil Change',
    'Filter Replacement',
    'Brake Inspection',
    'General Vehicle Inspection'
);

foreach($services as $service) {
    $pdf->SetFillColor(255, 87, 34, 0.1);
    $pdf->Cell(5, 6, '', 0, 0); // Reduced height from 10 to 6
    $pdf->Cell(5, 6, chr(127), 0, 0); // Bullet point, reduced width from 8 to 5
    $pdf->Cell(0, 6, $service, 0, 1);
}

// Terms and Conditions in a box - reduced spacing
$pdf->Ln(5); // Reduced from 10 to 5
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('Arial', 'B', 11); // Reduced from 12 to 11
$pdf->Cell(0, 6, 'Terms and Conditions:', 0, 1, 'L');
$pdf->SetFont('Arial', '', 9); // Reduced from 10 to 9
$pdf->SetTextColor(80, 80, 80);
$pdf->MultiCell(0, 4, 'This complimentary service package is valid for two months from the date of purchase. All services must be scheduled in advance through our service center. Please contact our customer service department to book your appointment. Service availability may vary based on location and workshop capacity.', 1, 'L', true);

// Footer with signature - reduced spacing
$pdf->Ln(10); // Reduced from 20 to 10
$pdf->SetTextColor(51, 51, 51);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(95, 5, 'Generated on: ' . date('d M Y'), 0, 0);
$pdf->Cell(95, 5, 'Authorized Signature', 0, 1, 'R');

// Signature line
$pdf->SetDrawColor(100, 100, 100);
$pdf->Line(150, $pdf->GetY()+8, 190, $pdf->GetY()+8); // Reduced space from 10 to 8

// Output PDF
$pdf->Output('WheeledDeal_Service_Receipt.pdf', 'D'); 