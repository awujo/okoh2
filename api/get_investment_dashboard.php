<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get total invested and total profit
$totalQuery = $conn->prepare("SELECT 
    COALESCE(SUM(amount), 0) AS total_invested, 
    COALESCE(SUM(profit), 0) AS total_profit 
    FROM investment WHERE user_id = ?");
$totalQuery->bind_param("i", $user_id);
$totalQuery->execute();
$totalResult = $totalQuery->get_result()->fetch_assoc();

// Get KYC status
$kycQuery = $conn->prepare("SELECT kyc_is_done FROM user WHERE id = ?");
$kycQuery->bind_param("i", $user_id);
$kycQuery->execute();
$kycResult = $kycQuery->get_result()->fetch_assoc();
$kycDone = (int)$kycResult['kyc_is_done'] === 1;

// Get active investments
$investmentsQuery = $conn->prepare("SELECT id, amount, profit, days_count,interest_earned, created_at, status FROM investment WHERE user_id = ? AND status = 'active'");
$investmentsQuery->bind_param("i", $user_id);
$investmentsQuery->execute();
$investmentsResult = $investmentsQuery->get_result();
$activeInvestments = $investmentsResult->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'status' => true,
    'total_invested' => $totalResult['total_invested'],
    'total_profit' => $totalResult['total_profit'],
    'kyc_done' => $kycDone,
    'active_investments' => $activeInvestments
]);
