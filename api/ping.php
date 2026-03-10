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
    $userResult = $conn->query("SELECT COUNT(*) AS total_user FROM user");
    if ($userResult) {
        $row = $userResult->fetch_assoc();
        echo "📊 Total user: <strong>" . number_format($row['total_user']) . "</strong><br>";
    } else {
        echo "✗ Failed to count user: " . $conn->error . "<br>";
    }
} else {
    echo "⚠️ 'user' table does not exist yet.<br>";
}

$conn->close();
?>