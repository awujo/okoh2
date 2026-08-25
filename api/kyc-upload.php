<?php
require_once 'auth.php';
require_once 'db.php';

// ✅ Fix autoload path
require __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// ✅ Init Cloudinary (like your deposit code)
$cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => 'dgkv0zo7l',
        'api_key'    => '163423683817439',
        'api_secret' => 'oP0RbDzfjmsByZ84J2hH5JRqmiA'
    ]
]);

function uploadErrorMessage($field, $error) {
    switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "{$field} is too large. Please upload a smaller image.";
        case UPLOAD_ERR_PARTIAL:
            return "{$field} upload was interrupted. Please try again.";
        case UPLOAD_ERR_NO_FILE:
            return "{$field} is required";
        default:
            return "{$field} upload failed (error code {$error}). Please try again.";
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

    // Upload NID file
    $nidError = $_FILES['nid_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($nidError !== UPLOAD_ERR_OK) {
        throw new Exception(uploadErrorMessage('ID document', $nidError));
    }
    $nidResult = $cloudinary->uploadApi()->upload($_FILES['nid_file']['tmp_name'], [
        "folder" => "kyc/id_docs",
        "public_id" => "id_" . $user_id . "_" . time()
    ]);
    $nidUrl = $nidResult['secure_url'];

    // Upload selfie
    $selfieError = $_FILES['selfie_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($selfieError !== UPLOAD_ERR_OK) {
        throw new Exception(uploadErrorMessage('Selfie', $selfieError));
    }
    $selfieResult = $cloudinary->uploadApi()->upload($_FILES['selfie_file']['tmp_name'], [
        "folder" => "kyc/selfies",
        "public_id" => "selfie_" . $user_id . "_" . time(),
        "resource_type" => "auto" // allows image or video
    ]);
    $selfieUrl = $selfieResult['secure_url'];

    // Save to DB (same as before but store URLs)
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $nid = $conn->real_escape_string($_POST['nid']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $country = $conn->real_escape_string($_POST['country']);
    $state = $conn->real_escape_string($_POST['state']);
    $hobby = $conn->real_escape_string($_POST['hobby']);

    $conn->begin_transaction();

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

    $stmt->bind_param(
        "issssssss",
        $user_id,
        $fullname,
        $nid,
        $gender,
        $country,
        $state,
        $hobby,
        $nidUrl,
        $selfieUrl
    );
    if (!$stmt->execute()) {
        throw new Exception('Failed to save KYC record: ' . $stmt->error);
    }

    $updateUser = $conn->prepare("UPDATE user SET kyc_is_done = 0 WHERE id = ?");
    $updateUser->bind_param("i", $user_id);
    if (!$updateUser->execute()) {
        throw new Exception('Failed to update user status: ' . $updateUser->error);
    }

    $conn->commit();

    echo json_encode([
        'status' => true,
        'message' => 'KYC submitted successfully!',
        'nid_url' => $nidUrl,
        'selfie_url' => $selfieUrl
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
