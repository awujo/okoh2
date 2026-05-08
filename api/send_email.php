
<?php
// ==================== SEND MAIL VIA API ====================
function sendEmail($to, $subject, $body) {
    $apiUrl = 'https://white-rail-435258.hostingersite.com/okoh.php'; // API URL for sending emails
    $apiKey = 'your-secret-api-key-here'; // Replace with your actual API key

    // Prepare payload
    $payload = json_encode([
        'email' => $to,
        'name' => 'Recipient Name',  // You can modify this to include the recipient's name if available
        'subject' => $subject,
        'html' => $body
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

    // Execute and handle response
    $apiResponse = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        error_log('cURL error (Email API): ' . curl_error($ch));
        return ['success' => false, 'error' => 'cURL error: ' . curl_error($ch)];
    } elseif ($httpStatus !== 200) {
        error_log("Email API returned status $httpStatus: $apiResponse");
        return ['success' => false, 'error' => 'API error: ' . $apiResponse];
    } else {
        error_log("Email API Response: $apiResponse");
        return ['success' => true, 'message' => "Email sent to {$to}"];
    }

    curl_close($ch);
}
// ==================== END MAIL VIA API ====================
?>
