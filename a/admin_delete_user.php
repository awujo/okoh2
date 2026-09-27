<?php
// admin_delete_user.php
// Allows an admin to permanently delete a user account AND all of its related
// data (deposits, withdrawals, investments, KYC records and support tickets).
require_once 'admin_auth.php';

if (!isAdminLoggedIn()) {
    header("Location: admin_login.php");
    exit;
}

// Accept both GET links (with confirmation token) and POST forms
$id    = isset($_POST['user_id']) ? (int)$_POST['user_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$token = isset($_POST['confirm_token']) ? $_POST['confirm_token'] : (isset($_GET['token']) ? $_GET['token'] : '');

if ($id <= 0) {
    header("Location: admin_users.php");
    exit;
}

// Simple CSRF protection: a token is generated in the admin UI and must match
if (empty($_SESSION['admin_delete_token']) || empty($token) || !hash_equals($_SESSION['admin_delete_token'], $token)) {
    $_SESSION['admin_message'] = "Deletion cancelled: confirmation token missing or invalid.";
    header("Location: admin_user_view.php?id=$id");
    exit;
}

// Make sure the user exists
$stmt = $conn->prepare("SELECT id, username FROM user WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['admin_message'] = "User not found.";
    header("Location: admin_users.php");
    exit;
}

$conn->begin_transaction();

try {
    // Order matters because of the foreign keys pointing at `user`
    $child_tables = ['deposit', 'withdrawal', 'investment', 'kyc', 'support_ticket'];
    foreach ($child_tables as $table) {
        $stmt = $conn->prepare("DELETE FROM `$table` WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    // Finally delete the account itself
    $stmt = $conn->prepare("DELETE FROM user WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    // One-time use token – regenerate on next page load
    unset($_SESSION['admin_delete_token']);
    $_SESSION['admin_message'] = "User \"" . $user['username'] . "\" (ID #$id) and all related transactions were deleted successfully.";
    header("Location: admin_users.php");
    exit;
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['admin_message'] = "Failed to delete user #" . $id . ".";
    error_log("admin_delete_user error: " . $e->getMessage());
    header("Location: admin_user_view.php?id=$id");
    exit;
}
?>
