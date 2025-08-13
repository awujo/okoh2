<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$ticketId = $_GET['id'] ?? null;
if (!$ticketId) {
    echo json_encode(['status' => false, 'message' => 'Ticket ID required']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get ticket details
    $stmt = $conn->prepare("
        SELECT id, ticket_id, subject, status, created, updated_at 
        FROM support_ticket 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $ticketId, $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        echo json_encode(['status' => false, 'message' => 'Ticket not found']);
        exit;
    }
    
    echo json_encode(['status' => true, 'ticket' => $result]);
    
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}