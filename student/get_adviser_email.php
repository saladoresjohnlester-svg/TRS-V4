<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
include '../connection.php';

// Get adviser name from query parameter
$adviser = isset($_GET['adviser']) ? $_GET['adviser'] : '';

if (!empty($adviser)) {
    // Query to get adviser email
    $stmt = $conn->prepare("SELECT email FROM adviser WHERE fullname = ?");
    $stmt->bind_param("s", $adviser);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo $row['email'];
    } else {
        echo ''; // No email found
    }
    
    $stmt->close();
} else {
    echo ''; // No adviser provided
}

// Close the connection
$conn->close();
?> 