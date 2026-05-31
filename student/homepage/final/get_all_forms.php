<?php
include '../../../connection.php';
$route1_id = $_GET['route1_id'] ?? '';

if ($route1_id) {
    $stmt = $conn->prepare("
        SELECT 
            fmf.*,
            a.fullname AS adviser_name,
            p.fullname AS panel_name
        FROM final_monitoring_form fmf
        LEFT JOIN adviser a ON fmf.adviser_id = a.adviser_id
        LEFT JOIN panel p ON fmf.panel_id = p.panel_id
        WHERE fmf.route1_id = ?
        AND fmf.routeNumber = 'Route 1'
        ORDER BY fmf.routeNumber ASC, fmf.date_submitted ASC
    ");
    $stmt->bind_param("s", $route1_id);
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