<?php
session_start();
include '../../../connection.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// MUST be before any output
header('Content-Type: application/json');

if (!file_exists('auto_deadline_checker.php')) {
    error_log("auto_deadline_checker.php not found!");
    echo json_encode(['success' => false, 'message' => 'Deadline checker file not found']);
    exit;
}

require_once 'auto_deadline_checker.php';

error_log("=== check_deadline.php called ===");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$rawInput = file_get_contents('php://input');
error_log("Raw input: " . $rawInput);

$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

$route1_id = isset($input['route1_id']) ? (string)$input['route1_id'] : null;
error_log("route1_id received: " . ($route1_id ?? 'NULL'));

if (empty($route1_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing route1_id']);
    exit;
}

// Check deadline status
$checkQuery = $conn->prepare("SELECT panel_deadline FROM route1proposal_files WHERE route1_id = ?");
if (!$checkQuery) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    exit;
}
$checkQuery->bind_param("s", $route1_id);
$checkQuery->execute();
$checkData = $checkQuery->get_result()->fetch_assoc();
$checkQuery->close();

$deadline_passed = false;
if (!empty($checkData['panel_deadline'])) {
    $timezone     = new DateTimeZone('Asia/Manila');
    $now          = new DateTime('now', $timezone);
    $deadlineDate = new DateTime($checkData['panel_deadline'], $timezone);
    $deadline_passed = ($now > $deadlineDate);
}

// Run the checker
try {
    checkAndInsertNoComments($conn, $route1_id);
    echo json_encode([
        'success'         => true,
        'deadline_passed' => $deadline_passed,
        'db_error'        => $conn->error ?: null,
    ]);
} catch (Exception $e) {
    error_log("Exception in checkAndInsertNoComments: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>