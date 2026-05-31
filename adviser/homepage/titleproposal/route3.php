<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['adviser_id'])) {
    header("Location: ../../../logout.php");
    exit;
}

include '../../../connection.php';

$adviser_id = $_SESSION['adviser_id'];
$fullname   = $_SESSION['fullname'] ?? 'Adviser';

$stmt = $conn->prepare("SELECT student_id, route3_id FROM route3proposal_files WHERE adviser_id = ?");
if ($stmt === false) die("Error preparing the query: " . $conn->error);
$stmt->bind_param("s", $adviser_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row        = $result->fetch_assoc();
    $student_id = $row['student_id'];
    $route3_id  = $row['route3_id'];

    $adviserReviewCheck = $conn->prepare("SELECT COUNT(*) as count FROM proposal_monitoring_form WHERE route3_id = ? AND adviser_id = ?");
    $adviserReviewCheck->bind_param("is", $route3_id, $adviser_id);
    $adviserReviewCheck->execute();
    $adviserReviewData = $adviserReviewCheck->get_result()->fetch_assoc();
    $hasReviewed = $adviserReviewData['count'] > 0;
    if (!$hasReviewed) {
        $advisorNotification = "You must review this document first. Panel members will not be able to access it until you've completed your review.";
    }
} else {
    $student_id = null;
    $route3_id  = null;
}

$showModal = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dateSubmitted'])) {
    $dateSubmittedArr   = $_POST['dateSubmitted'];
    $chapterArr         = $_POST['chapter'];
    $feedbackArr        = $_POST['feedback'];
    $paragraphNumberArr = $_POST['paragraphNumber'];
    $pageNumberArr      = $_POST['pageNumber'];
    $adviserNameArr     = $_POST['adviserName'];
    $dateReleasedArr    = $_POST['dateReleased'];
    $docuRoute3         = $_POST['docuRoute3'];
    $route3_id          = $_POST['route3_id'];
    $student_id         = $_POST['student_id'];
    $status             = $_POST['status'];
    $routeNumberArr     = $_POST['routeNumber'];

    $stmt = $conn->prepare("INSERT INTO proposal_monitoring_form 
    (adviser_id, adviser_name, student_id, date_submitted, chapter, feedback, paragraph_number, page_number, date_released, docuRoute3, route3_id, status, routeNumber) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) die("Error preparing the insert query: " . $conn->error);

    $studentEmailStmt = $conn->prepare("SELECT email, fullname FROM student WHERE student_id = ?");
    $studentEmailStmt->bind_param("s", $student_id);
    $studentEmailStmt->execute();
    $studentEmailData = $studentEmailStmt->get_result()->fetch_assoc();
    $studentEmail     = $studentEmailData['email'] ?? '';
    $studentName      = $studentEmailData['fullname'] ?? 'Student';
    $studentEmailStmt->close();

    $feedbackSummary = "New feedback for chapters: " . implode(", ", array_slice($chapterArr, 0, 3));
    if (count($chapterArr) > 3) $feedbackSummary .= " and " . (count($chapterArr) - 3) . " more";

    $insertSuccess = true;
    for ($i = 0; $i < count($chapterArr); $i++) {
        $dateSubmitted   = $dateSubmittedArr[$i];
        $chapter         = $chapterArr[$i];
        $feedback        = $feedbackArr[$i];
        $paragraphNumber = $paragraphNumberArr[$i];
        $pageNumber      = $pageNumberArr[$i];
        $adviserName     = $adviserNameArr[$i];
        $dateReleased    = $dateReleasedArr[$i];
        $routeNumber     = $routeNumberArr[$i];
        $stmt->bind_param("ssssssiisssss", $adviser_id, $adviserName, $student_id, $dateSubmitted, $chapter, $feedback, $paragraphNumber, $pageNumber, $dateReleased, $docuRoute3, $route3_id, $status, $routeNumber);
        if (!$stmt->execute()) {
            echo "<script>alert('Error on row $i: " . addslashes($stmt->error) . "');</script>";
            $insertSuccess = false;
            break;
        }
    }

    if ($insertSuccess && !empty($studentEmail)) {
        $adviserStatusQuery = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved FROM proposal_monitoring_form WHERE route3_id = ? AND adviser_id = ?");
        $adviserStatusQuery->bind_param("ss", $route3_id, $adviser_id);
        $adviserStatusQuery->execute();
        $adviserStatus      = $adviserStatusQuery->get_result()->fetch_assoc();
        $allAdviserApproved = ($adviserStatus['total'] > 0 && $adviserStatus['total'] == $adviserStatus['approved']);
        $adviserStatusQuery->close();

        if (isValidEmail($studentEmail) && $allAdviserApproved) {
            $emailSent = sendStudentNotificationEmail($studentEmail, $studentName, $fullname, $feedbackSummary);
            $msg = $emailSent ? "Form submitted successfully and notification email sent to student." : "Form submitted successfully but failed to send notification email to student.";
        } else {
            $msg = 'Form submitted successfully.';
            if (!isValidEmail($studentEmail)) $msg .= ' Student email address is invalid.';
            else if (!$allAdviserApproved) $msg .= ' Email will be sent when all your feedback is marked as Approved.';
        }
    } else if ($insertSuccess) {
        $msg = "Form submitted successfully. No student email available for notification.";
    }
    echo "<script>alert('$msg'); window.location.href=window.location.href;</script>";
    exit;
}

