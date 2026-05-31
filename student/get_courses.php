<?php
// Database connection
include '../connection.php';

if (isset($_GET['department'])) {
    $department = mysqli_real_escape_string($conn, $_GET['department']);
    
    // Query to get courses for the selected department
    $sql = "SELECT course FROM departmentcourse WHERE department = '$department' ORDER BY course";
    $result = $conn->query($sql);
    
    // Default option
    echo '<option value="">Select Course</option>';
    
    // Add options for each course
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo '<option value="' . $row["course"] . '">' . $row["course"] . '</option>';
        }
    } else {
        echo '<option value="">No courses found</option>';
    }
} else {
    echo '<option value="">Select Department First</option>';
}

// Close the connection
$conn->close();
?>