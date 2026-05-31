<?php

include '../../../connection.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['student_id'])) {
    header("Location: ../../../logout.php");
    exit;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sendAdviserNotificationEmail($adviser_email, $adviser_name, $fullname, $title) {
    try {
        if (!isValidEmail($adviser_email)) {
            error_log("Invalid email address format: $adviser_email");
            return false;
        }
        require_once '../../../src/PHPMailer.php';
        require_once '../../../src/SMTP.php';
        require_once '../../../src/Exception.php';
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'smcctrs@gmail.com';
        $mail->Password = 'cplw gwfe vxcy naoi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('smcctrs@gmail.com', 'Thesis Routing System');
        $mail->addAddress($adviser_email, $adviser_name);
        $mail->isHTML(true);
        $mail->Subject = 'New Thesis Document (Route 3) Submitted for Review';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                <h2 style='color: #4366b3; text-align: center;'>Thesis Routing System Notification</h2>
                <p>Dear <strong>{$adviser_name}</strong>,</p>
                <p>A new thesis document (Route 3) has been submitted and requires your review.</p>
                <p><strong>Student:</strong> {$fullname}</p>
                <p><strong>Title:</strong> {$title}</p>
                <p>Please log in to the Thesis Routing System to review this document.</p>
                <div style='margin-top: 30px; text-align: center;'>
                    <a href='https://capstone.smccnasipit.edu.ph/trs/adviser/login.php' style='background-color: #4366b3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Review</a>
                </div>
                <p style='margin-top: 10px; text-align: center;'>If the button above doesn't work, copy and paste this URL into your browser: <br><a href='https://capstone.smccnasipit.edu.ph/trs/adviser/login.php'>https://capstone.smccnasipit.edu.ph/trs/adviser/login.php</a></p>
                <p style='margin-top: 30px; font-size: 12px; color: #777; text-align: center;'>This is an automated message from the Thesis Routing System. Please do not reply to this email.</p>
            </div>
        ";
        $mail->AltBody = "Dear {$adviser_name}, A new thesis document (Route 3) has been submitted by {$fullname} with the title '{$title}'. Please login at: https://capstone.smccnasipit.edu.ph/trs/adviser/login.php";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
        return false;
    }
}

$alertMessage = "";

// HANDLE DELETE
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_file'])) {
    $student_id   = $_SESSION['student_id'];
    $fileToDelete = $_POST['delete_file'];
    $stmt = $conn->prepare("SELECT docuRoute3 FROM route3proposal_files WHERE student_id = ? AND docuRoute3 = ?");
    $stmt->bind_param("ss", $student_id, $fileToDelete);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        if (file_exists($fileToDelete)) unlink($fileToDelete);
        $deleteStmt = $conn->prepare("DELETE FROM route3proposal_files WHERE student_id = ? AND docuRoute3 = ?");
        $deleteStmt->bind_param("ss", $student_id, $fileToDelete);
        $deleteStmt->execute();
        $deleteStmt->close();
        $alertMessage = "File deleted successfully.";
    } else { $alertMessage = "File not found or you don't have permission."; }
    $stmt->close();
    $_SESSION['alert_message'] = $alertMessage;
    header("Location: route3.php"); exit;
}

// HANDLE REUPLOAD
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["docuRoute3"]) && isset($_POST['old_file_path'])) {
    $student_id  = $_SESSION['student_id'];
    $oldFilePath = $_POST['old_file_path'];
    $routeIdStmt = $conn->prepare("SELECT route3_id FROM route3proposal_files WHERE student_id = ? AND docuRoute3 = ?");
    $routeIdStmt->bind_param("ss", $student_id, $oldFilePath);
    $routeIdStmt->execute();
    $routeIdResult = $routeIdStmt->get_result();
    if ($routeIdResult->num_rows > 0) {
        $routeIdRow = $routeIdResult->fetch_assoc();
        $route3_id  = $routeIdRow['route3_id'];
        $routeIdStmt->close();
        $fileTmpPath = $_FILES["docuRoute3"]["tmp_name"];
        $fileName    = $_FILES["docuRoute3"]["name"];
        $uploadDir   = "../../../uploads/";
        $newFilePath = $uploadDir . basename($fileName);
        if (in_array($_FILES["docuRoute3"]["type"], ["application/pdf"])) {
            if (file_exists($oldFilePath)) unlink($oldFilePath);
            if (move_uploaded_file($fileTmpPath, $newFilePath)) {
                $updateStmt = $conn->prepare("UPDATE route3proposal_files SET docuRoute3 = ? WHERE route3_id = ?");
                $updateStmt->bind_param("si", $newFilePath, $route3_id);
                $alertMessage = $updateStmt->execute() ? "File reuploaded successfully." : "Error updating database: " . $updateStmt->error;
                $updateStmt->close();
            } else { $alertMessage = "Error moving the uploaded file."; }
        } else { $alertMessage = "Invalid file type. Only PDF files are allowed."; }
    } else { $alertMessage = "Error: Could not find the file record."; $routeIdStmt->close(); }
    $_SESSION['alert_message'] = $alertMessage;
    header("Location: route3.php"); exit;
}

