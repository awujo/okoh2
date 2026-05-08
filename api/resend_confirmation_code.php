<?php
require_once 'db.php';

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, username FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $username);

    if ($stmt->fetch()) {
        $stmt->close();

        // Generate new confirmation code
        $new_code = rand(100000, 999999);

        // Update code in database
        $update = $conn->prepare("UPDATE user SET code = ? WHERE id = ?");
        $update->bind_param("ii", $new_code, $id);
        $update->execute();

        // =================== SEND EMAIL VIA API ===================
        $apiUrl = 'https://white-rail-435258.hostingersite.com/okoh.php'; // Your API endpoint
        $apiKey = 'your-secret-api-key-here'; // Replace with your real API key

        $subject = 'Email Confirmation Code';
        $htmlBody = "
            <p>Hello $username,</p>
            <p>Your new confirmation code is: <strong>$new_code</strong></p>
            <p>Please use this code to verify your email address.</p>
            <p>— North Bridge Support</p>
        ";

        // Prepare payload
        $payload = json_encode([
            'email' => $email,
            'name' => $username,
            'subject' => $subject,
            'html' => $htmlBody
        ]);

        // Send using cURL
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-KEY: ' . $apiKey
        ]);

        $apiResponse = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log("cURL error: " . curl_error($ch));
            echo json_encode(['success' => false, 'message' => 'Failed to resend code.', 'error' => curl_error($ch)]);
        } elseif ($httpStatus !== 200) {
            error_log("API error (status $httpStatus): $apiResponse");
            echo json_encode(['success' => false, 'message' => 'Failed to resend code.', 'error' => $apiResponse]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Reset code resent.']);
        }

        curl_close($ch);
        // =================== END EMAIL VIA API ===================

    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
