<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check KYC status
$kycQuery = $conn->prepare("SELECT kyc_is_done FROM user WHERE id = ?");
$kycQuery->bind_param("i", $user_id);
$kycQuery->execute();
$kycResult = $kycQuery->get_result()->fetch_assoc();

if ((int)$kycResult['kyc_is_done'] !== 1) {
    echo json_encode(['status' => false, 'redirect' => 'kyc-form.html']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get user balances
    $balanceQuery = $conn->prepare("SELECT deposit_balance, interest_balance FROM user WHERE id = ?");
    $balanceQuery->bind_param("i", $user_id);
    $balanceQuery->execute();
    $balanceResult = $balanceQuery->get_result()->fetch_assoc();
    
    echo json_encode([
        'status' => true,
        'balances' => [
            'deposit' => $balanceResult['deposit_balance'],
            'interest' => $balanceResult['interest_balance']
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $amount = $input['amount'] ?? 0;
    $wallet_address = $input['wallet_address'] ?? '';
    $gateway = $input['gateway'] ?? '';
    $wallet_type = $input['wallet_type'] ?? ''; // 'deposit' or 'interest'
    
    // Validate input
    if ($amount <= 0 || empty($wallet_address) || empty($gateway) || !in_array($wallet_type, ['deposit', 'interest'])) {
        echo json_encode(['status' => false, 'message' => 'Invalid withdrawal request']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get user balances with FOR UPDATE to lock the row
        $balanceQuery = $conn->prepare("SELECT deposit_balance, interest_balance FROM user WHERE id = ? FOR UPDATE");
        $balanceQuery->bind_param("i", $user_id);
        $balanceQuery->execute();
        $balanceResult = $balanceQuery->get_result()->fetch_assoc();
        
        $available_balance = $wallet_type === 'deposit' 
            ? $balanceResult['deposit_balance'] 
            : $balanceResult['interest_balance'];
        
        // Check sufficient balance
        if ($amount > $available_balance) {
            throw new Exception('Insufficient balance for withdrawal');
        }
        
        // Calculate withdrawal fee (example: 10%)
        $withdrawal_fee = $amount * 0.1;
        $withdrawable_amount = $amount - $withdrawal_fee;
        
        // Generate transaction ID
        $transaction_id = 'W' . time() . bin2hex(random_bytes(4));
        
        // Create withdrawal record
        $withdrawalQuery = $conn->prepare("INSERT INTO withdrawal (
            user_id, transaction_id, amount, withdrawable_amount, 
            wallet, type, wallet_address, gateway, status
        ) VALUES (?, ?, ?, ?, ?, 'minus', ?, ?, 'pending')");
        
        $wallet_field = $wallet_type . '_wallet';
        $withdrawalQuery->bind_param(
            "isddsss", 
            $user_id, 
            $transaction_id, 
            $amount, 
            $withdrawable_amount,
            $wallet_field,
            $wallet_address,
            $gateway
        );
        
        if (!$withdrawalQuery->execute()) {
            throw new Exception('Failed to create withdrawal record');
        }
        
        // Update user balance
        $updateField = $wallet_type === 'deposit' ? 'deposit_balance' : 'interest_balance';
        $updateQuery = $conn->prepare("UPDATE user SET $updateField = $updateField - ? WHERE id = ?");
        $updateQuery->bind_param("di", $amount, $user_id);
        
        if (!$updateQuery->execute()) {
            throw new Exception('Failed to update user balance');
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'status' => true,
            'message' => 'Withdrawal request submitted successfully',
            'transaction_id' => $transaction_id,
            'withdrawable_amount' => $withdrawable_amount,
            'fee' => $withdrawal_fee
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    }
}