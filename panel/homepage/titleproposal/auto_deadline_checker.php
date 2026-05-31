<?php
// auto_deadline_checker.php

function checkAndInsertNoComments($conn, $route1_id) {
    error_log("=== Deadline Checker Called === route1_id: $route1_id");

    $route1_id = (string)$route1_id;

    $deadlineQuery = $conn->prepare("
        SELECT panel_deadline, panel1_id, panel2_id, panel3_id, panel4_id, panel5_id, panel6_id, 
               docuRoute1, student_id, fullname, title
        FROM route1proposal_files 
        WHERE route1_id = ?
    ");

    if (!$deadlineQuery) {
        error_log("PREPARE FAILED (deadlineQuery): " . $conn->error);
        return;
    }

    $deadlineQuery->bind_param("s", $route1_id);
    $deadlineQuery->execute();
    $result = $deadlineQuery->get_result();

    if ($result->num_rows === 0) {
        error_log("No records found for route1_id: $route1_id");
        $deadlineQuery->close();
        return;
    }

    $data = $result->fetch_assoc();
    $deadlineQuery->close();

    $deadline   = $data['panel_deadline'];
    $docuRoute1 = $data['docuRoute1'];
    $student_id = (string)$data['student_id'];

    error_log("Deadline from DB: " . ($deadline ?? 'NULL'));

    if (empty($deadline)) {
        error_log("No deadline set - exiting");
        return;
    }

    // ✅ Force Philippines timezone (UTC+8)
    $timezone     = new DateTimeZone('Asia/Manila');
    $now          = new DateTime('now', $timezone);
    $deadlineDate = new DateTime($deadline, $timezone);

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
            "SELECT COUNT(*) as cnt FROM proposal_monitoring_form WHERE route1_id = ? AND panel_id = ?"
        );
        if (!$checkFeedback) {
            error_log("PREPARE FAILED (checkFeedback): " . $conn->error);
            continue;
        }
        $checkFeedback->bind_param("ss", $route1_id, $panelId);
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

        // ✅ Only Route 1 columns - removed route2, route3, and finaldocu columns
        $insertNoComment = $conn->prepare("
            INSERT INTO proposal_monitoring_form 
                (panel_id, panel_name, date_submitted, chapter, feedback,
                 paragraph_number, page_number, date_released,
                 docuRoute1, route1_id, student_id, status, routeNumber,
                 adviser_id, adviser_name)
            VALUES
                (?, ?, NOW(), 'N/A', 'No comment - Deadline exceeded',
                 0, 0, NOW(),
                 ?, ?, ?, 'Approved', 'Route 1',
                 0, '')
        ");

        if (!$insertNoComment) {
            error_log("PREPARE FAILED (insertNoComment): " . $conn->error);
            continue;
        }

        $insertNoComment->bind_param("sssss",
            $panelId,    // s — panel_id
            $panelName,  // s — panel_name
            $docuRoute1, // s — docuRoute1
            $route1_id,  // s — route1_id
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