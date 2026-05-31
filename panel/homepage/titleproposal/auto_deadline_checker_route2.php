<?php
// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/auto_deadline_errors.log');
// auto_deadline_checker.php
function checkAndInsertNoCommentsRoute2($conn, $route2_id) {
    error_log("=== Deadline Checker Called === route2_id: $route2_id");

    $route2_id = (string)$route2_id;

    $deadlineQuery = $conn->prepare("
        SELECT panel_deadline, panel1_id, panel2_id, panel3_id, panel4_id, panel5_id, panel6_id, 
               docuRoute2, student_id, fullname, title
        FROM route2proposal_files 
        WHERE route2_id = ?
    ");

    if (!$deadlineQuery) {
        error_log("PREPARE FAILED (deadlineQuery): " . $conn->error);
        return;
    }

    $deadlineQuery->bind_param("s", $route2_id);
    $deadlineQuery->execute();
    $result = $deadlineQuery->get_result();

    if ($result->num_rows === 0) {
        error_log("No records found for route2_id: $route2_id");
        $deadlineQuery->close();
        return;
    }

    $data = $result->fetch_assoc();
    $deadlineQuery->close();

    $deadline   = $data['panel_deadline'];
    $docuRoute2 = $data['docuRoute2'];
    $student_id = (string)$data['student_id'];

    error_log("Deadline from DB: " . ($deadline ?? 'NULL'));

// Check if deadline is valid
if (empty($deadline) || $deadline === '0000-00-00 00:00:00' || $deadline === NULL) {
    error_log("No deadline set or invalid deadline - exiting");
    return;
}

// Additional validation: Try to parse the date
try {
    $timezone     = new DateTimeZone('Asia/Manila');
    $now          = new DateTime('now', $timezone);
    $deadlineDate = new DateTime($deadline, $timezone);
    
    // Check if the year is valid (not a default/error date)
    if ($deadlineDate->format('Y') < 1970) {
        error_log("Invalid deadline date (year < 1970) - exiting");
        return;
    }
} catch (Exception $e) {
    error_log("Error parsing deadline: " . $e->getMessage() . " - exiting");
    return;
}

    error_log("PH time NOW: " . $now->format('Y-m-d H:i:s'));
    error_log("Deadline:    " . $deadlineDate->format('Y-m-d H:i:s'));

    if ($now <= $deadlineDate) {
        error_log("Deadline not yet passed - exiting");
        return;
    }

    error_log("Deadline HAS PASSED - processing panels...");

    $panelIds = array_filter([
        $data['panel1_id'],
        $data['panel2_id'],
        $data['panel3_id'],
        $data['panel4_id'],
        $data['panel5_id'],
        $data['panel6_id'],
    ], function($v) { return !empty($v); });

    error_log("Non-empty panel IDs: " . json_encode(array_values($panelIds)));

    foreach ($panelIds as $panelId) {
        $panelId = (string)$panelId;
        error_log("Checking panel_id: $panelId");

        $checkFeedback = $conn->prepare(
            "SELECT COUNT(*) as cnt FROM proposal_monitoring_form WHERE route2_id = ? AND panel_id = ?"
        );
        if (!$checkFeedback) {
            error_log("PREPARE FAILED (checkFeedback): " . $conn->error);
            continue;
        }
        $checkFeedback->bind_param("ss", $route2_id, $panelId);
        $checkFeedback->execute();
        $feedbackCount = (int)$checkFeedback->get_result()->fetch_assoc()['cnt'];
        $checkFeedback->close();

        error_log("Panel $panelId feedback count: $feedbackCount");

        if ($feedbackCount > 0) {
            error_log("Panel $panelId already has feedback - skipping");
            continue;
        }

        // Get panel name
        $getPanelName = $conn->prepare("SELECT fullname FROM panel WHERE panel_id = ?");
        if (!$getPanelName) {
            error_log("PREPARE FAILED (getPanelName): " . $conn->error);
            continue;
        }
        $getPanelName->bind_param("s", $panelId);
        $getPanelName->execute();
        $panelRow  = $getPanelName->get_result()->fetch_assoc();
        $getPanelName->close();
        $panelName = $panelRow['fullname'] ?? 'Unknown Panel';

        error_log("Inserting 'No comment' for panel: $panelName (ID: $panelId)");

        // ✅ Only Route 2 columns - removed route1, route3, and finaldocu columns
// ✅ Only Route 2 columns - removed adviser columns
$insertNoComment = $conn->prepare("
    INSERT INTO proposal_monitoring_form 
        (panel_id, panel_name, date_submitted, chapter, feedback,
         paragraph_number, page_number, date_released,
         docuRoute2, route2_id, student_id, status, routeNumber)
    VALUES
        (?, ?, NOW(), 'N/A', 'No comment - Deadline exceeded',
         0, 0, NOW(),
         ?, ?, ?, 'Approved', 'Route 2')
");

if (!$insertNoComment) {
    error_log("PREPARE FAILED (insertNoComment): " . $conn->error);
    continue;
}

$insertNoComment->bind_param("sssss",
    $panelId,    // s — panel_id
    $panelName,  // s — panel_name
    $docuRoute2, // s — docuRoute2
    $route2_id,  // s — route2_id
    $student_id  // s — student_id
);

        if ($insertNoComment->execute()) {
            error_log("✅ INSERT SUCCESS for panel_id: $panelId");
        } else {
            error_log("❌ INSERT FAILED: " . $insertNoComment->error . " (errno: " . $insertNoComment->errno . ")");
        }
        $insertNoComment->close();
    }

    error_log("=== Deadline Checker Completed ===");
}
?>