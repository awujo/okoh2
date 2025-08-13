<?php
require_once 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $code = trim($_POST['code']);

    $stmt = $conn->prepare("SELECT id, email_is_confirmed, code FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $is_confirmed, $db_code);
    if ($stmt->fetch()) {
        if ($is_confirmed) {
            echo json_encode(['status' => 'already_verified']);
        } elseif ($code == $db_code) {
            $stmt->close();
            $update = $conn->prepare("UPDATE user SET email_is_confirmed = 1 WHERE id = ?");
            $update->bind_param("i", $id);
            $update->execute();
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'invalid_code']);
        }
    } else {
        echo json_encode(['status' => 'email_not_found']);
    }
    // $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'invalid_method']);
}
?>
