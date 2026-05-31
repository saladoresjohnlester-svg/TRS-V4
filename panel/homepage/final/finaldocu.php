<?php
session_start();

if (!isset($_SESSION['panel_id'])) {
    header("Location: ../../../logout.php");
    exit;
}

include '../../../connection.php';

// Fetch departments
$departments = [];
$query = "SELECT DISTINCT department FROM student";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) $departments[] = $row['department'];
}

$selectedDepartment = $_POST['department'] ?? '';
$panel_id           = $_SESSION['panel_id'];
$panel_fullname     = $_SESSION['fullname'] ?? 'Panelist';

$stmt = $conn->prepare("SELECT student_id, finaldocu_id FROM finaldocufinal_files WHERE panel1_id=? OR panel2_id=? OR panel3_id=? OR panel4_id=? OR panel5_id=? OR panel6_id=?");
if ($stmt === false) die("Error preparing the query: " . $conn->error);
$stmt->bind_param("ssssss", $panel_id, $panel_id, $panel_id, $panel_id, $panel_id, $panel_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row          = $result->fetch_assoc();
    $student_id   = $row['student_id'];
    $finaldocu_id = $row['finaldocu_id'];

    $adviserCheck = $conn->prepare("SELECT COUNT(*) as count FROM final_monitoring_form WHERE finaldocu_id=? AND adviser_id IS NOT NULL");
    $adviserCheck->bind_param("i", $finaldocu_id);
    $adviserCheck->execute();
    $finaldocu_advised = ($adviserCheck->get_result()->fetch_assoc()['count'] > 0);
    $adviserCheck->close();

    if (!$finaldocu_advised) {
        $route3Check = $conn->prepare("SELECT route3_id FROM route3final_files WHERE student_id=?");
        $route3Check->bind_param("s", $student_id);
        $route3Check->execute();
        $route3Result = $route3Check->get_result();
        if ($route3Result && $route3Row = $route3Result->fetch_assoc()) {
            $route3_id = $route3Row['route3_id'];
            $route3ApprovalCheck = $conn->prepare("SELECT COUNT(*) as count FROM final_monitoring_form WHERE route3_id=? AND adviser_id IS NOT NULL");
            $route3ApprovalCheck->bind_param("i", $route3_id);
            $route3ApprovalCheck->execute();
            $route3_advised = ($route3ApprovalCheck->get_result()->fetch_assoc()['count'] > 0);
            $route3ApprovalCheck->close();
            if ($route3_advised) $finaldocu_advised = true;
        }
        $route3Check->close();
    }
    if (!$finaldocu_advised) {
        echo "<script>alert('The adviser must review this document first before panel members can access it.');</script>";
    }
} else {
    $student_id   = null;
    $finaldocu_id = null;
}

$currentPanelPosition = '';
$stmtPos = $conn->prepare("SELECT position FROM panel WHERE panel_id = ?");
$stmtPos->bind_param("i", $_SESSION['panel_id']);
$stmtPos->execute();
$stmtPos->bind_result($currentPanelPosition);
$stmtPos->fetch();
$stmtPos->close();

