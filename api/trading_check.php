<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check user's trading status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get user's deposit balance
    $userQuery = $conn->prepare("SELECT deposit_balance FROM user WHERE id = ?");
    $userQuery->bind_param("i", $user_id);
    $userQuery->execute();
    $user = $userQuery->get_result()->fetch_assoc();
    
    // Check for active investments
    $investmentQuery = $conn->prepare("SELECT id FROM investment WHERE user_id = ? AND status = 'running' LIMIT 1");
    $investmentQuery->bind_param("i", $user_id);
    $investmentQuery->execute();
    $hasActiveInvestment = $investmentQuery->get_result()->num_rows > 0;
    
    echo json_encode([
        'status' => true,
        'hasDeposit' => $user['deposit_balance'] > 0,
        'hasActiveInvestment' => $hasActiveInvestment,
        'balance' => $user['deposit_balance']
    ]);
    exit;
}