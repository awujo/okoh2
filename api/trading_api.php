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
    $userQuery = $conn->prepare("SELECT deposit_balance FROM user WHERE id = ?");
    $userQuery->bind_param("i", $user_id);
    $userQuery->execute();
    $user = $userQuery->get_result()->fetch_assoc();
    
    $investmentQuery = $conn->prepare("SELECT status FROM investment WHERE user_id = ? AND status = 'running' LIMIT 1");
    $investmentQuery->bind_param("i", $user_id);
    $investmentQuery->execute();
    $hasActiveInvestment = $investmentQuery->get_result()->num_rows > 0;
    
    echo json_encode([
        'status' => true,
        'hasBalance' => $user['deposit_balance'] > 0,
        'hasInvestment' => $hasActiveInvestment,
        'balance' => $user['deposit_balance']
    ]);
    exit;
}

// Handle trade request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? ''; // 'buy' or 'sell'
    
    // Check if user has active investment
    $investmentQuery = $conn->prepare("SELECT id FROM investment WHERE user_id = ? AND status = 'running' LIMIT 1");
    $investmentQuery->bind_param("i", $user_id);
    $investmentQuery->execute();
    
    if ($investmentQuery->get_result()->num_rows > 0) {
        echo json_encode(['status' => false, 'message' => 'You already have an active investment plan']);
        exit;
    }
    
    // Check user balance
    $userQuery = $conn->prepare("SELECT deposit_balance FROM user WHERE id = ?");
    $userQuery->bind_param("i", $user_id);
    $userQuery->execute();
    $user = $userQuery->get_result()->fetch_assoc();
    
    if ($user['deposit_balance'] <= 0) {
        echo json_encode(['status' => false, 'message' => 'Please deposit funds to trade']);
        exit;
    }
    
    // If we get here, user can trade
    echo json_encode([
        'status' => true,
        'message' => 'Please enroll in an investment plan to start trading',
        'action' => $action
    ]);
}