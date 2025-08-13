<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get username from database
    $query = $conn->prepare("SELECT username FROM user WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Generate referral link (replace with your actual domain)
    $base_url = "https://nexoracapitals.com"; // Change to your domain
    $referral_link = $base_url . "/getIn/register.html?reference=" . $user['username'];
    
    echo json_encode([
        'status' => true,
        'username' => $user['username'],
        'referral_link' => $referral_link
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Failed to get user data: ' . $e->getMessage()
    ]);
}