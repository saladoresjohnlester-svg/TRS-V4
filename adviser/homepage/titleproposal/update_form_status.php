<?php
include '../../../connection.php';

header("Content-Type: application/json");

// Make sure session is started
session_start();

// Function to validate email address
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Custom error logging function for email issues
function logEmailError($message) {
    $logFile = __DIR__ . '/../../../email_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    // Also log to PHP error log
    error_log($message);
    
    // Write to custom log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Function to send email notification to student in the background
function sendEmailInBackground($email_data) {
    try {
        // Create a temporary file with unique name
        $temp_file = tempnam(sys_get_temp_dir(), 'email_');
        
        // Write email data to file
        file_put_contents($temp_file, json_encode($email_data));
        
        // Execute background PHP script to send email
        exec("php " . __DIR__ . "/../../send_email_background.php " . escapeshellarg($temp_file) . " > /dev/null 2>&1 &");
        
        return true;
    } catch (Exception $e) {
        logEmailError("Failed to prepare email for background sending: " . $e->getMessage());
        return false;
    }
}

function sendApprovalNotificationEmail($student_email, $student_name, $adviser_name, $form_details) {
    try {
        if (!isValidEmail($student_email)) {
            logEmailError("Invalid email address format: $student_email");
            return false;
        }
        
        $email_data = [
            'to' => $student_email,
            'to_name' => $student_name,
            'subject' => "Form Approved - Thesis Routing System",
            'body' => "<h2>Form Approved - Thesis Routing System</h2>
            <p>Dear $student_name,</p>
            <p>Your form titled \"$form_details[title]\" has been approved by $adviser_name.</p>
            <p>Here are the details:</p>
            <ul>
                <li>Form ID: $form_details[form_id]</li>
                <li>Submission Date: $form_details[submission_date]</li>
                <li>Status: Approved</li>
            </ul>
            <p>Best regards,<br>Thesis Routing System</p>",
            'smtp_settings' => [
                'host' => 'smtp.gmail.com',
                'username' => 'smcctrs@gmail.com',
                'password' => 'YOUR_GMAIL_APP_PASSWORD_HERE',
                'port' => 587,
                'secure' => 'tls'
            ]
        ];
        
        return sendEmailInBackground($email_data);
    } catch (Exception $e) {
        logEmailError("Failed to prepare approval notification email: " . $e->getMessage());
        return false;
    }
}

function sendAllApprovedNotification($student_email, $student_name, $adviser_name, $feedback_summary, $route_number) {
    try {
        if (!isValidEmail($student_email)) {
            logEmailError("Invalid email address format: $student_email");
            return false;
        }
        
        $email_data = [
            'to' => $student_email,
            'to_name' => $student_name,
            'subject' => "All Feedback Approved - Thesis Routing System",
            'body' => "<h2>All Feedback Approved - Thesis Routing System</h2>
            <p>Dear $student_name,</p>
            <p>All feedback for your thesis has been approved by $adviser_name.</p>
            <p>Here is a summary of the feedback:</p>
            <p>$feedback_summary</p>
            <p>Route Number: $route_number</p>
            <p>Best regards,<br>Thesis Routing System</p>",
            'smtp_settings' => [
                'host' => 'smtp.gmail.com',
                'username' => 'smcctrs@gmail.com',
                'password' => 'cplw gwfe vxcy naoi',
                'port' => 587,
                'secure' => 'tls'
            ]
        ];
        
        return sendEmailInBackground($email_data);
    } catch (Exception $e) {
        logEmailError("Failed to prepare all approved notification email: " . $e->getMessage());
        return false;
    }
}

// Get data from either POST form data or JSON input
$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the request has a JSON content type
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // Read JSON input
        $input = file_get_contents("php://input");
        error_log("Raw JSON input: " . $input);
        $data = json_decode($input, true);
        error_log("Decoded JSON data: " . print_r($data, true));
    } else {
        // Use regular POST data
        error_log("Using regular POST data");
        $data = $_POST;
    }
}

// Check if we have the required data
if (!isset($data['id']) || !isset($data['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

$form_id = $data['id'];
$new_status = $data['status'];

// Get adviser ID from session
$adviser_id = isset($_SESSION['adviser_id']) ? $_SESSION['adviser_id'] : null;
$adviser_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Adviser';

if (!$adviser_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Adviser not logged in'
    ]);
    exit;
}

// Update the form status in the database
$stmt = $conn->prepare("UPDATE proposal_monitoring_form SET status = ? WHERE id = ? AND adviser_id = ?");
$stmt->bind_param("sis", $new_status, $form_id, $adviser_id);
$result = $stmt->execute();

if ($result) {
    // Get the form details to use in email notification
    $formQuery = $conn->prepare("
        SELECT 
            student_id, 
            chapter, 
            paragraph_number, 
            page_number, 
            routeNumber,
            route2_id
        FROM 
            proposal_monitoring_form 
        WHERE 
            id = ?
    ");
    $formQuery->bind_param("i", $form_id);
    $formQuery->execute();
    $formResult = $formQuery->get_result();
    
    if ($formResult->num_rows > 0) {
        $formData = $formResult->fetch_assoc();
        $student_id = $formData['student_id'];
        $route2_id = $formData['route2_id'];
        
        // Get student email for notification
        $studentQuery = $conn->prepare("SELECT email, fullname FROM student WHERE student_id = ?");
        $studentQuery->bind_param("s", $student_id);
        $studentQuery->execute();
        $studentResult = $studentQuery->get_result();
        
        if ($studentResult->num_rows > 0) {
            $studentData = $studentResult->fetch_assoc();
            $student_email = $studentData['email'];
            $student_name = $studentData['fullname'];
            
            // Send email notification if status is Approved
            if ($new_status === 'Approved') {
                // Send individual approval notification
                sendEmailInBackground($student_email, $student_name, $adviser_name, $formData);
                
                // Check if all forms for this student and adviser are approved
                $allFormsQuery = $conn->prepare("
                    SELECT 
                        COUNT(*) as total, 
                        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved 
                    FROM 
                        proposal_monitoring_form 
                    WHERE 
                        student_id = ? AND 
                        adviser_id = ? AND
                        route2_id = ?
                ");
                $allFormsQuery->bind_param("ssi", $student_id, $adviser_id, $route2_id);
                $allFormsQuery->execute();
                $allFormsResult = $allFormsQuery->get_result();
                $allFormsData = $allFormsResult->fetch_assoc();
                
                $all_approved = ($allFormsData['total'] > 0 && $allFormsData['total'] == $allFormsData['approved']);
                
                if ($all_approved) {
                    // Get a summary of the feedback
                    $feedbackQuery = $conn->prepare("
                        SELECT GROUP_CONCAT(chapter SEPARATOR ', ') as chapters
                        FROM proposal_monitoring_form 
                        WHERE student_id = ? AND adviser_id = ? AND route2_id = ?
                    ");
                    $feedbackQuery->bind_param("ssi", $student_id, $adviser_id, $route2_id);
                    $feedbackQuery->execute();
                    $feedbackResult = $feedbackQuery->get_result();
                    $feedbackData = $feedbackResult->fetch_assoc();
                    
                    $formData['feedback_summary'] = "All feedback for chapters " . $feedbackData['chapters'] . " has been approved";
                    
                    // Send comprehensive approval notification
                    sendEmailInBackground($student_email, $student_name, $adviser_name, $formData, true);
                    
                    echo json_encode([
                        'success' => true,
                        'all_approved' => true,
                        'message' => 'Status updated successfully. All forms are now approved.'
                    ]);
                    exit;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'all_approved' => false,
        'message' => 'Status updated successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
