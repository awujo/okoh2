<?php
require_once 'auth.php';
require_once 'session.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$gateway = $_POST['gateway'] ?? '';
$amount = $_POST['amount'] ?? 0;
$hash = $_POST['hash'] ?? '';

if (!$gateway || $amount <= 0 || !$hash || !isset($_FILES['screenshot'])) {
    echo json_encode(['status' => 'error', 'message' => 'Incomplete data']);
    exit;
}

$transaction_id = uniqid('dp_');
$upload_dir = '../uploads/';
$screenshot_path = '';

if (!file_exists(filename: $upload_dir)) mkdir($upload_dir, 0777, true);

$img = $_FILES['screenshot'];
$ext = pathinfo($img['name'], PATHINFO_EXTENSION);
$filename = uniqid('screenshot_') . '.' . $ext;
$screenshot_path = $upload_dir . $filename;
move_uploaded_file($img['tmp_name'], $screenshot_path);

$sql = "INSERT INTO deposit (user_id, transaction_id, gateway, amount, status, wallet, type, is_withdrawal_fee, screenshot, hash) 
        VALUES (?, ?, ?, ?, 'pending', 'deposit_wallet', 'plus', 0, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("issdss", $user_id, $transaction_id, $gateway, $amount, $filename, $hash); // Corrected type definition string
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to submit deposit']);
}
