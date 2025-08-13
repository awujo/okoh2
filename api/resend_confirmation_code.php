<?php
require_once 'db.php';
require_once 'send_email.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id, username FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $username);
    if ($stmt->fetch()) {
        $new_code = rand(100000, 999999);
        $stmt->close();

        $update = $conn->prepare("UPDATE user SET code = ? WHERE id = ?");
        $update->bind_param("ii", $new_code, $id);
        $update->execute();

        $mail = new PHPMailer\PHPMailer\PHPMailer();
$mail->isSMTP();
$mail->Host = 'smtp.hostinger.com';
$mail->SMTPAuth = true;
$mail->Username = 'support@nexoracapitals.com';
$mail->Password = 'Hustle@001';
$mail->SMTPSecure = 'tls';
$mail->Port = 587;

$mail->setFrom('support@nexoracapitals.com', 'Neroxa Capitals');
$mail->addAddress($email);
$mail->Subject = 'Email Confirmation Code';
$mail->Body = "Hello $username, your new confirmation code is: <b>$new_code</b>";

        if ($mail->send()) {
            echo json_encode(['success' => true, 'message' => 'Reset code resent.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to resend code.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
    }
} else {
    echo json_encode(['status' => 'invalid_method']);
}
?>
