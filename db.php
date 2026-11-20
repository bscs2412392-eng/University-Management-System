<?php
// db.php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "university_db";

$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Database auto creation
$sql = "CREATE DATABASE IF NOT EXISTS $db";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($db);
} else {
    die("Error creating database: " . $conn->error);
}
?>