<?php
include '../../../connection.php';
$student_id = $_GET['student_id'] ?? '';

if ($student_id) {
    $stmt = $conn->prepare("
        SELECT 
            fmf.*,
            a.fullname AS adviser_name,
            p.fullname AS panel_name
        FROM final_monitoring_form fmf
        LEFT JOIN adviser a ON fmf.adviser_id = a.adviser_id
        LEFT JOIN panel p ON fmf.panel_id = p.panel_id
        WHERE fmf.student_id = ?
        AND fmf.routeNumber IN ('Route 1', 'Route 2')
        ORDER BY fmf.routeNumber DESC, fmf.date_submitted DESC
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