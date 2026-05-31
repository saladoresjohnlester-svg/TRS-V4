<?php
session_start(); // Start the session

// Check if the user is not logged in (session variable is not set)
if (!isset($_SESSION['admin_id'])) {
    // Redirect to login page if not logged in
    header("Location: ../../../logout.php");
    exit;
}

// Database connection settings
include '../../../connection.php';

// Fetch departments
$departments = [];
$deptQuery = "SELECT DISTINCT department FROM student";
$deptResult = $conn->query($deptQuery);
if ($deptResult->num_rows > 0) {
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

// Fetch semesters
$semesters = [];
$semesterQuery = "SELECT DISTINCT semester FROM student WHERE semester IS NOT NULL AND semester != ''";
$semesterResult = $conn->query($semesterQuery);
if ($semesterResult && $semesterResult->num_rows > 0) {
    while ($row = $semesterResult->fetch_assoc()) {
        // Store original value with original case and formatting
        $semesters[] = $row['semester'];
    }
}

// Make sure all standard semesters are included regardless of database values
$standardSemesters = ['First semester', 'Second semester', 'Summer'];

// Create a normalized array for comparison (lowercase and trimmed)
$normalizedSemesters = array_map(function($sem) {
    return strtolower(trim($sem));
}, $semesters);

// Add standard semesters only if they don't exist (case-insensitive check)
foreach ($standardSemesters as $stdSemester) {
    $normalizedStdSemester = strtolower(trim($stdSemester));
    if (!in_array($normalizedStdSemester, $normalizedSemesters)) {
        $semesters[] = $stdSemester;
        $normalizedSemesters[] = $normalizedStdSemester;
    }
}

// Remove duplicates (case-insensitive)
$uniqueSemesters = [];
$uniqueNormalizedSemesters = [];
foreach ($semesters as $semester) {
    $normalizedSemester = strtolower(trim($semester));
    if (!in_array($normalizedSemester, $uniqueNormalizedSemesters)) {
        $uniqueSemesters[] = $semester;
        $uniqueNormalizedSemesters[] = $normalizedSemester;
    }
}
$semesters = $uniqueSemesters;

// Sort them in logical order
usort($semesters, function($a, $b) {
    $order = ['first semester' => 1, 'second semester' => 2, 'summer' => 3];
    $aOrder = isset($order[strtolower(trim($a))]) ? $order[strtolower(trim($a))] : 999;
    $bOrder = isset($order[strtolower(trim($b))]) ? $order[strtolower(trim($b))] : 999;
    return $aOrder - $bOrder;
});

// Add a function to check if all routes are approved for a student
function checkAllRoutesApproved($conn, $student_id) {
    // Check Route 1 status
    $route1Approved = false;
        
    // First check if there are any Route 1 forms
    $totalStmt = $conn->prepare("
        SELECT COUNT(*) as total_count 
        FROM proposal_monitoring_form 
        WHERE student_id = ? 
        AND route1_id IS NOT NULL
    ");
    $totalStmt->bind_param("s", $student_id);
    $totalStmt->execute();
    $result = $totalStmt->get_result();
    $totalRow = $result->fetch_assoc();
    $route1_total = (int)$totalRow['total_count'];
    $totalStmt->close();
    
    // Then check how many are approved
    $stmt = $conn->prepare("
        SELECT COUNT(*) as approved_count 
        FROM proposal_monitoring_form 
        WHERE student_id = ?
        AND route1_id IS NOT NULL
        AND (status = 'Approved' OR status = 'approved')
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $route1_approved_count = 0;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $route1_approved_count = (int)$row['approved_count'];
        // Only mark as approved if ALL forms are approved
        $route1Approved = ($route1_total > 0 && $route1_approved_count == $route1_total);
    }
    $stmt->close();
    
    // Check Route 2 status
    $route2Approved = false;
    
    // First check if there are any Route 2 forms
    $totalStmt = $conn->prepare("
        SELECT COUNT(*) as total_count 
        FROM proposal_monitoring_form 
        WHERE student_id = ?
        AND route2_id IS NOT NULL
    ");
    $totalStmt->bind_param("s", $student_id);
    $totalStmt->execute();
    $result = $totalStmt->get_result();
    $totalRow = $result->fetch_assoc();
    $route2_total = (int)$totalRow['total_count'];
    $totalStmt->close();
    
    // Then check how many are approved
    $stmt = $conn->prepare("
        SELECT COUNT(*) as approved_count 
        FROM proposal_monitoring_form 
        WHERE student_id = ?
        AND route2_id IS NOT NULL
        AND (status = 'Approved' OR status = 'approved')
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $route2_approved_count = 0;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $route2_approved_count = (int)$row['approved_count'];
        // Only mark as approved if ALL forms are approved
        $route2Approved = ($route2_total > 0 && $route2_approved_count == $route2_total);
    }
    $stmt->close();
    
    // Check Route 3 status
    $route3Approved = false;
    
    // First check if there are any Route 3 forms
    $totalStmt = $conn->prepare("
        SELECT COUNT(*) as total_count 
        FROM proposal_monitoring_form 
        WHERE student_id = ?
        AND route3_id IS NOT NULL
    ");
    $totalStmt->bind_param("s", $student_id);
    $totalStmt->execute();
    $result = $totalStmt->get_result();
    $totalRow = $result->fetch_assoc();
    $route3_total = (int)$totalRow['total_count'];
    $totalStmt->close();
    
    // Then check how many are approved
    $stmt = $conn->prepare("
        SELECT COUNT(*) as approved_count 
        FROM proposal_monitoring_form 
        WHERE student_id = ?
        AND route3_id IS NOT NULL
        AND (status = 'Approved' OR status = 'approved')
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $route3_approved_count = 0;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $route3_approved_count = (int)$row['approved_count'];
        // Only mark as approved if ALL forms are approved
        $route3Approved = ($route3_total > 0 && $route3_approved_count == $route3_total);
    }
    $stmt->close();
    
    // Create array of approved routes for status display
    $approvedRoutes = [];
    if ($route1Approved) $approvedRoutes[] = "Route 1";
    if ($route2Approved) $approvedRoutes[] = "Route 2";
    if ($route3Approved) $approvedRoutes[] = "Route 3";
    
    // Return results
    return [
        'route1_approved' => $route1Approved,
        'route2_approved' => $route2Approved,
        'route3_approved' => $route3Approved,
        'all_approved' => ($route1Approved && $route2Approved && $route3Approved),
        'approved_routes' => $approvedRoutes,
        'approved_count' => count($approvedRoutes),
        'route1_counts' => [
            'total' => $route1_total,
            'approved' => $route1_approved_count
        ],
        'route2_counts' => [
            'total' => $route2_total,
            'approved' => $route2_approved_count
        ],
        'route3_counts' => [
            'total' => $route3_total,
            'approved' => $route3_approved_count
        ]
    ];
}

// Fetch school years
$schoolYears = [];
$schoolYearQuery = "SELECT DISTINCT school_year FROM finaldocuproposal_files ORDER BY school_year DESC";
$schoolYearResult = $conn->query($schoolYearQuery);
if ($schoolYearResult && $schoolYearResult->num_rows > 0) {
    while ($row = $schoolYearResult->fetch_assoc()) {
        $schoolYears[] = $row['school_year'];
    }
}

// Helper functions to get names from IDs
function getPanelName($conn, $panel_id) {
    $stmt = $conn->prepare("SELECT fullname FROM panel WHERE panel_id = ?");
    if ($stmt === false) {
        error_log("Error preparing panel name statement: " . $conn->error);
        return "";
    }
    $stmt->bind_param("i", $panel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['fullname'];
    }
    $stmt->close();
    return "";
}

function getAdviserName($conn, $adviser_id) {
    $stmt = $conn->prepare("SELECT fullname FROM adviser WHERE adviser_id = ?");
    if ($stmt === false) {
        error_log("Error preparing adviser name statement: " . $conn->error);
        return "";
    }
    $stmt->bind_param("i", $adviser_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['fullname'];
    }
    $stmt->close();
    return "";
}

// Initialize variables
$panel = [];
$adviser = [];
$files = [];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['department'])) {
    $selectedDepartment = $_POST['department'];
    $_SESSION['selected_department'] = $selectedDepartment;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['school_year'])) {
    $selectedSchoolYear = $_POST['school_year'];
    $_SESSION['selected_school_year'] = $selectedSchoolYear;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['semester'])) {
    $selectedSemester = $_POST['semester'];
    $_SESSION['selected_semester'] = $selectedSemester;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Load data for all departments by default or selected department if specified
$selectedDepartment = $_POST['department'] ?? $_SESSION['selected_department'] ?? '';
$selectedSchoolYear = $_POST['school_year'] ?? $_SESSION['selected_school_year'] ?? '';
$selectedSemester = $_POST['semester'] ?? $_SESSION['selected_semester'] ?? '';

$_SESSION['selected_department'] = $selectedDepartment;
$_SESSION['selected_school_year'] = $selectedSchoolYear;
$_SESSION['selected_semester'] = $selectedSemester;

// Fetch panel data if department is selected
if (!empty($selectedDepartment)) {
    $panelStmt = $conn->prepare("SELECT * FROM panel WHERE department = ?");
    if ($panelStmt === false) {
        echo "Error preparing panel statement: " . $conn->error;
    } else {
        $panelStmt->bind_param("s", $selectedDepartment);
        $panelStmt->execute();
        $panelResult = $panelStmt->get_result();
        while ($row = $panelResult->fetch_assoc()) {
            $panel[$row['position']][] = $row['fullname'];
        }
        $panelStmt->close();
    }

    // Fetch adviser data
    $adviserStmt = $conn->prepare("SELECT * FROM adviser WHERE department = ?");
    if ($adviserStmt === false) {
        echo "Error preparing adviser statement: " . $conn->error;
    } else {
        $adviserStmt->bind_param("s", $selectedDepartment);
        $adviserStmt->execute();
        $adviserResult = $adviserStmt->get_result();
        while ($row = $adviserResult->fetch_assoc()) {
            $adviser[] = $row['fullname'];
        }
        $adviserStmt->close();
    }
}

// Fetch files
$sqlQuery = "SELECT 
    fd.finaldocu AS filepath, 
    fd.finaldocu AS filename, 
    fd.date_submitted,
    fd.controlNo,
    fd.group_number,
    fd.fullname,
    fd.title,
    fd.student_id, 
    fd.panel1_id, 
    fd.panel2_id, 
    fd.panel3_id, 
    fd.panel4_id, 
    fd.panel5_id, 
    fd.panel6_id, 
    fd.adviser_id,
    fd.finaldocu_id,
    fd.department,
    r1.minutes
 FROM finaldocuproposal_files fd
 LEFT JOIN route1proposal_files r1 ON fd.student_id = r1.student_id
 WHERE 1=1";

$types = "";
$params = [];

if (!empty($selectedDepartment)) {
    $sqlQuery .= " AND fd.department = ?";
    $types .= "s";
    $params[] = $selectedDepartment;
}

if (!empty($selectedSchoolYear)) {
    $sqlQuery .= " AND fd.school_year = ?";
    $types .= "s";
    $params[] = $selectedSchoolYear;
}

if (!empty($selectedSemester)) {
    $sqlQuery .= " AND fd.student_id IN (SELECT student_id FROM student WHERE semester = ?)";
    $types .= "s";
    $params[] = $selectedSemester;
}

$fileStmt = $conn->prepare($sqlQuery);

if (!empty($params)) {
    $bind_params = array();
    $bind_params[] = &$types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_params[] = &$params[$i];
    }
    call_user_func_array([$fileStmt, 'bind_param'], $bind_params);
}

