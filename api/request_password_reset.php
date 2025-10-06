<?php
// Include necessary files for database connection
require_once 'db.php';

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';
if (!$email) exit(json_encode(['success' => false, 'message' => 'Email is required']));

// Check if the user exists in the database
$stmt = $conn->prepare("SELECT id FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) exit(json_encode(['success' => false, 'message' => 'Email not found.']));

$code = rand(100000, 999999);

// Save the code to the database
$stmt = $conn->prepare("UPDATE user SET code = ? WHERE email = ?");
$stmt->bind_param("is", $code, $email);
$stmt->execute();

// ==================== SEND MAIL VIA API ====================
$apiUrl = 'https://lightslategray-clam-797439.hostingersite.com/okoh.php'; // Update with your actual API URL
$apiKey = 'your-secret-api-key-here'; // Replace with your actual API key

// Prepare the email body content
$htmlBody = "
    <p>Your reset code is: <strong>$code</strong></p>
";

// Prepare the payload for the API request
$payload = json_encode([
    'email' => $email,
    'name' => '',  // Optional: You can include a name if available
    'subject' => 'Your Password Reset Code',
    'html' => $htmlBody
]);

// Initialize cURL to send the email via the API
$ch = curl_init($apiUrl);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-KEY: ' . $apiKey
]);

// Execute the API request and handle the response
$apiResponse = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    error_log('cURL error (Email API): ' . curl_error($ch));
    echo json_encode(['success' => false, 'message' => 'Failed to send email.', 'error' => curl_error($ch)]);
} elseif ($httpStatus !== 200) {
    error_log("Email API returned status $httpStatus: $apiResponse");
    echo json_encode(['success' => false, 'message' => 'Failed to send email.', 'error' => $apiResponse]);
} else {
    echo json_encode(['success' => true, 'message' => 'Reset code sent to your email.']);
}

curl_close($ch);
// ==================== END MAIL VIA API ====================
?>
