<?php
include '../../../connection.php';

$student_id = $_GET['student_id'] ?? '';

if ($student_id) {
    // Updated query to check for route3_id in addition to route1_id and route2_id
    $stmt = $conn->prepare("
        SELECT * FROM proposal_monitoring_form 
        WHERE student_id = ? 
        AND routeNumber = 'Route 1'
        ORDER BY routeNumber DESC, date_submitted DESC
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $forms = [];
    while ($row = $result->fetch_assoc()) {
        $forms[] = $row;
    }

    // Debugging: Check the result array
    if (empty($forms)) {
        echo "No forms found for this student with route1_id, route2_id, or route3_id.";
    } else {
        // Output the forms array as JSON
        header('Content-Type: application/json');
        echo json_encode($forms);
    }
} else {
    echo json_encode([]);
}
?>
