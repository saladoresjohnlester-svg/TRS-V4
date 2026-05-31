<?php
$servername = "localhost";
$username = "root";
$password = "123";
$dbname = "trs_v4";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>