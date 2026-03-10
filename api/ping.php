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

// Check if 'users' table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $userResult = $conn->query("SELECT COUNT(*) AS total_users FROM users");
    if ($userResult) {
        $row = $userResult->fetch_assoc();
        echo "📊 Total users: <strong>" . number_format($row['total_users']) . "</strong><br>";
    } else {
        echo "✗ Failed to count users: " . $conn->error . "<br>";
    }
} else {
    echo "⚠️ 'users' table does not exist yet.<br>";
}

$conn->close();
?>