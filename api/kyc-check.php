<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Check KYC status from user table
    $userQuery = $conn->prepare("SELECT kyc_is_done FROM user WHERE id = ?");
    $userQuery->bind_param("i", $user_id);
    $userQuery->execute();
    $userResult = $userQuery->get_result()->fetch_assoc();

    // If KYC is already approved in user table, redirect to withdrawal
    if ((int)$userResult['kyc_is_done'] === 1) {
        echo json_encode([
            'status' => true, 
            'redirect' => 'withdrawal.html'
        ]);
        exit;
    }

    // Check if KYC submission exists in kyc table
    $kycQuery = $conn->prepare("SELECT status FROM kyc WHERE user_id = ?");
    $kycQuery->bind_param("i", $user_id);
    $kycQuery->execute();
    $kycResult = $kycQuery->get_result()->fetch_assoc();

    if ($kycResult) {
        echo json_encode([
            'status' => true,
            'kyc_status' => $kycResult['status'],
            'submitted' => true
        ]);
    } else {
        echo json_encode([
            'status' => true,
            'kyc_status' => 'not_submitted',
            'submitted' => false
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}