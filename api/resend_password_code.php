<?php
require_once 'db.php';
require_once '../PHPMailer-master/PHPMailerAutoload.php';

header('Content-Type: application/json');
$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
if (!$email) exit(json_encode(['success' => false, 'message' => 'Email is required.']));

// Same logic as initial reset
$code = rand(100000, 999999);
$stmt = $conn->prepare("UPDATE user SET code = ? WHERE email = ?");
$stmt->bind_param("is", $code, $email);
$stmt->execute();

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
$mail->Subject = 'Your Reset Code (Resent)';
$mail->Body = "Your new reset code is: $code";

if ($mail->send()) {
    echo json_encode(['success' => true, 'message' => 'Reset code resent.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to resend code.']);
}
