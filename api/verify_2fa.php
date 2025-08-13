<?php
require_once 'db.php';
require_once '../vendor/autoload.php'; // For OTPHP
session_start();

header('Content-Type: application/json');

// Check if 2FA is pending
if (empty($_SESSION['2fa_pending']) || empty($_SESSION['2fa_user_id'])) {
    echo json_encode(["status" => "error", "message" => "Invalid 2FA request"]);
    exit;
}

// Check if 2FA session expired
if (time() > $_SESSION['2fa_expiry']) {
    session_unset();
    session_destroy();
    echo json_encode(["status" => "error", "message" => "Session expired. Please login again."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';

    
    if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
        echo json_encode(["status" => "error", "message" => "Please enter a valid 6-digit code"]);
        exit;
    }

    try {
        // Get user's 2FA secret
        $stmt = $conn->prepare("SELECT 2fa_secret FROM user WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['2fa_user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user || empty($user['2fa_secret'])) {
            throw new Exception("2FA not properly configured");
        }

        // Verify the code
        $totp = OTPHP\TOTP::create(
            $user['2fa_secret'],
            30,      // 30-second window
            'sha1',  // SHA1 algorithm
            6        // 6-digit codes
        );

        if ($totp->verify($code)) {
            // Verification successful - set proper session
            $_SESSION['user_id'] = $_SESSION['2fa_user_id'];
            $_SESSION['email'] = $_SESSION['2fa_email'];
            $_SESSION['2fa_verified'] = true;
            
            // Clear temporary 2FA data
            unset($_SESSION['2fa_user_id']);
            unset($_SESSION['2fa_email']);
            unset($_SESSION['2fa_pending']);
            unset($_SESSION['2fa_expiry']);
            
            // // Update last login
            // $update = $conn->prepare("UPDATE user SET last_login = NOW() WHERE id = ?");
            // $update->bind_param("i", $_SESSION['user_id']);
            // $update->execute();
            
            echo json_encode([
                "status" => "success", 
                "message" => "Verification successful",
                "redirect" => "../in/index.html" // Changed to .php for consistency
            ]);
        } else {
            throw new Exception("Invalid verification code");
        }
    } catch (Exception $e) {
        echo json_encode([
            "status" => "error", 
            "message" => $e->getMessage()
        ]);
    }
    exit;
}

// For GET requests, just confirm 2FA is required
echo json_encode(["status" => "success", "twoFARequired" => true]);