<?php
// admin_auth.php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Enable if using HTTPS
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../api/db.php';

function adminLogin($username, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ?");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return false;
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        
        // Debug output - remove after testing
        error_log("Stored hash: " . $admin['password']);
        error_log("Input password: " . $password);
        error_log("Verification result: " . (password_verify($password, $admin['password']) ? 'true' : 'false'));
        
        if (password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            return true;
        }
    }
    
    return false;
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && 
           $_SESSION['admin_logged_in'] === true &&
           $_SESSION['user_agent'] === $_SERVER['HTTP_USER_AGENT'] &&
           $_SESSION['ip_address'] === $_SERVER['REMOTE_ADDR'];
}

function adminLogout() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// Validate session on each admin page
function validateAdminSession() {
    if (!isAdminLoggedIn()) {
        adminLogout();
        header("Location: admin_login.php");
        exit;
    }
}
?>