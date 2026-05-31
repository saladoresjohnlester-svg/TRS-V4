<?php
session_start();
include '../../../connection.php';

header("Content-Type: application/json");

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check if panel member is logged in
if (!isset($_SESSION['panel_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied: not authenticated']);
    exit;
}

// Function to validate email address
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Function to log email errors
function logEmailError($message) {
    $logFile = __DIR__ . '/../../../email_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    // Also log to PHP error log
    error_log($message);
    
    // Write to custom log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Function to send approval notification email to student in background process
function sendApprovalNotificationEmail($student_email, $student_name, $panel_name, $form_details) {
    try {
        // Validate email address first
        if (!isValidEmail($student_email)) {
            logEmailError("Invalid email address format: $student_email");
            return false;
        }
        
        // Prepare email data
        $email_data = [
            'to' => $student_email,
            'student_name' => $student_name,
            'panel_name' => $panel_name,
            'feedback_summary' => $form_details['feedback'],
            'route_number' => $form_details['routeNumber'],
            'is_final' => true,
            'subject' => "Final Defense - Panel Form Approved - Thesis Routing System",
            'form_details' => $form_details
        ];
        
        // Create temporary file
        $temp_file = tempnam(sys_get_temp_dir(), 'trs_email_');
        if ($temp_file === false) {
            logEmailError("Failed to create temporary file for email data");
            return false;
        }
        
        // Write email data to file
        if (!file_put_contents($temp_file, json_encode($email_data))) {
            logEmailError("Failed to write email data to temporary file");
            unlink($temp_file);
            return false;
        }
        
        // Execute background PHP script
        $cmd = escapeshellcmd("php " . __DIR__ . '/../../../send_email_background.php "' . $temp_file . '"');
        exec($cmd . ' > /dev/null 2>&1 &');
        
        return true;
    } catch (Exception $e) {
        logEmailError("Error preparing background email: " . $e->getMessage());
        return false;
    }
}

// Read JSON from request body
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['id']) || !isset($data['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$form_id = intval($data['id']);
$new_status = $conn->real_escape_string($data['status']);
$panel_id = $_SESSION['panel_id'];

// Only proceed if status is valid
if (!in_array($new_status, ['Pending', 'Approved', 'For Revision'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

// Check if the panel is authorized for this form
$checkQuery = "SELECT panel_id FROM final_monitoring_form WHERE id = ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("i", $form_id);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Form not found']);
    exit;
}

$row = $result->fetch_assoc();
if ($row['panel_id'] != $panel_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You are not allowed to approve the status because you are not the assigned Panel']);
    exit;
}

// Update the form status in the database
$query = "UPDATE final_monitoring_form SET status = ? WHERE id = ? AND panel_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sii", $new_status, $form_id, $panel_id);

if (!$stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->close();

// If status was changed to "Approved", send an email notification to the student
$email_sent = false;
if ($new_status === 'Approved') {
    // Get form details and student information
    $getFormStmt = $conn->prepare("
        SELECT 
            p.student_id, 
            p.chapter, 
            p.feedback, 
            p.paragraph_number, 
            p.page_number, 
            p.panel_name,
            p.routeNumber,
            p.route1_id,
            p.route2_id,
            p.route3_id,
            p.finaldocu_id,
            s.fullname AS student_name,
            s.email AS student_email
        FROM 
            final_monitoring_form p
        JOIN 
            student s ON p.student_id = s.student_id
        WHERE 
            p.id = ?
    ");
    
    $getFormStmt->bind_param("i", $form_id);
    $getFormStmt->execute();
    $result = $getFormStmt->get_result();
    
    if ($result->num_rows > 0) {
        $formData = $result->fetch_assoc();
        $student_email = $formData['student_email'];
        $student_name = $formData['student_name'];
        $panel_name = $formData['panel_name'];
        
        $form_details = [
            'chapter' => $formData['chapter'],
            'feedback' => $formData['feedback'],
            'paragraph_number' => $formData['paragraph_number'],
            'page_number' => $formData['page_number'],
            'routeNumber' => $formData['routeNumber']
        ];
        
        // Send email notification
        $email_sent = sendApprovalNotificationEmail($student_email, $student_name, $panel_name, $form_details);
    }
    
    $getFormStmt->close();
}

// Return success response with email status
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'email_sent' => $email_sent,
    'message' => $email_sent ? 'Status updated and email notification sent' : 'Status updated but could not send email notification'
]);
?>
