<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'PHPMailer-master/src/PHPMailer.php';
require_once 'PHPMailer-master/src/SMTP.php';
require_once 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Validate input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Verify reCAPTCHA
$recaptchaSecret = '6LfL_lorAAAAACDa0nsftNgU99mqQA5NTKX2SBRV';
$recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

if (empty($recaptchaResponse)) {
    echo json_encode(['success' => false, 'message' => 'Please complete the CAPTCHA verification']);
    exit;
}

$recaptchaUrl = "https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecret}&response={$recaptchaResponse}";
$recaptcha = file_get_contents($recaptchaUrl);
$recaptcha = json_decode($recaptcha);

if (!$recaptcha || !$recaptcha->success) {
    echo json_encode(['success' => false, 'message' => "CAPTCHA verification failed. Please try again."]);
    exit;
}

$required_fields = ['name', 'email', 'subject', 'message'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
}

// Sanitize input
$name = htmlspecialchars(strip_tags($_POST['name']));
$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
$subject = htmlspecialchars(strip_tags($_POST['subject']));
$message = htmlspecialchars(strip_tags($_POST['message']));

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Create PHPMailer instance
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'support@nexoracapitals.com';
    $mail->Password = 'Hustle@001';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('support@nexoracapitals.com', 'Neroxa Capitals');
    $mail->addAddress('support@nexoracapitals.com', 'contact');
    $mail->addReplyTo($email, $name);

    // Content
    $mail->isHTML(true);
    $mail->Subject = "New Contact Form Submission: $subject";
    $email_body = "
        <h2>New Contact Form Submission</h2>
        <p><strong>Name:</strong> $name</p>
        <p><strong>Email:</strong> $email</p>
        <p><strong>Subject:</strong> $subject</p>
        <p><strong>Message:</strong></p>
        <p>$message</p>
        <p><strong>reCAPTCHA Score:</strong> " . (isset($recaptcha->score) ? $recaptcha->score : 'N/A') . "</p>
    ";
    $mail->Body = $email_body;
    $mail->AltBody = strip_tags($email_body);

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Mailer Error: {$mail->ErrorInfo}");
    echo json_encode(['success' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
}