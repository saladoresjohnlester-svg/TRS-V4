<?php
include '../../../connection.php';
$student_id = $_GET['student_id'] ?? '';
if ($student_id) {
    $stmt = $conn->prepare("
        SELECT * FROM proposal_monitoring_form 
        WHERE student_id = ?
        AND (route1_id IS NOT NULL OR route2_id IS NOT NULL OR route3_id IS NOT NULL)
        ORDER BY routeNumber DESC, date_submitted DESC
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $forms = [];
    while ($row = $result->fetch_assoc()) {
        $forms[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($forms);
} else {
    echo json_encode([]);
}
?>