<?php
$host = 'mysql-19b972d0-hackerxy-4d45.g.aivencloud.com';  
$port = '18217';    // or your DB host
$db   = 'defaultdb';  // your actual database name
$user = 'avnadmin';  // your DB username
$pass = 'AVNS_RfFfn4ze5auGVOAtIfF';  // your DB password

$conn = new mysqli($host, $user, $pass, $db, $port);

// $host = 'localhost';  // or your DB host
// $db   = 'okoh';  // your actual database name
// $user = 'root';    // your DB username             
// $pass = '';    // your DB password

// $conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
