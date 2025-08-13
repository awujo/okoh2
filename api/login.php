<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, password, is_suspended, email_is_confirmed, 2fa_is_done FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Invalid credentials");
    }

    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        throw new Exception("Invalid credentials");
    }

    if ($user['is_suspended']) {
        throw new Exception("Your account is suspended.");
    }

    if (!$user['email_is_confirmed']) {
        throw new Exception("Please verify your email before logging in.");
    }

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    if ($user['2fa_is_done']) {
        // For 2FA users, set temporary session data
        $_SESSION = []; // Clear existing session
        $_SESSION['2fa_user_id'] = $user['id'];
        $_SESSION['2fa_email'] = $email;
        $_SESSION['2fa_pending'] = true;
        $_SESSION['2fa_expiry'] = time() + 300; // 5 minute expiry
        
        echo json_encode([
            "status" => "success", 
            "message" => "2FA verification required", 
            "twoFARequired" => true
        ]);
    } else {
        // For non-2FA users, set full session
        $_SESSION = []; // Clear existing session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email;
        $_SESSION['2fa_verified'] = true;
        
        echo json_encode([
            "status" => "success", 
            "message" => "Login successful", 
            "twoFARequired" => false
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "error", 
        "message" => $e->getMessage()
    ]);
}