// HANDLE UPLOAD
if (isset($_FILES["docuRoute3"]) && $_FILES["docuRoute3"]["error"] == UPLOAD_ERR_OK) {
    $student_id = $_POST["student_id"];

    $stmt = $conn->prepare("SELECT department, controlNo, fullname, group_number, title, adviser, adviser_email, school_year FROM student WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->bind_result($department, $controlNo, $fullname, $group_number, $title, $adviser_name, $adviser_email, $school_year);
    $stmt->fetch();
    $stmt->close();

    if (!$department) {
        echo "<script>alert('No account found with the provided ID number.'); window.history.back();</script>";
        exit;
    }

    // ── Check Route 2 exists
    $route2Stmt = $conn->prepare("SELECT route2_id, adviser_id, panel1_id, panel2_id, panel3_id, panel4_id, panel5_id, panel6_id FROM route2proposal_files WHERE student_id = ?");
    $route2Stmt->bind_param("s", $student_id);
    $route2Stmt->execute();
    $route2Result = $route2Stmt->get_result();

    if ($route2Result->num_rows == 0) {
        echo "<script>alert('You must complete Route 2 first before proceeding to Route 3.'); window.history.back();</script>";
        exit;
    }

    $route2Row  = $route2Result->fetch_assoc();
    $route2_id  = $route2Row['route2_id'];
    $adviser_id = $route2Row['adviser_id'];
    $route2Stmt->close();

    // ── Build panel list (exclude adviser)
    $panelIds = [];
    foreach (['panel1_id','panel2_id','panel3_id','panel4_id','panel5_id','panel6_id'] as $key) {
        if (!empty($route2Row[$key])) {
            $panelIds[] = (int)$route2Row[$key];
        }
    }
    $panelIds = array_unique($panelIds);
    $panelIds = array_filter($panelIds, function($pid) use ($adviser_id) { return $pid != $adviser_id; });
    $panelIds = array_values($panelIds);

    if (empty($panelIds)) {
        echo "<script>alert('You cannot upload for Route 3 because no panel members have been assigned yet. Please contact the administrator.'); window.history.back();</script>";
        exit;
    }

    // ── Check that every panel has commented on Route 2
    $panelsCommentedCount   = 0;
    $panelsWhoDidNotComment = [];

    foreach ($panelIds as $pid) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM proposal_monitoring_form WHERE panel_id = ? AND student_id = ? AND (routeNumber = 'Route 2' OR route2_id = ?)");
        $stmt->bind_param("isi", $pid, $student_id, $route2_id);
        $stmt->execute();
        $stmt->bind_result($commentCount);
        $stmt->fetch();
        $stmt->close();
        if ($commentCount > 0) {
            $panelsCommentedCount++;
        } else {
            $panelsWhoDidNotComment[] = $pid;
        }
    }

    // ── Check that adviser has commented on Route 2
    $stmt = $conn->prepare("SELECT COUNT(*) FROM proposal_monitoring_form WHERE adviser_id = ? AND student_id = ? AND (routeNumber = 'Route 2' OR route2_id = ?)");
    $stmt->bind_param("isi", $adviser_id, $student_id, $route2_id);
    $stmt->execute();
    $stmt->bind_result($adviserCommentCount);
    $stmt->fetch();
    $stmt->close();
    $adviserCommented = ($adviserCommentCount > 0);
    if (!$adviserCommented) {
        $panelsWhoDidNotComment[] = "Adviser (ID: $adviser_id)";
    }

    $totalPanelsAssigned = count($panelIds);
    $totalCommented      = $panelsCommentedCount + ($adviserCommented ? 1 : 0);
    $totalRequired       = $totalPanelsAssigned + 1; // panels + adviser

    if ($totalCommented < $totalRequired) {
        $missingCount    = $totalRequired - $totalCommented;
        $missingReviewers = implode(', ', $panelsWhoDidNotComment);
        echo "<script>alert('You cannot proceed to Route 3 until all assigned panels and adviser have provided comments in Route 2.\\n\\nProgress: $totalCommented out of $totalRequired have commented.\\nStill waiting for comments from: $missingReviewers'); window.history.back();</script>";
        exit;
    }

    // ── Proceed with file upload
    $fileTmpPath = $_FILES["docuRoute3"]["tmp_name"];
    $fileName    = $_FILES["docuRoute3"]["name"];
    $uploadDir   = "../../../uploads/";
    $filePath    = $uploadDir . basename($fileName);

    if (in_array($_FILES["docuRoute3"]["type"], ["application/pdf"])) {
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $s = $conn->prepare("SELECT COUNT(*) FROM route3proposal_files WHERE student_id = ? AND department = ?");
        $s->bind_param("ss", $student_id, $department);
        $s->execute();
        $s->bind_result($count);
        $s->fetch();
        $s->close();

        if ($count > 0) {
            echo "<script>alert('You can only upload one file for Route 3.'); window.history.back();</script>";
            exit;
        } elseif (move_uploaded_file($fileTmpPath, $filePath)) {
            // Fetch panel/adviser IDs from route2
            $panelStmt = $conn->prepare("SELECT panel1_id, panel2_id, panel3_id, panel4_id, panel5_id, panel6_id, adviser_id FROM route2proposal_files WHERE student_id = ?");
            $panelStmt->bind_param("s", $student_id);
            $panelStmt->execute();
            $panelStmt->bind_result($panel1_id, $panel2_id, $panel3_id, $panel4_id, $panel5_id, $panel6_id, $adviser_id);
            $panelStmt->fetch();
            $panelStmt->close();

            if (empty($panel1_id) && empty($panel2_id) && empty($panel3_id) && empty($panel4_id) && empty($panel5_id) && empty($panel6_id)) {
                echo "<script>alert('No panel members assigned yet. Please contact the administrator.'); window.history.back();</script>";
                exit;
            }

            $date_submitted = date("Y-m-d H:i:s");
            $stmt = $conn->prepare("INSERT INTO route3proposal_files (student_id, docuRoute3, department, panel1_id, panel2_id, panel3_id, panel4_id, panel5_id, panel6_id, adviser_id, date_submitted, controlNo, fullname, group_number, title, school_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiiiiiiissssss", $student_id, $filePath, $department, $panel1_id, $panel2_id, $panel3_id, $panel4_id, $panel5_id, $panel6_id, $adviser_id, $date_submitted, $controlNo, $fullname, $group_number, $title, $school_year);
            if ($stmt->execute()) {
                if ($adviser_email && isValidEmail($adviser_email)) {
                    $emailSent = sendAdviserNotificationEmail($adviser_email, $adviser_name, $fullname, $title);
                    $msg = $emailSent ? "File uploaded successfully and notification sent to adviser." : "File uploaded successfully but failed to send notification email.";
                } else {
                    $msg = "File uploaded successfully. No adviser email available.";
                }
                echo "<script>alert('$msg'); window.location.href = 'route3.php';</script>";
            } else {
                echo "<script>alert('Error saving record.'); window.history.back();</script>";
            }
            $stmt->close();
        } else {
            echo "<script>alert('Error moving the file.'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('Invalid file type. Only PDF files are allowed.'); window.history.back();</script>";
    }
}

// Fetch rows for display
$student_id = $_SESSION['student_id'];
$stmt = $conn->prepare("
    SELECT
        r.docuRoute3, r.route3_id, r.controlNo, r.fullname, r.group_number, r.title, r.minutes,
        (SELECT minutes FROM route1proposal_files WHERE student_id = r.student_id) as route1_minutes
    FROM route3proposal_files r WHERE r.student_id = ?
");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$fileResult = $stmt->get_result();
$fileRows   = [];
while ($row = $fileResult->fetch_assoc()) $fileRows[] = $row;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Route 3 – Thesis Routing System</title>
<style>
:root {
    --primary: #4366b3;
    --primary-light: #0a3885;
    --accent: #4a6fd1;
    --light: #f5f7fd;
    --dark: #333;
    --success: #28a745;
    --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --border: #e0e0e0;
    --white: #ffffff;
    --hover: #f5f7fd;
    --active: #e5ebf8;
    --text-light: #777777;
    --radius: 8px;
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background-color:var(--light); color:var(--dark); min-height:100vh; }
.container { display:flex; flex-direction:column; min-height:100vh; }
.header { background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%); color:white; padding:1rem 2rem; box-shadow:var(--shadow); }
.logo-container { display:flex; align-items:center; }
.logo-container img { height:50px; margin-right:15px; }
.logo { font-size:1.5rem; font-weight:600; }
.top-bar { display:flex; justify-content:space-between; align-items:center; padding:0.75rem 2rem; background-color:white; box-shadow:0 2px 4px rgba(0,0,0,0.05); border-bottom:1px solid var(--border); }
.navigation { display:flex; align-items:center; }
.navigation a { color:var(--accent); text-decoration:none; font-weight:500; transition:all 0.3s; }
.navigation a:hover { color:var(--primary); }
.submit-button a { margin-left:50px; color:var(--accent); text-decoration:none; font-weight:500; transition:all 0.3s; }
.user-info { display:flex; align-items:center; }
.vl { border-left:1px solid var(--border); height:20px; margin:0 10px; }
.role { font-weight:600; margin-right:5px; color:var(--primary); }
.user-name { color:var(--dark); }
.main-content { display:flex; flex:1; }
.sidebar { width:250px; background-color:white; padding:1.5rem 0; box-shadow:2px 0 5px rgba(0,0,0,0.05); display:flex; flex-direction:column; justify-content:space-between; border-right:1px solid var(--border); }
.sidebar a { display:block; padding:0.75rem 1.5rem; color:var(--dark); text-decoration:none; transition:all 0.3s; border-left:3px solid transparent; }
.sidebar a:hover { background-color:var(--light); color:var(--accent); border-left:3px solid var(--accent); }
.logout { padding:0 1.5rem; margin-top:auto; border-top:1px solid var(--border); padding-top:1rem; }
.logout a { display:flex; align-items:center; justify-content:center; background-color:#f0f0f0; color:#555; padding:0.75rem; border-radius:8px; text-decoration:none; font-weight:500; transition:all 0.3s; }
.logout a:hover { background-color:#e0e0e0; transform:translateY(-2px); }
.content { flex:1; padding:2rem; position:relative; overflow:auto; }
table { width:100%; border-collapse:collapse; margin-top:1rem; background-color:white; box-shadow:var(--shadow); border-radius:8px; overflow:hidden; }
th, td { padding:1rem; text-align:left; border-bottom:1px solid var(--border); }
th { background-color:var(--primary); color:white; font-weight:600; }
tr:nth-child(even) { background-color:rgba(0,0,0,0.02); }
tr:hover { background-color:rgba(0,0,0,0.03); }
button { padding:0.5rem 1rem; border:none; border-radius:4px; cursor:pointer; font-weight:500; transition:all 0.3s; }
.view-button { background-color:var(--accent); color:white; margin-right:0.5rem; }
.view-button:hover { background-color:var(--primary); }
.delete-button { background-color:var(--accent); color:white; margin-right:0.5rem; }
.delete-button:hover { background-color:var(--primary); }
.action-buttons { display:flex; justify-content:center; gap:8px; }
.action-label { text-align:center; }

/* Sidebar nav */
.nav-menu { display:flex; flex-direction:column; padding:1rem 0; gap:4px; }
.menu-item { display:flex; flex-direction:column; }
.menu-header { display:flex; align-items:center; padding:0.75rem 1rem; cursor:pointer; border-radius:8px; transition:all 0.2s ease; gap:12px; }
.icon { width:20px; height:20px; display:flex; align-items:center; justify-content:center; }
.icon svg { width:18px; height:18px; stroke:var(--primary); }
.menu-header span { flex:1; font-size:14px; color:var(--dark); }
.dropdown-icon { width:20px; height:20px; display:flex; align-items:center; justify-content:center; transition:transform 0.2s ease; }
.dropdown-icon svg { width:16px; height:16px; stroke:#777; }
.expanded .dropdown-icon { transform:rotate(180deg); }
.menu-header:hover { background-color:var(--light); }
.dropdown-content { display:none; flex-direction:column; padding-left:2.5rem; }
.dropdown-content.show { display:flex; }
.submenu-item { padding:0.6rem 1rem; font-size:13px; color:#777; text-decoration:none; border-radius:8px; transition:all 0.2s ease; }
.submenu-item:hover { background-color:var(--light); color:var(--primary); }

/* ═══ VIEWER MODAL ═══ */
.modal-overlay {
    position:fixed; inset:0;
    background:rgba(8,15,32,0.75);
    backdrop-filter:blur(8px);
    z-index:1000;
    display:none; align-items:center; justify-content:center;
    padding:16px; opacity:0; transition:opacity 0.3s ease;
}
.modal-overlay.open { display:flex; opacity:1; }
.viewer-modal {
    background:#fff; border-radius:20px; width:100%; max-width:1320px;
    height:92vh; max-height:920px; display:flex; flex-direction:column;
    overflow:hidden; box-shadow:0 12px 48px rgba(15,31,61,0.22);
    transform:scale(0.95) translateY(24px);
    transition:transform 0.38s cubic-bezier(0.34,1.56,0.64,1);
}
.modal-overlay.open .viewer-modal { transform:scale(1) translateY(0); }
.modal-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 24px; background:var(--primary); flex-shrink:0;
    position:relative; overflow:hidden;
}
.modal-header::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:160px; height:160px; border-radius:50%;
    background:rgba(255,255,255,0.08); pointer-events:none;
}
.mh-left { display:flex; align-items:center; gap:14px; }
.mh-badge { background:white; color:var(--primary); font-size:10px; font-weight:800; letter-spacing:1px; text-transform:uppercase; padding:4px 10px; border-radius:20px; flex-shrink:0; }
.mh-title { color:white; font-size:17px; font-weight:600; }
.mh-sub { color:rgba(255,255,255,0.55); font-size:11px; margin-top:2px; }
.mh-right { display:flex; align-items:center; gap:8px; }
.mh-btn { display:inline-flex; align-items:center; gap:7px; background:white; color:var(--primary); border:none; padding:8px 16px; border-radius:9px; font-family:inherit; font-size:12px; font-weight:700; cursor:pointer; transition:all 0.2s; }
.mh-btn:hover { background:#f0f0f0; transform:translateY(-1px); }
.mh-btn svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2; }
.mh-close { width:34px; height:34px; border-radius:9px; border:1px solid rgba(255,255,255,0.25); background:rgba(255,255,255,0.1); color:white; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:19px; line-height:1; transition:background 0.2s; }
.mh-close:hover { background:rgba(220,50,50,0.45); }
.accent-bar { height:3px; background:linear-gradient(90deg,white 0%,var(--accent) 60%,transparent); flex-shrink:0; }
.modal-body { display:flex; flex:1; overflow:hidden; }

/* PDF Pane */
.pane-pdf { flex:1.15; display:flex; flex-direction:column; background:#21242d; overflow:hidden; }
.pdf-toolbar { display:flex; align-items:center; gap:12px; padding:9px 16px; background:#181b22; border-bottom:1px solid rgba(255,255,255,0.07); flex-shrink:0; }
.pdf-tlabel { font-size:10px; font-weight:700; letter-spacing:0.8px; text-transform:uppercase; color:rgba(255,255,255,0.35); white-space:nowrap; }
.pdf-fname { font-size:12px; color:rgba(255,255,255,0.7); flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pdf-live { display:flex; align-items:center; gap:5px; font-size:11px; color:rgba(255,255,255,0.4); white-space:nowrap; }
.pdf-live-dot { width:7px; height:7px; border-radius:50%; background:#22c55e; box-shadow:0 0 6px #22c55e; animation:blink1 2.5s ease-in-out infinite; }
@keyframes blink1 { 0%,100%{opacity:1}50%{opacity:0.35} }
.pdf-wrap { flex:1; position:relative; overflow:hidden; }
.pdf-wrap iframe { width:100%; height:100%; border:none; }
.pdf-loader { position:absolute; inset:0; background:#21242d; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px; z-index:5; transition:opacity 0.4s; }
.pdf-loader.hidden { opacity:0; pointer-events:none; }
.pdf-spinner { width:40px; height:40px; border-radius:50%; border:3px solid rgba(67,102,179,0.25); border-top-color:var(--accent); animation:spin1 0.85s linear infinite; }
@keyframes spin1 { to { transform:rotate(360deg); } }
.pdf-loader-text { font-size:12px; color:rgba(255,255,255,0.4); }

/* Form Pane */
.pane-form { width:430px; flex-shrink:0; display:flex; flex-direction:column; background:#f5f7fd; border-left:1px solid var(--border); overflow:hidden; }
.form-hdr { padding:18px 20px 14px; background:white; border-bottom:1px solid var(--border); flex-shrink:0; }
.inst-row { display:flex; align-items:center; gap:9px; margin-bottom:10px; }
.inst-logo { width:30px; height:30px; background:var(--primary); border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.inst-logo svg { width:16px; height:16px; stroke:white; fill:none; stroke-width:1.8; }
.inst-name { font-size:10px; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:0.4px; line-height:1.45; }
.fhdr-title { font-size:16px; font-weight:700; color:var(--primary); margin-bottom:5px; }
.fhdr-meta { display:flex; gap:12px; flex-wrap:wrap; }
.fhdr-meta-item { display:flex; align-items:center; gap:4px; font-size:11px; color:#777; }
.fhdr-meta-item svg { width:11px; height:11px; stroke:currentColor; fill:none; stroke-width:2; }
.form-tabs { display:flex; background:white; border-bottom:1px solid var(--border); flex-shrink:0; }
.ftab { flex:1; padding:10px 6px; font-size:11.5px; font-weight:500; color:#777; text-align:center; cursor:pointer; border-bottom:2px solid transparent; transition:all 0.2s; }
.ftab:hover { color:var(--accent); }
.ftab.active { color:var(--primary); border-bottom-color:var(--accent); font-weight:700; }
.ftab-count { display:inline-flex; align-items:center; justify-content:center; background:var(--accent); color:white; font-size:9px; font-weight:700; width:15px; height:15px; border-radius:50%; margin-left:4px; vertical-align:middle; }
.form-body { flex:1; overflow-y:auto; padding:14px; }
.form-body::-webkit-scrollbar { width:4px; }
.form-body::-webkit-scrollbar-thumb { background:var(--border); border-radius:4px; }
.tab-pane { display:none; }
.tab-pane.active { display:block; }

/* Route Groups */
.rg { margin-bottom:18px; }
.rg-header { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.rg-pill { background:var(--primary); color:white; font-size:10px; font-weight:800; padding:3px 10px; border-radius:20px; letter-spacing:0.4px; white-space:nowrap; }
.rg-line { flex:1; height:1px; background:linear-gradient(90deg,var(--border),transparent); }
.rg-ct { font-size:11px; color:#777; white-space:nowrap; }

/* Comment Cards */
.cc { background:white; border:1px solid var(--border); border-radius:11px; padding:13px 14px 13px 17px; margin-bottom:9px; position:relative; transition:box-shadow 0.2s,transform 0.2s; animation:fadeUp1 0.3s ease forwards; opacity:0; }
@keyframes fadeUp1 { from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:translateY(0)} }
.cc:hover { box-shadow:0 4px 16px rgba(0,0,0,0.08); transform:translateY(-1px); }
.cc-bar { position:absolute; left:0; top:0; bottom:0; width:3px; border-radius:11px 0 0 11px; }
.cc-bar.adviser { background:var(--accent); }
.cc-bar.panel { background:#7c3aed; }
.cc-top { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:7px; gap:6px; }
.cc-who { display:flex; align-items:center; gap:7px; }
.cc-avatar { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
.cc-avatar.adviser { background:#e5ebf8; color:var(--primary); }
.cc-avatar.panel { background:#ede9fe; color:#5b21b6; }
.cc-name { font-size:12px; font-weight:700; color:#333; }
.cc-role { font-size:10px; color:#777; margin-top:1px; }
.cc-date { font-size:10px; color:#777; background:#f5f7fd; padding:2px 7px; border-radius:20px; white-space:nowrap; flex-shrink:0; }
.cc-locs { display:flex; gap:5px; flex-wrap:wrap; margin-bottom:7px; }
.loc-chip { display:flex; align-items:center; gap:3px; background:#f5f7fd; border:1px solid var(--border); border-radius:5px; padding:2px 7px; font-size:10px; color:#777; }
.loc-chip strong { color:#333; font-weight:600; }
.cc-feedback { font-size:12px; color:#333; line-height:1.6; background:#f5f7fd; border-radius:7px; padding:9px 11px; border-left:3px solid var(--border); margin-bottom:7px; }
.cc-footer { display:flex; align-items:center; justify-content:space-between; }
.rel-badge { display:flex; align-items:center; gap:4px; font-size:10px; color:#155724; background:#d4edda; padding:2px 8px; border-radius:20px; }
.rel-badge svg { width:10px; height:10px; stroke:currentColor; fill:none; stroke-width:2.5; }

/* Loading / Empty */
.comments-loading { display:flex; align-items:center; justify-content:center; padding:40px; flex-direction:column; gap:12px; }
.comments-loading p { font-size:12px; color:#777; }
.empty-comments { text-align:center; padding:40px 20px; }
.empty-comments svg { width:36px; height:36px; stroke:var(--border); fill:none; stroke-width:1.5; margin-bottom:10px; }
.empty-comments p { font-size:13px; color:#777; }

/* Summary */
.stat-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; margin-bottom:12px; }
.stat-box { background:white; border:1px solid var(--border); border-radius:10px; padding:13px; text-align:center; }
.stat-num { font-size:26px; font-weight:700; color:var(--primary); line-height:1; margin-bottom:3px; }
.stat-lbl { font-size:10px; color:#777; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; }
.sum-card { background:white; border:1px solid var(--border); border-radius:10px; padding:14px; margin-bottom:10px; }
.sum-card-title { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:#777; margin-bottom:9px; }
.sum-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #f5f7fd; font-size:12px; }
.sum-row:last-child { border-bottom:none; }
.sum-lbl { color:#777; }
.sum-val { font-weight:600; color:#333; }

/* Timeline */
.timeline1 { position:relative; padding-left:24px; }
.timeline1::before { content:''; position:absolute; left:9px; top:4px; bottom:4px; width:2px; background:var(--border); }
.tl1-item { position:relative; margin-bottom:18px; }
.tl1-dot { position:absolute; left:-19px; top:3px; width:11px; height:11px; border-radius:50%; border:2px solid white; }
.tl1-dot.done { background:#28a745; box-shadow:0 0 0 2px #28a745; }
.tl1-dot.adviser { background:var(--accent); box-shadow:0 0 0 2px var(--accent); }
.tl1-dot.panel { background:#7c3aed; box-shadow:0 0 0 2px #7c3aed; }
.tl1-dot.pending { background:var(--border); box-shadow:0 0 0 2px var(--border); }
.tl1-date { font-size:10px; color:#777; margin-bottom:2px; }
.tl1-title { font-size:12px; font-weight:700; color:#333; margin-bottom:2px; }
.tl1-desc { font-size:11px; color:#777; line-height:1.5; }

/* Modal Footer */
.modal-footer1 { padding:11px 22px; background:white; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.mf-text { font-size:11px; color:#777; }
.mf-actions { display:flex; gap:7px; }
.mf-btn { padding:6px 14px; border-radius:7px; font-family:inherit; font-size:12px; font-weight:600; cursor:pointer; border:none; transition:all 0.2s; }
.mf-btn.outline { background:transparent; border:1px solid var(--border); color:#333; }
.mf-btn.outline:hover { border-color:var(--accent); color:var(--accent); }
.mf-btn.primary { background:var(--accent); color:white; }
.mf-btn.primary:hover { background:var(--primary); }

#minutesOverlay .modal-body { background:#21242d; }
</style>
</head>
<body>

<?php if (isset($_SESSION['alert_message'])): ?>
<script>alert('<?= addslashes($_SESSION['alert_message']) ?>');</script>
<?php unset($_SESSION['alert_message']); endif; ?>

<div class="container">
    <!-- HEADER -->
    <header class="header">
        <div class="logo-container">
            <img src="../../../assets/logo.png" alt="SMCC Logo">
            <div class="logo">Thesis Routing System</div>
        </div>
    </header>

    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="navigation">
            <a href="../homepage.php">Home Page</a>
            <div class="submit-button">
                <a href="#" id="submit-file-button">Submit File</a>
            </div>
        </div>
        <div class="user-info">
            <div class="routeNo" style="margin-right:20px;">Proposal - Route 3</div>
            <div class="vl"></div>
            <span class="role">Student:</span>
            <span class="user-name"><?= isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Guest' ?></span>
        </div>
    </div>

    <div class="main-content">
        <!-- SIDEBAR -->
        <nav class="sidebar">
            <nav class="nav-menu">
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
            </nav>
            <div class="logout">
                <a href="../../../logout.php">Logout</a>
            </div>
        </nav>

        <!-- CONTENT -->
        <div class="content" id="content-area">
            <?php if (!empty($fileRows)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Control No.</th>
                        <th>Leader</th>
                        <th>Group No.</th>
                        <th>Title</th>
                        <th>Minutes</th>
                        <th class="action-label">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fileRows as $row):
                        $filePath  = htmlspecialchars($row['docuRoute3'], ENT_QUOTES);
                        $route3_id = htmlspecialchars($row['route3_id'], ENT_QUOTES);
                        $controlNo = htmlspecialchars($row['controlNo'], ENT_QUOTES);
                        $fullName  = htmlspecialchars($row['fullname'], ENT_QUOTES);
                        $groupNo   = htmlspecialchars($row['group_number'], ENT_QUOTES);
                        $title     = htmlspecialchars($row['title'], ENT_QUOTES);

                        $minutes = '';
                        $minutesLabel = '<span style="color:red;">Not Available</span>';
                        if (!empty($row['minutes'])) {
                            $minutes = htmlspecialchars($row['minutes'], ENT_QUOTES);
                            $minutesLabel = '<span style="color:green;">Available</span>';
                        } elseif (!empty($row['route1_minutes'])) {
                            $minutes = htmlspecialchars($row['route1_minutes'], ENT_QUOTES);
                            $minutesLabel = '<span style="color:green;">Available (from Route 1)</span>';
                        }
                    ?>
                    <tr>
                        <td><?= $controlNo ?></td>
                        <td><?= $fullName ?></td>
                        <td><?= $groupNo ?></td>
                        <td><?= $title ?></td>
                        <td><?= $minutesLabel ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="view-button" onclick="viewFile('<?= $filePath ?>','<?= $student_id ?>','<?= $route3_id ?>','<?= addslashes($controlNo) ?>','<?= addslashes($fullName) ?>','<?= addslashes($groupNo) ?>','<?= addslashes($title) ?>')">View</button>
                                <button class="delete-button" onclick="confirmReupload('<?= $filePath ?>')">Reupload</button>
                                <?php if ($minutes): ?>
                                <button class="view-button" onclick="viewMinutes('<?= $minutes ?>')">View Minutes</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="background-color:white;border-radius:10px;box-shadow:var(--shadow);padding:2rem;text-align:center;">
                <h1 style="color:var(--primary);margin-bottom:1rem;">No Files Uploaded Yet</h1>
                <p style="color:#666;line-height:1.6;margin-bottom:1.5rem;">Click on 'Submit File' to upload your thesis documents.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden Forms -->
<form action="route3.php" method="POST" enctype="multipart/form-data" id="file-upload-form" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>">
    <input type="hidden" name="student_id" value="<?= htmlspecialchars($_SESSION['student_id']); ?>">
    <input type="file" name="docuRoute3" id="docuRoute3Input" accept=".pdf">
</form>

<form action="route3.php" method="POST" enctype="multipart/form-data" id="file-reupload-form" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="student_id" value="<?= htmlspecialchars($_SESSION['student_id']); ?>">
    <input type="hidden" name="old_file_path" id="oldFilePath">
    <input type="file" name="docuRoute3" id="reuploadInput" accept=".pdf">
</form>

<!-- VIEWER MODAL -->
<div class="modal-overlay" id="viewerOverlay">
    <div class="viewer-modal">
        <div class="modal-header">
            <div class="mh-left">
                <span class="mh-badge">Route 3</span>
                <div>
                    <div class="mh-title" id="modalDocTitle">Research Proposal</div>
                    <div class="mh-sub">Saint Michael College of Caraga — TRS</div>
                </div>
            </div>
            <div class="mh-right">
                <button class="mh-btn" onclick="printForm()">
                    <svg viewBox="0 0 24 24"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
                    Print Form
                </button>
                <button class="mh-close" onclick="closeViewer()">×</button>
            </div>
        </div>
        <div class="accent-bar"></div>

        <div class="modal-body">
            <!-- PDF Pane -->
            <div class="pane-pdf">
                <div class="pdf-toolbar">
                    <span class="pdf-tlabel">Document</span>
                    <span class="pdf-fname" id="pdfFilenameLabel">–</span>
                    <span class="pdf-live"><span class="pdf-live-dot"></span>Live Preview</span>
                </div>
                <div class="pdf-wrap">
                    <div class="pdf-loader" id="pdfLoader">
                        <div class="pdf-spinner"></div>
                        <span class="pdf-loader-text">Loading document…</span>
                    </div>
                    <iframe id="pdfFrame" src="" onload="hidePdfLoader()" title="Thesis Document"></iframe>
                </div>
            </div>

            <!-- Form Pane -->
            <div class="pane-form">
                <div class="form-hdr">
                    <div class="inst-row">
                        <div class="inst-logo">
                            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                        </div>
                        <div class="inst-name">Saint Michael College of Caraga<br>Research &amp; Instructional Innovation Dept.</div>
                    </div>
                    <div class="fhdr-title">Routing Monitoring Form</div>
                    <div class="fhdr-meta">
                        <div class="fhdr-meta-item">
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span id="modalSchoolYear">–</span>
                        </div>
                        <div class="fhdr-meta-item">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            Group <span id="modalGroupNo">–</span>
                        </div>
                        <div class="fhdr-meta-item">
                            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <span id="modalControlNo">–</span>
                        </div>
                    </div>
                </div>

                <div class="form-tabs">
                    <div class="ftab active" onclick="switchTab('comments')">Comments <span class="ftab-count" id="tabCommentCount">0</span></div>
                    <div class="ftab" onclick="switchTab('summary')">Summary</div>
                    <div class="ftab" onclick="switchTab('timeline')">Timeline</div>
                </div>

                <div class="form-body">
                    <!-- Comments -->
                    <div class="tab-pane active" id="pane-comments">
                        <div class="comments-loading" id="commentsLoader">
                            <div class="pdf-spinner" style="border-top-color:var(--accent);"></div>
                            <p>Loading comments…</p>
                        </div>
                        <div id="commentsContainer" style="display:none;"></div>
                    </div>

                    <!-- Summary -->
                    <div class="tab-pane" id="pane-summary">
                        <div class="stat-grid">
                            <div class="stat-box"><div class="stat-num" id="sumTotalComments">0</div><div class="stat-lbl">Total Comments</div></div>
                            <div class="stat-box"><div class="stat-num" id="sumRoutes">0</div><div class="stat-lbl">Routes Done</div></div>
                            <div class="stat-box"><div class="stat-num" id="sumReviewers">0</div><div class="stat-lbl">Reviewers</div></div>
                            <div class="stat-box"><div class="stat-num" id="sumAdviser">0</div><div class="stat-lbl">By Adviser</div></div>
                        </div>
                        <div class="sum-card">
                            <div class="sum-card-title">Document Details</div>
                            <div class="sum-row"><span class="sum-lbl">Control No.</span><span class="sum-val" id="sumControlNo">–</span></div>
                            <div class="sum-row"><span class="sum-lbl">Leader</span><span class="sum-val" id="sumLeader">–</span></div>
                            <div class="sum-row"><span class="sum-lbl">Group No.</span><span class="sum-val" id="sumGroup">–</span></div>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="tab-pane" id="pane-timeline">
                        <div class="timeline1">
                            <div class="tl1-item">
                                <div class="tl1-dot done"></div>
                                <div class="tl1-date">Submission</div>
                                <div class="tl1-title">Document Uploaded</div>
                                <div class="tl1-desc">Route 3 thesis document submitted to the system.</div>
                            </div>
                            <div id="timelineDynamic"></div>
                            <div class="tl1-item">
                                <div class="tl1-dot pending"></div>
                                <div class="tl1-date">Pending</div>
                                <div class="tl1-title">Awaiting Next Action</div>
                                <div class="tl1-desc">Incorporate feedback and resubmit for the next route.</div>
                            </div>
                        </div>
                    </div>
                </div><!-- /form-body -->

                <div class="modal-footer1">
                    <span class="mf-text">SMCC Thesis Routing System</span>
                    <div class="mf-actions">
                        <button class="mf-btn outline" onclick="closeViewer()">Close</button>
                        <button class="mf-btn primary" onclick="printForm()">Print Form</button>
                    </div>
                </div>
            </div><!-- /pane-form -->
        </div><!-- /modal-body -->
    </div>
</div>

<!-- MINUTES MODAL -->
<div class="modal-overlay" id="minutesOverlay">
    <div class="viewer-modal">
        <div class="modal-header">
            <div class="mh-left">
                <span class="mh-badge">Minutes</span>
                <div>
                    <div class="mh-title">Meeting Minutes</div>
                    <div class="mh-sub">Route 3 — Research Proposal</div>
                </div>
            </div>
            <div class="mh-right">
                <button class="mh-close" onclick="closeMinutes()">×</button>
            </div>
        </div>
        <div class="accent-bar"></div>
        <div class="modal-body" style="background:#21242d;">
            <iframe id="minutesFrame" src="" style="width:100%;height:100%;border:none;" title="Meeting Minutes"></iframe>
        </div>
    </div>
</div>

<script>
    // ── Upload triggers
    document.getElementById('submit-file-button').onclick = function(e) {
        e.preventDefault();
        document.getElementById('docuRoute3Input').click();
    };
    document.getElementById('docuRoute3Input').onchange = function() {
        document.getElementById('file-upload-form').submit();
    };
    document.getElementById('reuploadInput').onchange = function() {
        document.getElementById('file-reupload-form').submit();
    };

    function confirmReupload(path) {
        if (confirm('Replace the current file with a new upload?')) {
            document.getElementById('oldFilePath').value = path;
            document.getElementById('reuploadInput').click();
        }
    }

    // ── State
    let currentFormData = [];

    // ── Open viewer
    function viewFile(filePath, studentId, route3Id, controlNo, leader, groupNo, title) {
        document.getElementById('modalDocTitle').textContent = title || 'Research Proposal';
        document.getElementById('pdfFilenameLabel').textContent = filePath.split('/').pop();
        document.getElementById('modalControlNo').textContent = controlNo;
        document.getElementById('modalGroupNo').textContent = groupNo;
        document.getElementById('sumControlNo').textContent = controlNo;
        document.getElementById('sumLeader').textContent = leader;
        document.getElementById('sumGroup').textContent = groupNo;
        const yr = new Date().getFullYear();
        document.getElementById('modalSchoolYear').textContent = 'AY ' + yr + '–' + (yr + 1);

        document.getElementById('pdfLoader').classList.remove('hidden');
        document.getElementById('pdfFrame').src = filePath;
        document.getElementById('commentsLoader').style.display = 'flex';
        document.getElementById('commentsContainer').style.display = 'none';
        document.getElementById('commentsContainer').innerHTML = '';
        document.getElementById('timelineDynamic').innerHTML = '';

        const overlay = document.getElementById('viewerOverlay');
        overlay.style.display = 'flex';
        requestAnimationFrame(() => overlay.classList.add('open'));
        switchTab('comments');

        fetch('route3get_all_forms.php?student_id=' + encodeURIComponent(studentId))
            .then(r => r.json())
            .then(data => {
                currentFormData = Array.isArray(data) ? data : [];
                renderComments(currentFormData);
                renderSummary(currentFormData);
                renderTimeline(currentFormData);
            })
            .catch(() => {
                document.getElementById('commentsLoader').style.display = 'none';
                document.getElementById('commentsContainer').style.display = 'block';
                document.getElementById('commentsContainer').innerHTML = '<div class="empty-comments"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><p>Could not load comments. Please try again.</p></div>';
            });
    }

    function closeViewer() {
        const overlay = document.getElementById('viewerOverlay');
        overlay.classList.remove('open');
        setTimeout(() => { overlay.style.display = 'none'; document.getElementById('pdfFrame').src = ''; }, 320);
    }
    function hidePdfLoader() { document.getElementById('pdfLoader').classList.add('hidden'); }

    // ── Minutes
    function viewMinutes(minutesPath) {
        const overlay = document.getElementById('minutesOverlay');
        document.getElementById('minutesFrame').src = minutesPath + '?t=' + Date.now();
        overlay.style.display = 'flex';
        requestAnimationFrame(() => overlay.classList.add('open'));
    }
    function closeMinutes() {
        const overlay = document.getElementById('minutesOverlay');
        overlay.classList.remove('open');
        setTimeout(() => { overlay.style.display = 'none'; document.getElementById('minutesFrame').src = ''; }, 320);
    }

    // ── Tabs
    function switchTab(name) {
        const names = ['comments', 'summary', 'timeline'];
        document.querySelectorAll('.ftab').forEach((t, i) => t.classList.toggle('active', names[i] === name));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.toggle('active', p.id === 'pane-' + name));
    }

    // ── Render comments
    function renderComments(data) {
        document.getElementById('commentsLoader').style.display = 'none';
        const container = document.getElementById('commentsContainer');
        container.style.display = 'block';
        if (!data.length) {
            container.innerHTML = '<div class="empty-comments"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg><p>No comments yet. Your adviser will add feedback here after reviewing.</p></div>';
            document.getElementById('tabCommentCount').textContent = '0';
            return;
        }
        document.getElementById('tabCommentCount').textContent = data.length;
        const groups = {};
        data.forEach(row => { const rn = row.routeNumber || 'Route 3'; if (!groups[rn]) groups[rn] = []; groups[rn].push(row); });
        let html = '';
        Object.entries(groups).forEach(([routeName, rows]) => {
            html += `<div class="rg"><div class="rg-header"><span class="rg-pill">${e(routeName)}</span><div class="rg-line"></div><span class="rg-ct">${rows.length} comment${rows.length !== 1 ? 's' : ''}</span></div>`;
            rows.forEach((row, i) => {
                const isAdviser = !!row.adviser_name;
                const role      = isAdviser ? 'adviser' : 'panel';
                const name      = isAdviser ? row.adviser_name : (row.panel_name || 'Reviewer');
                const initials  = name.split(',')[0].trim().substring(0, 2).toUpperCase();
                html += `
                    <div class="cc ${role}" style="animation-delay:${i * 0.05}s">
                        <div class="cc-bar ${role}"></div>
                        <div class="cc-top">
                            <div class="cc-who">
                                <div class="cc-avatar ${role}">${e(initials)}</div>
                                <div><div class="cc-name">${e(name)}</div><div class="cc-role">${isAdviser ? 'Thesis Adviser' : 'Panel Member'}</div></div>
                            </div>
                            <div class="cc-date">${e(row.date_submitted || '—')}</div>
                        </div>
                        <div class="cc-locs">
                            ${row.chapter ? `<div class="loc-chip"><strong>Ch.</strong>${e(String(row.chapter))}</div>` : ''}
                            ${row.page_number ? `<div class="loc-chip"><strong>Pg.</strong>${e(String(row.page_number))}</div>` : ''}
                            ${row.paragraph_number ? `<div class="loc-chip"><strong>¶</strong>${e(String(row.paragraph_number))}</div>` : ''}
                        </div>
                        <div class="cc-feedback">${e(row.feedback || 'No feedback provided.')}</div>
                        <div class="cc-footer">
                            ${row.date_released ? `<div class="rel-badge"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Released ${e(row.date_released)}</div>` : '<span style="font-size:10px;color:#777;">Not yet released</span>'}
                            <span style="font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;${
                                (row.status||'').toLowerCase() === 'approved'
                                    ? 'background:#d4edda;color:#155724;'
                                    : 'background:#fff3cd;color:#856404;'
                            }">${e(row.status || 'Pending')}</span>
                        </div>
                    </div>`;
            });
            html += '</div>';
        });
        container.innerHTML = html;
    }

    function renderSummary(data) {
        const advisers = new Set(), panels = new Set();
        let advCount = 0; const routes = new Set();
        data.forEach(r => { if (r.adviser_name) { advisers.add(r.adviser_name); advCount++; } if (r.panel_name) panels.add(r.panel_name); if (r.routeNumber) routes.add(r.routeNumber); });
        document.getElementById('sumTotalComments').textContent = data.length;
        document.getElementById('sumRoutes').textContent = routes.size;
        document.getElementById('sumReviewers').textContent = advisers.size + panels.size;
        document.getElementById('sumAdviser').textContent = advCount;
    }

    function renderTimeline(data) {
        const dyn = document.getElementById('timelineDynamic');
        const groups = {};
        data.forEach(r => { const rn = r.routeNumber || 'Route 3'; if (!groups[rn]) groups[rn] = []; groups[rn].push(r); });
        let html = '';
        Object.entries(groups).forEach(([rn, rows]) => {
            const d = rows[rows.length - 1].date_released || rows[rows.length - 1].date_submitted || '';
            html += `<div class="tl1-item"><div class="tl1-dot adviser"></div><div class="tl1-date">${e(d)}</div><div class="tl1-title">${e(rn)} Review Completed</div><div class="tl1-desc">${rows.length} comment${rows.length !== 1 ? 's' : ''} submitted.</div></div>`;
        });
        dyn.innerHTML = html;
    }

    // ── Print
    function printForm() {
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        document.body.appendChild(iframe);
        const rows = currentFormData.map(row => `<tr>
            <td>${e(row.date_submitted || '—')}</td>
            <td>${e(String(row.chapter || '—'))}</td>
            <td>${e(String(row.page_number || '—'))}</td>
            <td>${e(String(row.paragraph_number || '—'))}</td>
            <td>${e(row.feedback || '—')}</td>
            <td>${e(row.adviser_name || row.panel_name || 'Reviewer')} – ${row.adviser_name ? 'Adviser' : 'Panel'}</td>
            <td>${e(row.date_released || '—')}</td>
            <td>${e(row.routeNumber || '—')}</td>
        </tr>`).join('');
        const doc = `<!DOCTYPE html><html><head><title>Routing Monitoring Form</title>
        <style>body{font-family:Arial,sans-serif;padding:20px;font-size:12px;}h2{color:#4366b3;text-align:center;}h3{text-align:center;font-size:13px;color:#555;margin-bottom:20px;}table{width:100%;border-collapse:collapse;}th{background:#4366b3;color:white;padding:8px;font-size:11px;text-align:left;}td{padding:7px 8px;border-bottom:1px solid #eee;font-size:11px;}@media print{@page{size:landscape;margin:1cm}}</style>
        </head><body>
        <h2>SAINT MICHAEL COLLEGE OF CARAGA</h2>
        <h3>Research &amp; Instructional Innovation Department — Routing Monitoring Form</h3>
        <table><thead><tr><th>Date Submitted</th><th>Chapter</th><th>Page No.</th><th>Paragraph No.</th><th>Feedback</th><th>Submitted By</th><th>Date Released</th><th>Route</th></tr></thead>
        <tbody>${rows || '<tr><td colspan="8" style="text-align:center;color:#999;">No records found.</td></tr>'}</tbody></table>
        </body></html>`;
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(doc);
        iframe.contentWindow.document.close();
        iframe.onload = () => { setTimeout(() => { iframe.contentWindow.print(); setTimeout(() => document.body.removeChild(iframe), 500); }, 300); };
    }

    // ── Close on overlay click / ESC
    document.getElementById('viewerOverlay').addEventListener('click', ev => { if (ev.target === ev.currentTarget) closeViewer(); });
    document.getElementById('minutesOverlay').addEventListener('click', ev => { if (ev.target === ev.currentTarget) closeMinutes(); });
    document.addEventListener('keydown', ev => { if (ev.key === 'Escape') { closeViewer(); closeMinutes(); } });

    // ── Sidebar accordion
    document.addEventListener('DOMContentLoaded', function() {
        const menuHeaders = document.querySelectorAll('.menu-header');
        const path = window.location.pathname;
        menuHeaders.forEach(header => {
            const dropdownContent = header.nextElementSibling;
            const label = header.querySelector('span').textContent.trim().toLowerCase();
            header.querySelector('.dropdown-icon').classList.remove('expanded');
            dropdownContent.classList.remove('show');
            if (path.includes('/titleproposal/') && label.includes('research proposal')) {
                header.querySelector('.dropdown-icon').classList.add('expanded');
                dropdownContent.classList.add('show');
            } else if (path.includes('/final/') && label === 'final defense') {
                header.querySelector('.dropdown-icon').classList.add('expanded');
                dropdownContent.classList.add('show');
            }
            header.addEventListener('click', function() {
                menuHeaders.forEach(h => {
                    h.querySelector('.dropdown-icon').classList.remove('expanded');
                    h.nextElementSibling.classList.remove('show');
                });
                this.querySelector('.dropdown-icon').classList.toggle('expanded');
                dropdownContent.classList.toggle('show');
            });
        });
    });

    // ── Util
    function e(str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
</script>
</body>
</html>