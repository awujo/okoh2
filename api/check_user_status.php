<?php
require_once '../api/auth.php';
require_once '../api/session.php';
require_once '../api/db.php';
header('Content-Type: application/json');

$stmt = $conn->prepare("SELECT deposit_balance, 2fa_is_done, kyc_is_done FROM user WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'deposit_balance' => (float)$user['deposit_balance'],
    'two_fa_done' => (bool)$user['2fa_is_done'],
    'kyc_done' => (bool)$user['kyc_is_done'],
]);
