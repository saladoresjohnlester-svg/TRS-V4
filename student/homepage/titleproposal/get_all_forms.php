<?php
include '../../../connection.php';
$student_id = $_GET['student_id'] ?? '';

if ($student_id) {
    $stmt = $conn->prepare("
        SELECT 
            pmf.*,
            a.fullname AS adviser_name,
            p.fullname AS panel_name
        FROM proposal_monitoring_form pmf
        LEFT JOIN adviser a ON pmf.adviser_id = a.adviser_id
        LEFT JOIN panel p ON pmf.panel_id = p.panel_id
        WHERE pmf.student_id = ?
        AND pmf.route1_id IS NOT NULL
        AND pmf.routeNumber = 'Route 1'
        ORDER BY pmf.routeNumber DESC, pmf.date_submitted DESC
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