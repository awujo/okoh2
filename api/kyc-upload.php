<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// File upload configuration
$uploadDir = __DIR__ . '/uploads/kyc/';
$allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
$allowedVideoTypes = ['video/mp4', 'video/quicktime'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
}

try {
    // Validate required fields
    $requiredFields = ['fullname', 'nid', 'gender', 'country', 'state', 'hobby'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            http_response_code(400);
            throw new Exception("Field {$field} is required");
        }
    }

    // Process ID document upload
    if (empty($_FILES['nid_file']['tmp_name'])) {
        throw new Exception('ID document is required');
    }

    $nidFile = $_FILES['nid_file'];
    $nidFileType = mime_content_type($nidFile['tmp_name']);
    
    if (!in_array($nidFileType, $allowedImageTypes)) {
        throw new Exception('ID document must be an image (JPEG, PNG, GIF)');
    }

    if ($nidFile['size'] > $maxFileSize) {
        throw new Exception('ID document must be less than 5MB');
    }

    $nidFileName = 'id_' . $user_id . '_' . time() . '.' . pathinfo($nidFile['name'], PATHINFO_EXTENSION);
    $nidFilePath = $uploadDir . $nidFileName;

    if (!move_uploaded_file($nidFile['tmp_name'], $nidFilePath)) {
        throw new Exception('Failed to upload ID document');
    }

    // Process selfie upload
    if (empty($_FILES['selfie_file']['tmp_name'])) {
        throw new Exception('Selfie is required');
    }

    $selfieFile = $_FILES['selfie_file'];
    $selfieFileType = mime_content_type($selfieFile['tmp_name']);
    
    if (!in_array($selfieFileType, array_merge($allowedImageTypes, $allowedVideoTypes))) {
        throw new Exception('Selfie must be an image or video (JPEG, PNG, GIF, MP4, MOV)');
    }

    if ($selfieFile['size'] > $maxFileSize) {
        throw new Exception('Selfie must be less than 5MB');
    }

    $selfieFileName = 'selfie_' . $user_id . '_' . time() . '.' . pathinfo($selfieFile['name'], PATHINFO_EXTENSION);
    $selfieFilePath = $uploadDir . $selfieFileName;

    if (!move_uploaded_file($selfieFile['tmp_name'], $selfieFilePath)) {
        throw new Exception('Failed to upload selfie');
    }

    // Prepare data for database
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $nid = $conn->real_escape_string($_POST['nid']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $country = $conn->real_escape_string($_POST['country']);
    $state = $conn->real_escape_string($_POST['state']);
    $hobby = $conn->real_escape_string($_POST['hobby']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert or update KYC record
        $stmt = $conn->prepare("INSERT INTO kyc (
            user_id, status, fullname, nid, gender, country, state, hobby, nid_url, selfie_url
        ) VALUES (?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            fullname = VALUES(fullname),
            nid = VALUES(nid),
            gender = VALUES(gender),
            country = VALUES(country),
            state = VALUES(state),
            hobby = VALUES(hobby),
            nid_url = VALUES(nid_url),
            selfie_url = VALUES(selfie_url),
            status = 'pending',
            created_at = NOW()");

        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param(
            "issssssss", 
            $user_id, 
            $fullname, 
            $nid, 
            $gender, 
            $country, 
            $state, 
            $hobby,
            $nidFileName,
            $selfieFileName
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }

        // Update user table to mark KYC as pending
        $updateUser = $conn->prepare("UPDATE user SET kyc_is_done = 0 WHERE id = ?");
        $updateUser->bind_param("i", $user_id);
        if (!$updateUser->execute()) {
            throw new Exception('Failed to update user status');
        }

        $conn->commit();

        echo json_encode([
            'status' => true,
            'message' => 'KYC submitted successfully!',
            'kyc_status' => 'pending'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        // Clean up uploaded files if DB operation failed
        if (file_exists($nidFilePath)) unlink($nidFilePath);
        if (file_exists($selfieFilePath)) unlink($selfieFilePath);
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}