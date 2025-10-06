<?php
// Include necessary files for database connection
require_once 'db.php';

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

// ==================== SEND MAIL VIA API ====================
$apiUrl = 'https://lightslategray-clam-797439.hostingersite.com/okoh.php'; // Update this with your API URL
$apiKey = 'your-secret-api-key-here'; // Replace with your actual API key

// Prepare the email body content
$htmlBody = "
    <p>Your new reset code is: <strong>$code</strong></p>
";

// Prepare payload for API request
$payload = json_encode([
    'email' => $email,
    'name' => '',  // Optional: You can include a name if it's available
    'subject' => 'Your Reset Code (Resent)',
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

// Execute the API request and handle the response
$apiResponse = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    error_log('cURL error (Email API): ' . curl_error($ch));
    echo json_encode(['success' => false, 'message' => 'Failed to resend code.', 'error' => curl_error($ch)]);
} elseif ($httpStatus !== 200) {
    error_log("Email API returned status $httpStatus: $apiResponse");
    echo json_encode(['success' => false, 'message' => 'Failed to resend code.', 'error' => $apiResponse]);
} else {
    echo json_encode(['success' => true, 'message' => 'Reset code resent.']);
}

curl_close($ch);
// ==================== END MAIL VIA API ====================
?>