// Unique to finaldocu: checks all routes approval status
function checkAllRoutesApproved($conn, $student_id) {
    $routes = ['route1' => 'Route 1', 'route2' => 'Route 2', 'route3' => 'Route 3'];
    $result = [];
    $all_approved = true;

    foreach ($routes as $key => $label) {
        $totalStmt = $conn->prepare("SELECT COUNT(*) as c FROM final_monitoring_form WHERE student_id=? AND (routeNumber=? OR routeNumber=? OR routeNumber=?)");
        $u = strtoupper($label); $l = strtolower($label);
        $totalStmt->bind_param("ssss", $student_id, $label, $l, $u);
        $totalStmt->execute();
        $total = (int)$totalStmt->get_result()->fetch_assoc()['c'];
        $totalStmt->close();

        $appStmt = $conn->prepare("SELECT COUNT(*) as c FROM final_monitoring_form WHERE student_id=? AND (routeNumber=? OR routeNumber=? OR routeNumber=?) AND (status='Approved' OR status='approved' OR status='APPROVED')");
        $appStmt->bind_param("ssss", $student_id, $label, $l, $u);
        $appStmt->execute();
        $approved = (int)$appStmt->get_result()->fetch_assoc()['c'];
        $appStmt->close();

        $is_approved = ($total > 0 && $approved === $total);
        $result[$key] = $is_approved;
        $result[$key . '_counts'] = ['total' => $total, 'approved' => $approved];
        if ($total > 0 && !$is_approved) $all_approved = false;
    }
    $result['all_approved'] = $all_approved;
    return $result;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Final Document – Thesis Routing System</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.4.2/mammoth.browser.min.js"></script>
<style>
:root {
    --primary: #4366b3;
    --primary-light: #0a3885;
    --accent: #4a6fd1;
    --light: #f5f7fd;
    --dark: #333;
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
.dropdown-container { display:flex; gap:8px; margin-left:15px; }
.dropdown-container select { padding:0.5rem; border-radius:4px; border:1px solid var(--border); background-color:white; font-family:inherit; cursor:pointer; }
.user-info { display:flex; align-items:center; }
.vl { border-left:1px solid var(--border); height:20px; margin:0 10px; }
.role { font-weight:600; margin-right:5px; color:var(--primary); }
.main-content { display:flex; flex:1; }
.sidebar { width:250px; background-color:white; padding:1.5rem 0; box-shadow:2px 0 5px rgba(0,0,0,0.05); display:flex; flex-direction:column; justify-content:space-between; border-right:1px solid var(--border); position:sticky; top:0; height:100vh; overflow-y:auto; }
.sidebar a { display:block; padding:0.75rem 1.5rem; color:var(--dark); text-decoration:none; transition:all 0.3s; border-left:3px solid transparent; }
.sidebar a:hover { background-color:var(--light); color:var(--accent); border-left:3px solid var(--accent); }
.logout { position:sticky; bottom:0; background-color:white; padding:1rem 1.5rem; border-top:1px solid var(--border); }
.logout a { display:flex; align-items:center; justify-content:center; background-color:#f0f0f0; color:#555; padding:0.75rem; border-radius:8px; text-decoration:none; font-weight:500; transition:all 0.3s; }
.logout a:hover { background-color:#e0e0e0; transform:translateY(-2px); }
.content { flex:1; padding:2rem; overflow:auto; }
table { width:100%; border-collapse:collapse; margin-top:1rem; background-color:white; box-shadow:var(--shadow); border-radius:8px; overflow:hidden; }
th,td { padding:1rem; text-align:left; border-bottom:1px solid var(--border); }
th { background-color:var(--primary); color:white; font-weight:600; }
tr:nth-child(even) { background-color:rgba(0,0,0,0.02); }
tr:hover { background-color:rgba(0,0,0,0.03); }
button { padding:0.5rem 1rem; border:none; border-radius:4px; cursor:pointer; font-weight:500; transition:all 0.3s; }
.view-button { background-color:var(--accent); color:white; margin-right:0.5rem; }
.view-button:hover { background-color:var(--primary); }
.action-label { text-align:center; }
.search-container { margin:15px 0; width:100%; display:flex; justify-content:flex-start; align-items:center; }
.search-box { padding:8px 12px; border:1px solid #ccc; border-radius:4px; width:300px; font-size:14px; margin-bottom:15px; }

/* ─── Sidebar nav ─── */
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

/* ═══ SLEEK VIEWER MODAL ═══ */
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
.pdf-wrap .docx-content { width:100%; height:100%; overflow-y:auto; background:white; padding:2rem; }
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

/* Comment Cards */
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

/* Summary stats */
.stat-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; margin-bottom:12px; }
.stat-box { background:white; border:1px solid var(--border); border-radius:10px; padding:13px; text-align:center; }
.stat-num { font-size:26px; font-weight:700; color:var(--primary); line-height:1; margin-bottom:3px; }
.stat-lbl { font-size:10px; color:#777; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; }

/* Route progress */
.route-progress-card { background:white; border:1px solid var(--border); border-radius:10px; padding:14px; margin-bottom:10px; }
.route-progress-title { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:#777; margin-bottom:12px; }
.rp-row { display:flex; align-items:center; gap:10px; margin-bottom:9px; }
.rp-label { font-size:11px; color:#555; width:55px; flex-shrink:0; }
.rp-bar-wrap { flex:1; background:#f5f7fd; border-radius:20px; height:7px; overflow:hidden; }
.rp-bar { height:100%; border-radius:20px; background:var(--accent); transition:width 0.6s ease; }
.rp-bar.complete { background:#22c55e; }
.rp-frac { font-size:10px; color:#777; width:32px; text-align:right; flex-shrink:0; }
.rp-badge { font-size:9px; font-weight:700; padding:2px 6px; border-radius:20px; flex-shrink:0; }
.rp-badge.ok { background:#d4edda; color:#155724; }
.rp-badge.pend { background:#fff3cd; color:#856404; }

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

.comments-loading { display:flex; align-items:center; justify-content:center; padding:30px; flex-direction:column; gap:12px; }
.comments-loading p { font-size:12px; color:#777; }
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
        <div class="dropdown-container">
            <?php
            $checkPanel1Query = "SELECT COUNT(*) as count FROM finaldocufinal_files WHERE panel1_id=? OR panel2_id=? OR panel3_id=? OR panel4_id=? OR panel5_id=? OR panel6_id=?";
            $checkStmt = $conn->prepare($checkPanel1Query);
            $checkStmt->bind_param("ssssss", $panel_id, $panel_id, $panel_id, $panel_id, $panel_id, $panel_id);
            $checkStmt->execute();
            $isPanel1 = ($checkStmt->get_result()->fetch_assoc()['count'] > 0);
            if ($isPanel1): ?>
            <form method="POST">
                <select name="department" onchange="this.form.submit()">
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= htmlspecialchars($department) ?>" <?= $selectedDepartment == $department ? 'selected' : '' ?>>
                            <?= htmlspecialchars($department) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <div class="routeNo" style="margin-right:20px;">Final - Final Document</div>
            <div class="vl"></div>
            <span class="role">Panelist:</span>
            <span class="user-name"><?= htmlspecialchars($panel_fullname) ?></span>
        </div>
    </div>

    <div class="main-content">
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
                        <div class="dropdown-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                    </div>
                    <div class="dropdown-content">
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

        <div class="content" id="content-area">
            <div class="search-container">
                <input type="text" id="searchInput" class="search-box" placeholder="Search by leader name..." onkeyup="searchTable()">
            </div>

            <?php
            $query = "SELECT f.finaldocu, f.department, f.student_id, f.finaldocu_id, f.controlNo, f.fullname, f.group_number, f.title
                      FROM finaldocufinal_files f
                      WHERE (f.panel1_id=? OR f.panel2_id=? OR f.panel3_id=? OR f.panel4_id=? OR f.panel5_id=? OR f.panel6_id=?)"
                    . ($selectedDepartment ? " AND f.department=?" : "");
            $stmt = $conn->prepare($query);
            if ($selectedDepartment) {
                $stmt->bind_param("sssssss", $panel_id, $panel_id, $panel_id, $panel_id, $panel_id, $panel_id, $selectedDepartment);
            } else {
                $stmt->bind_param("ssssss", $panel_id, $panel_id, $panel_id, $panel_id, $panel_id, $panel_id);
            }
            $stmt->execute();
            $result           = $stmt->get_result();
            $files_to_display = [];

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $s_id         = $row['student_id'];
                    $fd_id        = $row['finaldocu_id'];
                    $can_display  = false;

                    $check_fd = $conn->prepare("SELECT COUNT(*) as count FROM final_monitoring_form WHERE finaldocu_id=? AND adviser_id IS NOT NULL");
                    $check_fd->bind_param("i", $fd_id);
                    $check_fd->execute();
                    $finaldocu_has_adviser = ($check_fd->get_result()->fetch_assoc()['count'] > 0);
                    $check_fd->close();

                    if ($finaldocu_has_adviser) {
                        $can_display = true;
                    } else {
                        $get_route3 = $conn->prepare("SELECT route3_id FROM route3final_files WHERE student_id=?");
                        $get_route3->bind_param("s", $s_id);
                        $get_route3->execute();
                        $r3_result = $get_route3->get_result();
                        $get_route3->close();
                        if ($r3_result && $r3_row = $r3_result->fetch_assoc()) {
                            $r3_id = $r3_row['route3_id'];
                            $check_r3 = $conn->prepare("SELECT COUNT(*) as count FROM final_monitoring_form WHERE route3_id=? AND adviser_id IS NOT NULL");
                            $check_r3->bind_param("i", $r3_id);
                            $check_r3->execute();
                            $route3_has_adviser = ($check_r3->get_result()->fetch_assoc()['count'] > 0);
                            $check_r3->close();
                            if ($route3_has_adviser) $can_display = true;
                        }
                    }
                    if ($can_display) $files_to_display[] = $row;
                }
            }

            if (count($files_to_display) > 0):
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Control No.</th>
                        <th>Leader</th>
                        <th>Group No.</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th class="action-label">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($files_to_display as $row):
                    $filePath     = htmlspecialchars($row['finaldocu'], ENT_QUOTES);
                    $finaldocu_id = htmlspecialchars($row['finaldocu_id'], ENT_QUOTES);
                    $student_id   = htmlspecialchars($row['student_id'], ENT_QUOTES);
                    $controlNo    = htmlspecialchars($row['controlNo'], ENT_QUOTES);
                    $fullname     = htmlspecialchars($row['fullname'], ENT_QUOTES);
                    $groupNo      = htmlspecialchars($row['group_number'], ENT_QUOTES);
                    $title        = htmlspecialchars($row['title'], ENT_QUOTES);

                    $routeStatus = checkAllRoutesApproved($conn, $student_id);
                    if ($routeStatus['all_approved']) {
                        $statusLabel = 'Complete'; $statusColor = 'green';
                    } else {
                        $hasSome = false;
                        foreach (['route1','route2','route3'] as $rk) {
                            if (($routeStatus[$rk . '_counts']['total'] ?? 0) > 0) { $hasSome = true; break; }
                        }
                        if ($hasSome) { $statusLabel = 'In Progress'; $statusColor = 'orange'; }
                        else          { $statusLabel = 'Pending';     $statusColor = 'red'; }
                    }

                    $rsJson = json_encode($routeStatus);
                ?>
                <tr>
                    <td><?= $controlNo ?></td>
                    <td><?= $fullname ?></td>
                    <td><?= $groupNo ?></td>
                    <td><?= $title ?></td>
                    <td><span style="color:<?= $statusColor ?>;font-weight:bold;"><?= $statusLabel ?></span></td>
                    <td style="text-align:center;">
                        <button class="view-button" onclick="viewFile('<?= addslashes($filePath) ?>','<?= $finaldocu_id ?>','<?= $student_id ?>','<?= addslashes($controlNo) ?>','<?= addslashes($fullname) ?>','<?= addslashes($groupNo) ?>','<?= addslashes($title) ?>',<?= htmlspecialchars($rsJson, ENT_QUOTES) ?>)">View</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="background-color:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin-bottom:20px;border-radius:4px;">
                <h4 style="color:#856404;margin-top:0;">No Files Available for Review</h4>
                <p>No files are available. Route 3 Final must have adviser review before Final Document becomes accessible.</p>
            </div>
            <?php endif; $stmt->close(); ?>
        </div>
    </div>
</div>

<!-- ═══ SLEEK VIEWER MODAL ═══ -->
<div class="modal-overlay" id="viewerOverlay">
    <div class="viewer-modal">
        <div class="modal-header">
            <div class="mh-left">
                <span class="mh-badge">Final · Document</span>
                <div>
                    <div class="mh-title" id="modalDocTitle">Final Defense Document</div>
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
                    <iframe id="pdfFrame" src="" title="Final Document" style="display:none;"></iframe>
                    <div id="docxContent" class="docx-content" style="display:none;"></div>
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
                    <div class="ftab active" onclick="switchTab('submitted')">Submitted Forms</div>
                    <div class="ftab" onclick="switchTab('summary')">Summary</div>
                </div>

                <div class="form-body">

                    <!-- TAB: Submitted Forms -->
                    <div class="tab-pane active" id="pane-submitted">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <span style="font-size:11px;font-weight:700;color:#777;text-transform:uppercase;letter-spacing:0.5px;">Submitted Feedback</span>
                            <button type="button" id="toggleFormsBtn" onclick="toggleForms()" style="background:var(--accent);color:white;border:none;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;">Hide</button>
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
                        <div class="route-progress-card">
                            <div class="route-progress-title">Route Approval Progress</div>
                            <div class="rp-row" id="rpRow1">
                                <span class="rp-label">Route 1</span>
                                <div class="rp-bar-wrap"><div class="rp-bar" id="rpBar1" style="width:0%"></div></div>
                                <span class="rp-frac" id="rpFrac1">0/0</span>
                                <span class="rp-badge pend" id="rpBadge1">Pending</span>
                            </div>
                            <div class="rp-row" id="rpRow2">
                                <span class="rp-label">Route 2</span>
                                <div class="rp-bar-wrap"><div class="rp-bar" id="rpBar2" style="width:0%"></div></div>
                                <span class="rp-frac" id="rpFrac2">0/0</span>
                                <span class="rp-badge pend" id="rpBadge2">Pending</span>
                            </div>
                            <div class="rp-row" id="rpRow3">
                                <span class="rp-label">Route 3</span>
                                <div class="rp-bar-wrap"><div class="rp-bar" id="rpBar3" style="width:0%"></div></div>
                                <span class="rp-frac" id="rpFrac3">0/0</span>
                                <span class="rp-badge pend" id="rpBadge3">Pending</span>
                            </div>
                        </div>
                        <div class="sum-card">
                            <div class="sum-card-title">Document Details</div>
                            <div class="sum-row"><span class="sum-lbl">Control No.</span><span class="sum-val" id="sumControlNo">–</span></div>
                            <div class="sum-row"><span class="sum-lbl">Leader</span><span class="sum-val" id="sumLeader">–</span></div>
                            <div class="sum-row"><span class="sum-lbl">Group No.</span><span class="sum-val" id="sumGroup">–</span></div>
                            <div class="sum-row"><span class="sum-lbl">Overall Status</span><span class="sum-val" id="sumOverall">–</span></div>
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
            </div>
        </div><!-- /modal-body -->
    </div>
</div>

<script>
const panelName            = <?= json_encode($panel_fullname) ?>;
const currentPanelPosition = "<?= htmlspecialchars($currentPanelPosition) ?>";
let currentFormData    = [];
let formsVisible       = true;
let currentStudentId   = null;
let currentFinalDocuId = null;
let currentFilePath    = null;
let currentRouteStatus = null;

function viewFile(filePath, finaldocu_id, student_id, controlNo, leader, groupNo, title, routeStatus) {
    fetch('check_adviser_finaldocu.php?finaldocu_id=' + finaldocu_id)
        .then(r => r.json())
        .then(data => {
            if (!data.hasReviewed) {
                alert('This document cannot be viewed yet. ' + data.message);
                return;
            }

            currentStudentId   = student_id;
            currentFinalDocuId = finaldocu_id;
            currentFilePath    = filePath;
            currentRouteStatus = routeStatus;

            document.getElementById('modalDocTitle').textContent    = title || 'Final Document';
            document.getElementById('pdfFilenameLabel').textContent = filePath.split('/').pop();
            document.getElementById('modalControlNo').textContent   = controlNo;
            document.getElementById('modalGroupNo').textContent     = groupNo;
            document.getElementById('sumControlNo').textContent     = controlNo;
            document.getElementById('sumLeader').textContent        = leader;
            document.getElementById('sumGroup').textContent         = groupNo;
            const yr = new Date().getFullYear();
            document.getElementById('modalSchoolYear').textContent  = 'AY ' + yr + '–' + (yr + 1);

            const pdfFrame  = document.getElementById('pdfFrame');
            const docxDiv   = document.getElementById('docxContent');
            const pdfLoader = document.getElementById('pdfLoader');
            const extension = filePath.split('.').pop().toLowerCase();

            pdfLoader.classList.remove('hidden');
            pdfFrame.style.display = 'none';
            docxDiv.style.display  = 'none';
            docxDiv.innerHTML      = '';

            if (extension === 'pdf') {
                pdfFrame.style.display = 'block';
                pdfFrame.src = filePath;
                pdfFrame.onload = () => pdfLoader.classList.add('hidden');
            } else if (extension === 'docx') {
                fetch(filePath)
                    .then(res => { if (!res.ok) throw new Error('Failed to fetch'); return res.arrayBuffer(); })
                    .then(buffer => mammoth.convertToHtml({ arrayBuffer: buffer }))
                    .then(result => {
                        docxDiv.innerHTML     = result.value;
                        docxDiv.style.display = 'block';
                        pdfLoader.classList.add('hidden');
                    })
                    .catch(() => {
                        docxDiv.innerHTML     = "<p style='color:#dc3545;padding:2rem;'>Error loading file.</p>";
                        docxDiv.style.display = 'block';
                        pdfLoader.classList.add('hidden');
                    });
            } else {
                docxDiv.innerHTML     = "<p style='color:#dc3545;padding:2rem;'>Unsupported file type.</p>";
                docxDiv.style.display = 'block';
                pdfLoader.classList.add('hidden');
            }

            renderRouteProgress(routeStatus);

            const overlay = document.getElementById('viewerOverlay');
            overlay.style.display = 'flex';
            requestAnimationFrame(() => overlay.classList.add('open'));
            switchTab('submitted');

            loadAllForms(student_id);
        })
        .catch(error => {
            console.error('Error checking adviser approval:', error);
            alert('Unable to verify adviser approval status. Please try again later or contact support.');
        });
}

function renderRouteProgress(rs) {
    if (!rs) return;
    ['1','2','3'].forEach(n => {
        const key    = 'route' + n;
        const counts = rs[key + '_counts'] || { total: 0, approved: 0 };
        const pct    = counts.total > 0 ? Math.round((counts.approved / counts.total) * 100) : 0;
        const isOk   = rs[key] === true;

        const bar   = document.getElementById('rpBar' + n);
        const frac  = document.getElementById('rpFrac' + n);
        const badge = document.getElementById('rpBadge' + n);

        if (bar)   { bar.style.width = pct + '%'; bar.className = 'rp-bar' + (isOk ? ' complete' : ''); }
        if (frac)  frac.textContent = counts.approved + '/' + counts.total;
        if (badge) { badge.textContent = isOk ? 'Done' : 'Pending'; badge.className = 'rp-badge ' + (isOk ? 'ok' : 'pend'); }
    });

    const overall = document.getElementById('sumOverall');
    if (overall) {
        if (rs.all_approved) { overall.textContent = 'Complete'; overall.style.color = 'green'; }
        else { overall.textContent = 'In Progress'; overall.style.color = 'orange'; }
    }
}

function closeViewer() {
    const overlay = document.getElementById('viewerOverlay');
    overlay.classList.remove('open');
    setTimeout(() => {
        overlay.style.display = 'none';
        document.getElementById('pdfFrame').src              = '';
        document.getElementById('pdfFrame').style.display    = 'none';
        document.getElementById('docxContent').innerHTML     = '';
        document.getElementById('docxContent').style.display = 'none';
    }, 320);
}

function switchTab(name) {
    const names = ['submitted', 'summary'];
    document.querySelectorAll('.ftab').forEach((t, i) => t.classList.toggle('active', names[i] === name));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.toggle('active', p.id === 'pane-' + name));
}

function loadAllForms(student_id) {
    const container = document.getElementById('submittedFormsContainer');
    const noMsg     = document.getElementById('noFormsMessage');
    const loader    = document.getElementById('commentsLoader2');
    container.innerHTML = '';
    noMsg.textContent   = '';
    if (loader) loader.style.display = 'flex';

    fetch('route3get_all_forms.php?student_id=' + encodeURIComponent(student_id))
        .then(r => { if (!r.ok) throw new Error('Network error: ' + r.status); return r.json(); })
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

            const groups = {};
            currentFormData.forEach(row => {
                const rn = row.routeNumber || 'Final Document';
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

                rows.forEach((form, i) => {
                    const formId    = form.id;
                    const isAdviser = !!form.adviser_name;
                    const role      = isAdviser ? 'adviser' : 'panel';
                    const name      = isAdviser ? form.adviser_name : (form.panel_name || 'Reviewer');
                    const initials  = name.split(',')[0].trim().substring(0, 2).toUpperCase();
                    const status    = (form.status || 'Pending').trim();

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
                            <div class="cc-date">${e(form.date_submitted || '—')}</div>
                        </div>
                        <div class="cc-locs">
                            ${form.chapter          ? `<div class="loc-chip"><strong>Chapter</strong>${e(String(form.chapter))}</div>` : ''}
                            ${form.page_number      ? `<div class="loc-chip"><strong>Page No.</strong>${e(String(form.page_number))}</div>` : ''}
                            ${form.paragraph_number ? `<div class="loc-chip"><strong>Para No.</strong>${e(String(form.paragraph_number))}</div>` : ''}
                        </div>
                        <div class="cc-feedback">${e(form.feedback || 'No feedback provided.')}</div>
                        <div class="cc-footer">
                            <div class="cc-footer-left">
                                ${form.date_released
                                    ? `<div class="rel-badge"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Released ${e(form.date_released)}</div>`
                                    : '<span style="font-size:10px;color:#777;">Not yet released</span>'}
                            </div>
                            <div class="cc-status-row">
                                <select id="statusSelect_${formId}" onchange="saveStatus(${formId})">
                                    <option value="Pending"  ${status === 'Pending'  ? 'selected' : ''}>Pending</option>
                                    <option value="Approved" ${status === 'Approved' ? 'selected' : ''}>Approved</option>
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
        });
}

function toggleForms() {
    const container = document.getElementById('submittedFormsContainer');
    const btn       = document.getElementById('toggleFormsBtn');
    const noMsg     = document.getElementById('noFormsMessage');
    const loader    = document.getElementById('commentsLoader2');
    if (formsVisible) {
        container.innerHTML = '';
        noMsg.textContent   = '';
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
    if (currentRouteStatus) renderRouteProgress(currentRouteStatus);
}

function saveStatus(formId) {
    const statusSelect = document.getElementById(`statusSelect_${formId}`);
    const saveButton   = document.getElementById(`saveButton_${formId}`);
    saveButton.disabled    = true;
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
    const doc = `<!DOCTYPE html><html><head><title>Routing Monitoring Form – Final Document</title>
    <style>body{font-family:Arial,sans-serif;padding:20px;font-size:12px;}h2{color:#4366b3;text-align:center;}h3{text-align:center;font-size:13px;color:#555;margin-bottom:20px;}table{width:100%;border-collapse:collapse;}th{background:#4366b3;color:white;padding:8px;font-size:11px;text-align:left;}td{padding:7px 8px;border-bottom:1px solid #eee;font-size:11px;}@media print{@page{size:landscape;margin:1cm}}</style>
    </head><body>
    <h2>SAINT MICHAEL COLLEGE OF CARAGA</h2>
    <h3>Research &amp; Instructional Innovation Department — Routing Monitoring Form (Final Document)</h3>
    <table><thead><tr><th>Date</th><th>Chapter</th><th>Feedback</th><th>Para No.</th><th>Page No.</th><th>Submitted By</th><th>Released</th><th>Route</th><th>Status</th></tr></thead>
    <tbody>${rows || '<tr><td colspan="9" style="text-align:center;color:#999;">No records found.</td></tr>'}</tbody></table>
    </body></html>`;
    iframe.contentWindow.document.open();
    iframe.contentWindow.document.write(doc);
    iframe.contentWindow.document.close();
    iframe.onload = () => { setTimeout(() => { iframe.contentWindow.print(); setTimeout(() => document.body.removeChild(iframe), 500); }, 300); };
}

document.getElementById('viewerOverlay').addEventListener('click', ev => { if (ev.target === ev.currentTarget) closeViewer(); });
document.addEventListener('keydown', ev => { if (ev.key === 'Escape') closeViewer(); });

function searchTable() {
    const filter = document.getElementById('searchInput').value.toUpperCase();
    const tbody  = document.querySelector('table tbody');
    if (!tbody) return;
    Array.from(tbody.rows).forEach(row => {
        const cell = row.cells[1];
        row.style.display = cell && cell.textContent.toUpperCase().includes(filter) ? '' : 'none';
    });
}

function e(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

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
</script>
</body>
</html>