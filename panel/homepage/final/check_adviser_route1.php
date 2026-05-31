<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
include '../../../connection.php';

// Get route1_id from request
$route1_id = isset($_GET['route1_id']) ? intval($_GET['route1_id']) : 0;

// Default response
$response = [
    'hasReviewed' => false,
    'message' => 'Document not yet reviewed by adviser'
];

if ($route1_id > 0) {
    // Get student_id from route1_id
    $stmt = $conn->prepare("SELECT student_id FROM route1final_files WHERE route1_id = ?");
    $stmt->bind_param("i", $route1_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_id = null;
    
    if ($result && $row = $result->fetch_assoc()) {
        $student_id = $row['student_id'];
    }
    $stmt->close();
    
    if ($student_id) {
        // Check if adviser has submitted ANY form for final route1 (not looking for approval)
        $check_route1 = $conn->prepare("SELECT COUNT(*) as count FROM final_monitoring_form 
                                       WHERE route1_id = ? AND adviser_id IS NOT NULL");
        $check_route1->bind_param("i", $route1_id);
        $check_route1->execute();
        $route1_result = $check_route1->get_result();
        $route1_has_review = ($route1_result && $route1_result->fetch_assoc()['count'] > 0);
        $check_route1->close();
        
        if ($route1_has_review) {
            // If adviser has submitted any review, allow access
            $response['hasReviewed'] = true;
            $response['message'] = 'Final Route 1 document has been reviewed by adviser';
        } else {
            $response['hasReviewed'] = false;
            $response['message'] = 'Adviser must submit routing form for Final Route 1 before panel members can view this document';
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 