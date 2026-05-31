<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check for Composer autoloader
$autoloader_path = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoloader_path)) {
    error_log("PHPMailer autoloader not found. Please install PHPMailer via Composer.");
    exit(1);
}

// Include the autoloader
require_once $autoloader_path;

// Get the temp file path from command line argument
$tempFile = $argv[1] ?? null;

if (!$tempFile || !file_exists($tempFile)) {
    error_log("No valid temp file provided for email processing");
    exit(1);
}

// Read email data from temp file
$emailData = json_decode(file_get_contents($tempFile), true);

if (!$emailData) {
    error_log("Failed to read email data from temp file");
    unlink($tempFile); // Clean up temp file
    exit(1);
}

try {
    // Create instance of PHPMailer
    $mail = new PHPMailer(true);

    // Server settings
    $mail->SMTPDebug  = 0;  // Enable verbose debug output (0 for no output, 2 for verbose)
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
    $mail->addAddress($emailData['adviser_email'], $emailData['adviser_name']);     

    // Content
    $mail->isHTML(true);                                  
    $mail->Subject = 'New Thesis Document Submitted for Review';
    
    // Get server URL dynamically
    $base_url = 'https://capstone.smccnasipit.edu.ph/';
    
    $login_url = $base_url . 'trs/adviser/login.php';
    
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
            <h2 style='color: #4366b3; text-align: center;'>Thesis Routing System Notification</h2>
            <p>Dear <strong>{$emailData['adviser_name']}</strong>,</p>
            <p>A new thesis document has been submitted and requires your review.</p>
            <p><strong>Student:</strong> {$emailData['fullname']}</p>
            <p><strong>Title:</strong> {$emailData['title']}</p>
            <p>Please log in to the Thesis Routing System to review this document.</p>
            <div style='margin-top: 30px; text-align: center;'>
                <a href='{$login_url}' style='background-color: #4366b3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Review</a>
            </div>
            <p style='margin-top: 10px; text-align: center;'>If the button above doesn't work, copy and paste this URL into your browser: <br><a href='{$login_url}'>{$login_url}</a></p>
            <p style='margin-top: 30px; font-size: 12px; color: #777; text-align: center;'>This is an automated message from the Thesis Routing System. Please do not reply to this email.</p>
        </div>
    ";
    $mail->AltBody = "Dear {$emailData['adviser_name']}, A new thesis document has been submitted by {$emailData['fullname']} with the title '{$emailData['title']}' and requires your review. Please login at: {$login_url}";

    // Add additional headers that may help with deliverability
    $mail->addCustomHeader('X-Mailer', 'Thesis Routing System');
    $mail->addCustomHeader('X-Priority', '3');

    $mail->send();
    error_log("Email sent successfully to: {$emailData['adviser_email']} using PHPMailer");
    
} catch (Exception $e) {
    $errorMsg = "Email could not be sent to: {$emailData['adviser_email']}. ";
    
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
    
    error_log($errorMsg);
}

// Clean up temp file
unlink($tempFile);
exit(0);
