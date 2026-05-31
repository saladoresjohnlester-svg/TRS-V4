<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
include '../connection.php';

// Query to get all available advisers
$query = "SELECT adviser_id, fullname, email FROM adviser ORDER BY fullname";
$result = $conn->query($query);

// Default option
echo '<option value="">Select Adviser</option>';

// Display all advisers
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo '<option value="' . $row["fullname"] . '" data-email="' . $row["email"] . '">' . $row["fullname"] . '</option>';
    }
} else {
    echo '<option value="">No advisers available</option>';
}

// Close the connection
$conn->close();
?> 