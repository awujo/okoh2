<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$search = $_POST['search'] ?? '';
$walletType = $_POST['wallet_type'] ?? '';

// Base query
$query = "SELECT * FROM deposit WHERE user_id = ?";
$types = "s"; // for user_id
$params = [$user_id];

// Add search condition
if (!empty($search)) {
    $query .= " AND transaction_id LIKE ?";
    $types .= "s";
    $params[] = "%$search%";
}

// Add wallet type condition
if (!empty($walletType)) {
    $query .= " AND wallet = ?";
    $types .= "s";
    $params[] = $walletType;
}

// Finalize query
$query .= " ORDER BY created_at DESC";

// Prepare and bind
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['status' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$deposits = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'status' => true,
    'data' => $deposits
]);
