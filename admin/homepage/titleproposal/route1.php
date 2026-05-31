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

// Import PHPMailer classes at the top of the file
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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

// Fetch school years
$schoolYears = [];
$schoolYearQuery = "SELECT DISTINCT school_year FROM route1proposal_files ORDER BY school_year DESC";
$schoolYearResult = $conn->query($schoolYearQuery);
if ($schoolYearResult && $schoolYearResult->num_rows > 0) {
    while ($row = $schoolYearResult->fetch_assoc()) {
        $schoolYears[] = $row['school_year'];
    }
}

// Helper functions to get names from IDs
function getPanelName($conn, $panel_id) {
    $stmt = $conn->prepare("SELECT fullname, email FROM panel WHERE panel_id = ?");
    $stmt->bind_param("i", $panel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

function getAdviserName($conn, $adviser_id) {
    $stmt = $conn->prepare("SELECT fullname FROM adviser WHERE adviser_id = ?");
    $stmt->bind_param("i", $adviser_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['fullname'];
    }
    return "";
}

// Function to validate email address
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Custom error logging function for email issues
function logEmailError($message) {
    $logFile = __DIR__ . '/../../../email_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    // Also log to PHP error log
    error_log($message);
    
    // Write to custom log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Function to prepare email data for background processing
function prepareEmailData($panelEmail, $panelName, $position, $studentName, $title) {
    $emailData = [
        'email' => $panelEmail,
        'name' => $panelName,
        'position' => $position,
        'student_name' => $studentName,
        'title' => $title,
        'smtp_config' => [
            'host' => 'smtp.gmail.com',
            'username' => 'smcctrs@gmail.com',
            'password' => 'cplw gwfe vxcy naoi',
            'secure' => 'tls',
            'port' => 587,
            'charset' => 'UTF-8',
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'timeout' => 60,
            'keep_alive' => true
        ],
        'headers' => [
            'from' => 'smcctrs@gmail.com',
            'reply_to' => 'smcctrs@gmail.com',
            'subject' => "Panel Assignment Notification - Thesis Routing System",
            'x_mailer' => 'Thesis Routing System',
            'x_priority' => '3'
        ],
        'content' => [
            'html' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                    <h2 style='color: #4366b3; text-align: center;'>Thesis Routing System Notification</h2>
                    <p>Dear <strong>{$panelName}</strong>,</p>
                    <p>You have been assigned as <strong>{$position}</strong> for the following thesis:</p>
                    <p><strong>Student:</strong> {$studentName}</p>
                    <p><strong>Title:</strong> {$title}</p>
                    <p>Please log in to the Thesis Routing System to review the proposal.</p>
                    <div style='margin-top: 30px; text-align: center;'>
                        <a href='https://capstone.smccnasipit.edu.ph/trs/panel/login.php' style='background-color: #4366b3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Review</a>
                    </div>
                    <p style='margin-top: 10px; text-align: center;'>If the button above doesn't work, copy and paste this URL into your browser: <br><a href='https://capstone.smccnasipit.edu.ph/trs/panel/login.php'>https://capstone.smccnasipit.edu.ph/trs/panel/login.php</a></p>
                    <p style='margin-top: 30px; font-size: 12px; color: #777; text-align: center;'>This is an automated message from the Thesis Routing System. Please do not reply to this email.</p>
                </div>
            ",
            'text' => "Dear {$panelName}, You have been assigned as {$position} for the thesis '{$title}' by {$studentName}. Please login at: https://capstone.smccnasipit.edu.ph/trs/panel/login.php to review the proposal."
        ]
    ];

    return serialize($emailData);
}

// Function to send email notification to panel (asynchronous)
function sendPanelNotificationEmail($panelEmail, $panelName, $position, $studentName, $title) {
    try {
        // Validate email address first
        if (!isValidEmail($panelEmail)) {
            logEmailError("Invalid email address format: $panelEmail");
            return false;
        }

        // Prepare email data
        $emailData = prepareEmailData($panelEmail, $panelName, $position, $studentName, $title);

        // Create temporary file with email data
        $tempFile = tempnam(sys_get_temp_dir(), 'email_');
        file_put_contents($tempFile, $emailData);

        // Execute background script to send email
        $backgroundScript = __DIR__ . '/send_email_background.php';
        if (!file_exists($backgroundScript)) {
            logEmailError("Background script not found: $backgroundScript");
            return false;
        }

        // Run background script asynchronously
        $command = escapeshellcmd("php $backgroundScript $tempFile > /dev/null 2>&1 &");
        exec($command);

        return true;
    } catch (Exception $e) {
        logEmailError("Error preparing email data: " . $e->getMessage());
        return false;
    }
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

// Load data for selected department
if (isset($_SESSION['selected_department'])) {
    $selectedDepartment = $_POST['department'] ?? $_SESSION['selected_department'] ?? '';
    $selectedSchoolYear = $_POST['school_year'] ?? $_SESSION['selected_school_year'] ?? '';
    $selectedSemester = $_POST['semester'] ?? $_SESSION['selected_semester'] ?? '';
    
    $_SESSION['selected_department'] = $selectedDepartment;
    $_SESSION['selected_school_year'] = $selectedSchoolYear;
    $_SESSION['selected_semester'] = $selectedSemester;

    // Fetch panel data
    $panelStmt = $conn->prepare("SELECT * FROM panel WHERE department = ?");
    $panelStmt->bind_param("s", $selectedDepartment);
    $panelStmt->execute();
    $panelResult = $panelStmt->get_result();
    while ($row = $panelResult->fetch_assoc()) {
        $panel[$row['position']][] = $row['fullname'];
    }
    $panelStmt->close();

    // Fetch adviser data
    $adviserStmt = $conn->prepare("SELECT * FROM adviser WHERE department = ?");
    $adviserStmt->bind_param("s", $selectedDepartment);
    $adviserStmt->execute();
    $adviserResult = $adviserStmt->get_result();
    while ($row = $adviserResult->fetch_assoc()) {
        $adviser[] = $row['fullname'];
    }
    $adviserStmt->close();

    // Create proper query with placeholders
    $sqlQuery = "SELECT 
            docuRoute1 AS filepath, 
            docuRoute1 AS filename, 
            date_submitted,
            panel_deadline,
            controlNo,
            group_number,
            fullname,
            title,
            student_id, panel1_id, panel2_id, panel3_id, panel4_id, panel5_id, panel6_id, adviser_id,
            minutes,
            route1_id,
            department
         FROM route1proposal_files";
    
    // Create array of parameters (must be variables, not direct values)
    $types = "";
    $params = [];
    
    // Only filter by department if one is selected
    if (!empty($selectedDepartment)) {
        $sqlQuery .= " WHERE department = ?";
        $types .= "s";
        $params[] = $selectedDepartment;
        
        // Add school year filter if selected
        if (!empty($selectedSchoolYear)) {
            $sqlQuery .= " AND school_year = ?";
            $types .= "s";
            $params[] = $selectedSchoolYear;
        }
        
        // Add semester filter if selected
        if (!empty($selectedSemester)) {
            $sqlQuery .= " AND student_id IN (SELECT student_id FROM student WHERE semester = ?)";
            $types .= "s";
            $params[] = $selectedSemester;
        }
    } else {
        // If no department selected, just add other filters
        $whereAdded = false;
        
        // Add school year filter if selected
        if (!empty($selectedSchoolYear)) {
            $sqlQuery .= " WHERE school_year = ?";
            $whereAdded = true;
            $types .= "s";
            $params[] = $selectedSchoolYear;
        }
        
        // Add semester filter if selected
        if (!empty($selectedSemester)) {
            if ($whereAdded) {
                $sqlQuery .= " AND student_id IN (SELECT student_id FROM student WHERE semester = ?)";
            } else {
                $sqlQuery .= " WHERE student_id IN (SELECT student_id FROM student WHERE semester = ?)";
                $whereAdded = true;
            }
            $types .= "s";
            $params[] = $selectedSemester;
        }
    }
    
    $fileStmt = $conn->prepare($sqlQuery);
    
    // Bind parameters if there are any
    if (!empty($params)) {
        // Create an array of references
        $bind_params = array();
        $bind_params[] = $types; // First parameter is the types string
        
        // Add parameters by reference
        foreach ($params as $key => $value) {
            $bind_params[] = &$params[$key];
        }
        
        // Call bind_param with the parameters
        $ref = new \ReflectionMethod('mysqli_stmt', 'bind_param');
        $ref->invokeArgs($fileStmt, $bind_params);
    }
    
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
    'minutes' => $row['minutes'],
    'route1_id' => $row['route1_id'],
    'department' => $row['department'],
    'panel_deadline' => $row['panel_deadline']  // ADD THIS LINE
];
    }
    
    $fileStmt->close();
} else {
    // No department selected, fetch all files
    $sqlQuery = "SELECT 
            docuRoute1 AS filepath, 
            docuRoute1 AS filename, 
            date_submitted,
            panel_deadline,
            controlNo,
            group_number,
            fullname,
            title,
            student_id, panel1_id, panel2_id, panel3_id, panel4_id, panel5_id, panel6_id, adviser_id,
            minutes,
            route1_id,
            department
         FROM route1proposal_files";
    
    $fileStmt = $conn->prepare($sqlQuery);
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
            'minutes' => $row['minutes'],
            'route1_id' => $row['route1_id'],
            'department' => $row['department'],
            'panel_deadline' => $row['panel_deadline'] 
        ];
    }
    
    $fileStmt->close();
}

