<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
include '../../../connection.php';

// Get finaldocu_id from request
$finaldocu_id = isset($_GET['finaldocu_id']) ? intval($_GET['finaldocu_id']) : 0;

// Default response
$response = [
    'hasReviewed' => false,
    'message' => 'Document not yet approved by adviser'
];

if ($finaldocu_id > 0) {
    // Get student_id from finaldocu_id
    $stmt = $conn->prepare("SELECT student_id FROM finaldocufinal_files WHERE finaldocu_id = ?");
    $stmt->bind_param("i", $finaldocu_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_id = null;
    
    if ($result && $row = $result->fetch_assoc()) {
        $student_id = $row['student_id'];
    }
    $stmt->close();
    
    if ($student_id) {
        // First check if there's any finaldocu monitoring form from the adviser (not requiring approval)
        $check_finaldocu = $conn->prepare("SELECT COUNT(*) as count FROM final_monitoring_form 
                                       WHERE finaldocu_id = ? AND adviser_id IS NOT NULL");
        $check_finaldocu->bind_param("i", $finaldocu_id);
        $check_finaldocu->execute();
        $finaldocu_result = $check_finaldocu->get_result();
        $finaldocu_has_adviser_form = ($finaldocu_result && $finaldocu_result->fetch_assoc()['count'] > 0);
        $check_finaldocu->close();
        
        if ($finaldocu_has_adviser_form) {
            // If there's any adviser form submission for finaldocu, allow viewing
            $response['hasReviewed'] = true;
            $response['message'] = 'Adviser has submitted a form for this document';
        } else {
            // If no finaldocu review exists, check if route3 has any submission from adviser
            $get_route3 = $conn->prepare("SELECT route3_id FROM route3final_files WHERE student_id = ?");
            $get_route3->bind_param("s", $student_id);
            $get_route3->execute();
            $route3_result = $get_route3->get_result();
            $route3_id = null;
            
            if ($route3_result && $route3_row = $route3_result->fetch_assoc()) {
                $route3_id = $route3_row['route3_id'];
            }
            $get_route3->close();
            
            if ($route3_id) {
                $check_route3 = $conn->prepare("SELECT COUNT(*) as count FROM final_monitoring_form 
                                              WHERE route3_id = ? AND adviser_id IS NOT NULL");
                $check_route3->bind_param("i", $route3_id);
                $check_route3->execute();
                $route3_result = $check_route3->get_result();
                $route3_has_adviser_form = ($route3_result && $route3_result->fetch_assoc()['count'] > 0);
                $check_route3->close();
                
                if ($route3_has_adviser_form) {
                    $response['hasReviewed'] = true;
                    $response['message'] = 'Adviser has submitted a form for Route 3';
                } else {
                    $response['hasReviewed'] = false;
                    $response['message'] = 'Adviser must submit a form for Route 3 or Final Document before panel members can view this document';
                }
            } else {
                $response['hasReviewed'] = false;
                $response['message'] = 'Route 3 document not found. Adviser must review Final Document first';
            }
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 