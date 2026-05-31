<?php
session_start();

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

include '../../../connection.php';

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

// Function to send approval notification email to student
function sendApprovalNotificationEmail($student_email, $student_name, $panel_name, $form_details) {
    try {
        // Validate email address first
        if (!isValidEmail($student_email)) {
            logEmailError("Invalid email address format: $student_email");
            return false;
        }
        
        // Check for Composer autoloader
        $autoloader_path = __DIR__ . '/../../../vendor/autoload.php';
        
        if (!file_exists($autoloader_path)) {
            logEmailError("PHPMailer autoloader not found at: $autoloader_path. Please install PHPMailer via Composer.");
            return false;
        }
        
        // Include the autoloader
        require_once $autoloader_path;
        
        // Create instance of PHPMailer
        $mail = new PHPMailer(true);

        // Server settings
        $mail->SMTPDebug  = 0;  // Enable verbose debug output (0 for no output, 2 for verbose)
        $mail->Debugoutput = function($str, $level) { logEmailError("PHPMailer [$level]: $str"); };
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'smcctrs@gmail.com'; // Your Gmail
        $mail->Password   = 'cplw gwfe vxcy naoi';   // App password for smcctrs@gmail.com
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8'; // Ensure proper character encoding
        
        // Recommended Gmail-specific settings
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set Timeout values
        $mail->Timeout    = 60; // Increased HTTP timeout in seconds
        $mail->SMTPKeepAlive = true; // SMTP keep alive

        // Sender and recipient settings
        $mail->setFrom('smcctrs@gmail.com', 'Thesis Routing System', false);
        $mail->addReplyTo('smcctrs@gmail.com', 'Thesis Routing System');
        $mail->addAddress($student_email, $student_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Panel Form Approved - Thesis Routing System";
        
        // Get server URL dynamically
        $base_url = 'https://capstone.smccnasipit.edu.ph/';
        $login_url = $base_url . 'trs/student/login.php';
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                <h2 style='color: #4366b3; text-align: center;'>Thesis Routing System Notification</h2>
                <p>Dear <strong>{$student_name}</strong>,</p>
                <p>Your panel member <strong>{$panel_name}</strong> has <span style='color: green; font-weight: bold;'>APPROVED</span> your submission.</p>
                <p><strong>Form Details:</strong></p>
                <ul>
                    <li><strong>Chapter:</strong> {$form_details['chapter']}</li>
                    <li><strong>Feedback:</strong> {$form_details['feedback']}</li>
                    <li><strong>Paragraph:</strong> {$form_details['paragraph_number']}</li>
                    <li><strong>Page:</strong> {$form_details['page_number']}</li>
                    <li><strong>Route:</strong> {$form_details['routeNumber']}</li>
                </ul>
                <p>Please log in to the Thesis Routing System to review the approval and proceed with your next steps.</p>
                <div style='margin-top: 30px; text-align: center;'>
                    <a href='{$login_url}' style='background-color: #4366b3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to System</a>
                </div>
                <p style='margin-top: 10px; text-align: center;'>If the button above doesn't work, copy and paste this URL into your browser: <br><a href='{$login_url}'>{$login_url}</a></p>
                <p style='margin-top: 30px; font-size: 12px; color: #777; text-align: center;'>This is an automated message from the Thesis Routing System. Please do not reply to this email.</p>
            </div>
        ";
        $mail->AltBody = "Dear {$student_name}, Your panel member {$panel_name} has APPROVED your submission for Chapter: {$form_details['chapter']}, Feedback: {$form_details['feedback']}, Paragraph: {$form_details['paragraph_number']}, Page: {$form_details['page_number']}, Route: {$form_details['routeNumber']}. Please login at: {$login_url} to review.";

        // Add additional headers that may help with deliverability
        $mail->addCustomHeader('X-Mailer', 'Thesis Routing System');
        $mail->addCustomHeader('X-Priority', '3');

        $mail->send();
        error_log("Approval email sent successfully to student: $student_email using PHPMailer");
        return true;
    } catch (Exception $e) {
        $errorMsg = "Approval email could not be sent to student: $student_email. ";
        
        if (isset($mail)) {
            $errorMsg .= "PHPMailer Error: " . $mail->ErrorInfo;
            
            // Log SMTP debug info for connection issues
            if (strpos($mail->ErrorInfo, 'SMTP connect() failed') !== false) {
                $errorMsg .= ". Possible connection issue with SMTP server.";
            } else if (strpos($mail->ErrorInfo, 'authentication failed') !== false) {
                $errorMsg .= ". Authentication issue - check username and password.";
            } else if (strpos($mail->ErrorInfo, 'Invalid address') !== false) {
                $errorMsg .= ". Invalid email address format.";
            } else if (strpos($mail->ErrorInfo, 'Could not authenticate') !== false) {
                $errorMsg .= ". Gmail may be blocking this attempt. Check Gmail settings and app password.";
            } else if (strpos($mail->ErrorInfo, 'Recipient') !== false) {
                $errorMsg .= ". There's an issue with the recipient address. Check if the address is valid.";
            }
        } else {
            $errorMsg .= "Exception: " . $e->getMessage();
        }
        
        logEmailError($errorMsg);
        return false;
    }
}

// Get and sanitize input data
$input = json_decode(file_get_contents('php://input'), true);

// Handle approve_all action
if (isset($input['approve_all']) && $input['approve_all'] === true) {
    if (!isset($input['route3_id']) || !isset($input['student_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing required parameters for approve_all']);
        exit;
    }
    $route3_id = intval($input['route3_id']);
    $student_id = $conn->real_escape_string($input['student_id']);
    $panel_id = $_SESSION['panel_id'];

    // Update all forms for this panel member and route3_id to 'Approved'
    $updateAllStmt = $conn->prepare("UPDATE proposal_monitoring_form SET status = 'Approved' WHERE route3_id = ? AND panel_id = ? AND status != 'Approved'");
    $updateAllStmt->bind_param("is", $route3_id, $panel_id);
    $result = $updateAllStmt->execute();
    $affected = $updateAllStmt->affected_rows;
    $updateAllStmt->close();

    if (!$result) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => "Updated $affected forms to Approved"]);
    exit;
}

