<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
include '../../../connection.php';

// Query to get unique departments from departmentcourse table
$query = "SELECT DISTINCT department FROM departmentcourse ORDER BY department";
$result = $conn->query($query);

// Default option
echo '<option value="">Select Department</option>';

// Display all departments
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo '<option value="' . $row["department"] . '">' . $row["department"] . '</option>';
    }
} else {
    echo '<option value="">No departments available</option>';
}

// Close the connection
$conn->close();
?> 