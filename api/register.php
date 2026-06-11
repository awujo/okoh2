<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

header("Content-Type: application/json");

// ─────────────────────────────────────────
// 1. COLLECT & SANITIZE POST DATA
// ─────────────────────────────────────────
$username  = trim($_POST['username']      ?? '');
$fullname  = trim($_POST['fullname']      ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$country   = trim($_POST['country']       ?? '');
$phone     = trim($_POST['phone_number']  ?? '');
$address   = trim($_POST['address']       ?? '');
$state     = trim($_POST['state']         ?? '');
$city      = trim($_POST['city']          ?? '');
$zipcode   = trim($_POST['zipcode']       ?? '');
$password  = $_POST['password']           ?? '';

// ─────────────────────────────────────────
// 2. VALIDATE REQUIRED FIELDS
// ─────────────────────────────────────────
if (!$username || !$fullname || !$email || !$country || !$phone ||
    !$address  || !$state   || !$city  || !$zipcode || !$password) {
    echo json_encode(["success" => false, "message" => "All fields are required."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email address."]);
    exit;
}

// ─────────────────────────────────────────
// 3. CHECK FOR DUPLICATE EMAIL OR USERNAME
// ─────────────────────────────────────────
$stmt = $conn->prepare("SELECT id FROM user WHERE email = ? OR username = ?");
$stmt->bind_param("ss", $email, $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Email or username already registered."]);
    $stmt->close();
    exit;
}
$stmt->close();

// ─────────────────────────────────────────
// 4. PREPARE & INSERT USER
// ─────────────────────────────────────────
$hashed_password   = password_hash($password, PASSWORD_DEFAULT);
$confirmation_code = rand(100000, 999999);

$stmt = $conn->prepare("
    INSERT INTO user (
        username, fullname, email, password, country, google_id, phone_number,
        deposit_balance, interest_balance, referal_balance, referrer_id,
        email_is_confirmed, 2fa_is_done, 2fa_secret, kyc_is_done, is_suspended,
        address, state, zipcode, city, code
    ) VALUES (
        ?, ?, ?, ?, ?, NULL, ?,
        0, 0, 0, 0,
        0, 0, 0, 0, 0,
        ?, ?, ?, ?, ?
    )
");

$stmt->bind_param(
    "ssssssssssi",
    $username, $fullname, $email, $hashed_password, $country, $phone,
    $address, $state, $zipcode, $city, $confirmation_code
);

if (!$stmt->execute()) {
    error_log("Registration DB error: " . $stmt->error);
    echo json_encode(["success" => false, "message" => "Registration failed. Please try again."]);
    $stmt->close();
    exit;
}
$stmt->close();

// ─────────────────────────────────────────
// 5. SEND CONFIRMATION EMAIL
// ─────────────────────────────────────────

// ✅ Must match $API_KEY in okoh.php exactly
$apiUrl = 'https://white-rail-435258.hostingersite.com/okoh.php';
$apiKey = 'northbridge-secret-2025'; // ← set your real key here, same in okoh.php

$subject  = 'Confirm Your Email – North Bridge';
$htmlBody = "
    <p>Hi " . htmlspecialchars($fullname) . ",</p>
    <p>Thank you for registering on <strong>North Bridge Investments</strong>.</p>
    <p>Your confirmation code is:</p>
    <h2 style='letter-spacing:6px; font-size:32px;'>$confirmation_code</h2>
    <p>Enter this code on the verification page to activate your account.</p>
    <p>If you did not register, please ignore this email.</p>
    <br>
    <p>— North Bridge Support</p>
";

$payload = json_encode([
    'email'   => $email,
    'name'    => $fullname,
    'subject' => $subject,
    'html'    => $htmlBody,
]);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey,
    ],
]);

$apiResponse = curl_exec($ch);
$httpStatus  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError   = curl_errno($ch) ? curl_error($ch) : null;
curl_close($ch);

// ─────────────────────────────────────────
// 6. RESPOND BASED ON EMAIL RESULT
// ─────────────────────────────────────────
if ($curlError) {
    error_log("cURL error: $curlError");
    echo json_encode([
        "success" => false,
        "message" => "Account created but confirmation email could not be sent. Contact support.",
    ]);
    exit;
}

if ($httpStatus !== 200) {
    error_log("Email API HTTP $httpStatus: $apiResponse");
    echo json_encode([
        "success" => false,
        "message" => "Account created but confirmation email failed. Contact support.",
        "debug"   => $apiResponse, // remove this line in production
    ]);
    exit;
}

echo json_encode([
    "success"  => true,
    "message"  => "Registration successful! Please check your email for the confirmation code.",
    "redirect" => "getin/verify.html",
]);
exit;
?>