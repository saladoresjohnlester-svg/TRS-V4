<?php
include '../../../connection.php';

echo "<h2>Deadline Checker Test</h2>";
echo "<pre>";

// Test with route1_id 97 which has a deadline
$test_route1_id = 97; // Jake Castillon's record

echo "Testing deadline checker for route1_id: $test_route1_id\n\n";

// First, let's see what data exists
echo "=== Checking Database Data ===\n";

$checkQuery = $conn->prepare("
    SELECT route1_id, panel_deadline, panel1_id, panel2_id, panel3_id, panel4_id, panel5_id, panel6_id,
           docuRoute1, student_id, fullname, title
    FROM route1proposal_files 
    WHERE route1_id = ?
");
$checkQuery->bind_param("i", $test_route1_id);
$checkQuery->execute();
$result = $checkQuery->get_result();

if ($result->num_rows === 0) {
    echo "❌ No record found for route1_id: $test_route1_id\n";
    exit;
}

$data = $result->fetch_assoc();
echo "✓ Record found!\n";
echo "Student: {$data['fullname']}\n";
echo "Title: {$data['title']}\n";
echo "Deadline: " . ($data['panel_deadline'] ?? 'NOT SET') . "\n";
echo "Panel 1 ID: " . ($data['panel1_id'] ?? 'NOT SET') . "\n";
echo "Panel 2 ID: " . ($data['panel2_id'] ?? 'NOT SET') . "\n";
echo "Panel 3 ID: " . ($data['panel3_id'] ?? 'NOT SET') . "\n";
echo "Panel 4 ID: " . ($data['panel4_id'] ?? 'NOT SET') . "\n";
echo "Panel 5 ID: " . ($data['panel5_id'] ?? 'NOT SET') . "\n";
echo "Panel 6 ID: " . ($data['panel6_id'] ?? 'NOT SET') . "\n\n";

// Check if deadline is set
if (empty($data['panel_deadline'])) {
    echo "❌ No deadline is set for this route!\n";
    exit;
}

// Check if deadline has passed
$now = new DateTime();
$deadlineDate = new DateTime($data['panel_deadline']);

echo "=== Deadline Check ===\n";
echo "Current Time: " . $now->format('Y-m-d H:i:s') . "\n";
echo "Deadline Time: " . $deadlineDate->format('Y-m-d H:i:s') . "\n";

if ($now <= $deadlineDate) {
    echo "❌ Deadline has NOT passed yet!\n";
    $diff = $deadlineDate->diff($now);
    echo "Time remaining: {$diff->h} hours, {$diff->i} minutes, {$diff->s} seconds\n\n";
    
    echo "=== MANUALLY SETTING DEADLINE TO PAST FOR TESTING ===\n";
    // Set deadline to yesterday for testing
    $pastDeadline = (new DateTime())->modify('-1 day')->format('Y-m-d H:i:s');
    $updateStmt = $conn->prepare("UPDATE route1proposal_files SET panel_deadline = ? WHERE route1_id = ?");
    $updateStmt->bind_param("si", $pastDeadline, $test_route1_id);
    $updateStmt->execute();
    echo "✓ Deadline changed to: $pastDeadline\n";
    echo "Now the deadline has passed!\n\n";
    
    // Update the data variable
    $data['panel_deadline'] = $pastDeadline;
    $deadlineDate = new DateTime($pastDeadline);
} else {
    echo "✓ Deadline HAS passed!\n\n";
}

// Check which panels have submitted
echo "=== Panel Submission Status ===\n";
$panelIds = [
    1 => $data['panel1_id'],
    2 => $data['panel2_id'],
    3 => $data['panel3_id'],
    4 => $data['panel4_id'],
    5 => $data['panel5_id'],
    6 => $data['panel6_id']
];

$panelsNeedingComment = [];

foreach ($panelIds as $position => $panelId) {
    if (empty($panelId)) {
        echo "Panel $position: NOT ASSIGNED\n";
        continue;
    }
    
    // Get panel name
    $getPanelName = $conn->prepare("SELECT fullname FROM panel WHERE panel_id = ?");
    $getPanelName->bind_param("i", $panelId);
    $getPanelName->execute();
    $panelResult = $getPanelName->get_result();
    $panelName = $panelResult->fetch_assoc()['fullname'] ?? 'Unknown Panel';
    $getPanelName->close();
    
    // Check if submitted
    $checkFeedback = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM proposal_monitoring_form 
        WHERE route1_id = ? AND panel_id = ?
    ");
    $checkFeedback->bind_param("ii", $test_route1_id, $panelId);
    $checkFeedback->execute();
    $feedbackResult = $checkFeedback->get_result();
    $feedbackCount = $feedbackResult->fetch_assoc()['count'];
    $checkFeedback->close();
    
    if ($feedbackCount > 0) {
        echo "Panel $position ($panelName - ID: $panelId): ✓ HAS submitted ($feedbackCount feedback entries)\n";
    } else {
        echo "Panel $position ($panelName - ID: $panelId): ❌ NO submission - WILL INSERT 'No comment'\n";
        $panelsNeedingComment[] = ['id' => $panelId, 'name' => $panelName, 'position' => $position];
    }
}

echo "\n=== Running Auto-Insert Function ===\n";

// Now include and run the function
require_once 'auto_deadline_checker.php';
checkAndInsertNoComments($conn, $test_route1_id);

echo "✓ Function executed\n\n";

// Check results
echo "=== Verification - Checking What Was Inserted ===\n";
$verifyQuery = $conn->prepare("
    SELECT panel_id, panel_name, feedback, date_submitted, status, chapter
    FROM proposal_monitoring_form 
    WHERE route1_id = ?
    ORDER BY date_submitted DESC
");
$verifyQuery->bind_param("i", $test_route1_id);
$verifyQuery->execute();
$verifyResult = $verifyQuery->get_result();

if ($verifyResult->num_rows === 0) {
    echo "❌ No entries found in proposal_monitoring_form for this route\n";
    echo "This means the auto-insert didn't work!\n";
} else {
    echo "Found {$verifyResult->num_rows} entries:\n\n";
    $autoInsertedCount = 0;
    while ($row = $verifyResult->fetch_assoc()) {
        echo "- Panel: {$row['panel_name']} (ID: {$row['panel_id']})\n";
        echo "  Chapter: {$row['chapter']}\n";
        echo "  Feedback: {$row['feedback']}\n";
        echo "  Status: {$row['status']}\n";
        echo "  Date: {$row['date_submitted']}\n";
        
        if (strpos($row['feedback'], 'No comment - Deadline exceeded') !== false) {
            echo "  ✓ This is an auto-inserted 'No comment'\n";
            $autoInsertedCount++;
        }
        echo "\n";
    }
    
    echo "Summary: $autoInsertedCount auto-inserted 'No comment' entries found\n";
}

echo "</pre>";
echo "<h3>Test Complete!</h3>";

// Reset deadline back to future if you want
echo "<p><a href='?reset=1'>Click here to reset deadline back to future</a></p>";

if (isset($_GET['reset'])) {
    $futureDeadline = (new DateTime())->modify('+1 day')->format('Y-m-d H:i:s');
    $updateStmt = $conn->prepare("UPDATE route1proposal_files SET panel_deadline = ? WHERE route1_id = ?");
    $updateStmt->bind_param("si", $futureDeadline, $test_route1_id);
    $updateStmt->execute();
    echo "<p style='color: green;'>Deadline reset to: $futureDeadline</p>";
}
?>