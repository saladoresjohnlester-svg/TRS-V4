<?php
session_start();
include '../../../connection.php';
if (!isset($_SESSION['adviser_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}
header('Content-Type: application/json');
if (!isset($_GET['student_id'])) {
    echo json_encode(['error' => 'Missing student_id parameter']);
    exit;
}
$student_id = $_GET['student_id'];
$query = "
    SELECT 
        id,
        date_submitted,
        chapter,
        feedback,
        paragraph_number,
        page_number,
        adviser_name,
        panel_name,
        date_released,
        status,
        routeNumber,
        adviser_id,
        panel_id
    FROM 
        proposal_monitoring_form
    WHERE 
        student_id = ?
        AND routeNumber IN ('Route 1', 'Route 2')
    ORDER BY 
        routeNumber DESC, date_submitted DESC
";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$forms = [];
while ($row = $result->fetch_assoc()) {
    $forms[] = $row;
}
$stmt->close();
echo json_encode($forms);
?>