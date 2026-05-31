<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Security check
if (!isset($_SESSION['student_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

include '../../../connection.php';

// Get route3_id from GET parameter
$route3_id = isset($_GET['route3_id']) ? intval($_GET['route3_id']) : 0;
$student_id = $_SESSION['student_id'];

if ($route3_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid route3_id']);
    exit;
}

// First verify the student owns this route3 record
$stmt = $conn->prepare("SELECT 1 FROM route3proposal_files WHERE student_id = ? AND route3_id = ?");
$stmt->bind_param("si", $student_id, $route3_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Record not found or access denied', 'success' => false]);
    exit;
}
$stmt->close();

// Now get the minutes from route1proposal_files
$stmt = $conn->prepare("SELECT minutes FROM route1proposal_files WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $minutes = $row['minutes'];
    
    if ($minutes) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'minutes_path' => $minutes]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No minutes found', 'success' => false]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No route1 record found', 'success' => false]);
}

$stmt->close();
$conn->close();
?> 