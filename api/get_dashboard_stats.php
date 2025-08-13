<?php
require_once 'auth.php';
require_once 'session.php';
require_once 'db.php';

header('Content-Type: application/json');

$response = [
    'deposit' => ['completed' => 0, 'pending' => 0, 'rejected' => 0, 'requested' => 0],
    'withdrawal' => ['completed' => 0, 'pending' => 0, 'rejected' => 0, 'requested' => 0],
    'investment' => ['completed' => 0, 'running' => 0, 'interest' => 0, 'from_deposit' => 0, 'from_interest' => 0]
];

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Deposits
$stmt = $conn->prepare("SELECT status, SUM(amount) as total FROM deposit WHERE user_id = ? GROUP BY status");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $status = strtolower($row['status']);
    $response['deposit'][$status] = (float)$row['total'];
}
$stmt->close();

// Total deposit requests (including ones not yet submitted)
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM deposit WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$response['deposit']['requested'] = (float)($row['total'] ?? 0);
$stmt->close();

// Withdrawals
$stmt = $conn->prepare("SELECT status, SUM(amount) as total FROM withdrawal WHERE user_id = ? GROUP BY status");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $status = strtolower($row['status']);
    $response['withdrawal'][$status] = (float)$row['total'];
}
$stmt->close();

// Total withdrawal requests
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM withdrawal WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$response['withdrawal']['requested'] = (float)($row['total'] ?? 0);
$stmt->close();

// Investments
$stmt = $conn->prepare("SELECT status, SUM(amount) as total FROM investment WHERE user_id = ? GROUP BY status");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $status = strtolower($row['status']);
    if ($status === 'completed') {
        $response['investment']['completed'] = (float)$row['total'];
    } elseif ($status === 'running') {
        $response['investment']['running'] = (float)$row['total'];
    }
}
$stmt->close();

// Interests
$stmt = $conn->prepare("SELECT SUM(interest_earned) as total FROM investment WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$response['investment']['interest'] = (float)($row['total'] ?? 0);
$stmt->close();

// Optional: If you have a column like source_of_funds, you can add more breakdown.

echo json_encode($response);
?>
