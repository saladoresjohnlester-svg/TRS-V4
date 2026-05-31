<?php
session_start();
include '../../../connection.php';

// Logging configuration
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/deadline_checker_errors_route3.log');

// MUST be before any output
header('Content-Type: application/json');

// Check if deadline checker file exists
if (!file_exists('auto_deadline_checker_route3.php')) {
    error_log("CRITICAL: auto_deadline_checker_route3.php not found!");
    echo json_encode([
        'success' => false, 
        'message' => 'Deadline checker file not found',
        'file_path' => __DIR__ . '/auto_deadline_checker_route3.php'
    ]);
    exit;
}

require_once 'auto_deadline_checker_route3.php';

error_log("=== check_deadline_route3.php called ===");
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request time: " . date('Y-m-d H:i:s'));

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERROR: Invalid request method - " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method. POST required.'
    ]);
    exit;
}

// Get and validate input
$rawInput = file_get_contents('php://input');
error_log("Raw input received: " . $rawInput);

$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("ERROR: JSON decode failed - " . json_last_error_msg());
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid JSON: ' . json_last_error_msg()
    ]);
    exit;
}

// Extract and validate route3_id
$route3_id = isset($input['route3_id']) ? trim((string)$input['route3_id']) : null;
error_log("route3_id received: " . ($route3_id ?? 'NULL'));

if (empty($route3_id)) {
    error_log("ERROR: Missing route3_id");
    echo json_encode([
        'success' => false, 
        'message' => 'Missing route3_id parameter'
    ]);
    exit;
}

// Verify route3_id exists in database
$verifyQuery = $conn->prepare("SELECT route3_id, panel_deadline FROM route3proposal_files WHERE route3_id = ?");
if (!$verifyQuery) {
    error_log("ERROR: Failed to prepare verify query - " . $conn->error);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$verifyQuery->bind_param("s", $route3_id);
$verifyQuery->execute();
$verifyResult = $verifyQuery->get_result();

if ($verifyResult->num_rows === 0) {
    error_log("ERROR: route3_id not found in database - " . $route3_id);
    $verifyQuery->close();
    echo json_encode([
        'success' => false, 
        'message' => 'Route3 ID not found in database'
    ]);
    exit;
}

$checkData = $verifyResult->fetch_assoc();
$verifyQuery->close();

// Check deadline status
$deadline_passed = false;
$deadline_info = null;

if (!empty($checkData['panel_deadline'])) {
    try {
        $timezone = new DateTimeZone('Asia/Manila');
        $now = new DateTime('now', $timezone);
        $deadlineDate = new DateTime($checkData['panel_deadline'], $timezone);
        $deadline_passed = ($now > $deadlineDate);
        
        $deadline_info = [
            'deadline' => $deadlineDate->format('Y-m-d H:i:s'),
            'current_time' => $now->format('Y-m-d H:i:s'),
            'is_overdue' => $deadline_passed
        ];
        
        error_log("Deadline info: " . json_encode($deadline_info));
    } catch (Exception $e) {
        error_log("ERROR: Failed to parse deadline - " . $e->getMessage());
    }
} else {
    error_log("INFO: No deadline set for route3_id " . $route3_id);
}

// Run the deadline checker
try {
    error_log("Calling checkAndInsertNoCommentsRoute3 for route3_id: " . $route3_id);
    checkAndInsertNoCommentsRoute3($conn, $route3_id);
    
    error_log("SUCCESS: Deadline checker completed successfully");
    
    echo json_encode([
        'success' => true,
        'message' => 'Deadline check completed',
        'deadline_passed' => $deadline_passed,
        'deadline_info' => $deadline_info,
        'route3_id' => $route3_id
    ]);
    
} catch (Exception $e) {
    error_log("EXCEPTION in checkAndInsertNoCommentsRoute3: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Exception occurred: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

error_log("=== check_deadline_route3.php completed ===\n");
?>