if ($fileStmt === false) {
    echo "Error preparing statement: " . $conn->error;
} else {
    $fileStmt->execute();
    $fileResult = $fileStmt->get_result();
    while ($row = $fileResult->fetch_assoc()) {
        $files[] = [
            'filepath' => $row['filepath'],
            'filename' => $row['filename'],
            'controlNo' => $row['controlNo'],
            'group_number' => $row['group_number'],
            'fullname' => $row['fullname'],
            'student_id' => $row['student_id'],
            'panel1_id' => $row['panel1_id'],
            'panel2_id' => $row['panel2_id'],
            'panel3_id' => $row['panel3_id'],
            'panel4_id' => $row['panel4_id'],
            'panel5_id' => $row['panel5_id'],
            'panel6_id' => $row['panel6_id'],
            'adviser_id' => $row['adviser_id'],
            'title' => $row['title'],
            'finaldocu_id' => $row['finaldocu_id'],
            'department' => $row['department'],
            'minutes' => $row['minutes'] ?? ''
        ];
    }
    $fileStmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_file'])) {
    $finaldocuId = $_POST['delete_file'];
    $filepath = $_POST['filepath'] ?? '';

    if (!empty($filepath) && file_exists('../../../' . $filepath)) {
        @unlink('../../../' . $filepath);
    }

    $deleteStmt = $conn->prepare("DELETE FROM finaldocuproposal_files WHERE finaldocu_id = ?");
    $deleteStmt->bind_param("i", $finaldocuId);

    if ($deleteStmt->execute()) {
        echo "<script>alert('File deleted successfully!'); window.location.href = window.location.href;</script>";
    } else {
        echo "<script>alert('Error deleting file: ' + " . json_encode($conn->error) . "); window.location.href = window.location.href;</script>";
    }

    $deleteStmt->close();
    exit();
}

