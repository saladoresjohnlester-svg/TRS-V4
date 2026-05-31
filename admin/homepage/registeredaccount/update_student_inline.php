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
    $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    // New ID number (can be edited in the form)
    $school_id = mysqli_real_escape_string($conn, $_POST['school_id']);
    // Original ID number (kept for compatibility, but we now key by student_id)
    $original_school_id = mysqli_real_escape_string($conn, $_POST['original_school_id'] ?? $school_id);

    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Check if passwords match
    if ($password != $confirm_password) {
        echo "Passwords do not match.";
        exit;
    }

    // Check if password was provided
    if (!empty($password)) {
        // Update student with password and allow changing school_id (ID Number)
        $sql = "UPDATE student SET fullname = ?, department = ?, school_id = ?, password = ?, confirm_password = ?, email = ? WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $fullname, $department, $school_id, $password, $confirm_password, $email, $student_id);
    } else {
        // Update student without changing password but allow changing school_id
        $sql = "UPDATE student SET fullname = ?, department = ?, school_id = ?, email = ? WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $fullname, $department, $school_id, $email, $student_id);
    }

    if ($stmt->execute()) {
        // Redirect with success message
        header("Location: student_register.php?status=success");
        exit;
    } else {
        echo "Error: " . $stmt->error;
        exit;
    }
}

// Close the connection
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
$conn->close();
?>