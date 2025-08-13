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
    // Verify ticket belongs to user
    $stmt = $conn->prepare("SELECT id FROM support_ticket WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $ticketId, $userId);
    $stmt->execute();
    
    if (!$stmt->get_result()->num_rows) {
        echo json_encode(['status' => false, 'message' => 'Ticket not found']);
        exit;
    }
    
    // Get messages
    $stmt = $conn->prepare("
        SELECT id, message, created_at, is_admin_reply 
        FROM ticket_replies 
        WHERE ticket_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    echo json_encode(['status' => true, 'messages' => $messages]);
    
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}