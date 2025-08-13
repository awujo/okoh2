<?php
session_start();
header("Content-Type: application/json");

// Check if the user is authenticated
if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'authenticated' => true,
        'user_id' => $_SESSION['user_id'],
        'user_email' => $_SESSION['email']
    ]);
} else {
    echo json_encode([
        'authenticated' => false,
        'message' => 'User is not authenticated.'
    ]);
}