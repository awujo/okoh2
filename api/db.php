<?php
$host = 'localhost';      // or your DB host
$db   = 'okoh';  // your actual database name
$user = 'root';  // your DB username
$pass = '';  // your DB password

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
