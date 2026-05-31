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

// Get route1_id from GET parameter
$route1_id = isset($_GET['route1_id']) ? intval($_GET['route1_id']) : 0;
$student_id = $_SESSION['student_id'];

if ($route1_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid route1_id']);
    exit;
}

// Check if this is the student's own record
$stmt = $conn->prepare("SELECT minutes FROM route1proposal_files WHERE student_id = ? AND route1_id = ?");
$stmt->bind_param("si", $student_id, $route1_id);
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
    echo json_encode(['error' => 'Record not found or access denied', 'success' => false]);
}

$stmt->close();
$conn->close();
?> 