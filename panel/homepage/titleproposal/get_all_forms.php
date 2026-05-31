<?php
include '../../../connection.php';
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
        AND routeNumber = 'Route 1'
    ORDER BY routeNumber DESC, date_submitted DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$forms = [];
while ($row = $result->fetch_assoc()) {
    $forms[] = $row;
}

header('Content-Type: application/json');

if (empty($forms)) {
    echo json_encode([]);
} else {
    echo json_encode($forms);
}
?>