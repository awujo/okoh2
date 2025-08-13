<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// Fetch user profile data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = $conn->prepare("SELECT username, fullname, email, phone_number, address, state, zipcode, city, country FROM user WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result()->fetch_assoc();
    
    if ($result) {
        // Split fullname into first and last name
        $nameParts = explode(' ', $result['fullname'], 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        echo json_encode([
            'status' => true,
            'data' => [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $result['email'],
                'phone' => $result['phone_number'],
                'address' => $result['address'],
                'state' => $result['state'],
                'zip' => $result['zipcode'],
                'city' => $result['city'],
                'country' => $result['country']
            ]
        ]);
    } else {
        echo json_encode(['status' => false, 'message' => 'User not found']);
    }
    exit;
}

// Update user profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $required = ['firstname', 'lastname', 'address', 'state', 'zip', 'city'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            echo json_encode(['status' => false, 'message' => "$field is required"]);
            exit;
        }
    }
    
    // Combine first and last name
    $fullname = $input['firstname'] . ' ' . $input['lastname'];
    $address = $input['address'];
    $state = $input['state'];
    $zip = $input['zip'];
    $city = $input['city'];
    
    try {
        $conn->begin_transaction();
        
        $stmt = $conn->prepare("UPDATE user SET fullname = ?, address = ?, state = ?, zipcode = ?, city = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $fullname, $address, $state, $zip, $city, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update profile');
        }
        
        $conn->commit();
        echo json_encode(['status' => true, 'message' => 'Profile updated successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    }
}