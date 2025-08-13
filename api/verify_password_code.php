<?php
require_once 'db.php';

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';
$code = $_POST['code'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (!$email || !$code || !$new_password) {
    exit(json_encode(['success' => false, 'message' => 'All fields are required.']));
}

$stmt = $conn->prepare("SELECT code FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) exit(json_encode(['success' => false, 'message' => 'Email not found.']));

$row = $result->fetch_assoc();
if ($row['code'] != $code) {
    exit(json_encode(['success' => false, 'message' => 'Incorrect code.']));
}

// Update password
$hashed = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE user SET password = ?, code = 0 WHERE email = ?");
$stmt->bind_param("ss", $hashed, $email);
$stmt->execute();

echo json_encode(['success' => true, 'message' => 'Password successfully updated.']);
