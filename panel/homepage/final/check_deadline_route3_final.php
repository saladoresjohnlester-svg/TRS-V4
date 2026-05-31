<?php
header('Content-Type: application/json');
include '../../../connection.php';

// Include the deadline checker function
include_once 'auto_deadline_checker_route3_final.php';

$input = json_decode(file_get_contents('php://input'), true);
$route3_id = $input['route3_id'] ?? null;

if (!$route3_id) {
    echo json_encode(['success' => false, 'message' => 'Missing route3_id']);
    exit;
}

// Run the deadline checker for this specific route3_id
checkAndInsertNoCommentsRoute3Final($conn, $route3_id);

// Check if deadline has passed
$stmt = $conn->prepare("SELECT panel_deadline FROM route3final_files WHERE route3_id = ?");
$stmt->bind_param("s", $route3_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$deadline_passed = false;
if ($row && $row['panel_deadline']) {
    $deadline = $row['panel_deadline'];
    if ($deadline !== '0000-00-00 00:00:00' && $deadline !== NULL) {
        try {
            $timezone = new DateTimeZone('Asia/Manila');
            $now = new DateTime('now', $timezone);
            $deadlineDate = new DateTime($deadline, $timezone);
            
            if ($deadlineDate->format('Y') >= 1970 && $now > $deadlineDate) {
                $deadline_passed = true;
            }
        } catch (Exception $e) {
            // Invalid date, treat as not passed
        }
    }
}

echo json_encode([
    'success' => true,
    'deadline_passed' => $deadline_passed,
    'message' => $deadline_passed ? 'Deadline has passed' : 'Deadline not yet passed'
]);
?>