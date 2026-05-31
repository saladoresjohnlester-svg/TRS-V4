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
    $stmt = $conn->prepare("SELECT student_id FROM route2final_files WHERE route2_id = ?");
    $stmt->bind_param("i", $route2_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_id = null;
    
    if ($result && $row = $result->fetch_assoc()) {
        $student_id = $row['student_id'];
    }
    $stmt->close();
    
    if ($student_id) {
        // Ignore route2 status entirely and only check route1
        // Get route1_id
        $get_route1 = $conn->prepare("SELECT route1_id FROM route1final_files WHERE student_id = ?");
        $get_route1->bind_param("s", $student_id);
        $get_route1->execute();
        $route1_result = $get_route1->get_result();
        $route1_id = null;
        
        if ($route1_result && $route1_row = $route1_result->fetch_assoc()) {
            $route1_id = $route1_row['route1_id'];
        }
        $get_route1->close();
        
        if ($route1_id) {
            $check_route1 = $conn->prepare("SELECT COUNT(*) as count FROM final_monitoring_form 
                                          WHERE route1_id = ? AND adviser_id IS NOT NULL 
                                          AND (status = 'Approved' OR status = 'approved')");
            $check_route1->bind_param("i", $route1_id);
            $check_route1->execute();
            $route1_result = $check_route1->get_result();
            $route1_approved = ($route1_result && $route1_result->fetch_assoc()['count'] > 0);
            $check_route1->close();
            
            if ($route1_approved) {
                $response['hasReviewed'] = true;
                $response['message'] = 'Final Route 1 document was approved by adviser';
            } else {
                $response['hasReviewed'] = false;
                $response['message'] = 'Adviser must approve Final Route 1 before panel members can view Final Route 2 documents';
            }
        } else {
            $response['hasReviewed'] = false;
            $response['message'] = 'Final Route 1 document not found. Adviser must complete Final Route 1 review first';
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 