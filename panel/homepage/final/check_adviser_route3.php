<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../../connection.php';

$route3_id = isset($_GET['route3_id']) ? intval($_GET['route3_id']) : 0;

$response = [
    'hasReviewed' => false,
    'message'     => 'Document not yet reviewed by adviser'
];

if ($route3_id > 0) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as count FROM final_monitoring_form
         WHERE route3_id = ? AND adviser_id IS NOT NULL"
    );
    $stmt->bind_param("i", $route3_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    if ($count > 0) {
        $response['hasReviewed'] = true;
        $response['message']     = 'Adviser has reviewed this Route 3 Final document';
    } else {
        $response['hasReviewed'] = false;
        $response['message']     = 'The adviser must comment on this Route 3 document before panel members can view it';
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>