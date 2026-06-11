<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

$plan = $_POST['plan'] ?? '';
$amount = intval($_POST['amount'] ?? 0);
// $interest_earned = intval($_POST['interest_earned'] ?? 0); // should be calculated or passed by frontend
// $days_count = intval($_POST['days_count'] ?? 0); // should match plan length (e.g., 14 or 19)
$days_count = 0;
$interest_earned = 0;

if (empty($plan) || $amount <= 0 || $interest_earned = 0 || $days_count = 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

// Check user balance
$stmt = $conn->prepare("SELECT deposit_balance FROM user WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($deposit_balance);
$stmt->fetch();
$stmt->close();

if ($deposit_balance < $amount) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient deposit balance']);
    exit;
}

// Generate a unique transaction ID
$transaction_id = strtoupper(uniqid('INV'));

$stmt = $conn->prepare("INSERT INTO investment (user_id, transaction_id, plan, amount, interest_earned, days_count, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'running')");
$stmt->bind_param("issiii", $user_id, $transaction_id, $plan, $amount, $interest_earned, $days_count);

if ($stmt->execute()) {
    $stmt->close();

    // Deduct the amount from user's deposit_balance
    $stmt = $conn->prepare("UPDATE user SET deposit_balance = deposit_balance - ? WHERE id = ?");
    $stmt->bind_param("ii", $amount, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success', 'transaction_id' => $transaction_id]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create investment']);
}
