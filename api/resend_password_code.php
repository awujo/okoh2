<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'db.php';
require_once '../PHPMailer-master/src/Exception.php';
require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';

header('Content-Type: application/json');
$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
if (!$email) {
    exit(json_encode(['success' => false, 'message' => 'Email is required.']));
}

// Generate and save code
$code = rand(100000, 999999);
$stmt = $conn->prepare("UPDATE user SET code = ? WHERE email = ?");
$stmt->bind_param("is", $code, $email);
$stmt->execute();

// Send email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $_ENV['SMTP_PORT'];

    $mail->setFrom('support@northbridgeinvestments.com', 'NORTH BRIDGE');
    $mail->addAddress($email);
    $mail->isHTML(false);
    $mail->Subject = 'Your Reset Code (Resent)';
    $mail->Body = "Your new reset code is: $code";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Reset code resent.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to resend code.', 'error' => $mail->ErrorInfo]);
}
