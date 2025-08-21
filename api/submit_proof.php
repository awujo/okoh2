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

// ✅ Load Cloudinary SDK

require __DIR__ . '/../vendor/autoload.php';
use Cloudinary\Cloudinary;


$cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => 'dgkv0zo7l',
        'api_key'    => '163423683817439',
        'api_secret' => 'oP0RbDzfjmsByZ84J2hH5JRqmiA'
    ]
]);

// ✅ Upload file to Cloudinary
$img = $_FILES['screenshot']['tmp_name'];
try {
    $upload_result = $cloudinary->uploadApi()->upload($img, [
        'folder' => 'deposits' // Optional: will organize uploads inside "deposits/"
    ]);

    $screenshot_url = $upload_result['secure_url']; // Cloudinary hosted URL
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Upload failed: ' . $e->getMessage()]);
    exit;
}

// ✅ Insert into DB (store Cloudinary URL instead of filename)
$sql = "INSERT INTO deposit (user_id, transaction_id, gateway, amount, status, wallet, type, is_withdrawal_fee, screenshot, hash) 
        VALUES (?, ?, ?, ?, 'pending', 'deposit_wallet', 'plus', 0, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("issdss", $user_id, $transaction_id, $gateway, $amount, $screenshot_url, $hash);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to submit deposit']);
}
