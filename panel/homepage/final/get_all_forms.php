<?php
include '../../../connection.php';

$route1_id = $_GET['route1_id'] ?? '';

if ($route1_id) {
$stmt = $conn->prepare("SELECT * FROM final_monitoring_form WHERE route1_id = ? AND routeNumber = 'Route 1' ORDER BY routeNumber DESC, date_submitted DESC");
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
