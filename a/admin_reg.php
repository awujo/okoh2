<?php
require_once '../api/db.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Password to be hashed
$password = "okoh";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// SQL query using prepared statement
$query = "INSERT INTO admin (username, password) VALUES (?, ?)";
$stmt = $conn->prepare($query);

if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}

// Bind parameters
$username = 'okoh';
$stmt->bind_param("ss", $username, $hashed_password);

// Execute and verify
if ($stmt->execute()) {
    // Verify the password was stored correctly
    $check = $conn->prepare("SELECT * FROM admin WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $result = $check->get_result();
    $admin = $result->fetch_assoc();
    
    if (password_verify($password, $admin['password'])) {
        echo "Admin account created and verified successfully!";
    } else {
        echo "Account created but password verification failed! Check hashing.";
    }
    $check->close();
} else {
    echo "Error: " . htmlspecialchars($stmt->error);
}

$stmt->close();
$conn->close();
?>