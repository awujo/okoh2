<?php
require 'auth.php';
require 'db.php';
require_once '../vendor/autoload.php'; // Include this if you're using Composer for Google Authenticator

use OTPHP\TOTP;

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// Generate new 2FA secret if not exists
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Check if user already has 2FA enabled
        $query = $conn->prepare("SELECT 2fa_secret, 2fa_is_done FROM user WHERE id = ?");
        $query->bind_param("i", $user_id);
        $query->execute();
        $result = $query->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // If user already has 2FA enabled
        if ($user['2fa_is_done']) {
            echo json_encode([
                'status' => false,
                'message' => '2FA is already enabled for this account'
            ]);
            exit;
        }
        
        // Generate new secret if doesn't exist
        $secret = $user['2fa_secret'] ?: TOTP::generate()->getSecret();
        
        // Create TOTP object
        $totp = TOTP::create(
            $secret,
            30,          // 30-second window
            'sha1',      // SHA1 algorithm
            6            // 6-digit codes
        );
        
        // Set issuer and account name
        $totp->setLabel($_SESSION['email']); // Change to your app name
        $totp->setIssuer('Nexora Capitals'); // Change to your company name
        
        // Generate QR code URI
        $qrCodeUri = $totp->getQrCodeUri(
            'https://api.qrserver.com/v1/create-qr-code/?data=[DATA]&size=300x300&ecc=M',
            '[DATA]'
        );
        
        // Save secret to database if it's new
        if (!$user['2fa_secret']) {
            $update = $conn->prepare("UPDATE user SET 2fa_secret = ? WHERE id = ?");
            $update->bind_param("si", $secret, $user_id);
            $update->execute();
        }
        
        echo json_encode([
            'status' => true,
            'secret' => $secret,
            'qrCodeUri' => $qrCodeUri
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => false,
            'message' => 'Failed to setup 2FA: ' . $e->getMessage()
        ]);
    }
}

// Verify 2FA code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['code']) || empty($input['key'])) {
        echo json_encode(['status' => false, 'message' => 'Verification code and secret key are required']);
        exit;
    }
    
    try {
        // Verify the code
        $totp = TOTP::create(
            $input['key'],
            30,          // 30-second window
            'sha1',      // SHA1 algorithm
            6           // 6-digit codes
        );
        
        if ($totp->verify($input['code'])) {
            // Update user record
            $update = $conn->prepare("UPDATE user SET 2fa_is_done = 1 WHERE id = ? AND 2fa_secret = ?");
            $update->bind_param("is", $user_id, $input['key']);
            $update->execute();
            
            if ($update->affected_rows === 1) {
                echo json_encode([
                    'status' => true,
                    'message' => '2FA successfully enabled'
                ]);
            } else {
                throw new Exception('Failed to update user record');
            }
        } else {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid verification code'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => false,
            'message' => 'Verification failed: ' . $e->getMessage()
        ]);
    }
}