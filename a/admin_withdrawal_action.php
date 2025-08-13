<?php
// admin_withdrawal_action.php
require_once 'admin_auth.php';

if (!isAdminLoggedIn()) {
    header("Location: admin_login.php");
    exit;
}

$withdrawal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($withdrawal_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    header("Location: admin_users.php");
    exit;
}

// Get withdrawal info
$stmt = $conn->prepare("SELECT * FROM withdrawal WHERE id = ?");
$stmt->bind_param("i", $withdrawal_id);
$stmt->execute();
$withdrawal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$withdrawal) {
    header("Location: admin_users.php");
    exit;
}

// Process action
if ($action === 'approve') {
    $new_status = 'completed';
    
    // Update withdrawal status
    $stmt = $conn->prepare("UPDATE withdrawal SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $withdrawal_id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['admin_message'] = "Withdrawal #$withdrawal_id approved successfully";
} 
elseif ($action === 'reject') {
    $new_status = 'rejected';
    
    // Update withdrawal status
    $stmt = $conn->prepare("UPDATE withdrawal SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $withdrawal_id);
    $stmt->execute();
    $stmt->close();
    
    // Return funds to user if withdrawal was pending
    if ($withdrawal['status'] === 'pending') {
        $wallet_field = $withdrawal['wallet'] === 'deposit_wallet' ? 'deposit_balance' : 'interest_balance';
        
        $stmt = $conn->prepare("UPDATE user SET $wallet_field = $wallet_field + ? WHERE id = ?");
        $stmt->bind_param("di", $withdrawal['amount'], $withdrawal['user_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    $_SESSION['admin_message'] = "Withdrawal #$withdrawal_id rejected";
}

header("Location: admin_user_view.php?id=" . $withdrawal['user_id']);
exit;
?>