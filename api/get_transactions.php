<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get filter parameters from POST
$input = json_decode(file_get_contents('php://input'), true);

$type = $input['type'] ?? 'all'; // 'deposit', 'investment', 'withdrawal', or 'all'
$status = $input['status'] ?? 'all'; // 'pending', 'completed', 'rejected', or 'all'
$start_date = $input['start_date'] ?? null;
$end_date = $input['end_date'] ?? null;

// Base queries
$depositQuery = "SELECT 
    id, transaction_id, gateway, amount, status, created_at 
    FROM deposit WHERE user_id = ?";

$investmentQuery = "SELECT 
    id, transaction_id, plan, amount, interest_earned as profit, 
    days_count, status, created_at 
    FROM investment WHERE user_id = ?";

$withdrawalQuery = "SELECT 
    id, transaction_id, amount, withdrawable_amount, 
    wallet, gateway, status, created_at 
    FROM withdrawal WHERE user_id = ?";

// Apply filters
$params = [$user_id];
$types = [];

if ($type !== 'all') {
    $types = [$type];
} else {
    $types = ['deposit', 'investment', 'withdrawal'];
}

$transactions = [];

foreach ($types as $t) {
    $query = '';
    $queryParams = $params;
    
    if ($t === 'deposit') {
        $query = $depositQuery;
    } elseif ($t === 'investment') {
        $query = $investmentQuery;
    } elseif ($t === 'withdrawal') {
        $query = $withdrawalQuery;
    }
    
    // Add status filter if not 'all'
    if ($status !== 'all') {
        $query .= " AND status = ?";
        $queryParams[] = $status;
    }
    
    // Add date filters if provided
    if ($start_date) {
        $query .= " AND created_at >= ?";
        $queryParams[] = $start_date;
    }
    if ($end_date) {
        $query .= " AND created_at <= ?";
        $queryParams[] = $end_date;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    $typesStr = str_repeat('s', count($queryParams));
    $stmt->bind_param($typesStr, ...$queryParams);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['type'] = $t;
        $transactions[] = $row;
    }
}

// Sort all transactions by date (newest first)
usort($transactions, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

echo json_encode([
    'status' => true,
    'transactions' => $transactions
]);