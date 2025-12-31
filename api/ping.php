<?php
require_once 'db.php';

$result = $conn->query("SELECT 1");

if ($result) {
    echo "Database query ping successful at " . date('Y-m-d H:i:s');
} else {
    echo "Database query ping failed: " . $conn->error;
}
$conn->close();
?>