<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php'; // database connection with $conn

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

// ==================== SEND CONFIRMATION EMAIL VIA API ====================
$apiUrl = 'https://white-rail-435258.hostingersite.com/okoh.php'; // Your email API endpoint
$apiKey = 'your-secret-api-key-here'; // Replace with your actual API key

$subject = 'Confirm Your Email';
$htmlBody = "
    <p>Hi $fullname,</p>
    <p>Your confirmation code is: <strong>$confirmation_code</strong></p>
    <p>Use this code to verify your email address on North Bridge.</p>
    <p>If you did not register, please ignore this email.</p>
    <p>— North Bridge Support</p>
";

// Prepare API payload
$payload = json_encode([
    'email' => $email,
    'name' => $fullname,
    'subject' => $subject,
    'html' => $htmlBody
]);

// Initialize cURL
$ch = curl_init($apiUrl);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-KEY: ' . $apiKey
]);

// Execute request and check response
$apiResponse = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    error_log('cURL error: ' . curl_error($ch));
    echo json_encode(['success' => false, 'message' => 'Failed to send confirmation email.', 'error' => curl_error($ch)]);
} elseif ($httpStatus !== 200) {
    error_log("Email API HTTP $httpStatus: $apiResponse");
    echo json_encode(['success' => false, 'message' => 'Failed to send confirmation email.', 'error' => $apiResponse]);
} else {
    echo json_encode(["success" => true, "message" => "Registration successful. Confirmation email sent."]);
}

curl_close($ch);
// ==================== END EMAIL VIA API ====================
