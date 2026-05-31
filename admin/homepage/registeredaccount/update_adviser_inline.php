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
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    if (!empty($password)) {
        // Update adviser with password
        $sql = "UPDATE adviser SET fullname = ?, department = ?, password = ?, email = ? WHERE school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $fullname, $department, $password, $email, $school_id);
    } else {
        // Update adviser without password
        $sql = "UPDATE adviser SET fullname = ?, department = ?, email = ? WHERE school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $fullname, $department, $email, $school_id);
    }

    if ($stmt->execute()) {
        // Redirect with success message
        header("Location: adviser_register.php?status=success");
        exit;
    } else {
        echo "Error: " . $stmt->error;
        exit;
    }
}

// Close the connection
$stmt->close();
$conn->close();
?>
