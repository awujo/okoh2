<?php
// admin_delete_transaction.php
// Allows an admin to permanently delete a single deposit / withdrawal / investment
// transaction (i.e. "delete a transaction history" entry).
require_once 'admin_auth.php';

if (!isAdminLoggedIn()) {
    header("Location: admin_login.php");
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Only accept known transaction tables (see in/drawSQL-mysql-export-2025-05-30.sql)
$allowed_types = ['deposit', 'withdrawal', 'investment'];
if ($transaction_id <= 0 || !in_array($type, $allowed_types, true)) {
    $_SESSION['admin_message'] = "Invalid request: transaction could not be deleted.";
    header("Location: " . ($user_id > 0 ? "admin_user_view.php?id=$user_id" : "admin_users.php"));
    exit;
}

// CSRF protection – token is generated in the admin UI (admin_user_view.php)
if (empty($_SESSION['admin_delete_token']) || empty($token) || !hash_equals($_SESSION['admin_delete_token'], $token)) {
    $_SESSION['admin_message'] = "Deletion cancelled: confirmation token missing or invalid.";
    header("Location: " . ($user_id > 0 ? "admin_user_view.php?id=$user_id" : "admin_users.php"));
    exit;
}

// Fetch the transaction so we can adjust balances if needed
$stmt = $conn->prepare("SELECT * FROM `$type` WHERE id = ?");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaction) {
    $_SESSION['admin_message'] = ucfirst($type) . " #$transaction_id not found.";
    header("Location: " . ($user_id > 0 ? "admin_user_view.php?id=$user_id" : "admin_users.php"));
    exit;
}

$conn->begin_transaction();

try {
    // If this was a COMPLETED deposit that credited the user, remove the credit
    // so deleting the record keeps the ledger and the balance consistent.
    if ($type === 'deposit' && $transaction['status'] === 'completed') {
        // deposit.amount is BIGINT in the schema (see drawSQL export); cast for bind safety
        $amt = (int)$transaction['amount'];
        $uid = (int)$transaction['user_id'];
        $stmt = $conn->prepare("UPDATE user SET deposit_balance = GREATEST(deposit_balance - ?, 0) WHERE id = ?");
        $stmt->bind_param("di", $amt, $uid);
        $stmt->execute();
        $stmt->close();
    }

    // Delete the transaction row
    $stmt = $conn->prepare("DELETE FROM `$type` WHERE id = ?");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $_SESSION['admin_message'] = ucfirst($type) . " #$transaction_id deleted successfully.";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['admin_message'] = "Failed to delete " . ucfirst($type) . " #$transaction_id.";
    error_log("admin_delete_transaction error: " . $e->getMessage());
}

$target_user = (int)$transaction['user_id'];
header("Location: " . ($target_user > 0 ? "admin_user_view.php?id=$target_user" : "admin_users.php"));
exit;
?>
