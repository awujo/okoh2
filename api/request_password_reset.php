<?php
require_once 'db.php';
require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';
require_once '../PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';
if (!$email) exit(json_encode(['success' => false, 'message' => 'Email is required']));

// Check user exists
$stmt = $conn->prepare("SELECT id FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) exit(json_encode(['success' => false, 'message' => 'Email not found.']));

$code = rand(100000, 999999);

// Save code to `code` column
$stmt = $conn->prepare("UPDATE user SET code = ? WHERE email = ?");
$stmt->bind_param("is", $code, $email);
$stmt->execute();

// Send email
$mail = new PHPMailer;
$mail->isSMTP();
$mail->Host = $_ENV['SMTP_HOST'];  // Set this properly
$mail->SMTPAuth = true;
$mail->Username = $_ENV['SMTP_USER'];
$mail->Password = $_ENV['SMTP_PASS'];
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = $_ENV['SMTP_PORT'];

$mail->setFrom($_ENV['SMTP_USER'], 'NORTH BRIDGE');
$mail->isHTML(true);
$mail->addAddress($email);
$mail->Subject = 'Your Password Reset Code';
$mail->Body = "Your reset code is: $code";

if ($mail->send()) {
    echo json_encode(['success' => true, 'message' => 'Reset code sent to your email.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send email.']);
}
