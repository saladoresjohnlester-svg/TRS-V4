<?php
// Start the session
session_start();

// Check if the user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../logout.php");
    exit;
}

// Database connection
include '../../../connection.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get values from the form
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $course = mysqli_real_escape_string($conn, $_POST['course']);
    
    // Validate inputs
    if (empty($department) || empty($course) || empty($id)) {
        // Redirect back with error
        header("Location: departmentcourse.php?status=error&message=All fields are required");
        exit;
    }
    
    // Update data in database
    $sql = "UPDATE departmentcourse SET department='$department', course='$course' WHERE id='$id'";
    
    if ($conn->query($sql) === TRUE) {
        // Success - redirect back with success message
        header("Location: departmentcourse.php?status=success");
    } else {
        // Error - redirect back with error message
        header("Location: departmentcourse.php?status=error&message=" . urlencode("Error: " . $conn->error));
    }
    
    // Close connection
    $conn->close();
} else {
    // If not a POST request, redirect to the form page
    header("Location: departmentcourse.php");
    exit;
}
?>