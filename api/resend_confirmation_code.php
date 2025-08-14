<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'db.php';
require_once 'send_email.php';

require_once '../PHPMailer-master/src/Exception.php';
require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';


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

        $mail = new PHPMailer(true);
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $_ENV['SMTP_PORT'];

    $mail->setFrom($_ENV['SMTP_USER'], 'NORTH BRIDGE');
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