// Handle file deletion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_file'])) {
    $routeId = $_POST['delete_file'];
    $filepath = $_POST['filepath'] ?? '';
    
    // First, delete the file from the server if it exists
    if (!empty($filepath) && file_exists('../../../' . $filepath)) {
        @unlink('../../../' . $filepath);
    }
    
    // Then delete the database record using route1_id
    $deleteStmt = $conn->prepare("DELETE FROM route1proposal_files WHERE route1_id = ?");
    $deleteStmt->bind_param("i", $routeId);
    
    if ($deleteStmt->execute()) {
        echo "<script>alert('File deleted successfully!'); window.location.href = window.location.href;</script>";
    } else {
        echo "<script>alert('Error deleting file: ' + " . json_encode($conn->error) . "); window.location.href = window.location.href;</script>";
    }
    
    $deleteStmt->close();
    exit();
}

// Handle file submission for panelists and adviser
// Handle file submission for panelists and adviser
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['selected_files'])) {
    $selectedFiles = $_POST['selected_files'];
    $panel1 = $_POST['panel1'] ?? null;
    $panel2 = $_POST['panel2'] ?? null;
    $panel3 = $_POST['panel3'] ?? null;
    $panel4 = $_POST['panel4'] ?? null;
    $panel5 = $_POST['panel5'] ?? null;
    $panel6 = $_POST['panel6'] ?? null;
    $deadline = $_POST['deadline'] ?? null; // Get deadline from form

    // Validation
    if (empty($selectedFiles)) {
        echo "<script>alert('Please select at least one file.');</script>";
        return;
    }

    // Get all selected panels
    $panels = [$panel1, $panel2, $panel3, $panel4, $panel5, $panel6];
    $selectedPanels = array_filter($panels, function($panel) { 
        return !empty($panel);
    });

    // Check if at least one panel is selected
    if (empty($selectedPanels)) {
        echo "<script>alert('Please select at least one panel member.');</script>";
        return;
    }

    // Check for duplicate panel assignments
    $uniquePanels = array_unique($selectedPanels);
    if (count($uniquePanels) !== count($selectedPanels)) {
        echo "<script>alert('Each panel position must have a unique panel member. Please remove duplicates.');</script>";
        return;
    } else {
        foreach ($selectedFiles as $filePath) {
            $fileName = $filePath; // Use the full path stored in the DB

            // Check if the file exists in DB
            $checkStmt = $conn->prepare("SELECT * FROM route1proposal_files WHERE docuRoute1 = ?");
            $checkStmt->bind_param("s", $fileName);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $fileExists = $result->num_rows > 0;
            $checkStmt->close();

            if ($fileExists) {
                // Update panel IDs and set date_submitted
                $dateNow = date('Y-m-d H:i:s'); // Get the current date and time
                
                // Prepare SQL based on whether deadline is provided
                if (!empty($deadline)) {
                    $updatePanelStmt = $conn->prepare("UPDATE route1proposal_files 
                        SET panel1_id = ?, panel2_id = ?, panel3_id = ?, panel4_id = ?, panel5_id = ?, panel6_id = ?, date_submitted = ?, panel_deadline = ? 
                        WHERE docuRoute1 = ?");
                    
                    // Set NULL for empty panels
                    $panelIds = [
                        empty($panel1) ? null : $panel1,
                        empty($panel2) ? null : $panel2,
                        empty($panel3) ? null : $panel3,
                        empty($panel4) ? null : $panel4,
                        empty($panel5) ? null : $panel5,
                        empty($panel6) ? null : $panel6
                    ];
                    
                    $updatePanelStmt->bind_param("iiiiiiiss", 
                        $panelIds[0], $panelIds[1], $panelIds[2], $panelIds[3], $panelIds[4], $panelIds[5], 
                        $dateNow, $deadline, $fileName
                    );
                } else {
                    $updatePanelStmt = $conn->prepare("UPDATE route1proposal_files 
                        SET panel1_id = ?, panel2_id = ?, panel3_id = ?, panel4_id = ?, panel5_id = ?, panel6_id = ?, date_submitted = ? 
                        WHERE docuRoute1 = ?");
                    
                    // Set NULL for empty panels
                    $panelIds = [
                        empty($panel1) ? null : $panel1,
                        empty($panel2) ? null : $panel2,
                        empty($panel3) ? null : $panel3,
                        empty($panel4) ? null : $panel4,
                        empty($panel5) ? null : $panel5,
                        empty($panel6) ? null : $panel6
                    ];
                    
                    $updatePanelStmt->bind_param("iiiiiiis", 
                        $panelIds[0], $panelIds[1], $panelIds[2], $panelIds[3], $panelIds[4], $panelIds[5], 
                        $dateNow, $fileName
                    );
                }
                
                $updatePanelStmt->execute();
                $updatePanelStmt->close();

                // Get student information for email notifications
                $studentInfoStmt = $conn->prepare("SELECT fullname, title FROM route1proposal_files WHERE docuRoute1 = ?");
                $studentInfoStmt->bind_param("s", $fileName);
                $studentInfoStmt->execute();
                $studentInfo = $studentInfoStmt->get_result()->fetch_assoc();
                $studentName = $studentInfo['fullname'] ?? 'Unknown Student';
                $thesisTitle = $studentInfo['title'] ?? 'Unknown Title';
                $studentInfoStmt->close();

                // Send emails to newly assigned panels (including Panel 6)
                $panelPositions = [
                    1 => $panel1,
                    2 => $panel2,
                    3 => $panel3,
                    4 => $panel4,
                    5 => $panel5,
                    6 => $panel6
                ];

                $deadlineMsg = !empty($deadline) ? " Deadline has been set." : "";
                echo "<script>alert('Successfully Submitted. Email notifications sent to panel members.$deadlineMsg'); window.location.href = window.location.href;</script>";
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
    $adviser_id = $_POST['adviser_id'] ?? null;
    $deadline = $_POST['deadline'] ?? null; // ADD THIS LINE

    // Get all selected panels
    $panels = [$panel1, $panel2, $panel3, $panel4, $panel5, $panel6];
    $selectedPanels = array_filter($panels, function($panel) { 
        return !empty($panel);
    });

    // Check if at least one panel is selected
    if (empty($selectedPanels)) {
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
            echo "<script>alert('Please select at least one panel member.');</script>";
        } else {
            if (!headers_sent()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please select at least one panel member.']);
                exit;
            }
        }
        return;
    }

    // Check for duplicate panel assignments
    $uniquePanels = array_unique($selectedPanels);
    if (count($uniquePanels) !== count($selectedPanels)) {
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
            echo "<script>alert('Each panel position must have a unique panel member. Please remove duplicates.');</script>";
        } else {
            if (!headers_sent()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Each panel position must have a unique panel member. Please remove duplicates.']);
                exit;
            }
        }
        return;
    }

    // Get student information
    $studentInfoStmt = $conn->prepare("SELECT fullname, title FROM route1proposal_files WHERE docuRoute1 = ?");
    $studentInfoStmt->bind_param("s", $filepath);
    $studentInfoStmt->execute();
    $studentInfo = $studentInfoStmt->get_result()->fetch_assoc();
    $studentName = $studentInfo['fullname'] ?? 'Unknown Student';
    $thesisTitle = $studentInfo['title'] ?? 'Unknown Title';
    $studentInfoStmt->close();

    // REPLACE THE UPDATE STATEMENT WITH THIS:
    if (!empty($deadline)) {
        $updateStmt = $conn->prepare("UPDATE route1proposal_files 
            SET panel1_id = ?, panel2_id = ?, panel3_id = ?, panel4_id = ?, panel5_id = ?, panel6_id = ?, panel_deadline = ?
            WHERE docuRoute1 = ?");
        $updateStmt->bind_param("iiiiiiss", $panel1, $panel2, $panel3, $panel4, $panel5, $panel6, $deadline, $filepath);
    } else {
        $updateStmt = $conn->prepare("UPDATE route1proposal_files 
            SET panel1_id = ?, panel2_id = ?, panel3_id = ?, panel4_id = ?, panel5_id = ?, panel6_id = ?, panel_deadline = NULL
            WHERE docuRoute1 = ?");
        $updateStmt->bind_param("iiiiiiis", $panel1, $panel2, $panel3, $panel4, $panel5, $panel6, $filepath);
    }
    
    if ($updateStmt->execute()) {
        // Send emails to newly assigned panels
        $panelPositions = [
            1 => $panel1,
            2 => $panel2,
            3 => $panel3,
            4 => $panel4,
            5 => $panel5
        ];

        foreach ($panelPositions as $position => $panelId) {
            if ($panelId) {
                $panelInfo = getPanelName($conn, $panelId);
                if ($panelInfo && !empty($panelInfo['email'])) {
                    sendPanelNotificationEmail(
                        $panelInfo['email'],
                        $panelInfo['fullname'],
                        "Panel {$position}",
                        $studentName,
                        $thesisTitle
                    );
                }
            }
        }

        // Only show alert if not an AJAX request
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
            echo "<script>alert('Assignments updated successfully and notifications sent.');</script>";
        } else {
            // For AJAX requests, just return a success message that will be handled by JavaScript
            if (!headers_sent()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Assignments updated successfully and notifications sent.']);
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
    $stmt->bind_param("s", $department);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $advisers[] = $row;
    }
    
    $stmt->close();
    return $advisers;
}

// If department is selected, fetch advisers
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
    <title>Route 1 - Thesis Routing System</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.4.2/mammoth.browser.min.js"></script>
    <link rel="stylesheet" href="proposalstyle.css">
    <style>
    .action-label {text-align:center}
        /* Add styles for the search bar */
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
        text-decoration: none;
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
        text-decoration: none;
    }

    .assignment-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        text-decoration: none;
    }

    .assignment-modal-close:hover,
    .assignment-modal-close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
        text-decoration: none;
    }

    .assignment-list {
        margin-top: 20px;
        text-decoration: none;
    }

    .not-assigned {
        color: #888;
        font-style: italic;
        text-decoration: none;
    }

    .assignment-list h3 {
        margin-bottom: 5px;
        border-bottom: 1px solid #eee;
        padding-bottom: 5px;
        text-decoration: none;
    }

    .assignment-list ul {
        padding-left: 20px;
        margin-top: 5px;
        text-decoration: none;
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

/* Style to fix the panel buttons */
.assignment-button {
    white-space: nowrap;
}
    
    /* Styles for disabled rows and checkboxes */
    .already-assigned {
        background-color: #f5f5f5;
        color: #777;
    }
    
    .already-assigned:hover {
        cursor: default;
    }
    
    .selectable-row:hover {
        background-color: #f0f8ff;
        cursor: pointer;
    }
    
    input[type="checkbox"]:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .form-grid-container > div {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem;
        font-size: 0.8rem;
        border: 1px solid var(--border);
        background-color: white;
        text-align: center;
        word-break: break-word;
        overflow-wrap: break-word;
        min-height: 40px;
    }
    
    /* Specific style for the feedback cell (3rd column) - supports different grid sizes */
    .form-grid-container > div:nth-child(10n + 3),
    .form-grid-container > div:nth-child(9n + 3),
    .form-grid-container > div:nth-child(8n + 3) {
        text-align: left;
        justify-content: flex-start;
        overflow-y: visible;
        max-height: none;
        height: auto;
        white-space: pre-wrap;
    }

    .form-grid-container input,
    .form-grid-container textarea {
        width: 100%;
        height: 100%;
        padding: 4px;
        font-size: 0.75rem;
        box-sizing: border-box;
        border: none;
        outline: none;
        resize: none;
    }

    .form-input-row textarea {
        resize: vertical;
        min-height: 40px;
    }

    /* CSS Animation for spinner */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .sidebar {
            width: 250px;
            background-color: white;
            padding: 1.5rem 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid var(--border);
            position: sticky;
            top: 52px; /* Updated to account for the top bar height */
            height: calc(100vh - 52px); /* Adjusted to account for the top bar */
            overflow-y: auto;
            z-index: 90; /* Add z-index to ensure proper stacking */
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
.action-cell {
    display: flex;
    flex-direction: row;
    gap: 5px;
    align-items: center;
    flex-wrap: nowrap;
    white-space: nowrap;
}
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
                <div class="routeNo" style="margin-right: 20px;">Proposal - Route 1</div>
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
                
                <!-- Panel Selection and Submit Button Area -->
                <!-- Panel Selection and Submit Button Area -->
<div class="panel-container">
    <select id="panel1-dropdown">
        <option value="">Panel 1</option>
<?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
    <select id="panel2-dropdown">
        <option value="">Panel 2</option>
<?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
    
    <select id="panel3-dropdown">
        <option value="">Panel 3</option>
<?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
    
    <select id="panel4-dropdown">
        <option value="">Panel 4</option>
<?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
    
    <select id="panel5-dropdown">
        <option value="">Panel 5</option>
<?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
    
    <select id="panel6-dropdown">
        <option value="">Panel 6</option>
<?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
    <button id="external-submit-button" type="button">Submit</button>
</div>



<!-- Search and Deadline Setter -->
<div class="search-container" style="justify-content: space-between; align-items: center;">
    <input type="text" id="searchInput" class="search-box" placeholder="Search by name, group, title or department..." onkeyup="searchTable()">
    
    <div style="display: flex; gap: 10px; align-items: center;">
        <label for="deadline-date-picker" style="font-weight: bold; white-space: nowrap;">Deadline (Optional):</label>
        <input type="datetime-local" id="deadline-date-picker" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
    </div>
</div>

<form id="submission-form" action="route1.php" method="POST">
    <table>
        <thead>
            <tr>
<th>Select</th>
<th>Control No.</th>
<th>Leader</th>
<th>Group No.</th>
<th>Title</th>
<th>Minutes</th>
<th>Deadline</th>
<th>Assigned</th>
<th class='action-label'>Action</th>
            </tr>
        </thead>
        <!-- Replace your existing table rows with this updated version -->
<tbody id="file-list">
    <?php if (!empty($files)): ?>
        <?php foreach ($files as $file): ?>
            <?php
                $filepath = htmlspecialchars($file['filepath'], ENT_QUOTES);
                $filename = htmlspecialchars(basename($file['filename']), ENT_QUOTES);
                $controlNo = htmlspecialchars($file['controlNo'] ?? '', ENT_QUOTES);
                $fullname = htmlspecialchars($file['fullname'] ?? '', ENT_QUOTES);
                $group_number = htmlspecialchars($file['group_number'] ?? '', ENT_QUOTES);
                $student_id = htmlspecialchars($file['student_id'] ?? '', ENT_QUOTES);
                $title = htmlspecialchars($file['title'] ?? '', ENT_QUOTES);
                
                // Panel and adviser information
                $assigned_panels = [];
                for ($i = 1; $i <= 6; $i++) {
                    $panel_id_key = "panel{$i}_id";
                    if (!empty($file[$panel_id_key])) {
                        $panel_info = getPanelName($conn, $file[$panel_id_key]);
                        if ($panel_info && is_array($panel_info)) {
                            $panel_name = $panel_info['fullname'] ?? 'Unknown';
                            if (!empty($panel_name)) {
                                $assigned_panels[] = ["name" => $panel_name, "position" => "Panel $i"];
                            }
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
                
                // Check if file is already assigned to determine if checkbox should be disabled
                // Only disable if a panel is assigned (don't consider adviser)
                $is_assigned = $has_panels;

                // Prepare file data for JS
// Prepare file data for JS
$fileData = [
    'filepath' => $filepath,
    'panel1_id' => $file['panel1_id'] ?? '',
    'panel2_id' => $file['panel2_id'] ?? '',
    'panel3_id' => $file['panel3_id'] ?? '',
    'panel4_id' => $file['panel4_id'] ?? '',
    'panel5_id' => $file['panel5_id'] ?? '',
    'panel6_id' => $file['panel6_id'] ?? '',
    'adviser_id' => $adviser_id,
    'deadline' => $file['panel_deadline'] ?? '', // ADD THIS
    'is_assigned' => $is_assigned,
    'has_panels' => $has_panels,
    'has_adviser' => $has_adviser
];

// Encode assignment data for the modal
$assignmentData = [
    'panels' => $assigned_panels,
    'adviser' => $adviser_name,
    'deadline' => $file['panel_deadline'] ?? '' // ADD THIS
];
            ?>
            <tr class="<?= $is_assigned ? 'already-assigned' : 'selectable-row' ?>">
                <td style="text-align: center;">
                    <input type="checkbox" name="selected_files[]" value="<?= $filepath ?>" <?= $is_assigned ? 'disabled' : '' ?> data-is-assigned="<?= $is_assigned ? 'true' : 'false' ?>">
                </td>
                <td><?= $controlNo ?></td>
                <td><?= $fullname ?></td>
                <td><?= $group_number ?></td>
                <td><?= $title ?></td>
                <td>
                    <?php 
                    $minutesStatus = $file['minutes'] ? '<span style="color: green;">Available</span>' : '<span style="color: red;">Not Available</span>';
                    echo $minutesStatus;
                    ?>
                </td>
                          <td>
    <?php 
    if (!empty($file['panel_deadline'])) {
        $deadline = new DateTime($file['panel_deadline']);
        $now = new DateTime();
        $isOverdue = $now > $deadline;
        
        echo '<div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">';
        echo '<span style="color: ' . ($isOverdue ? 'red' : 'green') . '; font-weight: bold; font-size: 0.85em;">';
        echo $deadline->format('M d, Y');
        echo '<br>';
        echo $deadline->format('h:i A');
        echo '</span>';
        
        if ($isOverdue) {
            echo '<span style="color: red; font-size: 0.75em; font-weight: bold;">(Overdue)</span>';
        }
        echo '</div>';
    } else {
        echo '<span style="color: #999; font-size: 0.85em;">Not Set</span>';
    }
    ?>
</td>
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
    <div class="action-cell">
        <button type="button" class="view-button" onclick="viewFile('<?= $filepath ?>', '<?= $student_id ?>', '<?= $file['route1_id'] ?? '' ?>')">View</button>
        <?php if ($file['minutes']): ?>
            <?php $minutesJs = json_encode($file['minutes']); ?>
            <button type="button" class="view-button" onclick='viewMinutes(<?= $minutesJs ?>)'>View Minutes</button>
        <?php endif; ?>
        <button type="button" class="delete-button" onclick="confirmDelete('<?= $file['route1_id'] ?>', '<?= $filepath ?>', '<?= $fullname ?>', '<?= $title ?>')">Delete</button>
    </div>
</td>
      
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="9" style="text-align: center;">No files found.</td>
        </tr>
    <?php endif; ?>
</tbody>
    </table>

    <!-- Hidden Inputs -->
<!-- Hidden Inputs -->
<input type="hidden" name="panel1" id="hidden-panel1">
<input type="hidden" name="panel2" id="hidden-panel2">
<input type="hidden" name="panel3" id="hidden-panel3">
<input type="hidden" name="panel4" id="hidden-panel4">
<input type="hidden" name="panel5" id="hidden-panel5">
<input type="hidden" name="panel6" id="hidden-panel6">
<input type="hidden" name="deadline" id="hidden-deadline">
</form>
            </div>
        </div>
    </div>

<!-- ═══ NEW VIEWER MODAL ═══ -->
<div class="viewer-overlay" id="viewerOverlay">
    <div class="viewer-modal-box">
        <div class="vmod-header">
            <div class="vmod-hl">
                <span class="vmod-badge">Route 1</span>
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
        <ul id="panelists-list">
            <!-- Will be populated dynamically -->
        </ul>
        
        <h3>Adviser</h3>
        <div id="adviser-name">
            <!-- Will be populated dynamically -->
        </div>
        
        <!-- ADD THIS NEW SECTION -->
        <h3>Deadline</h3>
        <div id="deadline-display">
            <!-- Will be populated dynamically -->
        </div>
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
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
                    <label for="edit-panel3">Panel 3:</label>
                    <select id="edit-panel3" name="panel3" class="form-control">
                        <option value="">None</option>
                        <?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
                    <label for="edit-panel4">Panel 4:</label>
                    <select id="edit-panel4" name="panel4" class="form-control">
                        <option value="">None</option>
                        <?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
                    <label for="edit-panel5">Panel 5:</label>
                    <select id="edit-panel5" name="panel5" class="form-control">
                        <option value="">None</option>
                        <?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
                    <label for="edit-panel6">Panel 6:</label>
                    <select id="edit-panel6" name="panel6" class="form-control">
                        <option value="">None</option>
                        <?php
                        if (isset($selectedDepartment)) {
                            $panelStmt = $conn->prepare("SELECT panel_id, fullname, department FROM panel");
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
                     <label for="edit-deadline">Deadline (Optional):</label>
                     <input type="datetime-local" id="edit-deadline" name="deadline" class="form-control">
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
</html>
<script>
    // Function to toggle file selection with assignment check
    function toggleFileSelection(fileElement) {
        const checkbox = fileElement.querySelector("input[type='checkbox']");
        
        // Check if the file is already assigned to a panel (not just adviser)
        if (checkbox.dataset.isAssigned === 'true') {
            // File has panel assignments, don't toggle
            alert("This file already has panel assignments and cannot be selected.");
            return;
        }
        
        // Otherwise toggle the checkbox
        checkbox.checked = !checkbox.checked;
        fileElement.classList.toggle("selected");
    }

    // Search function for the table
    function searchTable() {
        const input = document.getElementById("searchInput");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("file-list");
        const rows = table.getElementsByTagName("tr");
        
        for (let i = 0; i < rows.length; i++) {
            const leaderCell = rows[i].getElementsByTagName("td")[1]; // Leader name (index 1)
            const groupCell = rows[i].getElementsByTagName("td")[2]; // Group number (index 2)
            const titleCell = rows[i].getElementsByTagName("td")[3]; // Title (index 3)
            const deptCell = rows[i].getElementsByTagName("td")[4]; // Department (index 4)
            
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

    // Handles form submission
    // Handles form submission
document.getElementById("external-submit-button").addEventListener("click", function () {
    const panel1 = document.getElementById("panel1-dropdown").value.trim();
    const panel2 = document.getElementById("panel2-dropdown").value.trim();
    const panel3 = document.getElementById("panel3-dropdown").value.trim();
    const panel4 = document.getElementById("panel4-dropdown").value.trim();
    const panel5 = document.getElementById("panel5-dropdown").value.trim();
    const panel6 = document.getElementById("panel6-dropdown").value.trim();
    const deadline = document.getElementById("deadline-date-picker").value;

    // Set hidden fields
    document.getElementById("hidden-panel1").value = panel1;
    document.getElementById("hidden-panel2").value = panel2;
    document.getElementById("hidden-panel3").value = panel3;
    document.getElementById("hidden-panel4").value = panel4;
    document.getElementById("hidden-panel5").value = panel5;
    document.getElementById("hidden-panel6").value = panel6;
    document.getElementById("hidden-deadline").value = deadline;

    // Validate file selection
    const selectedFiles = document.querySelectorAll("input[name='selected_files[]']:checked:not(:disabled)");
    if (selectedFiles.length === 0) {
        alert("Please select at least one eligible file.");
        return;
    }

    // Check if at least one panel is selected
    const panels = [panel1, panel2, panel3, panel4, panel5, panel6];
    const selectedPanels = panels.filter(panel => panel !== '' && panel !== null);
    
    if (selectedPanels.length === 0) {
        alert("Please select at least one panel member.");
        return;
    }
    
    // Check for duplicate panel assignments (including Panel 6)
    const uniquePanels = [...new Set(selectedPanels)];
    if (uniquePanels.length !== selectedPanels.length) {
        alert("Each panel position must have a unique panel member.");
        return;
    }

    // Submit the form
    document.getElementById("submission-form").submit();
});

    // Add click event for rows to toggle file selection
    document.addEventListener('DOMContentLoaded', function() {
        const selectableRows = document.querySelectorAll(".selectable-row");
        selectableRows.forEach(row => {
            row.addEventListener('click', function(e) {
                // Only toggle if the click wasn't on a button or a link
                if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A' && e.target.tagName !== 'INPUT') {
                    const checkbox = this.querySelector("input[type='checkbox']");
                    if (checkbox && !checkbox.disabled) {
                        checkbox.checked = !checkbox.checked;
                    }
                }
            });
        });

        // Setup panel dropdown filtering
        setupPanelSelectionLogic();
        setupEditPanelSelectionLogic();
    });

    // Global variable to store current file data for edit mode
    let currentEditFile = null;

    // Function to setup panel selection logic for main dropdowns
    function setupPanelSelectionLogic() {
        // Get all panel dropdowns
        const panelDropdowns = [
            document.getElementById('panel1-dropdown'),
            document.getElementById('panel2-dropdown'),
            document.getElementById('panel3-dropdown'),
            document.getElementById('panel4-dropdown'),
            document.getElementById('panel5-dropdown'),
            document.getElementById('panel6-dropdown')
        ];
        
        // Store original options for each dropdown
        const originalOptions = panelDropdowns.map(dropdown => {
            if (!dropdown) return [];
            return Array.from(dropdown.options).filter(option => option.value !== '').map(option => {
                return {value: option.value, text: option.text};
            });
        });
        
        // Function to update available options in each dropdown
        function updateAvailableOptions() {
            // Get currently selected values
            const selectedValues = panelDropdowns.map(dropdown => 
                dropdown ? dropdown.value : null
            ).filter(value => value); // Remove empty values
            
            // Reset and update each dropdown
            panelDropdowns.forEach((dropdown, index) => {
                if (!dropdown) return;
                
                const currentValue = dropdown.value;
                
                // Store the first option (the label)
                const firstOption = dropdown.options[0];
                
                // Clear everything except the first option
                while (dropdown.options.length > 1) {
                    dropdown.remove(1);
                }
                
                // Add all options except those selected in other dropdowns
                originalOptions[index].forEach(option => {
                    if (option.value === currentValue || !selectedValues.includes(option.value)) {
                        const optionElement = document.createElement('option');
                        optionElement.value = option.value;
                        optionElement.textContent = option.text;
                        dropdown.appendChild(optionElement);
                        
                        // Re-select current value if it exists
                        if (option.value === currentValue) {
                            optionElement.selected = true;
                        }
                    }
                });
            });
        }
        
        // Add change event listeners to each dropdown
        panelDropdowns.forEach(dropdown => {
            if (dropdown) {
                dropdown.addEventListener('change', updateAvailableOptions);
            }
        });
        
        // Initialize dropdowns
        updateAvailableOptions();
    }
    
    // Function to setup panel selection logic for edit form dropdowns
    function setupEditPanelSelectionLogic() {
        // Get all edit panel dropdowns
        const editPanelDropdowns = [
            document.getElementById('edit-panel1'),
            document.getElementById('edit-panel2'),
            document.getElementById('edit-panel3'),
            document.getElementById('edit-panel4'),
            document.getElementById('edit-panel5')
        ];
        
        // Store original options for each dropdown
        const originalEditOptions = editPanelDropdowns.map(dropdown => {
            if (!dropdown) return [];
            return Array.from(dropdown.options).filter(option => option.value !== '').map(option => {
                return {value: option.value, text: option.text};
            });
        });
        
        // Function to update available options in each dropdown
        function updateEditAvailableOptions() {
            // Get currently selected values
            const selectedValues = editPanelDropdowns.map(dropdown => 
                dropdown ? dropdown.value : null
            ).filter(value => value); // Remove empty values
            
            // Reset and update each dropdown
            editPanelDropdowns.forEach((dropdown, index) => {
                if (!dropdown) return;
                
                const currentValue = dropdown.value;
                
                // Store the first option (the label)
                const firstOption = dropdown.options[0];
                
                // Clear everything except the first option
                while (dropdown.options.length > 1) {
                    dropdown.remove(1);
                }
                
                // Add all options except those selected in other dropdowns
                originalEditOptions[index].forEach(option => {
                    if (option.value === currentValue || !selectedValues.includes(option.value)) {
                        const optionElement = document.createElement('option');
                        optionElement.value = option.value;
                        optionElement.textContent = option.text;
                        dropdown.appendChild(optionElement);
                        
                        // Re-select current value if it exists
                        if (option.value === currentValue) {
                            optionElement.selected = true;
                        }
                    }
                });
            });
        }
        
        // Add change event listeners to each dropdown
        editPanelDropdowns.forEach(dropdown => {
            if (dropdown) {
                dropdown.addEventListener('change', updateEditAvailableOptions);
            }
        });
        
        // Initialize dropdowns when the edit modal is shown
        document.getElementById('edit-assignments-btn').addEventListener('click', function() {
            setTimeout(updateEditAvailableOptions, 100);
        });
    }

    function showAssignmentDetails(assignments, fileData = null) {
    const modal = document.getElementById('assignmentModal');
    const panelistsList = document.getElementById('panelists-list');
    const adviserName = document.getElementById('adviser-name');
    const deadlineDisplay = document.getElementById('deadline-display'); // ADD THIS
    
    // Store file data for edit mode
    currentEditFile = fileData;
    
    // Clear previous content
    panelistsList.innerHTML = '';
    adviserName.innerHTML = '';
    adviserName.className = '';
    deadlineDisplay.innerHTML = ''; // ADD THIS
    deadlineDisplay.className = ''; // ADD THIS
    
    // Reset view to show assignments
    document.getElementById('view-assignments-section').style.display = 'block';
    document.getElementById('edit-assignments-section').style.display = 'none';
    
    // Add panelists
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
    
    // Add adviser
    if (assignments.adviser && assignments.adviser.trim() !== '') {
        adviserName.textContent = assignments.adviser;
        adviserName.className = '';
    } else {
        adviserName.textContent = 'No adviser assigned';
        adviserName.className = 'not-assigned';
    }
    
    // ADD THIS SECTION FOR DEADLINE
    // ADD THIS SECTION FOR DEADLINE
if (assignments.deadline && assignments.deadline.trim() !== '') {
    const deadline = new Date(assignments.deadline);  // ✅ CORRECT - Use Date, not DateTime
    const now = new Date();
    const isOverdue = now > deadline;
    
    deadlineDisplay.innerHTML = `
        <span style="color: ${isOverdue ? 'red' : 'green'}; font-weight: bold;">
            ${deadline.toLocaleString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            })}
            ${isOverdue ? '<br><span style="font-size: 0.85em;">(Overdue)</span>' : ''}
        </span>
    `;
} else {
    deadlineDisplay.textContent = 'No deadline set';
    deadlineDisplay.className = 'not-assigned';
}
    // Show modal
    modal.style.display = 'block';
}

function showEditAssignments() {
    if (!currentEditFile) {
        alert("File data not available. Please try again.");
        return;
    }
    
    // Switch to edit view
    document.getElementById('view-assignments-section').style.display = 'none';
    document.getElementById('edit-assignments-section').style.display = 'block';
    
    // Set file path for the form
    document.getElementById('edit-filepath').value = currentEditFile.filepath;
    
    // Set current values in dropdowns
    if (currentEditFile.panel1_id) {
        document.getElementById('edit-panel1').value = currentEditFile.panel1_id;
    }
    if (currentEditFile.panel2_id) {
        document.getElementById('edit-panel2').value = currentEditFile.panel2_id;
    }
    if (currentEditFile.panel3_id) {
        document.getElementById('edit-panel3').value = currentEditFile.panel3_id;
    }
    if (currentEditFile.panel4_id) {
        document.getElementById('edit-panel4').value = currentEditFile.panel4_id;
    }
    if (currentEditFile.panel5_id) {
        document.getElementById('edit-panel5').value = currentEditFile.panel5_id;
    }
    if (currentEditFile.panel6_id) {
        document.getElementById('edit-panel6').value = currentEditFile.panel6_id;
    }
    if (currentEditFile.adviser_id) {
        document.getElementById('edit-adviser').value = currentEditFile.adviser_id;
    }
    
    // ADD THIS FOR DEADLINE
    if (currentEditFile.deadline) {
        // Convert PHP datetime to input format (YYYY-MM-DDTHH:MM)
        const deadline = new Date(currentEditFile.deadline);
        const localDateTime = new Date(deadline.getTime() - deadline.getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 16);
        document.getElementById('edit-deadline').value = localDateTime;
    }
    
    // Set minimum datetime for deadline picker
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('edit-deadline').min = now.toISOString().slice(0, 16);
}
    function cancelEdit() {
        // Switch back to view mode
        document.getElementById('view-assignments-section').style.display = 'block';
        document.getElementById('edit-assignments-section').style.display = 'none';
    }

    function closeAssignmentModal() {
        document.getElementById('assignmentModal').style.display = 'none';
        currentEditFile = null; // Clear current file data
    }

    // ═══ NEW VIEWER MODAL LOGIC ═══
let vmodFormData = [];

function viewFile(filePath, student_id, route1_id) {
    // Store meta for summary tab (use data from the table row)
    const rows = document.querySelectorAll('#file-list tr');
    let controlNo = '–', leader = '–', groupNo = '–', title = '–';
    rows.forEach(row => {
        const cb = row.querySelector('input[type="checkbox"]');
        if (cb && cb.value === filePath) {
            controlNo = row.cells[1]?.textContent.trim() || '–';
            leader    = row.cells[2]?.textContent.trim() || '–';
            groupNo   = row.cells[3]?.textContent.trim() || '–';
            title     = row.cells[4]?.textContent.trim() || '–';
        }
    });

    // Populate header meta
    document.getElementById('vmod-doc-title').textContent  = title;
    document.getElementById('vmod-pdf-fname').textContent  = filePath.split('/').pop();
    document.getElementById('vmod-control-no').textContent = controlNo;
    document.getElementById('vmod-group-no').textContent   = groupNo;
    const yr = new Date().getFullYear();
    document.getElementById('vmod-school-year').textContent = 'AY ' + yr + '–' + (yr + 1);

    // Summary tab meta
    document.getElementById('vmod-sum-control').textContent = controlNo;
    document.getElementById('vmod-sum-leader').textContent  = leader;
    document.getElementById('vmod-sum-group').textContent   = groupNo;
    document.getElementById('vmod-sum-title').textContent   = title;

    // Reset loader
    document.getElementById('vmod-pdf-loader').classList.remove('hidden');
    document.getElementById('vmod-pdf-frame').src    = '';
    document.getElementById('vmod-pdf-frame').style.display = 'block';
    document.getElementById('vmod-docx-preview').style.display = 'none';
    document.getElementById('vmod-docx-preview').innerHTML = '';

    // Open overlay
    const overlay = document.getElementById('viewerOverlay');
    overlay.style.display = 'flex';
    requestAnimationFrame(() => overlay.classList.add('open'));
    vmodSwitchTab('feedback');

    // Load PDF or DOCX
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

    // Load feedback
    vmodLoadFeedback(student_id, route1_id);
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

function vmodLoadFeedback(student_id, route1_id) {
    const container = document.getElementById('vmod-feedback-container');
    const noMsg     = document.getElementById('vmod-no-forms');
    const loader    = document.getElementById('vmod-loader');
    container.innerHTML = '';
    noMsg.textContent   = '';
    loader.style.display = 'flex';

    fetch(`get_all_forms.php?student_id=${encodeURIComponent(student_id)}&route1_id=${encodeURIComponent(route1_id)}`)
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

            // Group by route
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
    const doc = `<!DOCTYPE html><html><head><title>Routing Monitoring Form – Route 1</title>
    <style>body{font-family:Arial,sans-serif;padding:20px;font-size:12px;}h2{color:#4366b3;text-align:center;}h3{text-align:center;font-size:13px;color:#555;margin-bottom:20px;}table{width:100%;border-collapse:collapse;}th{background:#4366b3;color:white;padding:8px;font-size:11px;text-align:left;}td{padding:7px 8px;border-bottom:1px solid #eee;font-size:11px;}@media print{@page{size:landscape;margin:1cm}}</style>
    </head><body>
    <h2>SAINT MICHAEL COLLEGE OF CARAGA</h2>
    <h3>Research &amp; Instructional Innovation Department — Routing Monitoring Form (Route 1)</h3>
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

    // Modify the update form to use AJAX for submission
    document.addEventListener('DOMContentLoaded', function() {
        const updateForm = document.getElementById('update-assignments-form');
        if (updateForm) {
            updateForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Check if at least one panel position is filled (including Panel 6)
                const panels = [
                    document.getElementById('edit-panel1').value,
                    document.getElementById('edit-panel2').value,
                    document.getElementById('edit-panel3').value,
                    document.getElementById('edit-panel4').value,
                    document.getElementById('edit-panel5').value,
                    document.getElementById('edit-panel6').value
                ];
                
                const selectedPanels = panels.filter(panel => panel && panel.trim() !== '');
                
                if (selectedPanels.length === 0) {
                    alert("Please select at least one panel member.");
                    return;
                }

                // Check for duplicate panel assignments
                const uniquePanels = [...new Set(selectedPanels)];
                if (uniquePanels.length !== selectedPanels.length) {
                    alert("Each panel position must have a unique panel member. Please remove duplicates.");
                    return;
                }
                
                // Create form data object
                const formData = new FormData(this);
                
                // Remove adviser_id from the form data since it's disabled
                formData.delete('adviser_id');
                
                // Send AJAX request
                fetch('route1.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Unknown error occurred');
                    }
                    
                    // Show success message
                    alert(data.message);
                    
                    // Get updated panel information
                    const panel1Id = document.getElementById('edit-panel1').value;
                    const panel2Id = document.getElementById('edit-panel2').value;
                    const panel3Id = document.getElementById('edit-panel3').value;
                    const panel4Id = document.getElementById('edit-panel4').value;
                    const panel5Id = document.getElementById('edit-panel5').value;
                    const panel6Id = document.getElementById('edit-panel6').value;
                    
                    // Keep the existing adviser_id from currentEditFile
                    const adviserId = currentEditFile.adviser_id;
                    
                    // Update currentEditFile with new panel values only
                    currentEditFile.panel1_id = panel1Id;
                    currentEditFile.panel2_id = panel2Id;
                    currentEditFile.panel3_id = panel3Id;
                    currentEditFile.panel4_id = panel4Id;
                    currentEditFile.panel5_id = panel5Id;
                    currentEditFile.panel6_id = panel6Id;
                    // Note: adviser_id is not changed
                    
                    // Get selected panel and adviser names
                    const panels = [];
                    if (panel1Id) {
                        const panel1Select = document.getElementById('edit-panel1');
                        const panel1Name = panel1Select.options[panel1Select.selectedIndex].text;
                        panels.push({position: 'Panel 1', name: panel1Name});
                    }
                    if (panel2Id) {
                        const panel2Select = document.getElementById('edit-panel2');
                        const panel2Name = panel2Select.options[panel2Select.selectedIndex].text;
                        panels.push({position: 'Panel 2', name: panel2Name});
                    }
                    if (panel3Id) {
                        const panel3Select = document.getElementById('edit-panel3');
                        const panel3Name = panel3Select.options[panel3Select.selectedIndex].text;
                        panels.push({position: 'Panel 3', name: panel3Name});
                    }
                    if (panel4Id) {
                        const panel4Select = document.getElementById('edit-panel4');
                        const panel4Name = panel4Select.options[panel4Select.selectedIndex].text;
                        panels.push({position: 'Panel 4', name: panel4Name});
                    }
                    if (panel5Id) {
                        const panel5Select = document.getElementById('edit-panel5');
                        const panel5Name = panel5Select.options[panel5Select.selectedIndex].text;
                        panels.push({position: 'Panel 5', name: panel5Name});
                    }
                    if (panel6Id) {
                        const panel6Select = document.getElementById('edit-panel6');
                        const panel6Name = panel6Select.options[panel6Select.selectedIndex].text;
                        panels.push({position: 'Panel 6', name: panel6Name});
                    }
                    
                    // Get adviser name from existing select element (even though it's disabled)
                    let adviserName = '';
                    if (adviserId) {
                        const adviserSelect = document.getElementById('edit-adviser');
                        const selectedOption = Array.from(adviserSelect.options).find(option => option.value === adviserId);
                        adviserName = selectedOption ? selectedOption.text : '';
                    }
                    
                    // Update assigned data
// GET THE UPDATED DEADLINE VALUE
// GET THE UPDATED DEADLINE VALUE
const updatedDeadline = document.getElementById('edit-deadline').value;

// Update currentEditFile with new deadline
currentEditFile.deadline = updatedDeadline;

// Update assigned data
const updatedAssignmentData = {
    panels: panels,
    adviser: adviserName,
    deadline: updatedDeadline
};

// Show updated assignments in the modal
const panelistsList = document.getElementById('panelists-list');
const adviserNameElement = document.getElementById('adviser-name');

// Clear previous content
panelistsList.innerHTML = '';
adviserNameElement.innerHTML = '';
adviserNameElement.className = '';

// Add panelists
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

// Add adviser
if (adviserName && adviserName.trim() !== '') {
    adviserNameElement.textContent = adviserName;
    adviserNameElement.className = '';
} else {
    adviserNameElement.textContent = 'No adviser assigned';
    adviserNameElement.className = 'not-assigned';
}

// UPDATE DEADLINE DISPLAY
// UPDATE DEADLINE DISPLAY
const deadlineDisplay = document.getElementById('deadline-display');
if (updatedDeadline && updatedDeadline.trim() !== '') {
    const deadline = new Date(updatedDeadline);
    const now = new Date();
    const isOverdue = now > deadline;
    
    deadlineDisplay.innerHTML = `
        <span style="color: ${isOverdue ? 'red' : 'green'}; font-weight: bold;">
            ${deadline.toLocaleString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            })}
            ${isOverdue ? '<br><span style="font-size: 0.85em;">(Overdue)</span>' : ''}
        </span>
    `;
    deadlineDisplay.className = '';
} else {
    deadlineDisplay.textContent = 'No deadline set';
    deadlineDisplay.className = 'not-assigned';
}

// Update the assignment status button in the table
const filepath = document.getElementById('edit-filepath').value;
const assignmentButtons = document.querySelectorAll('.assignment-button');
                    
assignmentButtons.forEach(button => {
                        // Find the button for this file
                        const row = button.closest('tr');
                        const checkbox = row.querySelector('input[type="checkbox"]');
                        if (checkbox && checkbox.value === filepath) {
                            let newStatus = 'Not Assigned';
                            let isAssigned = false;
                            
                            if (panels.length > 0 && adviserId) {
                                newStatus = 'View Assignments';
                                button.classList.remove('not-assigned');
                                // Only disable if panels are assigned (not just adviser)
                                isAssigned = true;
                            } else if (panels.length > 0) {
                                newStatus = 'View Panelists';
                                button.classList.remove('not-assigned');
                                isAssigned = true;
                            } else if (adviserId) {
                                newStatus = 'View Adviser';
                                button.classList.remove('not-assigned');
                                // Don't disable if only adviser is assigned
                                isAssigned = false;
                            } else {
                                button.classList.add('not-assigned');
                                isAssigned = false;
                            }
                            
                            button.textContent = newStatus;
                            
                            // Update checkbox status based on panel assignment status only
                            if (isAssigned && panels.length > 0) { // Only disable if has panels
                                checkbox.disabled = true;
                                checkbox.dataset.isAssigned = 'true';
                                row.classList.remove('selectable-row');
                                row.classList.add('already-assigned');
                            } else {
                                checkbox.disabled = false;
                                checkbox.dataset.isAssigned = 'false';
                                row.classList.add('selectable-row');
                                row.classList.remove('already-assigned');
                            }
                            
                            // Update onclick function with new data
                            button.onclick = function() {
                                showAssignmentDetails(updatedAssignmentData, currentEditFile);
                            };
                        }
                    });
                    
                    // Switch back to view mode
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

    function autoGrow(textarea) {
        textarea.style.height = 'auto'; // Reset height
        textarea.style.height = (textarea.scrollHeight) + 'px'; // Set to scrollHeight
        
        // Ensure minimum height
        if (textarea.scrollHeight < 40) {
            textarea.style.height = '40px';
        }
    }
    
    // Function to handle file deletion with confirmation
    function confirmDelete(routeId, filepath, studentName, title) {
        if (confirm(`Are you sure you want to delete the file for ${studentName} (${title})?\nThis action cannot be undone.`)) {
            // Create a form to submit the delete request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            // Add route_id as the primary identifier
            const routeInput = document.createElement('input');
            routeInput.type = 'hidden';
            routeInput.name = 'delete_file';
            routeInput.value = routeId;
            
            // Also include filepath for file deletion if needed
            const fileInput = document.createElement('input');
            fileInput.type = 'hidden';
            fileInput.name = 'filepath';
            fileInput.value = filepath;
            
            form.appendChild(routeInput);
            form.appendChild(fileInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
    // Set minimum datetime for deadline picker on page load
document.addEventListener('DOMContentLoaded', function() {
    const deadlinePicker = document.getElementById('deadline-date-picker');
    if (deadlinePicker) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        deadlinePicker.min = now.toISOString().slice(0, 16);
    }
});


</script>
<script src="../sidebar.js"></script>


