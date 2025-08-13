<?php
// admin_deposit_action.php
require_once 'admin_auth.php';

if (!isAdminLoggedIn()) {
    header("Location: admin_login.php");
    exit;
}

$deposit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($deposit_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    header("Location: admin_users.php");
    exit;
}

// Get deposit info
$stmt = $conn->prepare("SELECT * FROM deposit WHERE id = ?");
$stmt->bind_param("i", $deposit_id);
$stmt->execute();
$deposit = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$deposit) {
    header("Location: admin_users.php");
    exit;
}

// Process action
if ($action === 'approve') {
    $new_status = 'completed';
    
    // Update deposit status
    $stmt = $conn->prepare("UPDATE deposit SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $deposit_id);
    $stmt->execute();
    $stmt->close();
    
    // Add to user's balance if not already done
    if ($deposit['status'] !== 'completed') {
        $stmt = $conn->prepare("UPDATE user SET deposit_balance = deposit_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $deposit['amount'], $deposit['user_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    $_SESSION['admin_message'] = "Deposit #$deposit_id approved successfully";
} 
elseif ($action === 'reject') {
    $new_status = 'rejected';
    
    // Update deposit status
    $stmt = $conn->prepare("UPDATE deposit SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $deposit_id);
    $stmt->execute();
    $stmt->close();
    
    // Deduct from user's balance if deposit was previously approved
    if ($deposit['status'] === 'completed') {
        $stmt = $conn->prepare("UPDATE user SET deposit_balance = deposit_balance - ? WHERE id = ?");
        $stmt->bind_param("di", $deposit['amount'], $deposit['user_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    $_SESSION['admin_message'] = "Deposit #$deposit_id rejected";
}

header("Location: admin_user_view.php?id=" . $deposit['user_id']);
exit;
?>