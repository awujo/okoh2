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

if (!$gateway || $amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

$wallets = [
    'bitcoin' => 'bc1qsllst0feqzljm58m5wqa503fc6w66sf6xg8a46',
    'tether' => 'TJMAcGCTnGkakTx8J9R3aqjyeeMAwU3oVx',
    'ethereum' => '0x8DCa74acc74490fb6d297B8edf5F031ef818cACD',
    'tron' => 'TJMAcGCTnGkakTx8J9R3aqjyeeMAwU3oVx',
    'solana' => '8dCitxB3zqrdmLfBeVcaaVoq85EejG9Doppz91fbMpae'
];

if (!isset($wallets[$gateway])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid gateway']);
    exit;
}

$response = [
    'status' => 'success',
    'wallet_address' => $wallets[$gateway],
    'gateway' => $gateway,
    'amount' => $amount
];

echo json_encode($response);
