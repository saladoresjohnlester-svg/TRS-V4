<?php
// Start the session at the beginning of the script
session_start();
include '../../../connection.php';
require '../../../vendor/autoload.php';
use Fpdf\Fpdf;

// Initialize session variables
$adviserName = $_GET['adviserName'] ?? 'Unknown Adviser';
$studentNames = isset($_GET['student']) ? explode(',', urldecode($_GET['student'])) : [];
$studentNames = array_filter(array_map('trim', $studentNames)); // Remove empty entries and trim spaces

// Database connection

// Ensure session contains student_id
if (!isset($_SESSION['student_id'])) {
    die("Student ID not found in session.");
}

$student_id = $_SESSION['student_id']; // Assuming student_id is stored in session

// Check if all Route 1 to Route 3 statuses are approved
$allApproved = true;

$stmt = $conn->prepare("SELECT status FROM proposal_monitoring_form WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$allApproved = true;

while ($row = $result->fetch_assoc()) {
    if ($row['status'] != 'Approved') {
        $allApproved = false;
        break; // Exit the loop as soon as we find a non-approved status
    }
}
$stmt->close();

// Check if necessary files have been uploaded
$hasRequiredFiles = false;

// Check if Route 1 file exists
$stmt = $conn->prepare("SELECT COUNT(*) FROM route1proposal_files WHERE student_id = ? AND docuRoute1 IS NOT NULL");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$stmt->bind_result($docuRoute1);
$stmt->fetch();
$stmt->close();

// Check if Route 2 file exists
$stmt = $conn->prepare("SELECT COUNT(*) FROM route2proposal_files WHERE student_id = ? AND docuRoute2 IS NOT NULL");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$stmt->bind_result($docuRoute2);
$stmt->fetch();
$stmt->close();

// Check if Route 3 file exists
$stmt = $conn->prepare("SELECT COUNT(*) FROM route3proposal_files WHERE student_id = ? AND docuRoute3 IS NOT NULL");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$stmt->bind_result($docuRoute3);
$stmt->fetch();
$stmt->close();

// Check if Final Document file exists
$stmt = $conn->prepare("SELECT COUNT(*) FROM finaldocuproposal_files WHERE student_id = ? AND finaldocu IS NOT NULL");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$stmt->bind_result($finaldocu);
$stmt->fetch();
$stmt->close();

// If all statuses are approved and all files are uploaded, proceed with PDF generation
if ($allApproved && $docuRoute1 && $docuRoute2 && $docuRoute3 && $finaldocu) {
    $date = date('F j, Y'); // Current date

    // Create PDF
    $pdf = new FPDF();
    $pdf->AddPage();

    // Set Font
    $pdf->SetFont('Arial', '', 9);

    // Try to add logos - with error handling
    try {
        // Left logo
        $pdf->Image('logo.png', 10, 10, 20);
        
        // Right logo - place it at the right side of the page
        // Assuming letter size paper (8.5 x 11 inches)
        // FPDF default is mm, so right edge is around 210mm
        $pdf->Image('socotecs.png', 170, 10, 30);
    } catch (Exception $e) {
        // Just continue without the image if there's an error
    }
    
    // Position for centered header text - move y position to account for logo height
    $pdf->SetXY(35, 10);

    // Header Text - centered between the two logos (from x=35 to x=175)
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(140, 7, 'Saint Michael College of Caraga', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(35);
    $pdf->Cell(140, 6, 'Brgy. 4, Nasipit, Agusan del Norte, Philippines', 0, 1, 'C');
    
    $pdf->SetX(35);
    $pdf->Cell(140, 6, 'Tel. Nos. +63 085 343-3251 / +63 085 283-3113', 0, 1, 'C');
    
    $pdf->SetX(35);
    $pdf->Cell(140, 6, 'Fax No. +63 085 808-0892', 0, 1, 'C');
    
    $pdf->SetX(35);
    $pdf->Cell(140, 6, 'www.smccnasipit.edu.ph', 0, 1, 'C');
    
    $pdf->Ln(15); // Space after header, adjusted to account for logo height

    // Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'CERTIFICATE OF ENDORSEMENT', 0, 1, 'C');
    $pdf->Ln(7);

    // Body Text
    $pdf->SetFont('Arial', '', 11);
    $body = "This is to certify that the following researchers have successfully completed a thorough checking and assessment of their software system and manuscript under my supervision. Therefore, I, $adviserName, as their Capstone/Thesis Adviser, hereby endorse them to proceed with their Final Oral Defense for the completion of their Capstone Project/Thesis in the degree of Bachelor of Science in Information Technology.";
    $pdf->MultiCell(0, 8, $body);
    $pdf->Ln(5);

    // Student List
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Researchers:', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($studentNames as $index => $name) {
        $pdf->Cell(0, 8, ($index + 1) . ". " . $name, 0, 1);
    }
    $pdf->Ln(5);

    // Additional Body Text
    $body2 = "Their project/thesis has met the required standards and criteria set forth by the College of Computing and Information Sciences, and I am confident in the quality and academic rigor of their work.";
    $pdf->MultiCell(0, 8, $body2);
    $pdf->Ln(10);

    // Adviser Signature
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Endorsed by:', 0, 1);
    $pdf->Cell(80, 8, $adviserName, 'B', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(80, 8, 'Capstone Adviser', 0, 1);
    $pdf->Cell(80, 8, $date, 0, 1);
    $pdf->Ln(10);

    // Instructor Approval
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Approved by:', 0, 1);
    $pdf->Cell(0, 8, 'MARLON JUHN M. TIMOGAN, MIT', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'Capstone Project/Thesis Instructor', 0, 1);
    $pdf->Cell(0, 8, $date, 0, 1);

    // Output PDF for download
    $pdf->Output('D', 'Certificate_of_Endorsement.pdf');
} else {
    echo "<script>alert('You cannot download the endorsement until all files are uploaded and approved for Route 1, Route 2, Route 3, and the Final Document.'); window.history.back();</script>";
}

$conn->close();
?>