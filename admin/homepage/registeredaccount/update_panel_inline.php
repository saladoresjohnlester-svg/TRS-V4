<?php
// Start session
session_start();

// Check if the user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../logout.php");
    exit;
}

// Database connection
include '../../../connection.php';

// Get form data
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get form fields
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $school_id = mysqli_real_escape_string($conn, $_POST['school_id']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Check if password was provided; if not, don't update password
    if (empty($password)) {
        // Update panel without changing password
        $sql = "UPDATE panel SET fullname = ?, department = ?, position = ?, email = ? WHERE school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $fullname, $department, $position, $email, $school_id);
    } else {
        // Update panel including password
        $sql = "UPDATE panel SET fullname = ?, department = ?, password = ?, position = ?, email = ? WHERE school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $fullname, $department, $password, $position, $email, $school_id);
    }

    if ($stmt->execute()) {
        // Redirect with success message
        header("Location: panel_register.php?status=success");
        exit;
    } else {
        echo "Error: " . $stmt->error;
        exit;
    }
    
    // Close the statement and connection
    
}
$stmt->close();
$conn->close();
?>