function isValidEmail($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }

function logEmailError($message) {
    $logFile = __DIR__ . '/../../../email_debug.log';
    error_log($message);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
}

function sendStudentNotificationEmail($student_email, $student_name, $adviser_name, $feedback_summary) {
    try {
        if (!isValidEmail($student_email)) { logEmailError("Invalid email: $student_email"); return false; }
        $autoloader_path = __DIR__ . '/../../../vendor/autoload.php';
        if (!file_exists($autoloader_path)) { logEmailError("PHPMailer autoloader not found."); return false; }
        require_once $autoloader_path;
        $mail = new PHPMailer(true);
        $mail->SMTPDebug  = 2;
        $mail->Debugoutput = function($str, $level) { logEmailError("PHPMailer [$level]: $str"); };
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'smcctrs@gmail.com';
        $mail->Password   = 'cplw gwfe vxcy naoi';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->Timeout       = 30;
        $mail->SMTPKeepAlive = false;
        $mail->setFrom('smcctrs@gmail.com', 'Thesis Routing System', false);
        $mail->addReplyTo('smcctrs@gmail.com', 'Thesis Routing System');
        $mail->addAddress($student_email, $student_name);
        $mail->isHTML(true);
        $mail->Subject = "New Adviser Feedback Submitted - Thesis Routing System";
        $login_url = 'https://trs.smcc-riid.com/v2/trs/student/login.php';
        $mail->Body = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;border:1px solid #ddd;border-radius:5px;'><h2 style='color:#4366b3;text-align:center;'>Thesis Routing System Notification</h2><p>Dear <strong>{$student_name}</strong>,</p><p>Your adviser <strong>{$adviser_name}</strong> has submitted feedback on your thesis proposal.</p><p><strong>Feedback Summary:</strong> {$feedback_summary}</p><p>Please log in to review detailed feedback.</p><div style='margin-top:30px;text-align:center;'><a href='{$login_url}' style='background-color:#4366b3;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Login to Review</a></div><p style='margin-top:30px;font-size:12px;color:#777;text-align:center;'>This is an automated message. Please do not reply.</p></div>";
        $mail->AltBody = "Dear {$student_name}, feedback submitted by {$adviser_name}. Login at: {$login_url}";
        $mail->addCustomHeader('X-Mailer', 'Thesis Routing System');
        $mail->addCustomHeader('X-Priority', '3');
        if (!$mail->send()) { logEmailError("Email failed: " . $mail->ErrorInfo); return false; }
        return true;
    } catch (Exception $e) { logEmailError("Email error: " . $e->getMessage()); return false; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Route 3 – Thesis Routing System</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.4.2/mammoth.browser.min.js"></script>
<style>
:root {
    --primary: #4366b3;
    --primary-light: #0a3885;
    --accent: #4a6fd1;
    --light: #f5f7fd;
    --dark: #333;
    --success: #28a745;
    --shadow: 0 4px 6px rgba(0,0,0,0.1);
    --border: #e0e0e0;
    --white: #ffffff;
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
.homepage a { color:var(--accent); text-decoration:none; font-weight:500; transition:all 0.3s; }
.homepage a:hover { color:var(--primary); }
.user-info { display:flex; align-items:center; }
.vl { border-left:1px solid var(--border); height:20px; margin:0 10px; }
.role { font-weight:600; margin-right:5px; color:var(--primary); }
.user-name { color:var(--dark); }
.main-content { display:flex; flex:1; }
.sidebar { width:250px; background-color:white; padding:1.5rem 0; box-shadow:2px 0 5px rgba(0,0,0,0.05); display:flex; flex-direction:column; justify-content:space-between; border-right:1px solid var(--border); position:sticky; top:0; height:100vh; overflow-y:auto; }
.sidebar a { display:block; padding:0.75rem 1.5rem; color:var(--dark); text-decoration:none; transition:all 0.3s; border-left:3px solid transparent; }
.sidebar a:hover { background-color:var(--light); color:var(--accent); border-left:3px solid var(--accent); }
.logout { position:sticky; bottom:0; background-color:white; padding:1rem 1.5rem; border-top:1px solid var(--border); }
.logout a { display:flex; align-items:center; justify-content:center; background-color:#f0f0f0; color:#555; padding:0.75rem; border-radius:8px; text-decoration:none; font-weight:500; transition:all 0.3s; }
.logout a:hover { background-color:#e0e0e0; transform:translateY(-2px); }
.content { flex:1; padding:2rem; position:relative; overflow:auto; }
table { width:100%; border-collapse:collapse; margin-top:1rem; background-color:white; box-shadow:var(--shadow); border-radius:8px; overflow:hidden; }
th,td { padding:1rem; text-align:left; border-bottom:1px solid var(--border); }
th { background-color:var(--primary); color:white; font-weight:600; }
tr:nth-child(even) { background-color:rgba(0,0,0,0.02); }
tr:hover { background-color:rgba(0,0,0,0.03); }
button { padding:0.5rem 1rem; border:none; border-radius:4px; cursor:pointer; font-weight:500; transition:all 0.3s; }
.view-button { background-color:var(--accent); color:white; margin-right:0.5rem; }
.view-button:hover { background-color:var(--primary); }
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

/* Search */
.search-container { margin:15px 0; width:100%; display:flex; justify-content:flex-start; align-items:center; }
.search-box { padding:8px 12px; border:1px solid #ccc; border-radius:4px; width:300px; font-size:14px; margin-bottom:15px; }

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
    background:#fff; border-radius:20px; width:100%; max-width:1400px;
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
.pane-pdf { flex:1.1; display:flex; flex-direction:column; background:#21242d; overflow:hidden; }
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
.pane-form { width:520px; flex-shrink:0; display:flex; flex-direction:column; background:#f5f7fd; border-left:1px solid var(--border); overflow:hidden; }
.form-hdr { padding:18px 20px 14px; background:white; border-bottom:1px solid var(--border); flex-shrink:0; }
.inst-row { display:flex; align-items:center; gap:9px; margin-bottom:10px; }
.inst-logo { width:30px; height:30px; background:var(--primary); border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.inst-logo svg { width:16px; height:16px; stroke:white; fill:none; stroke-width:1.8; }
.inst-name { font-size:10px; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:0.4px; line-height:1.45; }
.fhdr-title { font-size:16px; font-weight:700; color:var(--primary); margin-bottom:5px; }
.fhdr-meta { display:flex; gap:12px; flex-wrap:wrap; }
.fhdr-meta-item { display:flex; align-items:center; gap:4px; font-size:11px; color:#777; }
.fhdr-meta-item svg { width:11px; height:11px; stroke:currentColor; fill:none; stroke-width:2; }

/* Tabs */
.form-tabs { display:flex; background:white; border-bottom:1px solid var(--border); flex-shrink:0; }
.ftab { flex:1; padding:10px 6px; font-size:11.5px; font-weight:500; color:#777; text-align:center; cursor:pointer; border-bottom:2px solid transparent; transition:all 0.2s; }
.ftab:hover { color:var(--accent); }
.ftab.active { color:var(--primary); border-bottom-color:var(--accent); font-weight:700; }

.form-body { flex:1; overflow-y:auto; padding:14px; }
.form-body::-webkit-scrollbar { width:4px; }
.form-body::-webkit-scrollbar-thumb { background:var(--border); border-radius:4px; }
.tab-pane { display:none; }
.tab-pane.active { display:block; }

/* ══ INPUT FORM — card style (only this block changed) ══ */
.input-form-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.input-form-label { font-size:11px; font-weight:700; color:#777; text-transform:uppercase; letter-spacing:0.5px; }
.ic { background:white; border:1px solid var(--border); border-radius:11px; padding:14px 14px 14px 17px; margin-bottom:10px; position:relative; animation:fadeUp1 0.25s ease forwards; opacity:0; }
.ic-bar { position:absolute; left:0; top:0; bottom:0; width:3px; border-radius:11px 0 0 11px; background:var(--accent); }
.ic-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.ic-who { display:flex; align-items:center; gap:8px; }
.ic-avatar { width:28px; height:28px; border-radius:50%; background:#e5ebf8; color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
.ic-name { font-size:12px; font-weight:700; color:#333; }
.ic-role { font-size:10px; color:#777; margin-top:1px; }
.ic-remove { width:24px; height:24px; border-radius:6px; border:1px solid #fca5a5; background:#fff5f5; color:#ef4444; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; transition:all 0.2s; flex-shrink:0; }
.ic-remove:hover { background:#fee2e2; }
.ic-fields { display:flex; flex-direction:column; gap:9px; }
.ic-field-row { display:flex; gap:8px; }
.ic-field { display:flex; flex-direction:column; gap:4px; flex:1; }
.ic-field label { font-size:10px; font-weight:700; color:#777; text-transform:uppercase; letter-spacing:0.4px; }
.ic-field input, .ic-field textarea {
    width:100%; padding:7px 10px; border:1px solid var(--border); border-radius:7px;
    font-family:inherit; font-size:12px; color:#333; background:#f5f7fd;
    transition:border-color 0.2s, background 0.2s; outline:none;
}
.ic-field input:focus, .ic-field textarea:focus { border-color:var(--accent); background:white; box-shadow:0 0 0 3px rgba(74,111,209,0.1); }
.ic-field input[readonly] { background:#eef1f8; color:#777; cursor:default; }
.ic-field textarea { resize:vertical; min-height:72px; line-height:1.6; }
.ic-feedback-wrap { background:#f5f7fd; border-radius:7px; border-left:3px solid var(--border); padding:2px 0; }
.ic-feedback-wrap textarea { border:none; background:transparent; border-radius:0; padding:8px 10px; }
.ic-feedback-wrap textarea:focus { border:none; background:transparent; box-shadow:none; }
.input-submit-strip { display:flex; align-items:center; justify-content:space-between; background:white; border:1px solid var(--border); border-radius:11px; padding:10px 14px; margin-top:4px; }
.input-submit-strip span { font-size:11px; color:#777; }
.ib-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; border:none; font-family:inherit; font-size:12px; font-weight:700; cursor:pointer; transition:all 0.2s; }
.ib-btn.add { background:var(--light); border:1px solid var(--border); color:var(--primary); }
.ib-btn.add:hover { background:#e5ebf8; }
.ib-btn.add svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2.5; }
.ib-btn.submit { background:var(--accent); color:white; }
.ib-btn.submit:hover { background:var(--primary); }
.ib-btn.submit svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2.5; }

/* ── Comment Cards ── */
.rg { margin-bottom:18px; }
.rg-header { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.rg-pill { background:var(--primary); color:white; font-size:10px; font-weight:800; padding:3px 10px; border-radius:20px; letter-spacing:0.4px; white-space:nowrap; }
.rg-line { flex:1; height:1px; background:linear-gradient(90deg,var(--border),transparent); }
.rg-ct { font-size:11px; color:#777; white-space:nowrap; }
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
.loc-chip strong { color:#333; font-weight:600; margin-right:2px; }
.cc-feedback { font-size:12px; color:#333; line-height:1.6; background:#f5f7fd; border-radius:7px; padding:9px 11px; border-left:3px solid var(--border); margin-bottom:8px; }
.cc-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px; }
.cc-footer-left { display:flex; align-items:center; gap:6px; }
.rel-badge { display:flex; align-items:center; gap:4px; font-size:10px; color:#155724; background:#d4edda; padding:2px 8px; border-radius:20px; }
.rel-badge svg { width:10px; height:10px; stroke:currentColor; fill:none; stroke-width:2.5; }
.cc-status-row { display:flex; align-items:center; gap:5px; }
.cc-status-row select { font-size:10px; border:1px solid var(--border); border-radius:5px; padding:3px 6px; font-family:inherit; color:#333; background:white; }
.cc-save-btn { padding:3px 10px; font-size:10px; font-weight:700; background:var(--accent); color:white; border:none; border-radius:5px; cursor:pointer; font-family:inherit; transition:background 0.2s; }
.cc-save-btn:hover { background:var(--primary); }
.cc-save-btn:disabled { background:#ccc; cursor:default; }
.comments-loading { display:flex; align-items:center; justify-content:center; padding:30px; flex-direction:column; gap:12px; }
.comments-loading p { font-size:12px; color:#777; }

/* Summary stats */
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

/* Modal Footer */
.modal-footer1 { padding:11px 22px; background:white; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.mf-text { font-size:11px; color:#777; }
.mf-actions { display:flex; gap:7px; }
.mf-btn { padding:6px 14px; border-radius:7px; font-family:inherit; font-size:12px; font-weight:600; cursor:pointer; border:none; transition:all 0.2s; }
.mf-btn.outline { background:transparent; border:1px solid var(--border); color:#333; }
.mf-btn.outline:hover { border-color:var(--accent); color:var(--accent); }
.mf-btn.primary { background:var(--accent); color:white; }
.mf-btn.primary:hover { background:var(--primary); }

/* Minutes modal */
#minutesOverlay .modal-body { background:#21242d; }

@keyframes spin { 0%{transform:rotate(0deg)}100%{transform:rotate(360deg)} }
.spinner { border:4px solid rgba(0,0,0,0.1); width:40px; height:40px; border-radius:50%; border-left-color:var(--accent); animation:spin 1s linear infinite; margin:0 auto; }
</style>
</head>
<body>
<div class="container">
    <header class="header">
        <div class="logo-container">
            <img src="../../../assets/logo.png" alt="SMCC Logo">
            <div class="logo">Thesis Routing System</div>
        </div>
    </header>

    <div class="top-bar">
        <div class="homepage"><a href="../homepage.php">Home Page</a></div>
        <div class="user-info">
            <div class="routeNo" style="margin-right:20px;">Proposal - Route 3</div>
            <div class="vl"></div>
            <span class="role">Adviser:</span>
            <span class="user-name"><?= htmlspecialchars($fullname) ?></span>
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
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
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
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
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
            <div class="logout"><a href="../../../logout.php">Logout</a></div>
        </nav>

        <!-- CONTENT -->
        <div class="content" id="content-area">
            <?php if (isset($advisorNotification)): ?>
            <div style="background-color:#ffe6e6;border-left:4px solid #ff3333;padding:15px;margin-bottom:20px;border-radius:4px;">
                <h4 style="margin-top:0;color:#d32f2f;">Important Workflow Requirement</h4>
                <p><?= $advisorNotification ?></p>
                <p><strong>Note:</strong> Panel members will only be able to see and review the document after you have submitted your feedback.</p>
            </div>
            <?php endif; ?>

            <div class="search-container">
                <input type="text" id="searchInput" class="search-box" placeholder="Search by leader name..." onkeyup="searchTable()">
            </div>

            <?php
            $query = "
                SELECT r3.docuRoute3, r3.student_id, r3.route3_id, r3.department,
                       r3.group_number, r3.controlNo, r3.fullname, r3.title
                FROM route3proposal_files r3
                WHERE r3.adviser_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $adviser_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0):
            ?>
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
                <?php while ($row = $result->fetch_assoc()):
                    $filePath  = htmlspecialchars($row['docuRoute3'], ENT_QUOTES);
                    $sid       = htmlspecialchars($row['student_id'], ENT_QUOTES);
                    $rid       = htmlspecialchars($row['route3_id'], ENT_QUOTES);
                    $groupNo   = htmlspecialchars($row['group_number'], ENT_QUOTES);
                    $controlNo = htmlspecialchars($row['controlNo'], ENT_QUOTES);
                    $fullName  = htmlspecialchars($row['fullname'], ENT_QUOTES);
                    $title     = htmlspecialchars($row['title'], ENT_QUOTES);

                    // Get minutes from route1proposal_files
                    $mq = $conn->prepare("SELECT minutes FROM route1proposal_files WHERE student_id = ?");
                    $mq->bind_param("s", $sid);
                    $mq->execute();
                    $mr = $mq->get_result();
                    $minutes = '';
                    if ($mr->num_rows > 0) {
                        $mrow    = $mr->fetch_assoc();
                        $minutes = $mrow['minutes'] ? htmlspecialchars($mrow['minutes'], ENT_QUOTES) : '';
                    }
                    $mq->close();
                ?>
                <tr>
                    <td><?= $controlNo ?></td>
                    <td><?= $fullName ?></td>
                    <td><?= $groupNo ?></td>
                    <td><?= $title ?></td>
                    <td><?= $minutes ? '<span style="color:green;">Available</span>' : '<span style="color:red;">Not Available</span>' ?></td>
                    <td style="text-align:center;">
                        <button class="view-button" onclick="viewFile('<?= $filePath ?>','<?= $sid ?>','<?= $rid ?>','<?= addslashes($controlNo) ?>','<?= addslashes($fullName) ?>','<?= addslashes($groupNo) ?>','<?= addslashes($title) ?>')">View</button>
                        <?php if ($minutes): ?>
                        <button class="view-button" onclick="viewMinutes('<?= $minutes ?>')">View Minutes</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No files uploaded yet.</p>
            <?php endif; $stmt->close(); ?>
        </div>
    </div>
</div>

<!-- ═══ VIEWER MODAL ═══ -->
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
                <button class="mh-btn" onclick="doneEditing()" style="background:#22c55e;color:white;">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Mark as Done
                </button>
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
                    <div class="ftab active" onclick="switchTab('form')">Input Form</div>
                    <div class="ftab" onclick="switchTab('submitted')">Submitted Forms</div>
                    <div class="ftab" onclick="switchTab('summary')">Summary</div>
                </div>

                <div class="form-body">

                    <!-- TAB: Input Form -->
                    <div class="tab-pane active" id="pane-form">
                        <form method="POST" id="routingFormEl">
                            <input type="hidden" name="docuRoute3"  id="hid_docuRoute3">
                            <input type="hidden" name="student_id"  id="hid_student_id">
                            <input type="hidden" name="route3_id"   id="hid_route3_id">
                            <input type="hidden" name="status"      value="Pending">

                            <!-- ↓ ONLY THIS BLOCK CHANGED — was .routing-inner grid ↓ -->
                            <div class="input-form-header">
                                <span class="input-form-label">Add Feedback</span>
                                <button type="button" class="ib-btn add" onclick="addFormRow()">
                                    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add Row
                                </button>
                            </div>
                            <div id="routingRowsContainer"></div>
                            <div class="input-submit-strip">
                                <span id="rowCountLabel">1 row ready to submit</span>
                                <button type="button" class="ib-btn submit" onclick="submitRoutingForm()">
                                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Submit Feedback
                                </button>
                            </div>
                            <!-- ↑ END changed block ↑ -->

                        </form>
                    </div>

                    <!-- TAB: Submitted Forms -->
                    <div class="tab-pane" id="pane-submitted">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <span style="font-size:11px;font-weight:700;color:#777;text-transform:uppercase;letter-spacing:0.5px;">Submitted Feedback</span>
                            <button type="button" class="ri-btn toggle" id="toggleFormsBtn" onclick="toggleForms()" style="background:var(--accent);color:white;border:none;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;">Hide</button>
                        </div>
                        <div class="comments-loading" id="commentsLoader2">
                            <div class="pdf-spinner" style="border-top-color:var(--accent);"></div>
                            <p>Loading feedback…</p>
                        </div>
                        <div id="submittedFormsContainer"></div>
                        <div id="noFormsMessage" style="margin-top:10px;color:gray;font-size:12px;text-align:center;"></div>
                    </div>

                    <!-- TAB: Summary -->
                    <div class="tab-pane" id="pane-summary">
                        <div class="stat-grid">
                            <div class="stat-box"><div class="stat-num" id="sumTotal">0</div><div class="stat-lbl">Total Comments</div></div>
                            <div class="stat-box"><div class="stat-num" id="sumRoutes">0</div><div class="stat-lbl">Routes Done</div></div>
                            <div class="stat-box"><div class="stat-num" id="sumApproved">0</div><div class="stat-lbl">Approved</div></div>
                            <div class="stat-box"><div class="stat-num" id="sumPending">0</div><div class="stat-lbl">Pending</div></div>
                        </div>
                        <div class="sum-card">
                            <div class="sum-card-title">Document Details</div>
                            <div class="sum-row"><span class="sum-lbl">Control No.</span><span class="sum-val" id="sumControlNo">–</span></div>
                            <div class="sum-row"><span class="sum-lbl">Leader</span><span class="sum-val" id="sumLeader">–</span></div>
                            <div class="sum-row"><span class="sum-lbl">Group No.</span><span class="sum-val" id="sumGroup">–</span></div>
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
const adviserName = <?= json_encode($fullname) ?>;
let currentFormData  = [];
let formsVisible     = true;
let currentStudentId = null;
let currentRoute3Id  = null;
let currentFilePath  = null;
let rowCount         = 0; // ← added for card row tracking

// ── Open viewer
function viewFile(filePath, student_id, route3_id, controlNo, leader, groupNo, title) {
    currentStudentId = student_id;
    currentRoute3Id  = route3_id;
    currentFilePath  = filePath;

    document.getElementById('modalDocTitle').textContent    = title || 'Research Proposal';
    document.getElementById('pdfFilenameLabel').textContent = filePath.split('/').pop();
    document.getElementById('modalControlNo').textContent   = controlNo;
    document.getElementById('modalGroupNo').textContent     = groupNo;
    document.getElementById('sumControlNo').textContent     = controlNo;
    document.getElementById('sumLeader').textContent        = leader;
    document.getElementById('sumGroup').textContent         = groupNo;
    const yr = new Date().getFullYear();
    document.getElementById('modalSchoolYear').textContent  = 'AY ' + yr + '–' + (yr + 1);

    document.getElementById('hid_docuRoute3').value  = filePath;
    document.getElementById('hid_student_id').value  = student_id;
    document.getElementById('hid_route3_id').value   = route3_id;

    document.getElementById('pdfLoader').classList.remove('hidden');
    document.getElementById('pdfFrame').src = filePath;

    buildInputRows();

    const overlay = document.getElementById('viewerOverlay');
    overlay.style.display = 'flex';
    requestAnimationFrame(() => overlay.classList.add('open'));
    switchTab('form');

    loadAllForms(student_id);
}

// ↓ ONLY THESE FUNCTIONS CHANGED — were buildInputRows/addFormRow ↓
function buildInputRows() {
    rowCount = 0;
    document.getElementById('routingRowsContainer').innerHTML = '';
    addFormRow();
}

function addFormRow() {
    rowCount++;
    const today    = new Date().toISOString().split('T')[0];
    const initials = adviserName.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
    const div = document.createElement('div');
    div.className = 'ic';
    div.innerHTML = `
        <div class="ic-bar"></div>
        <div class="ic-top">
            <div class="ic-who">
                <div class="ic-avatar">${initials}</div>
                <div><div class="ic-name">${e(adviserName)}</div><div class="ic-role">Thesis Adviser · Row ${rowCount}</div></div>
            </div>
            ${rowCount > 1 ? `<button type="button" class="ic-remove" onclick="removeRow(this)">×</button>` : ''}
        </div>
        <div class="ic-fields">
            <div class="ic-field-row">
                <div class="ic-field" style="flex:1.2"><label>Date Submitted</label><input type="text" name="dateSubmitted[]" value="${today}" readonly></div>
                <div class="ic-field" style="flex:0.8"><label>Chapter</label><input type="text" name="chapter[]" placeholder="e.g. 1" required></div>
            </div>
            <div class="ic-field">
                <label>Feedback / Comment</label>
                <div class="ic-feedback-wrap"><textarea name="feedback[]" placeholder="Write your feedback here…" required oninput="autoGrow(this)"></textarea></div>
            </div>
            <div class="ic-field-row">
                <div class="ic-field"><label>Page No.</label><input type="number" name="pageNumber[]" placeholder="–" required></div>
                <div class="ic-field"><label>Para No.</label><input type="number" name="paragraphNumber[]" placeholder="–" required></div>
                <div class="ic-field" style="flex:1.4"><label>Date Released</label><input type="date" name="dateReleased[]" value="${today}" required></div>
            </div>
            <div class="ic-field-row">
                <div class="ic-field"><label>Adviser Name</label><input type="text" name="adviserName[]" value="${e(adviserName)}" readonly></div>
                <div class="ic-field"><label>Route</label><input type="text" name="routeNumber[]" value="Route 3" readonly></div>
            </div>
        </div>`;
    document.getElementById('routingRowsContainer').appendChild(div);
    updateRowCount();
}

function removeRow(btn) {
    btn.closest('.ic').remove();
    rowCount = Math.max(0, rowCount - 1);
    document.querySelectorAll('#routingRowsContainer .ic').forEach((card, i) => {
        const roleEl = card.querySelector('.ic-role');
        if (roleEl) roleEl.textContent = `Thesis Adviser · Row ${i + 1}`;
    });
    updateRowCount();
}

function updateRowCount() {
    const cnt = document.querySelectorAll('#routingRowsContainer .ic').length;
    document.getElementById('rowCountLabel').textContent = cnt + ' row' + (cnt !== 1 ? 's' : '') + ' ready to submit';
}
// ↑ END changed functions ↑

function submitRoutingForm() {
    document.getElementById('routingFormEl').submit();
}

function doneEditing() {
    const filePath   = document.getElementById('hid_docuRoute3').value;
    const student_id = document.getElementById('hid_student_id').value;
    const route3_id  = document.getElementById('hid_route3_id').value;

    if (confirm("Are you sure you want to mark this as done without additional comments? This indicates you've reviewed the document and have no further feedback.")) {
        const formData = new FormData();
        formData.append('dateSubmitted[]', new Date().toISOString().split('T')[0]);
        formData.append('chapter[]', 'All');
        formData.append('feedback[]', 'No additional comments. Document reviewed.');
        formData.append('paragraphNumber[]', '0');
        formData.append('pageNumber[]', '0');
        formData.append('adviserName[]', adviserName);
        formData.append('dateReleased[]', new Date().toISOString().split('T')[0]);
        formData.append('routeNumber[]', 'Route 3');
        formData.append('docuRoute3', filePath);
        formData.append('route3_id', route3_id);
        formData.append('student_id', student_id);
        formData.append('status', 'Approved');

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(() => {
                alert('Document marked as reviewed with no additional comments.');
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Document marked as reviewed with no additional comments.');
                window.location.reload();
            });
    }
}

function closeViewer() {
    const overlay = document.getElementById('viewerOverlay');
    overlay.classList.remove('open');
    setTimeout(() => { overlay.style.display = 'none'; document.getElementById('pdfFrame').src = ''; }, 320);
}

function hidePdfLoader() { document.getElementById('pdfLoader').classList.add('hidden'); }

// ── Tabs
function switchTab(name) {
    const names = ['form', 'submitted', 'summary'];
    document.querySelectorAll('.ftab').forEach((t, i) => t.classList.toggle('active', names[i] === name));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.toggle('active', p.id === 'pane-' + name));
}

// ── Load submitted forms as comment cards
function loadAllForms(student_id) {
    const container = document.getElementById('submittedFormsContainer');
    const noMsg     = document.getElementById('noFormsMessage');
    const loader    = document.getElementById('commentsLoader2');
    container.innerHTML = '';
    noMsg.textContent   = '';
    if (loader) loader.style.display = 'flex';

    fetch('route3get_all_forms.php?student_id=' + encodeURIComponent(student_id))
        .then(r => { if (!r.ok) throw new Error('Network response was not ok: ' + r.status); return r.json(); })
        .then(data => {
            if (loader) loader.style.display = 'none';
            currentFormData = Array.isArray(data) ? data : [];
            container.innerHTML = '';
            if (!currentFormData.length) {
                noMsg.innerHTML = `<div style="text-align:center;padding:30px 20px;">
                    <svg viewBox="0 0 24 24" style="width:36px;height:36px;stroke:#ccc;fill:none;stroke-width:1.5;margin-bottom:10px;display:block;margin-left:auto;margin-right:auto;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                    <p style="font-size:13px;color:#777;">No feedback submitted yet.</p></div>`;
                renderSummary([]);
                return;
            }
            noMsg.textContent = '';

            // Group by routeNumber
            const groups = {};
            currentFormData.forEach(row => {
                const rn = row.routeNumber || 'Route 3';
                if (!groups[rn]) groups[rn] = [];
                groups[rn].push(row);
            });

            let html = '';
            Object.entries(groups).forEach(([routeName, rows]) => {
                html += `<div class="rg">
                    <div class="rg-header">
                        <span class="rg-pill">${e(routeName)}</span>
                        <div class="rg-line"></div>
                        <span class="rg-ct">${rows.length} comment${rows.length !== 1 ? 's' : ''}</span>
                    </div>`;

                rows.forEach((row, i) => {
                    const formId      = row.id;
                    const statusValue = (row.status || 'Pending').trim();
                    const isAdviser   = !!row.adviser_name;
                    const role        = isAdviser ? 'adviser' : 'panel';
                    const name        = isAdviser ? row.adviser_name : (row.panel_name || 'Reviewer');
                    const initials    = name.split(',')[0].trim().substring(0, 2).toUpperCase();

                    html += `<div class="cc ${role}" style="animation-delay:${i * 0.05}s">
                        <div class="cc-bar ${role}"></div>
                        <div class="cc-top">
                            <div class="cc-who">
                                <div class="cc-avatar ${role}">${e(initials)}</div>
                                <div>
                                    <div class="cc-name">${e(name)}</div>
                                    <div class="cc-role">${isAdviser ? 'Thesis Adviser' : 'Panel Member'}</div>
                                </div>
                            </div>
                            <div class="cc-date">${e(row.date_submitted || '—')}</div>
                        </div>
                        <div class="cc-locs">
                            ${row.chapter         ? `<div class="loc-chip"><strong>Chapter</strong>${e(String(row.chapter))}</div>` : ''}
                            ${row.page_number     ? `<div class="loc-chip"><strong>Page No.</strong>${e(String(row.page_number))}</div>` : ''}
                            ${row.paragraph_number ? `<div class="loc-chip"><strong>Para No.</strong>${e(String(row.paragraph_number))}</div>` : ''}
                        </div>
                        <div class="cc-feedback">${e(row.feedback || 'No feedback provided.')}</div>
                        <div class="cc-footer">
                            <div class="cc-footer-left">
                                ${row.date_released
                                    ? `<div class="rel-badge"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Released ${e(row.date_released)}</div>`
                                    : '<span style="font-size:10px;color:#777;">Not yet released</span>'}
                            </div>
                            <div class="cc-status-row">
                                <select id="statusSelect_${formId}" onchange="enableSaveButton(${formId})" data-student-id="${e(student_id)}">
                                    <option value="Pending"  ${statusValue === 'Pending'  ? 'selected' : ''}>Pending</option>
                                    <option value="Approved" ${statusValue === 'Approved' ? 'selected' : ''}>Approved</option>
                                </select>
                                <button class="cc-save-btn" id="saveButton_${formId}" onclick="saveStatus(${formId})" disabled>Save</button>
                            </div>
                        </div>
                    </div>`;
                });
                html += '</div>';
            });

            container.innerHTML = html;
            formsVisible = true;
            document.getElementById('toggleFormsBtn').textContent = 'Hide';
            renderSummary(currentFormData);
        })
        .catch(error => {
            if (loader) loader.style.display = 'none';
            console.error('Error fetching forms:', error);
            noMsg.textContent = 'Error loading feedback: ' + error.message;
            container.innerHTML = '';
        });
}

function toggleForms() {
    const container = document.getElementById('submittedFormsContainer');
    const btn       = document.getElementById('toggleFormsBtn');
    const loader    = document.getElementById('commentsLoader2');
    if (formsVisible) {
        container.innerHTML = '';
        document.getElementById('noFormsMessage').textContent = '';
        if (loader) loader.style.display = 'none';
        btn.textContent = 'Show';
        formsVisible = false;
    } else {
        btn.textContent = 'Hide';
        loadAllForms(currentStudentId);
    }
}

function renderSummary(data) {
    const routes = new Set();
    let approved = 0, pending = 0;
    data.forEach(r => {
        if (r.routeNumber) routes.add(r.routeNumber);
        if ((r.status || '').trim() === 'Approved') approved++;
        else pending++;
    });
    document.getElementById('sumTotal').textContent    = data.length;
    document.getElementById('sumRoutes').textContent   = routes.size;
    document.getElementById('sumApproved').textContent = approved;
    document.getElementById('sumPending').textContent  = pending;
}

// ── Status save
function saveStatus(formId) {
    const statusSelect = document.getElementById(`statusSelect_${formId}`);
    const saveButton   = document.getElementById(`saveButton_${formId}`);
    saveButton.disabled  = true;
    saveButton.textContent = 'Saving...';

    const timeout = setTimeout(() => {
        statusSelect.parentElement.style.backgroundColor = '#e8f5e9';
        alert('Status saved successfully. The student will be notified by email shortly.');
        saveButton.style.display = 'none';
    }, 500);

    fetch('update_form_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: formId, status: statusSelect.value })
    })
    .then(r => r.json())
    .then(data => { if (!data.success) alert(data.message || 'Failed to update status.'); })
    .catch(error => console.error('Error:', error))
    .finally(() => clearTimeout(timeout));
}

function enableSaveButton(formId) {
    document.getElementById(`saveButton_${formId}`).disabled = false;
}

// ── Print
function printForm() {
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    document.body.appendChild(iframe);
    const rows = currentFormData.map(form => {
        let by = form.adviser_name ? form.adviser_name + ' – Adviser' : (form.panel_name ? form.panel_name + ' – Panel' : 'N/A');
        return `<tr>
            <td>${e(form.date_submitted||'—')}</td>
            <td>${e(String(form.chapter||'—'))}</td>
            <td>${e(form.feedback||'—')}</td>
            <td>${e(String(form.paragraph_number||'—'))}</td>
            <td>${e(String(form.page_number||'—'))}</td>
            <td>${e(by)}</td>
            <td>${e(form.date_released||'—')}</td>
            <td>${e(form.routeNumber||'—')}</td>
            <td>${e(form.status||'—')}</td>
        </tr>`;
    }).join('');
    const doc = `<!DOCTYPE html><html><head><title>Routing Monitoring Form – Route 3</title>
    <style>body{font-family:Arial,sans-serif;padding:20px;font-size:12px;}h2{color:#4366b3;text-align:center;}h3{text-align:center;font-size:13px;color:#555;margin-bottom:20px;}table{width:100%;border-collapse:collapse;}th{background:#4366b3;color:white;padding:8px;font-size:11px;text-align:left;}td{padding:7px 8px;border-bottom:1px solid #eee;font-size:11px;}@media print{@page{size:landscape;margin:1cm}}</style>
    </head><body>
    <h2>SAINT MICHAEL COLLEGE OF CARAGA</h2>
    <h3>Research &amp; Instructional Innovation Department — Routing Monitoring Form (Route 3)</h3>
    <table><thead><tr><th>Date</th><th>Chapter</th><th>Feedback</th><th>Para No.</th><th>Page No.</th><th>Submitted By</th><th>Released</th><th>Route</th><th>Status</th></tr></thead>
    <tbody>${rows || '<tr><td colspan="9" style="text-align:center;color:#999;">No records found.</td></tr>'}</tbody></table>
    </body></html>`;
    iframe.contentWindow.document.open();
    iframe.contentWindow.document.write(doc);
    iframe.contentWindow.document.close();
    iframe.onload = () => { setTimeout(() => { iframe.contentWindow.print(); setTimeout(() => document.body.removeChild(iframe), 500); }, 300); };
}

// ── Minutes
function viewMinutes(minutesPath) {
    if (!minutesPath) { alert('No minutes available for this document.'); return; }
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

// ── ESC / backdrop close
document.getElementById('viewerOverlay').addEventListener('click', ev => { if (ev.target === ev.currentTarget) closeViewer(); });
document.getElementById('minutesOverlay').addEventListener('click', ev => { if (ev.target === ev.currentTarget) closeMinutes(); });
document.addEventListener('keydown', ev => { if (ev.key === 'Escape') { closeViewer(); closeMinutes(); } });

// ── autoGrow
function autoGrow(el) { el.style.height = 'auto'; el.style.height = Math.max(el.scrollHeight, 32) + 'px'; }

// ── Search
function searchTable() {
    const filter = document.getElementById('searchInput').value.toUpperCase();
    const tbody  = document.querySelector('table tbody');
    if (!tbody) return;
    Array.from(tbody.rows).forEach(row => {
        const cell = row.cells[1];
        row.style.display = cell && cell.textContent.toUpperCase().includes(filter) ? '' : 'none';
    });
}

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

<?php if ($showModal): ?>
window.addEventListener('load', () => {
    viewFile("<?= addslashes($lastFilePath) ?>","<?= addslashes($student_id) ?>","<?= addslashes($route3_id) ?>","","","","");
});
<?php endif; ?>

function e(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>
</body>
</html>