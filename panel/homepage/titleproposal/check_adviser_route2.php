<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
include '../../../connection.php';

// Get route2_id from request
$route2_id = isset($_GET['route2_id']) ? intval($_GET['route2_id']) : 0;

// Default response
$response = [
    'hasReviewed' => false,
    'message' => 'Document not yet approved by adviser'
];

if ($route2_id > 0) {
    // Get student_id from route2_id
    $stmt = $conn->prepare("SELECT student_id FROM route2proposal_files WHERE route2_id = ?");
    $stmt->bind_param("i", $route2_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_id = null;
    
    if ($result && $row = $result->fetch_assoc()) {
        $student_id = $row['student_id'];
    }
    $stmt->close();
    
    if ($student_id) {
        // Check if there's a route2 monitoring form from the adviser (any status)
        $check_route2 = $conn->prepare("SELECT COUNT(*) as count FROM proposal_monitoring_form 
                                       WHERE route2_id = ? AND adviser_id IS NOT NULL");
        $check_route2->bind_param("i", $route2_id);
        $check_route2->execute();
        $route2_result = $check_route2->get_result();
        $route2_advised = ($route2_result && $route2_result->fetch_assoc()['count'] > 0);
        $check_route2->close();
        
        if ($route2_advised) {
            // If adviser has submitted any form for route2, allow viewing
            $response['hasReviewed'] = true;
            $response['message'] = 'Route 2 document reviewed by adviser';
        } else {
            // If no route2 review exists, check if route1 has any adviser submission
            $get_route1 = $conn->prepare("SELECT route1_id FROM route1proposal_files WHERE student_id = ?");
            $get_route1->bind_param("s", $student_id);
            $get_route1->execute();
            $route1_result = $get_route1->get_result();
            $route1_id = null;
            
            if ($route1_result && $route1_row = $route1_result->fetch_assoc()) {
                $route1_id = $route1_row['route1_id'];
            }
            $get_route1->close();
            
            if ($route1_id) {
                $check_route1 = $conn->prepare("SELECT COUNT(*) as count FROM proposal_monitoring_form 
                                              WHERE route1_id = ? AND adviser_id IS NOT NULL");
                $check_route1->bind_param("i", $route1_id);
                $check_route1->execute();
                $route1_result = $check_route1->get_result();
                $route1_advised = ($route1_result && $route1_result->fetch_assoc()['count'] > 0);
                $check_route1->close();
                
                if ($route1_advised) {
                    $response['hasReviewed'] = true;
                    $response['message'] = 'Route 1 document was reviewed by adviser';
                } else {
                    $response['hasReviewed'] = false;
                    $response['message'] = 'Adviser must submit a form for Route 1 or Route 2 before panel members can view this document';
                }
            } else {
                $response['hasReviewed'] = false;
                $response['message'] = 'Route 1 document not found. Adviser must review Route 2 first';
            }
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 