// Regular single form update
if (!$input || !isset($input['id']) || !isset($input['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$form_id = intval($input['id']);
$new_status = $conn->real_escape_string($input['status']);

// Only proceed if status is valid
if (!in_array($new_status, ['Pending', 'Approved', 'For Revision'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

// Get the panel_id from session
$panel_id = $_SESSION['panel_id'];

// Check if the form belongs to the current panel member before updating
$checkOwnershipStmt = $conn->prepare("SELECT COUNT(*) as form_count FROM proposal_monitoring_form WHERE id = ? AND panel_id = ?");
$checkOwnershipStmt->bind_param("is", $form_id, $panel_id);
$checkOwnershipStmt->execute();
$ownershipResult = $checkOwnershipStmt->get_result();
$ownershipData = $ownershipResult->fetch_assoc();
$checkOwnershipStmt->close();

if ($ownershipData['form_count'] == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You can only update the status of forms you submitted yourself']);
    exit;
}

// Update the form status in the database
$updateStmt = $conn->prepare("UPDATE proposal_monitoring_form SET status = ? WHERE id = ? AND panel_id = ?");
$updateStmt->bind_param("sis", $new_status, $form_id, $panel_id);
$result = $updateStmt->execute();

if (!$result) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$updateStmt->close();

// If status was changed to "Approved", send an email notification to the student
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
            p.panel_id,
            s.fullname AS student_name,
            s.email AS student_email
        FROM 
            proposal_monitoring_form p
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
        $panel_id = $formData['panel_id'];
        $route1_id = $formData['route1_id'];
        $route2_id = $formData['route2_id'];
        $route3_id = $formData['route3_id'];
        $finaldocu_id = $formData['finaldocu_id'];
        
        $form_details = [
            'chapter' => $formData['chapter'],
            'paragraph_number' => $formData['paragraph_number'],
            'page_number' => $formData['page_number'],
            'routeNumber' => $formData['routeNumber'],
            'feedback' => $formData['feedback'] // Include feedback in the form details
        ];

        // Always send individual approval notification first - this ensures notification happens
        $singleEmailSent = false;
        if (isValidEmail($student_email)) {
            $singleEmailSent = sendApprovalNotificationEmail($student_email, $student_name, $panel_name, $form_details);
            if ($singleEmailSent) {
                error_log("Individual approval email sent successfully to student: $student_email");
            } else {
                error_log("Failed to send individual approval email to student: $student_email");
            }
        } else {
            error_log("Invalid student email: $student_email");
        }

        // Check if all panel feedback for the relevant route is approved
        $allApproved = false;
        $debugInfo = [];
        
        if ($route2_id) {
            // Query ALL forms first to see what's in the database
            $debugStmt = $conn->prepare("
                SELECT id, status, chapter 
                FROM proposal_monitoring_form 
                WHERE route2_id = ? AND panel_id = ?
                ORDER BY id
            ");
            $debugStmt->bind_param("is", $route2_id, $panel_id);
            $debugStmt->execute();
            $debugResult = $debugStmt->get_result();
            
            while ($debugRow = $debugResult->fetch_assoc()) {
                $debugInfo[] = "Form ID: {$debugRow['id']}, Status: {$debugRow['status']}, Chapter: {$debugRow['chapter']}";
            }
            
            error_log("DEBUG - All forms in database for route2_id=$route2_id, panel_id=$panel_id:");
            foreach ($debugInfo as $info) {
                error_log("  " . $info);
            }
            
            // Continue with check but log results without sending email
            $checkStmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total, 
                    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status != 'Approved' THEN 1 ELSE 0 END) as not_approved
                FROM proposal_monitoring_form 
                WHERE route2_id = ? AND panel_id = ?
            ");
            $checkStmt->bind_param("is", $route2_id, $panel_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkData = $checkResult->fetch_assoc();
            
            error_log("DEBUG Stats: route2_id=$route2_id, panel_id=$panel_id, total={$checkData['total']}, approved={$checkData['approved']}, not_approved={$checkData['not_approved']}");
            
            // Add a more complex check with diagnostics
            $condition1 = $checkData['total'] >= 2;
            $condition2 = $checkData['not_approved'] == 0;
            $condition3 = $checkData['approved'] >= 2;
            
            error_log("Condition checks: multiple forms: " . ($condition1 ? "YES" : "NO") . 
                     ", no unapproved forms: " . ($condition2 ? "YES" : "NO") . 
                     ", multiple approved: " . ($condition3 ? "YES" : "NO"));
            
            if ($condition1 && $condition2 && $condition3) {
                $allApproved = true;
                error_log("All forms for route2_id $route2_id by panel_id $panel_id are approved. Total: {$checkData['total']}, Approved: {$checkData['approved']}");
                
                // Compile a summary of all approved feedback for this route
                $summaryStmt = $conn->prepare("
                    SELECT chapter, feedback
                    FROM proposal_monitoring_form 
                    WHERE route2_id = ? AND panel_id = ? AND status = 'Approved'
                    ORDER BY date_submitted DESC
                ");
                $summaryStmt->bind_param("is", $route2_id, $panel_id);
                $summaryStmt->execute();
                $summaryResult = $summaryStmt->get_result();
                
                $approvedChapters = [];
                $feedbackDetails = [];
                while ($summaryRow = $summaryResult->fetch_assoc()) {
                    $approvedChapters[] = $summaryRow['chapter'];
                    $feedbackDetails[] = [
                        'chapter' => $summaryRow['chapter'],
                        'feedback' => $summaryRow['feedback']
                    ];
                }
                
                // Create a summary message for the email
                $feedbackSummary = "All feedback has been approved for chapters: " . implode(", ", $approvedChapters);
                
                // Send notification email about all approvals
                if (isValidEmail($student_email)) {
                    $emailSent = sendAllApprovedNotification($student_email, $student_name, $panel_name, $feedbackSummary, $formData['routeNumber'], $feedbackDetails);
                    
                    if ($emailSent) {
                        error_log("All panel approvals notification email sent to student: $student_email");
                    } else {
                        error_log("Failed to send all panel approvals notification email to student: $student_email");
                    }
                }
            } else {
                error_log("Not all forms are approved yet for route2_id $route2_id by panel $panel_id. Total: {$checkData['total']}, Approved: {$checkData['approved']}");
            }
        } elseif ($route3_id) {
            // Similar logic for route3 as for route2
            $debugStmt = $conn->prepare("
                SELECT id, status, chapter 
                FROM proposal_monitoring_form 
                WHERE route3_id = ? AND panel_id = ?
                ORDER BY id
            ");
            $debugStmt->bind_param("is", $route3_id, $panel_id);
            $debugStmt->execute();
            $debugResult = $debugStmt->get_result();
            
            while ($debugRow = $debugResult->fetch_assoc()) {
                $debugInfo[] = "Form ID: {$debugRow['id']}, Status: {$debugRow['status']}, Chapter: {$debugRow['chapter']}";
            }
            
            error_log("DEBUG - All forms in database for route3_id=$route3_id, panel_id=$panel_id:");
            foreach ($debugInfo as $info) {
                error_log("  " . $info);
            }
            
            // Check status of all route3 forms by this panel member
            $checkStmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total, 
                    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status != 'Approved' THEN 1 ELSE 0 END) as not_approved
                FROM proposal_monitoring_form 
                WHERE route3_id = ? AND panel_id = ?
            ");
            $checkStmt->bind_param("is", $route3_id, $panel_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkData = $checkResult->fetch_assoc();
            
            error_log("DEBUG Stats: route3_id=$route3_id, panel_id=$panel_id, total={$checkData['total']}, approved={$checkData['approved']}, not_approved={$checkData['not_approved']}");
            
            // Add a more complex check with diagnostics
            $condition1 = $checkData['total'] >= 2;
            $condition2 = $checkData['not_approved'] == 0;
            $condition3 = $checkData['approved'] >= 2;
            
            error_log("Condition checks: multiple forms: " . ($condition1 ? "YES" : "NO") . 
                     ", no unapproved forms: " . ($condition2 ? "YES" : "NO") . 
                     ", multiple approved: " . ($condition3 ? "YES" : "NO"));
            
            if ($condition1 && $condition2 && $condition3) {
                $allApproved = true;
                error_log("All forms for route3_id $route3_id by panel_id $panel_id are approved. Total: {$checkData['total']}, Approved: {$checkData['approved']}");
                
                // Compile a summary of all approved feedback for this route
                $summaryStmt = $conn->prepare("
                    SELECT chapter, feedback
                    FROM proposal_monitoring_form 
                    WHERE route3_id = ? AND panel_id = ? AND status = 'Approved'
                    ORDER BY date_submitted DESC
                ");
                $summaryStmt->bind_param("is", $route3_id, $panel_id);
                $summaryStmt->execute();
                $summaryResult = $summaryStmt->get_result();
                
                $approvedChapters = [];
                $feedbackDetails = [];
                while ($summaryRow = $summaryResult->fetch_assoc()) {
                    $approvedChapters[] = $summaryRow['chapter'];
                    $feedbackDetails[] = [
                        'chapter' => $summaryRow['chapter'],
                        'feedback' => $summaryRow['feedback']
                    ];
                }
                
                // Create a summary message for the email
                $feedbackSummary = "All feedback has been approved for chapters: " . implode(", ", $approvedChapters);
                
                // Send notification email about all approvals
                if (isValidEmail($student_email)) {
                    $emailSent = sendAllApprovedNotification($student_email, $student_name, $panel_name, $feedbackSummary, $formData['routeNumber'], $feedbackDetails);
                    
                    if ($emailSent) {
                        error_log("All panel approvals notification email sent to student: $student_email");
                    } else {
                        error_log("Failed to send all panel approvals notification email to student: $student_email");
                    }
                }
            } else {
                error_log("Not all forms are approved yet for route3_id $route3_id by panel $panel_id. Total: {$checkData['total']}, Approved: {$checkData['approved']}");
            }
        } elseif ($route1_id) {
            // For route1, send individual notification only
            error_log("This form is for route 1, panel_id=$panel_id");
        } elseif ($finaldocu_id) {
            // Handle finaldocu similar to route3
            $debugStmt = $conn->prepare("
                SELECT id, status, chapter 
                FROM proposal_monitoring_form 
                WHERE finaldocu_id = ? AND panel_id = ?
                ORDER BY id
            ");
            $debugStmt->bind_param("is", $finaldocu_id, $panel_id);
            $debugStmt->execute();
            $debugResult = $debugStmt->get_result();
            
            while ($debugRow = $debugResult->fetch_assoc()) {
                $debugInfo[] = "Form ID: {$debugRow['id']}, Status: {$debugRow['status']}, Chapter: {$debugRow['chapter']}";
            }
            
            error_log("DEBUG - All forms in database for finaldocu_id=$finaldocu_id, panel_id=$panel_id:");
            foreach ($debugInfo as $info) {
                error_log("  " . $info);
            }
            
            // Check status of all finaldocu forms by this panel member
            $checkStmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total, 
                    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status != 'Approved' THEN 1 ELSE 0 END) as not_approved
                FROM proposal_monitoring_form 
                WHERE finaldocu_id = ? AND panel_id = ?
            ");
            $checkStmt->bind_param("is", $finaldocu_id, $panel_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkData = $checkResult->fetch_assoc();
            
            error_log("DEBUG Stats: finaldocu_id=$finaldocu_id, panel_id=$panel_id, total={$checkData['total']}, approved={$checkData['approved']}, not_approved={$checkData['not_approved']}");
            
            // Add a more complex check with diagnostics
            $condition1 = $checkData['total'] >= 2;
            $condition2 = $checkData['not_approved'] == 0;
            $condition3 = $checkData['approved'] >= 2;
            
            error_log("Condition checks: multiple forms: " . ($condition1 ? "YES" : "NO") . 
                     ", no unapproved forms: " . ($condition2 ? "YES" : "NO") . 
                     ", multiple approved: " . ($condition3 ? "YES" : "NO"));
            
            if ($condition1 && $condition2 && $condition3) {
                $allApproved = true;
                error_log("All forms for finaldocu_id $finaldocu_id by panel_id $panel_id are approved. Total: {$checkData['total']}, Approved: {$checkData['approved']}");
                
                // Compile a summary of all approved feedback for this route
                $summaryStmt = $conn->prepare("
                    SELECT chapter, feedback
                    FROM proposal_monitoring_form 
                    WHERE finaldocu_id = ? AND panel_id = ? AND status = 'Approved'
                    ORDER BY date_submitted DESC
                ");
                $summaryStmt->bind_param("is", $finaldocu_id, $panel_id);
                $summaryStmt->execute();
                $summaryResult = $summaryStmt->get_result();
                
                $approvedChapters = [];
                $feedbackDetails = [];
                while ($summaryRow = $summaryResult->fetch_assoc()) {
                    $approvedChapters[] = $summaryRow['chapter'];
                    $feedbackDetails[] = [
                        'chapter' => $summaryRow['chapter'],
                        'feedback' => $summaryRow['feedback']
                    ];
                }
                
                // Create a summary message for the email
                $feedbackSummary = "All feedback has been approved for chapters: " . implode(", ", $approvedChapters);
                
                // Send notification email about all approvals
                if (isValidEmail($student_email)) {
                    $emailSent = sendAllApprovedNotification($student_email, $student_name, $panel_name, $feedbackSummary, "Final Document", $feedbackDetails);
                    
                    if ($emailSent) {
                        error_log("All panel approvals notification email sent to student: $student_email for Final Document");
                    } else {
                        error_log("Failed to send all panel approvals notification email to student: $student_email for Final Document");
                    }
                }
            } else {
                error_log("Not all forms are approved yet for finaldocu_id $finaldocu_id by panel $panel_id. Total: {$checkData['total']}, Approved: {$checkData['approved']}");
            }
        }
        
        // Always return success
        header('Content-Type: application/json');
        echo json_encode([
            "success" => true, 
            "message" => "Status updated successfully for form ID $form_id.", 
            "all_approved" => $allApproved,
            "email_sent" => $singleEmailSent
        ]);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Status updated successfully but failed to retrieve student information']);
        exit;
    }
    
    $getFormStmt->close();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    exit;
}

// Function to send comprehensive approval notification email to student
function sendAllApprovedNotification($student_email, $student_name, $panel_name, $feedback_summary, $route_number, $feedbackDetails = []) {
    try {
        // Validate email address first
        if (!isValidEmail($student_email)) {
            logEmailError("Invalid email address format: $student_email");
            return false;
        }
        
        // Check for Composer autoloader
        $autoloader_path = __DIR__ . '/../../../vendor/autoload.php';
        
        if (!file_exists($autoloader_path)) {
            logEmailError("PHPMailer autoloader not found at: $autoloader_path. Please install PHPMailer via Composer.");
            return false;
        }
        
        // Include the autoloader
        require_once $autoloader_path;
        
        // Create instance of PHPMailer
        $mail = new PHPMailer(true);

        // Server settings
        $mail->SMTPDebug  = 0;  // Enable verbose debug output (0 for no output, 2 for verbose)
        $mail->Debugoutput = function($str, $level) { logEmailError("PHPMailer [$level]: $str"); };
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'smcctrs@gmail.com'; // Your Gmail
        $mail->Password   = 'YOUR_GMAIL_APP_PASSWORD_HERE';   // App password for smcctrs@gmail.com
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8'; // Ensure proper character encoding
        
        // Recommended Gmail-specific settings
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set Timeout values
        $mail->Timeout    = 60; // Increased HTTP timeout in seconds
        $mail->SMTPKeepAlive = true; // SMTP keep alive

        // Sender and recipient settings
        $mail->setFrom('smcctrs@gmail.com', 'Thesis Routing System', false);
        $mail->addReplyTo('smcctrs@gmail.com', 'Thesis Routing System');
        $mail->addAddress($student_email, $student_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "All Panel Feedback Approved - Thesis Routing System";
        
        // Get server URL dynamically
        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        $server_port = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != '80' ? ':' . $_SERVER['SERVER_PORT'] : '';
        $http_protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $base_url = $http_protocol . '://' . $server_name . $server_port;
        
        $login_url = $base_url . '/TRS/student/';
        
        // Generate feedback details HTML
        $feedbackDetailsHtml = "";
        if (!empty($feedbackDetails)) {
            $feedbackDetailsHtml = "<h3>Approved Feedback Details:</h3><ul>";
            foreach ($feedbackDetails as $detail) {
                $feedbackDetailsHtml .= "<li><strong>Chapter {$detail['chapter']}:</strong> {$detail['feedback']}</li>";
            }
            $feedbackDetailsHtml .= "</ul>";
        }
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                <h2 style='color: #4366b3; text-align: center;'>Thesis Routing System - All Panel Feedback Approved</h2>
                <p>Dear <strong>{$student_name}</strong>,</p>
                <p>Congratulations! Your panel member <strong>{$panel_name}</strong> has <span style='color: green; font-weight: bold;'>APPROVED ALL</span> feedback for your thesis proposal!</p>
                <p><strong>Summary:</strong> {$feedback_summary}</p>
                <p><strong>Route:</strong> {$route_number}</p>
                {$feedbackDetailsHtml}
                <p>You may now proceed to the next step in your thesis workflow. Please log in to the Thesis Routing System to view all approved feedback and continue with your thesis progress.</p>
                <div style='margin-top: 30px; text-align: center;'>
                    <a href='{$login_url}' style='background-color: #4366b3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to System</a>
                </div>
                <p style='margin-top: 10px; text-align: center;'>If the button above doesn't work, copy and paste this URL into your browser: <br><a href='{$login_url}'>{$login_url}</a></p>
                <p style='margin-top: 30px; font-size: 12px; color: #777; text-align: center;'>This is an automated message from the Thesis Routing System. Please do not reply to this email.</p>
            </div>
        ";
        
        // Prepare plain text version
        $feedbackDetailsText = "";
        if (!empty($feedbackDetails)) {
            $feedbackDetailsText = "Approved Feedback Details:\n";
            foreach ($feedbackDetails as $detail) {
                $feedbackDetailsText .= "- Chapter {$detail['chapter']}: {$detail['feedback']}\n";
            }
        }
        
        $mail->AltBody = "Dear {$student_name}, Congratulations! Your panel member {$panel_name} has APPROVED ALL feedback for your thesis proposal. Summary: {$feedback_summary}, Route: {$route_number}.\n\n{$feedbackDetailsText}\nPlease login at: {$login_url} to view all approved feedback and continue with your thesis progress.";

        // Add additional headers that may help with deliverability
        $mail->addCustomHeader('X-Mailer', 'Thesis Routing System');
        $mail->addCustomHeader('X-Priority', '3');

        $mail->send();
        error_log("All approved notification email sent successfully to student: $student_email using PHPMailer");
        return true;
    } catch (Exception $e) {
        $errorMsg = "All approved notification email could not be sent to student: $student_email. ";
        
        if (isset($mail)) {
            $errorMsg .= "PHPMailer Error: " . $mail->ErrorInfo;
            
            // Log SMTP debug info for connection issues
            if (strpos($mail->ErrorInfo, 'SMTP connect() failed') !== false) {
                $errorMsg .= ". Possible connection issue with SMTP server.";
            } else if (strpos($mail->ErrorInfo, 'authentication failed') !== false) {
                $errorMsg .= ". Authentication issue - check username and password.";
            } else if (strpos($mail->ErrorInfo, 'Invalid address') !== false) {
                $errorMsg .= ". Invalid email address format.";
            } else if (strpos($mail->ErrorInfo, 'Could not authenticate') !== false) {
                $errorMsg .= ". Gmail may be blocking this attempt. Check Gmail settings and app password.";
            } else if (strpos($mail->ErrorInfo, 'Recipient') !== false) {
                $errorMsg .= ". There's an issue with the recipient address. Check if the address is valid.";
            }
        } else {
            $errorMsg .= "Exception: " . $e->getMessage();
        }
        
        logEmailError($errorMsg);
        return false;
    }
}
?>