// Handle file submission for panelists and adviser
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['selected_files'])) {
    $selectedFiles = $_POST['selected_files'];
    $panel1 = $_POST['panel1'] ?? null;
    $panel2 = $_POST['panel2'] ?? null;
    $panel3 = $_POST['panel3'] ?? null;
    $panel4 = $_POST['panel4'] ?? null;
    $panel5 = $_POST['panel5'] ?? null;

    if (empty($selectedFiles)) {
        echo "<script>alert('Please select at least one file.');</script>";
    } elseif (empty($panel1) && empty($panel2) && empty($panel3) && empty($panel4) && empty($panel5)) {
        echo "<script>alert('Please select at least one panel.');</script>";
    } else {
        foreach ($selectedFiles as $filePath) {
            $fileName = $filePath;

            $checkStmt = $conn->prepare("SELECT * FROM finaldocuproposal_files WHERE finaldocu = ?");
            if ($checkStmt === false) {
                echo "<script>alert('Error preparing check statement: " . $conn->error . "');</script>";
                continue;
            }
            $checkStmt->bind_param("s", $fileName);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $fileExists = $result->num_rows > 0;
            $checkStmt->close();

            if ($fileExists) {
                $dateNow = date('Y-m-d H:i:s');
                $updatePanelStmt = $conn->prepare("UPDATE finaldocuproposal_files 
                    SET panel1_id = ?, panel2_id = ?, panel3_id = ?, panel4_id = ?, panel5_id = ?, panel6_id = ?, date_submitted = ? 
                    WHERE finaldocu = ?");
                if ($updatePanelStmt === false) {
                    echo "<script>alert('Error preparing update statement: " . $conn->error . "');</script>";
                    continue;
                }
                $updatePanelStmt->bind_param("iiiiiiiss", $panel1, $panel2, $panel3, $panel4, $panel5, $panel6, $dateNow, $fileName);
                $updatePanelStmt->execute();
                $updatePanelStmt->close();

                echo "<script>alert('Successfully Submitted.');</script>";
            } else {
                echo "<script>alert('File $fileName not found in the database.');</script>";
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_assignments'])) {
    $filepath = $_POST['filepath'];
    $panel1 = $_POST['panel1'] ?? null;
    $panel2 = $_POST['panel2'] ?? null;
    $panel3 = $_POST['panel3'] ?? null;
    $panel4 = $_POST['panel4'] ?? null;
    $panel5 = $_POST['panel5'] ?? null;
    $panel6 = $_POST['panel6'] ?? null;
    
    if (!isset($conn) || $conn->connect_error) {
        include '../../../connection.php';
    }
    
    $updateStmt = $conn->prepare("UPDATE finaldocuproposal_files 
        SET panel1_id = ?, panel2_id = ?, panel3_id = ?, panel4_id = ?, panel5_id = ?, panel6_id = ?
        WHERE finaldocu = ?");
    $updateStmt->bind_param("iiiiiis", $panel1, $panel2, $panel3, $panel4, $panel5, $panel6, $filepath);
    
    if ($updateStmt->execute()) {
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
            echo "<script>alert('Assignments updated successfully.');</script>";
        } else {
            if (!headers_sent()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Assignments updated successfully.']);
                exit;
            }
        }
    } else {
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
            echo "<script>alert('Failed to update assignments: " . $conn->error . "');</script>";
        } else {
            if (!headers_sent()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Failed to update assignments: ' . $conn->error]);
                exit;
            }
        }
    }
    $updateStmt->close();
}

// Fetch advisers for dropdown
function getAdvisers($conn, $department) {
    $advisers = [];
    $stmt = $conn->prepare("SELECT adviser_id, fullname FROM adviser WHERE department = ?");
    if ($stmt === false) {
        error_log("Error preparing advisers statement: " . $conn->error);
        return $advisers;
    }
    $stmt->bind_param("s", $department);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $advisers[] = $row;
    }
    $stmt->close();
    return $advisers;
}

$advisers = [];
if (isset($selectedDepartment)) {
    $advisers = getAdvisers($conn, $selectedDepartment);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Document - Thesis Routing System</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.4.2/mammoth.browser.min.js"></script>
    <link rel="stylesheet" href="proposalstyle.css">
    <style>
    .action-cell {
    display: flex;
    flex-direction: row;
    gap: 5px;
    align-items: center;
    flex-wrap: nowrap;
    white-space: nowrap;
}
        .search-container {
            margin: 15px 0;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-box {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 300px;
            font-size: 14px;
        }

        .panel-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .panel-container select {
            flex: 1;
            min-width: 120px;
            max-width: 200px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        #external-submit-button {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .action-label {
            text-align: center;
        }
        #external-submit-button:hover {
            background-color: #45a049;
        }
        .assignment-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .assignment-modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 5px;
            width: 50%;
            max-width: 500px;
            box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);
        }

        .assignment-modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .assignment-modal-close:hover,
        .assignment-modal-close:focus {
            color: black;
            cursor: pointer;
        }

        .assignment-list {
            margin-top: 20px;
        }

        .not-assigned {
            color: #888;
            font-style: italic;
        }

        .assignment-list h3 {
            margin-bottom: 5px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .assignment-list ul {
            padding-left: 20px;
            margin-top: 5px;
        }

        .edit-button {
            background-color: #FF9800;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
            font-size: 14px;
        }

        .edit-button:hover {
            background-color: #F57C00;
        }

        .save-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-right: 10px;
        }

        .save-button:hover {
            background-color: #45a049;
        }

        .cancel-button {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .cancel-button:hover {
            background-color: #d32f2f;
        }

        .delete-button {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 5px;
            font-size: 14px;
        }

        .delete-button:hover {
            background-color: #d32f2f;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-actions {
            margin-top: 20px;
            display: flex;
        }

        #view-assignments-section {
            margin-bottom: 20px;
        }

        .assignment-button {
            white-space: nowrap;
        }

        .form-input-row textarea {
            resize: vertical;
            min-height: 40px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ═══ NEW VIEWER MODAL STYLES ═══ */
        :root {
            --primary: #4366b3;
            --primary-light: #0a3885;
            --accent: #4a6fd1;
            --border: #e0e0e0;
        }
        .viewer-overlay {
            position: fixed; inset: 0;
            background: rgba(8,15,32,0.75);
            backdrop-filter: blur(8px);
            z-index: 2000;
            display: none; align-items: center; justify-content: center;
            padding: 16px; opacity: 0; transition: opacity 0.3s ease;
        }
        .viewer-overlay.open { display: flex; opacity: 1; }
        .viewer-modal-box {
            background: #fff; border-radius: 20px; width: 100%; max-width: 1400px;
            height: 92vh; max-height: 920px; display: flex; flex-direction: column;
            overflow: hidden; box-shadow: 0 12px 48px rgba(15,31,61,0.22);
            transform: scale(0.95) translateY(24px);
            transition: transform 0.38s cubic-bezier(0.34,1.56,0.64,1);
        }
        .viewer-overlay.open .viewer-modal-box { transform: scale(1) translateY(0); }
        .vmod-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 24px; background: var(--primary); flex-shrink: 0;
            position: relative; overflow: hidden;
        }
        .vmod-header::before {
            content: ''; position: absolute; top: -60px; right: -60px;
            width: 160px; height: 160px; border-radius: 50%;
            background: rgba(255,255,255,0.08); pointer-events: none;
        }
        .vmod-hl { display: flex; align-items: center; gap: 14px; }
        .vmod-badge {
            background: white; color: var(--primary); font-size: 10px; font-weight: 800;
            letter-spacing: 1px; text-transform: uppercase; padding: 4px 10px;
            border-radius: 20px; flex-shrink: 0;
        }
        .vmod-title { color: white; font-size: 17px; font-weight: 600; }
        .vmod-sub { color: rgba(255,255,255,0.55); font-size: 11px; margin-top: 2px; }
        .vmod-hr { display: flex; align-items: center; gap: 8px; }
        .vmod-print-btn {
            display: inline-flex; align-items: center; gap: 7px;
            background: white; color: var(--primary); border: none;
            padding: 8px 16px; border-radius: 9px; font-family: inherit;
            font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s;
        }
        .vmod-print-btn:hover { background: #f0f0f0; transform: translateY(-1px); }
        .vmod-print-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; }
        .vmod-close-btn {
            width: 34px; height: 34px; border-radius: 9px;
            border: 1px solid rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.1); color: white; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 19px; line-height: 1; transition: background 0.2s;
        }
        .vmod-close-btn:hover { background: rgba(220,50,50,0.45); }
        .vmod-accent-bar {
            height: 3px;
            background: linear-gradient(90deg, white 0%, var(--accent) 60%, transparent);
            flex-shrink: 0;
        }
        .vmod-body { display: flex; flex: 1; overflow: hidden; }

        /* PDF Pane */
        .vmod-pdf-pane { flex: 1.1; display: flex; flex-direction: column; background: #21242d; overflow: hidden; }
        .vmod-pdf-toolbar {
            display: flex; align-items: center; gap: 12px;
            padding: 9px 16px; background: #181b22;
            border-bottom: 1px solid rgba(255,255,255,0.07); flex-shrink: 0;
        }
        .vmod-pdf-label { font-size: 10px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: rgba(255,255,255,0.35); white-space: nowrap; }
        .vmod-pdf-fname { font-size: 12px; color: rgba(255,255,255,0.7); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .vmod-pdf-live { display: flex; align-items: center; gap: 5px; font-size: 11px; color: rgba(255,255,255,0.4); white-space: nowrap; }
        .vmod-pdf-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 6px #22c55e; animation: vmod-blink 2.5s ease-in-out infinite; }
        @keyframes vmod-blink { 0%,100%{opacity:1}50%{opacity:0.35} }
        .vmod-pdf-wrap { flex: 1; position: relative; overflow: hidden; }
        .vmod-pdf-wrap iframe { width: 100%; height: 100%; border: none; }
        .vmod-pdf-loader {
            position: absolute; inset: 0; background: #21242d;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 14px; z-index: 5; transition: opacity 0.4s;
        }
        .vmod-pdf-loader.hidden { opacity: 0; pointer-events: none; }
        .vmod-spinner {
            width: 40px; height: 40px; border-radius: 50%;
            border: 3px solid rgba(67,102,179,0.25);
            border-top-color: var(--accent);
            animation: vmod-spin 0.85s linear infinite;
        }
        @keyframes vmod-spin { to { transform: rotate(360deg); } }
        .vmod-loader-text { font-size: 12px; color: rgba(255,255,255,0.4); }

        /* Form Pane */
        .vmod-form-pane {
            width: 520px; flex-shrink: 0; display: flex; flex-direction: column;
            background: #f5f7fd; border-left: 1px solid var(--border); overflow: hidden;
        }
        .vmod-form-hdr {
            padding: 18px 20px 14px; background: white;
            border-bottom: 1px solid var(--border); flex-shrink: 0;
        }
        .vmod-inst-row { display: flex; align-items: center; gap: 9px; margin-bottom: 10px; }
        .vmod-inst-logo {
            width: 30px; height: 30px; background: var(--primary); border-radius: 7px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .vmod-inst-logo svg { width: 16px; height: 16px; stroke: white; fill: none; stroke-width: 1.8; }
        .vmod-inst-name { font-size: 10px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.4px; line-height: 1.45; }
        .vmod-fhdr-title { font-size: 16px; font-weight: 700; color: var(--primary); margin-bottom: 5px; }
        .vmod-fhdr-meta { display: flex; gap: 12px; flex-wrap: wrap; }
        .vmod-fhdr-meta-item { display: flex; align-items: center; gap: 4px; font-size: 11px; color: #777; }
        .vmod-fhdr-meta-item svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; }

        /* Tabs */
        .vmod-tabs { display: flex; background: white; border-bottom: 1px solid var(--border); flex-shrink: 0; }
        .vmod-tab {
            flex: 1; padding: 10px 6px; font-size: 11.5px; font-weight: 500; color: #777;
            text-align: center; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s;
        }
        .vmod-tab:hover { color: var(--accent); }
        .vmod-tab.active { color: var(--primary); border-bottom-color: var(--accent); font-weight: 700; }

        .vmod-form-body { flex: 1; overflow-y: auto; padding: 14px; }
        .vmod-form-body::-webkit-scrollbar { width: 4px; }
        .vmod-form-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        .vmod-tab-pane { display: none; }
        .vmod-tab-pane.active { display: block; }

        /* Comment Cards */
        .vmod-rg { margin-bottom: 18px; }
        .vmod-rg-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .vmod-rg-pill { background: var(--primary); color: white; font-size: 10px; font-weight: 800; padding: 3px 10px; border-radius: 20px; letter-spacing: 0.4px; white-space: nowrap; }
        .vmod-rg-line { flex: 1; height: 1px; background: linear-gradient(90deg, var(--border), transparent); }
        .vmod-rg-ct { font-size: 11px; color: #777; white-space: nowrap; }
        .vmod-cc {
            background: white; border: 1px solid var(--border); border-radius: 11px;
            padding: 13px 14px 13px 17px; margin-bottom: 9px; position: relative;
            transition: box-shadow 0.2s, transform 0.2s;
            animation: vmod-fadeUp 0.3s ease forwards; opacity: 0;
        }
        @keyframes vmod-fadeUp { from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:translateY(0)} }
        .vmod-cc:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); transform: translateY(-1px); }
        .vmod-cc-bar { position: absolute; left: 0; top: 0; bottom: 0; width: 3px; border-radius: 11px 0 0 11px; }
        .vmod-cc-bar.adviser { background: var(--accent); }
        .vmod-cc-bar.panel { background: #7c3aed; }
        .vmod-cc-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 7px; gap: 6px; }
        .vmod-cc-who { display: flex; align-items: center; gap: 7px; }
        .vmod-cc-avatar { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
        .vmod-cc-avatar.adviser { background: #e5ebf8; color: var(--primary); }
        .vmod-cc-avatar.panel { background: #ede9fe; color: #5b21b6; }
        .vmod-cc-name { font-size: 12px; font-weight: 700; color: #333; }
        .vmod-cc-role { font-size: 10px; color: #777; margin-top: 1px; }
        .vmod-cc-date { font-size: 10px; color: #777; background: #f5f7fd; padding: 2px 7px; border-radius: 20px; white-space: nowrap; flex-shrink: 0; }
        .vmod-cc-locs { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 7px; }
        .vmod-loc-chip { display: flex; align-items: center; gap: 3px; background: #f5f7fd; border: 1px solid var(--border); border-radius: 5px; padding: 2px 7px; font-size: 10px; color: #777; }
        .vmod-loc-chip strong { color: #333; font-weight: 600; margin-right: 2px; }
        .vmod-cc-feedback { font-size: 12px; color: #333; line-height: 1.6; background: #f5f7fd; border-radius: 7px; padding: 9px 11px; border-left: 3px solid var(--border); margin-bottom: 8px; }
        .vmod-cc-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px; }
        .vmod-cc-footer-left { display: flex; align-items: center; gap: 6px; }
        .vmod-rel-badge { display: flex; align-items: center; gap: 4px; font-size: 10px; color: #155724; background: #d4edda; padding: 2px 8px; border-radius: 20px; }

        /* Summary Stats */
        .vmod-stat-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 8px; margin-bottom: 12px; }
        .vmod-stat-box { background: white; border: 1px solid var(--border); border-radius: 10px; padding: 13px; text-align: center; }
        .vmod-stat-num { font-size: 26px; font-weight: 700; color: var(--primary); line-height: 1; margin-bottom: 3px; }
        .vmod-stat-lbl { font-size: 10px; color: #777; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
        .vmod-sum-card { background: white; border: 1px solid var(--border); border-radius: 10px; padding: 14px; margin-bottom: 10px; }
        .vmod-sum-card-title { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #777; margin-bottom: 9px; }
        .vmod-sum-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f5f7fd; font-size: 12px; }
        .vmod-sum-row:last-child { border-bottom: none; }
        .vmod-sum-lbl { color: #777; }
        .vmod-sum-val { font-weight: 600; color: #333; }

        /* Modal Footer */
        .vmod-footer {
            padding: 11px 22px; background: white; border-top: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
        }
        .vmod-footer-text { font-size: 11px; color: #777; }
        .vmod-footer-actions { display: flex; gap: 7px; }
        .vmod-footer-btn { padding: 6px 14px; border-radius: 7px; font-family: inherit; font-size: 12px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
        .vmod-footer-btn.outline { background: transparent; border: 1px solid var(--border); color: #333; }
        .vmod-footer-btn.outline:hover { border-color: var(--accent); color: var(--accent); }
        .vmod-footer-btn.primary { background: var(--accent); color: white; }
        .vmod-footer-btn.primary:hover { background: var(--primary); }

        .vmod-comments-loading { display: flex; align-items: center; justify-content: center; padding: 30px; flex-direction: column; gap: 12px; }
        .vmod-comments-loading p { font-size: 12px; color: #777; }
        /* ═══ END VIEWER MODAL STYLES ═══ */
    </style>
</head>

<body>
    <div class="container">
        <!-- Header Section -->
        <header class="header">
            <div class="logo-container">
                <img src="../../../assets/logo.png" alt="SMCC Logo">
                <div class="logo">Thesis Routing System</div>
            </div>
        </header>

        <!-- Top Navigation Bar -->
        <div class="top-bar" style="position: sticky; top: 0; z-index: 100;">
            <div class="navigation">
                <div class="homepage">
                    <a href="../homepage.php">Home Page</a>
                </div>

                <!-- Department Dropdown -->
                <div class="dropdown-container">
                    <form method="POST" style="display: inline;">
                        <select name="department" onchange="this.form.submit()">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= htmlspecialchars($department) ?>"
                                    <?= isset($selectedDepartment) && $selectedDepartment == $department ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($department) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <form method="POST" style="display: inline; margin-left: 10px;">
                        <select name="school_year" onchange="this.form.submit()">
                            <option value="">All School Years</option>
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?= htmlspecialchars($year) ?>"
                                    <?= isset($selectedSchoolYear) && $selectedSchoolYear == $year ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <form method="POST" style="display: inline; margin-left: 10px;">
                        <select name="semester" onchange="this.form.submit()">
                            <option value="">All Semesters</option>
                            <?php foreach ($semesters as $semester): ?>
                                <option value="<?= htmlspecialchars($semester) ?>"
                                    <?= isset($selectedSemester) && $selectedSemester == $semester ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($semester) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
            <div class="user-info">
                <div class="routeNo" style="margin-right: 20px;">Proposal - Final Document</div>
                <div class="vl"></div>
                <span class="role">Admin:</span>
                <span class="user-name">
                    <?php echo isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Guest'; ?>
                </span>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <nav class="sidebar">
                <nav class="nav-menu">
                    <!-- Title Proposal Section -->
                    <div class="menu-item dropdown">
                        <div class="menu-header">
                            <div class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                            </div>
                            <span>Research Proposal</span>
                            <div class="dropdown-icon expanded">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="dropdown-content show">
                            <a href="../titleproposal/route1.php" class="submenu-item">Route 1</a>
                            <a href="../titleproposal/route2.php" class="submenu-item">Route 2</a>
                            <a href="../titleproposal/route3.php" class="submenu-item">Route 3</a>
                            <a href="../titleproposal/finaldocu.php" class="submenu-item">Final Document</a>
                        </div>
                    </div>

                    <!-- Final Defense Section -->
                    <div class="menu-item dropdown">
                        <div class="menu-header">
                            <div class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                                </svg>
                            </div>
                            <span>Final Defense</span>
                            <div class="dropdown-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="dropdown-content">
                            <a href="../final/route1.php" class="submenu-item">Route 1</a>
                            <a href="../final/route2.php" class="submenu-item">Route 2</a>
                            <a href="../final/route3.php" class="submenu-item">Route 3</a>
                            <a href="../final/finaldocu.php" class="submenu-item">Final Document</a>
                        </div>
                    </div>

                    <!-- Department Course Section -->
                    <div class="menu-item dropdown">
                        <div class="menu-header">
                            <div class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </div>
                            <span>Department Course</span>
                            <div class="dropdown-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="dropdown-content">
                            <a href="../departmentcourse/departmentcourse.php" class="submenu-item">Department Course</a>
                        </div>
                    </div>

                    <!-- Registered Account Section -->
                    <div class="menu-item dropdown">
                        <div class="menu-header">
                            <div class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <span>Accounts</span>
                            <div class="dropdown-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="dropdown-content">
                            <a href="../registeredaccount/panel_register.php" class="submenu-item">Panel</a>
                            <a href="../registeredaccount/adviser_register.php" class="submenu-item">Adviser</a>
                            <a href="../registeredaccount/student_register.php" class="submenu-item">Student</a>
                        </div>
                    </div>
                </nav>
                <div class="logout">
                    <a href="../../../logout.php">Logout</a>
                </div>
            </nav>

            <div class="content" id="content-area">
                <!-- Search and Submit Button -->
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-box" placeholder="Search by name, group, title or department..." onkeyup="searchTable()">
                </div>

                <form id="submission-form" action="finaldocu.php" method="POST">
                    <table>
                        <thead>
                            <tr>
                                <th>Control No.</th>
                                <th>Leader</th>
                                <th>Group No.</th>
                                <th>Title</th>
                                <th>Assigned</th>
                                <th>Status</th>
                                <th class='action-label'>Action</th>
                            </tr>
                        </thead>
                        <tbody id="file-list">
                            <?php if (!empty($files)): ?>
                                <?php foreach ($files as $file): ?>
                                    <?php
                                        $filepath = htmlspecialchars($file['filepath'], ENT_QUOTES);
                                        $filename = htmlspecialchars(basename($file['filename']), ENT_QUOTES);
                                        $controlNo = htmlspecialchars($file['controlNo'] ?? '', ENT_QUOTES);
                                        $group_number = htmlspecialchars($file['group_number'] ?? '', ENT_QUOTES);
                                        $fullname = htmlspecialchars($file['fullname'] ?? '', ENT_QUOTES);
                                        $student_id = htmlspecialchars($file['student_id'] ?? '', ENT_QUOTES);
                                        $title = htmlspecialchars($file['title'] ?? '', ENT_QUOTES);
                                        $minutes = htmlspecialchars($file['minutes'] ?? '', ENT_QUOTES);

                                        // Check the approval status of all routes
                                        $routeStatus = checkAllRoutesApproved($conn, $student_id);
                                        
                                        // Determine status label and color
                                        $statusLabel = '';
                                        $statusColor = '';
                                        
                                        if ($routeStatus['all_approved']) {
                                            $statusLabel = 'Complete';
                                            $statusColor = 'green';
                                        } else {
                                            $approvedRoutes = [];
                                            if (isset($routeStatus['route1_counts'])) {
                                                if ($routeStatus['route1_approved']) {
                                                    $approvedRoutes[] = "Route 1 ({$routeStatus['route1_counts']['approved']}/{$routeStatus['route1_counts']['total']})";
                                                } else if ($routeStatus['route1_counts']['total'] > 0) {
                                                    $approvedRoutes[] = "Route 1 ({$routeStatus['route1_counts']['approved']}/{$routeStatus['route1_counts']['total']})";
                                                }
                                            }
                                            if (isset($routeStatus['route2_counts'])) {
                                                if ($routeStatus['route2_approved']) {
                                                    $approvedRoutes[] = "Route 2 ({$routeStatus['route2_counts']['approved']}/{$routeStatus['route2_counts']['total']})";
                                                } else if ($routeStatus['route2_counts']['total'] > 0) {
                                                    $approvedRoutes[] = "Route 2 ({$routeStatus['route2_counts']['approved']}/{$routeStatus['route2_counts']['total']})";
                                                }
                                            }
                                            if (isset($routeStatus['route3_counts'])) {
                                                if ($routeStatus['route3_approved']) {
                                                    $approvedRoutes[] = "Route 3 ({$routeStatus['route3_counts']['approved']}/{$routeStatus['route3_counts']['total']})";
                                                } else if ($routeStatus['route3_counts']['total'] > 0) {
                                                    $approvedRoutes[] = "Route 3 ({$routeStatus['route3_counts']['approved']}/{$routeStatus['route3_counts']['total']})";
                                                }
                                            }
                                            
                                            if (count($approvedRoutes) > 0) {
                                                $statusLabel = 'In Progress';
                                                $statusColor = 'orange';
                                            } else {
                                                $statusLabel = 'Pending';
                                                $statusColor = 'red';
                                            }
                                        }
                                        
                                        // Panel and adviser information
                                        $assigned_panels = [];
                                        for ($i = 1; $i <= 6; $i++) {
                                            $panel_id_key = "panel{$i}_id";
                                            if (!empty($file[$panel_id_key])) {
                                                $panel_name = getPanelName($conn, $file[$panel_id_key]);
                                                if (!empty($panel_name)) {
                                                    $assigned_panels[] = ["name" => $panel_name, "position" => "Panel $i"];
                                                }
                                            }
                                        }
                                        
                                        // Get assigned adviser name
                                        $adviser_name = "";
                                        $adviser_id = !empty($file['adviser_id']) ? $file['adviser_id'] : "";
                                        if (!empty($adviser_id)) {
                                            $adviser_name = getAdviserName($conn, $adviser_id);
                                        }
                                        
                                        // Create assignment status text
                                        $has_panels = !empty($assigned_panels);
                                        $has_adviser = !empty($adviser_name);
                                        if ($has_panels && $has_adviser) {
                                            $assignment_status = "View Assignments";
                                        } elseif ($has_panels) {
                                            $assignment_status = "View Panelists";
                                        } elseif ($has_adviser) {
                                            $assignment_status = "View Adviser";
                                        } else {
                                            $assignment_status = "Not Assigned";
                                        }

                                        $fileData = [
                                            'filepath' => $filepath,
                                            'panel1_id' => $file['panel1_id'] ?? '',
                                            'panel2_id' => $file['panel2_id'] ?? '',
                                            'panel3_id' => $file['panel3_id'] ?? '',
                                            'panel4_id' => $file['panel4_id'] ?? '',
                                            'panel5_id' => $file['panel5_id'] ?? '',
                                            'panel6_id' => $file['panel6_id'] ?? '',
                                            'adviser_id' => $adviser_id
                                        ];
                                        
                                        $assignmentData = [
                                            'panels' => $assigned_panels,
                                            'adviser' => $adviser_name
                                        ];
                                    ?>
                                    <tr>
                                        <td><?= $controlNo ?></td>
                                        <td><?= $fullname ?></td>
                                        <td><?= $group_number ?></td>
                                        <td><?= $title ?></td>

                                        <td>
                                            <button type="button" class="assignment-button <?= ($has_panels || $has_adviser) ? '' : 'not-assigned' ?>" 
                                                    onclick="showAssignmentDetails(
                                                        <?= htmlspecialchars(json_encode($assignmentData), ENT_QUOTES) ?>, 
                                                        <?= htmlspecialchars(json_encode($fileData), ENT_QUOTES) ?>
                                                    )">
                                                <?= $assignment_status ?>
                                            </button>
                                        </td>
                                        <td>
                                            <span style="color: <?= $statusColor ?>; font-weight: bold;">
                                                <?= $statusLabel ?>
                                            </span>
                                        </td>
<td>
    <div class="action-cell">
        <button type="button" class="view-button" onclick="viewFile('<?= $filepath ?>', '<?= $student_id ?>', '<?= $file['finaldocu_id'] ?? '' ?>')">View</button>
        <?php if (!empty($file['minutes'])): ?>
            <?php $minutesJs = json_encode($file['minutes']); ?>
            <button type="button" class="view-button" onclick='viewMinutes(<?= $minutesJs ?>)'>View Minutes</button>
        <?php endif; ?>
        <button type="button" class="delete-button" onclick="confirmDelete('<?= $file['finaldocu_id'] ?>', '<?= $filepath ?>', '<?= $fullname ?>', '<?= $title ?>')">Delete</button>
    </div>
</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No files found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
    </div>

<!-- ═══ NEW VIEWER MODAL ═══ -->
<div class="viewer-overlay" id="viewerOverlay">
    <div class="viewer-modal-box">
        <div class="vmod-header">
            <div class="vmod-hl">
                <span class="vmod-badge">Final Document</span>
                <div>
                    <div class="vmod-title" id="vmod-doc-title">Thesis Document</div>
                    <div class="vmod-sub">Saint Michael College of Caraga — TRS Admin</div>
                </div>
            </div>
            <div class="vmod-hr">
                <button class="vmod-print-btn" onclick="vmodPrint()">
                    <svg viewBox="0 0 24 24"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
                    Print Form
                </button>
                <button class="vmod-close-btn" onclick="closeModal()">×</button>
            </div>
        </div>
        <div class="vmod-accent-bar"></div>

        <div class="vmod-body">
            <!-- PDF Pane -->
            <div class="vmod-pdf-pane">
                <div class="vmod-pdf-toolbar">
                    <span class="vmod-pdf-label">Document</span>
                    <span class="vmod-pdf-fname" id="vmod-pdf-fname">–</span>
                    <span class="vmod-pdf-live"><span class="vmod-pdf-dot"></span>Live Preview</span>
                </div>
                <div class="vmod-pdf-wrap">
                    <div class="vmod-pdf-loader" id="vmod-pdf-loader">
                        <div class="vmod-spinner"></div>
                        <span class="vmod-loader-text">Loading document…</span>
                    </div>
                    <iframe id="vmod-pdf-frame" src="" onload="document.getElementById('vmod-pdf-loader').classList.add('hidden')" title="Thesis Document"></iframe>
                    <div id="vmod-docx-preview" style="display:none;overflow-y:auto;height:100%;background:white;padding:20px;"></div>
                </div>
            </div>

            <!-- Form Pane -->
            <div class="vmod-form-pane">
                <div class="vmod-form-hdr">
                    <div class="vmod-inst-row">
                        <div class="vmod-inst-logo">
                            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                        </div>
                        <div class="vmod-inst-name">Saint Michael College of Caraga<br>Research &amp; Instructional Innovation Dept.</div>
                    </div>
                    <div class="vmod-fhdr-title">Routing Monitoring Form</div>
                    <div class="vmod-fhdr-meta">
                        <div class="vmod-fhdr-meta-item">
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span id="vmod-school-year">–</span>
                        </div>
                        <div class="vmod-fhdr-meta-item">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            Group <span id="vmod-group-no">–</span>
                        </div>
                        <div class="vmod-fhdr-meta-item">
                            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <span id="vmod-control-no">–</span>
                        </div>
                    </div>
                </div>

                <div class="vmod-tabs">
                    <div class="vmod-tab active" onclick="vmodSwitchTab('feedback')">Submitted Forms</div>
                    <div class="vmod-tab" onclick="vmodSwitchTab('summary')">Summary</div>
                </div>

                <div class="vmod-form-body">
                    <!-- Feedback Tab -->
                    <div class="vmod-tab-pane active" id="vmod-pane-feedback">
                        <div class="vmod-comments-loading" id="vmod-loader">
                            <div class="vmod-spinner" style="border-top-color:var(--accent);"></div>
                            <p>Loading feedback…</p>
                        </div>
                        <div id="vmod-feedback-container"></div>
                        <div id="vmod-no-forms" style="margin-top:10px;color:gray;font-size:12px;text-align:center;"></div>
                    </div>

                    <!-- Summary Tab -->
                    <div class="vmod-tab-pane" id="vmod-pane-summary">
                        <div class="vmod-stat-grid">
                            <div class="vmod-stat-box"><div class="vmod-stat-num" id="vmod-sum-total">0</div><div class="vmod-stat-lbl">Total Comments</div></div>
                            <div class="vmod-stat-box"><div class="vmod-stat-num" id="vmod-sum-routes">0</div><div class="vmod-stat-lbl">Routes Done</div></div>
                            <div class="vmod-stat-box"><div class="vmod-stat-num" id="vmod-sum-approved">0</div><div class="vmod-stat-lbl">Approved</div></div>
                            <div class="vmod-stat-box"><div class="vmod-stat-num" id="vmod-sum-pending">0</div><div class="vmod-stat-lbl">Pending</div></div>
                        </div>
                        <div class="vmod-sum-card">
                            <div class="vmod-sum-card-title">Document Details</div>
                            <div class="vmod-sum-row"><span class="vmod-sum-lbl">Control No.</span><span class="vmod-sum-val" id="vmod-sum-control">–</span></div>
                            <div class="vmod-sum-row"><span class="vmod-sum-lbl">Leader</span><span class="vmod-sum-val" id="vmod-sum-leader">–</span></div>
                            <div class="vmod-sum-row"><span class="vmod-sum-lbl">Group No.</span><span class="vmod-sum-val" id="vmod-sum-group">–</span></div>
                            <div class="vmod-sum-row"><span class="vmod-sum-lbl">Title</span><span class="vmod-sum-val" id="vmod-sum-title" style="font-size:10px;max-width:200px;text-align:right;">–</span></div>
                        </div>
                    </div>
                </div>

                <div class="vmod-footer">
                    <span class="vmod-footer-text">SMCC Thesis Routing System — Admin</span>
                    <div class="vmod-footer-actions">
                        <button class="vmod-footer-btn outline" onclick="closeModal()">Close</button>
                        <button class="vmod-footer-btn primary" onclick="vmodPrint()">Print Form</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Minutes Modal Viewer -->
    <div id="minutesModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeMinutesModal()">×</span>
            <div class="modal-layout">
                <div id="minutesModalContent" class="file-preview-section" style="flex: 1;"></div>
            </div>
        </div>
    </div>

    <div id="assignmentModal" class="assignment-modal">
    <div class="assignment-modal-content">
        <span class="assignment-modal-close" onclick="closeAssignmentModal()">&times;</span>
        <h2>Assignment Details</h2>
        
        <div id="view-assignments-section">
            <div class="assignment-list">
                <h3>Panelists</h3>
                <ul id="panelists-list"></ul>
                <h3>Adviser</h3>
                <div id="adviser-name"></div>
            </div>
            <button id="edit-assignments-btn" class="edit-button" onclick="showEditAssignments()">Edit Assignments</button>
        </div>
        
        <div id="edit-assignments-section" style="display: none;">
            <form id="update-assignments-form" method="POST">
                <input type="hidden" id="edit-filepath" name="filepath">
                <input type="hidden" name="update_assignments" value="1">
                
                <div class="form-group">
                    <label for="edit-panel1">Panel 1:</label>
                    <select id="edit-panel1" name="panel1" class="form-control">
                        <option value="">None</option>
                        <?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel WHERE position = 'panel1'");
                            $panelStmt->execute();
                            $panelResult = $panelStmt->get_result();
                            while ($row = $panelResult->fetch_assoc()):
                            ?>
                                <option value="<?= htmlspecialchars($row['panel_id']) ?>">
                                    <?= htmlspecialchars($row['fullname']) ?> <?= ($row['department'] != $selectedDepartment) ? '(' . htmlspecialchars($row['department']) . ')' : '' ?>
                                </option>
                            <?php endwhile;
                            $panelStmt->close();
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-panel2">Panel 2:</label>
                    <select id="edit-panel2" name="panel2" class="form-control">
                        <option value="">None</option>
                        <?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname FROM panel WHERE department = ? AND position = 'panel2'");
                            $panelStmt->bind_param("s", $selectedDepartment);
                            $panelStmt->execute();
                            $panelResult = $panelStmt->get_result();
                            while ($row = $panelResult->fetch_assoc()):
                            ?>
                                <option value="<?= htmlspecialchars($row['panel_id']) ?>">
                                    <?= htmlspecialchars($row['fullname']) ?>
                                </option>
                            <?php endwhile;
                            $panelStmt->close();
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-panel3">Panel 3:</label>
                    <select id="edit-panel3" name="panel3" class="form-control">
                        <option value="">None</option>
                        <?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname FROM panel WHERE department = ? AND position = 'panel3'");
                            $panelStmt->bind_param("s", $selectedDepartment);
                            $panelStmt->execute();
                            $panelResult = $panelStmt->get_result();
                            while ($row = $panelResult->fetch_assoc()):
                            ?>
                                <option value="<?= htmlspecialchars($row['panel_id']) ?>">
                                    <?= htmlspecialchars($row['fullname']) ?>
                                </option>
                            <?php endwhile;
                            $panelStmt->close();
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-panel4">Panel 4:</label>
                    <select id="edit-panel4" name="panel4" class="form-control">
                        <option value="">None</option>
                        <?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname FROM panel WHERE department = ? AND position = 'panel4'");
                            $panelStmt->bind_param("s", $selectedDepartment);
                            $panelStmt->execute();
                            $panelResult = $panelStmt->get_result();
                            while ($row = $panelResult->fetch_assoc()):
                            ?>
                                <option value="<?= htmlspecialchars($row['panel_id']) ?>">
                                    <?= htmlspecialchars($row['fullname']) ?>
                                </option>
                            <?php endwhile;
                            $panelStmt->close();
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-panel5">Panel 5:</label>
                    <select id="edit-panel5" name="panel5" class="form-control">
                        <option value="">None</option>
                        <?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname FROM panel WHERE department = ? AND position = 'panel5'");
                            $panelStmt->bind_param("s", $selectedDepartment);
                            $panelStmt->execute();
                            $panelResult = $panelStmt->get_result();
                            while ($row = $panelResult->fetch_assoc()):
                            ?>
                                <option value="<?= htmlspecialchars($row['panel_id']) ?>">
                                    <?= htmlspecialchars($row['fullname']) ?>
                                </option>
                            <?php endwhile;
                            $panelStmt->close();
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit-panel6">Panel 6:</label>
                    <select id="edit-panel6" name="panel6" class="form-control">
                        <option value="">None</option>
                        <?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname FROM panel WHERE department = ? AND position = 'panel6'");
                            $panelStmt->bind_param("s", $selectedDepartment);
                            $panelStmt->execute();
                            $panelResult = $panelStmt->get_result();
                            while ($row = $panelResult->fetch_assoc()):
                            ?>
                                <option value="<?= htmlspecialchars($row['panel_id']) ?>">
                                    <?= htmlspecialchars($row['fullname']) ?>
                                </option>
                            <?php endwhile;
                            $panelStmt->close();
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-adviser">Adviser:</label>
                    <select id="edit-adviser" name="adviser_id" class="form-control" disabled>
                        <option value="">None</option>
                        <?php foreach ($advisers as $adv): ?>
                            <option value="<?= htmlspecialchars($adv['adviser_id']) ?>">
                                <?= htmlspecialchars($adv['fullname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="save-button">Save Changes</button>
                    <button type="button" class="cancel-button" onclick="cancelEdit()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

    // Search function for the table
    function searchTable() {
        const input = document.getElementById("searchInput");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("file-list");
        const rows = table.getElementsByTagName("tr");
        
        for (let i = 0; i < rows.length; i++) {
            const leaderCell = rows[i].getElementsByTagName("td")[1];
            const groupCell = rows[i].getElementsByTagName("td")[2];
            const titleCell = rows[i].getElementsByTagName("td")[3];
            const deptCell = rows[i].getElementsByTagName("td")[4];
            
            if (leaderCell && groupCell && titleCell && deptCell) {
                const leaderName = leaderCell.textContent || leaderCell.innerText;
                const groupNum = groupCell.textContent || groupCell.innerText;
                const title = titleCell.textContent || titleCell.innerText;
                const department = deptCell.textContent || deptCell.innerText;
                
                if (leaderName.toUpperCase().indexOf(filter) > -1 || 
                    groupNum.toUpperCase().indexOf(filter) > -1 ||
                    title.toUpperCase().indexOf(filter) > -1 ||
                    department.toUpperCase().indexOf(filter) > -1) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        }
    }

    // Global variable to store current file data for edit mode
    let currentEditFile = null;

    function showAssignmentDetails(assignments, fileData = null) {
        const modal = document.getElementById('assignmentModal');
        const panelistsList = document.getElementById('panelists-list');
        const adviserName = document.getElementById('adviser-name');
        
        currentEditFile = fileData;
        
        panelistsList.innerHTML = '';
        adviserName.innerHTML = '';
        adviserName.className = '';
        
        document.getElementById('view-assignments-section').style.display = 'block';
        document.getElementById('edit-assignments-section').style.display = 'none';
        
        if (assignments.panels && assignments.panels.length > 0) {
            assignments.panels.forEach(panel => {
                const li = document.createElement('li');
                li.textContent = `${panel.position}: ${panel.name}`;
                panelistsList.appendChild(li);
            });
        } else {
            const li = document.createElement('li');
            li.textContent = 'No panelists assigned';
            li.className = 'not-assigned';
            panelistsList.appendChild(li);
        }
        
        if (assignments.adviser && assignments.adviser.trim() !== '') {
            adviserName.textContent = assignments.adviser;
            adviserName.className = '';
        } else {
            adviserName.textContent = 'No adviser assigned';
            adviserName.className = 'not-assigned';
        }
        
        modal.style.display = 'block';
    }

    function showEditAssignments() {
        if (!currentEditFile) {
            alert("File data not available. Please try again.");
            return;
        }
        
        document.getElementById('view-assignments-section').style.display = 'none';
        document.getElementById('edit-assignments-section').style.display = 'block';
        
        document.getElementById('edit-filepath').value = currentEditFile.filepath;
        
        if (currentEditFile.panel1_id) document.getElementById('edit-panel1').value = currentEditFile.panel1_id;
        if (currentEditFile.panel2_id) document.getElementById('edit-panel2').value = currentEditFile.panel2_id;
        if (currentEditFile.panel3_id) document.getElementById('edit-panel3').value = currentEditFile.panel3_id;
        if (currentEditFile.panel4_id) document.getElementById('edit-panel4').value = currentEditFile.panel4_id;
        if (currentEditFile.panel5_id) document.getElementById('edit-panel5').value = currentEditFile.panel5_id;
        if (currentEditFile.panel6_id) document.getElementById('edit-panel6').value = currentEditFile.panel6_id;
        if (currentEditFile.adviser_id) document.getElementById('edit-adviser').value = currentEditFile.adviser_id;
    }

    function cancelEdit() {
        document.getElementById('view-assignments-section').style.display = 'block';
        document.getElementById('edit-assignments-section').style.display = 'none';
    }

    function closeAssignmentModal() {
        document.getElementById('assignmentModal').style.display = 'none';
        currentEditFile = null;
    }

    // ═══ NEW VIEWER MODAL LOGIC ═══
    let vmodFormData = [];

    // FIX 1: Function signature uses finaldocu_id, and calls vmodLoadFeedback correctly
    function viewFile(filePath, student_id, finaldocu_id) {
        const rows = document.querySelectorAll('#file-list tr');
        let controlNo = '–', leader = '–', groupNo = '–', title = '–';
        rows.forEach(row => {
            const viewBtn = row.querySelector('.view-button');
            if (viewBtn && viewBtn.getAttribute('onclick') && viewBtn.getAttribute('onclick').includes(filePath)) {
                controlNo = row.cells[0]?.textContent.trim() || '–';
                leader    = row.cells[1]?.textContent.trim() || '–';
                groupNo   = row.cells[2]?.textContent.trim() || '–';
                title     = row.cells[3]?.textContent.trim() || '–';
            }
        });

        document.getElementById('vmod-doc-title').textContent  = title;
        document.getElementById('vmod-pdf-fname').textContent  = filePath.split('/').pop();
        document.getElementById('vmod-control-no').textContent = controlNo;
        document.getElementById('vmod-group-no').textContent   = groupNo;
        const yr = new Date().getFullYear();
        document.getElementById('vmod-school-year').textContent = 'AY ' + yr + '–' + (yr + 1);

        document.getElementById('vmod-sum-control').textContent = controlNo;
        document.getElementById('vmod-sum-leader').textContent  = leader;
        document.getElementById('vmod-sum-group').textContent   = groupNo;
        document.getElementById('vmod-sum-title').textContent   = title;

        document.getElementById('vmod-pdf-loader').classList.remove('hidden');
        document.getElementById('vmod-pdf-frame').src    = '';
        document.getElementById('vmod-pdf-frame').style.display = 'block';
        document.getElementById('vmod-docx-preview').style.display = 'none';
        document.getElementById('vmod-docx-preview').innerHTML = '';

        const overlay = document.getElementById('viewerOverlay');
        overlay.style.display = 'flex';
        requestAnimationFrame(() => overlay.classList.add('open'));
        vmodSwitchTab('feedback');

        const ext = filePath.split('.').pop().toLowerCase();
        if (ext === 'pdf') {
            document.getElementById('vmod-pdf-frame').src = filePath;
        } else if (ext === 'docx') {
            document.getElementById('vmod-pdf-loader').classList.add('hidden');
            document.getElementById('vmod-pdf-frame').style.display = 'none';
            const preview = document.getElementById('vmod-docx-preview');
            preview.style.display = 'block';
            preview.innerHTML = '<div style="padding:20px;color:#aaa;text-align:center;">Converting document…</div>';
            fetch(filePath)
                .then(r => r.arrayBuffer())
                .then(buf => mammoth.convertToHtml({ arrayBuffer: buf }))
                .then(result => { preview.innerHTML = '<div style="font-family:Arial,sans-serif;font-size:13px;line-height:1.6;">' + result.value + '</div>'; })
                .catch(() => { preview.innerHTML = '<div style="padding:20px;color:red;text-align:center;">Failed to load document.</div>'; });
        } else {
            document.getElementById('vmod-pdf-loader').classList.add('hidden');
            document.getElementById('vmod-pdf-frame').style.display = 'none';
            document.getElementById('vmod-docx-preview').style.display = 'block';
            document.getElementById('vmod-docx-preview').innerHTML = '<div style="padding:20px;text-align:center;color:#999;">Unsupported file type.</div>';
        }

        // FIX 1: Pass finaldocu_id (not route1_id)
        vmodLoadFeedback(student_id, finaldocu_id);
    }

    function closeModal() {
        const overlay = document.getElementById('viewerOverlay');
        overlay.classList.remove('open');
        setTimeout(() => {
            overlay.style.display = 'none';
            document.getElementById('vmod-pdf-frame').src = '';
            document.getElementById('vmod-docx-preview').innerHTML = '';
            document.getElementById('vmod-feedback-container').innerHTML = '';
        }, 320);
    }

    function vmodSwitchTab(name) {
        const tabs  = document.querySelectorAll('.vmod-tab');
        const panes = document.querySelectorAll('.vmod-tab-pane');
        const names = ['feedback', 'summary'];
        tabs.forEach((t, i) => t.classList.toggle('active', names[i] === name));
        panes.forEach(p => p.classList.toggle('active', p.id === 'vmod-pane-' + name));
    }

    // FIX 2: Use finaldocu_id parameter correctly in the fetch URL (only student_id needed for route3)
    function vmodLoadFeedback(student_id, finaldocu_id) {
        const container = document.getElementById('vmod-feedback-container');
        const noMsg     = document.getElementById('vmod-no-forms');
        const loader    = document.getElementById('vmod-loader');
        container.innerHTML = '';
        noMsg.textContent   = '';
        loader.style.display = 'flex';

        fetch(`route3get_all_forms.php?student_id=${encodeURIComponent(student_id)}`)
            .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
            .then(data => {
                loader.style.display = 'none';
                vmodFormData = Array.isArray(data) ? data : [];

                if (!vmodFormData.length) {
                    noMsg.innerHTML = `<div style="text-align:center;padding:30px 20px;">
                        <svg viewBox="0 0 24 24" style="width:36px;height:36px;stroke:#ccc;fill:none;stroke-width:1.5;margin:0 auto 10px;display:block;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        <p style="font-size:13px;color:#777;">No feedback submitted yet.</p></div>`;
                    vmodRenderSummary([]);
                    return;
                }

                const groups = {};
                vmodFormData.forEach(row => {
                    const rn = row.routeNumber || 'Route';
                    if (!groups[rn]) groups[rn] = [];
                    groups[rn].push(row);
                });

                let html = '';
                Object.entries(groups).forEach(([routeName, rows]) => {
                    html += `<div class="vmod-rg">
                        <div class="vmod-rg-header">
                            <span class="vmod-rg-pill">${vmodE(routeName)}</span>
                            <div class="vmod-rg-line"></div>
                            <span class="vmod-rg-ct">${rows.length} comment${rows.length !== 1 ? 's' : ''}</span>
                        </div>`;

                    rows.forEach((row, i) => {
                        const isAdviser = !!row.adviser_name;
                        const role      = isAdviser ? 'adviser' : 'panel';
                        const name      = isAdviser ? row.adviser_name : (row.panel_name || 'Reviewer');
                        const initials  = name.split(',')[0].trim().substring(0, 2).toUpperCase();
                        const status    = (row.status || 'Pending').trim();

                        html += `<div class="vmod-cc ${role}" style="animation-delay:${i * 0.05}s">
                            <div class="vmod-cc-bar ${role}"></div>
                            <div class="vmod-cc-top">
                                <div class="vmod-cc-who">
                                    <div class="vmod-cc-avatar ${role}">${vmodE(initials)}</div>
                                    <div>
                                        <div class="vmod-cc-name">${vmodE(name)}</div>
                                        <div class="vmod-cc-role">${isAdviser ? 'Thesis Adviser' : 'Panel Member'}</div>
                                    </div>
                                </div>
                                <div class="vmod-cc-date">${vmodE(row.date_submitted || '—')}</div>
                            </div>
                            <div class="vmod-cc-locs">
                                ${row.chapter          ? `<div class="vmod-loc-chip"><strong>Chapter</strong>${vmodE(String(row.chapter))}</div>` : ''}
                                ${row.page_number      ? `<div class="vmod-loc-chip"><strong>Page</strong>${vmodE(String(row.page_number))}</div>` : ''}
                                ${row.paragraph_number ? `<div class="vmod-loc-chip"><strong>Para</strong>${vmodE(String(row.paragraph_number))}</div>` : ''}
                            </div>
                            <div class="vmod-cc-feedback">${vmodE(row.feedback || 'No feedback provided.')}</div>
                            <div class="vmod-cc-footer">
                                <div class="vmod-cc-footer-left">
                                    ${row.date_released
                                        ? `<div class="vmod-rel-badge">✓ Released ${vmodE(row.date_released)}</div>`
                                        : '<span style="font-size:10px;color:#777;">Not yet released</span>'}
                                </div>
                                <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:${status==='Approved'?'#d4edda':'#fff3cd'};color:${status==='Approved'?'#155724':'#856404'};">
                                    ${vmodE(status)}
                                </span>
                            </div>
                        </div>`;
                    });
                    html += '</div>';
                });

                container.innerHTML = html;
                vmodRenderSummary(vmodFormData);
            })
            .catch(err => {
                loader.style.display = 'none';
                noMsg.textContent = 'Error loading feedback: ' + err.message;
            });
    }

    function vmodRenderSummary(data) {
        const routes   = new Set();
        let approved = 0, pending = 0;
        data.forEach(r => {
            if (r.routeNumber) routes.add(r.routeNumber);
            if ((r.status || '').trim() === 'Approved') approved++; else pending++;
        });
        document.getElementById('vmod-sum-total').textContent    = data.length;
        document.getElementById('vmod-sum-routes').textContent   = routes.size;
        document.getElementById('vmod-sum-approved').textContent = approved;
        document.getElementById('vmod-sum-pending').textContent  = pending;
    }

    function vmodPrint() {
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        document.body.appendChild(iframe);
        const rows = vmodFormData.map(form => {
            const by = form.adviser_name ? form.adviser_name + ' – Adviser' : (form.panel_name ? form.panel_name + ' – Panel' : 'N/A');
            return `<tr>
                <td>${vmodE(form.date_submitted||'—')}</td>
                <td>${vmodE(String(form.chapter||'—'))}</td>
                <td>${vmodE(String(form.page_number||'—'))}</td>
                <td>${vmodE(String(form.paragraph_number||'—'))}</td>
                <td>${vmodE(form.feedback||'—')}</td>
                <td>${vmodE(by)}</td>
                <td>${vmodE(form.date_released||'—')}</td>
                <td>${vmodE(form.status||'—')}</td>
                <td>${vmodE(form.routeNumber||'—')}</td>
            </tr>`;
        }).join('');
        const doc = `<!DOCTYPE html><html><head><title>Routing Monitoring Form – Final Document</title>
        <style>body{font-family:Arial,sans-serif;padding:20px;font-size:12px;}h2{color:#4366b3;text-align:center;}h3{text-align:center;font-size:13px;color:#555;margin-bottom:20px;}table{width:100%;border-collapse:collapse;}th{background:#4366b3;color:white;padding:8px;font-size:11px;text-align:left;}td{padding:7px 8px;border-bottom:1px solid #eee;font-size:11px;}@media print{@page{size:landscape;margin:1cm}}</style>
        </head><body>
        <h2>SAINT MICHAEL COLLEGE OF CARAGA</h2>
        <h3>Research &amp; Instructional Innovation Department — Routing Monitoring Form (Final Document)</h3>
        <table><thead><tr><th>Date</th><th>Chapter</th><th>Page No</th><th>Para No</th><th>Feedback</th><th>Submitted By</th><th>Released</th><th>Status</th><th>Route</th></tr></thead>
        <tbody>${rows || '<tr><td colspan="9" style="text-align:center;color:#999;">No records found.</td></tr>'}</tbody></table>
        </body></html>`;
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(doc);
        iframe.contentWindow.document.close();
        iframe.onload = () => { setTimeout(() => { iframe.contentWindow.print(); setTimeout(() => document.body.removeChild(iframe), 500); }, 300); };
    }

    function vmodE(str) {
        if (str == null) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    // Close viewer on overlay click or Escape
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('viewerOverlay').addEventListener('click', function(ev) {
            if (ev.target === ev.currentTarget) closeModal();
        });
    });

    // FIX 3: Removed undefined closeMinutesModal() call — it IS defined below so keep it
    document.addEventListener('keydown', function(ev) {
        if (ev.key === 'Escape') {
            closeModal();
            closeMinutesModal();
            closeAssignmentModal();
        }
    });
    // ═══ END NEW VIEWER MODAL LOGIC ═══

    function closeMinutesModal() {
        const modal = document.getElementById("minutesModal");
        modal.style.display = "none";
        document.getElementById("minutesModalContent").innerHTML = '';
    }

    function viewMinutes(minutesPath) {
        const modal = document.getElementById("minutesModal");
        const contentArea = document.getElementById("minutesModalContent");

        modal.style.display = "flex";
        contentArea.innerHTML = "<div style='display: flex; justify-content: center; align-items: center; height: 100%;'><div style='text-align: center;'><div class='spinner' style='border: 4px solid rgba(0, 0, 0, 0.1); width: 40px; height: 40px; border-radius: 50%; border-left-color: var(--accent); animation: spin 1s linear infinite; margin: 0 auto;'></div><p style='margin-top: 10px;'>Loading minutes file...</p></div></div>";

        const extension = minutesPath.split('.').pop().toLowerCase();
        if (extension === "pdf") {
            contentArea.innerHTML = `<iframe src="${minutesPath}" width="100%" height="100%" style="border: none;"></iframe>`;
        } else {
            contentArea.innerHTML = "<div style='text-align: center; padding: 2rem;'><p style='color: #dc3545;'>Unsupported file type. Only PDF files are supported.</p></div>";
        }
    }

    // AJAX form submission for assignment updates
    document.addEventListener('DOMContentLoaded', function() {
        const submitButton = document.getElementById('external-submit-button');
        if (!submitButton) {
            const panelContainer = document.querySelector('.panel-container');
            if (panelContainer) panelContainer.style.display = 'none';
        }
        
        const updateForm = document.getElementById('update-assignments-form');
        if (updateForm) {
            updateForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.delete('adviser_id');
                
                fetch('finaldocu.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Unknown error occurred');
                    
                    alert(data.message);
                    
                    const panel1Id = document.getElementById('edit-panel1').value;
                    const panel2Id = document.getElementById('edit-panel2').value;
                    const panel3Id = document.getElementById('edit-panel3').value;
                    const panel4Id = document.getElementById('edit-panel4').value;
                    const panel5Id = document.getElementById('edit-panel5').value;
                    const panel6Id = document.getElementById('edit-panel6').value;
                    const adviserId = currentEditFile.adviser_id;
                    
                    currentEditFile.panel1_id = panel1Id;
                    currentEditFile.panel2_id = panel2Id;
                    currentEditFile.panel3_id = panel3Id;
                    currentEditFile.panel4_id = panel4Id;
                    currentEditFile.panel5_id = panel5Id;
                    currentEditFile.panel6_id = panel6Id;
                    
                    const panels = [];
                    ['panel1','panel2','panel3','panel4','panel5','panel6'].forEach((p, idx) => {
                        const sel = document.getElementById('edit-' + p);
                        if (sel.value) {
                            panels.push({position: 'Panel ' + (idx+1), name: sel.options[sel.selectedIndex].text});
                        }
                    });
                    
                    let adviserName = '';
                    if (adviserId) {
                        const adviserSelect = document.getElementById('edit-adviser');
                        const selectedOption = Array.from(adviserSelect.options).find(o => o.value === adviserId);
                        adviserName = selectedOption ? selectedOption.text : '';
                    }
                    
                    const updatedAssignmentData = { panels, adviser: adviserName };
                    
                    const panelistsList = document.getElementById('panelists-list');
                    const adviserNameElement = document.getElementById('adviser-name');
                    panelistsList.innerHTML = '';
                    adviserNameElement.innerHTML = '';
                    adviserNameElement.className = '';
                    
                    if (panels.length > 0) {
                        panels.forEach(panel => {
                            const li = document.createElement('li');
                            li.textContent = `${panel.position}: ${panel.name}`;
                            panelistsList.appendChild(li);
                        });
                    } else {
                        const li = document.createElement('li');
                        li.textContent = 'No panelists assigned';
                        li.className = 'not-assigned';
                        panelistsList.appendChild(li);
                    }
                    
                    if (adviserName && adviserName.trim() !== '') {
                        adviserNameElement.textContent = adviserName;
                    } else {
                        adviserNameElement.textContent = 'No adviser assigned';
                        adviserNameElement.className = 'not-assigned';
                    }
                    
                    document.getElementById('view-assignments-section').style.display = 'block';
                    document.getElementById('edit-assignments-section').style.display = 'none';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to update assignments: ' + error.message);
                });
            });
        }
    });

    function confirmDelete(finaldocuId, filepath, studentName, title) {
        if (confirm(`Are you sure you want to delete the file for ${studentName} (${title})?\nThis action cannot be undone.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'delete_file';
            idInput.value = finaldocuId;

            const fileInput = document.createElement('input');
            fileInput.type = 'hidden';
            fileInput.name = 'filepath';
            fileInput.value = filepath;

            form.appendChild(idInput);
            form.appendChild(fileInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
<script src="../sidebar.js"></script>