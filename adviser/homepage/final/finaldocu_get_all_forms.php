<?php
include '../../../connection.php';

$student_id = $_GET['student_id'] ?? '';

if ($student_id) {
    // Updated query to include finaldocu_id in addition to route1_id, route2_id, and route3_id
    $stmt = $conn->prepare("
        SELECT * FROM final_monitoring_form 
        WHERE student_id = ? 
        AND (route1_id IS NOT NULL OR route2_id IS NOT NULL OR route3_id IS NOT NULL OR finaldocu_id IS NOT NULL)
        ORDER BY date_submitted ASC
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $forms = [];
    while ($row = $result->fetch_assoc()) {
        $forms[] = $row;
    }

    // Output the forms array as JSON
    header('Content-Type: application/json');
    echo json_encode($forms);
} else {
    header('Content-Type: application/json');
    echo json_encode([]);
}
?> 