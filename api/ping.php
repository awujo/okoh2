<?php
require_once 'db.php';

// Ping check
$result = $conn->query("SELECT 1");

if (!$result) {
    echo "✗ Database ping failed: " . $conn->error . "<br>";
    $conn->close();
    exit;
}

echo "✓ Database connected at " . date('Y-m-d H:i:s') . "<br>";

// Check if 'user' table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'user'");

if ($tableCheck && $tableCheck->num_rows > 0) {

    $username = 'sk1';

    $stmt = $conn->prepare("SELECT * FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $userResult = $stmt->get_result();

    if ($userResult->num_rows > 0) {
        echo "✓ User '$username' exists.<br>";
    } else {
        echo "⚠️ User '$username' not found.<br>";
    }

    $stmt->close();

} else {
    echo "⚠️ 'user' table does not exist yet.<br>";
}

$conn->close();
?>