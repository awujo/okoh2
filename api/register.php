<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';
require_once '../PHPMailer-master/src/Exception.php';
require_once 'db.php'; // database connection with $conn

use PHPMailer\PHPMailer\PHPMailer;

header("Content-Type: application/json");

// Collect POST data
$username = $_POST['username'] ?? '';
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$country = $_POST['country'] ?? '';
$phone = $_POST['phone_number'] ?? '';
$address = $_POST['address'] ?? '';
$state = $_POST['state'] ?? '';
$city = $_POST['city'] ?? '';
$zipcode = $_POST['zipcode'] ?? '';
$password = $_POST['password'] ?? '';

// Validate required fields
if (!$username || !$fullname || !$email || !$country || !$phone || !$address || !$state || !$city || !$zipcode || !$password) {
    echo json_encode(["success" => false, "message" => "All fields are required."]);
    exit;
}

// Check for existing email
$stmt = $conn->prepare("SELECT id FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Email already registered."]);
    exit;
}
$stmt->close();

// Prepare data
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$confirmation_code = rand(100000, 999999);

// Insert new user
$stmt = $conn->prepare("INSERT INTO user (
    username, fullname, email, password, country, google_id, phone_number,
    deposit_balance, interest_balance, referal_balance, referrer_id,
    email_is_confirmed, 2fa_is_done, 2fa_secret, kyc_is_done, is_suspended,
    address, state, zipcode, city, code
) VALUES (?, ?, ?, ?, ?, NULL, ?, 0, 0, 0, 0, 0, 0, 0, 0, 0, ?, ?, ?, ?, ?)");

$stmt->bind_param("ssssssssssi", $username, $fullname, $email, $hashed_password, $country, $phone, $address, $state, $zipcode, $city, $confirmation_code);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Registration failed. Try again."]);
    exit;
}
$stmt->close();

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

    $mail->setFrom($_ENV['SMTP_USER'], 'Nexora Capitals');
    $mail->addAddress($email, $fullname);
    $mail->isHTML(false);
    $mail->Subject = 'Confirm Your Email';
    $mail->Body = "Hi $fullname,\n\nYour confirmation code is: $confirmation_code";

    $mail->send();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Mailer Error: {$mail->ErrorInfo}"]);
}
