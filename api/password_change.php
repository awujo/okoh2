<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($input['current_password']) || empty($input['password']) || empty($input['password_confirmation'])) {
        echo json_encode(['status' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    if ($input['password'] !== $input['password_confirmation']) {
        echo json_encode(['status' => false, 'message' => 'Password confirmation does not match']);
        exit;
    }
    
    if (strlen($input['password']) < 8) {
        echo json_encode(['status' => false, 'message' => 'Password must be at least 8 characters']);
        exit;
    }
    
    try {
        // Get current user password
        $query = $conn->prepare("SELECT password FROM user WHERE id = ?");
        $query->bind_param("i", $user_id);
        $query->execute();
        $result = $query->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Verify current password
        if (!password_verify($input['current_password'], $user['password'])) {
            echo json_encode(['status' => false, 'message' => 'Current password is incorrect']);
            exit;
        }
        
        // Hash new password
        $newPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $conn->prepare("UPDATE user SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $newPassword, $user_id);
        $stmt->execute();
        
        echo json_encode([
            'status' => true, 
            'message' => 'Password changed successfully'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => false, 
            'message' => 'Failed to change password: ' . $e->getMessage()
        ]);
    }
}