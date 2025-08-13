<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = $input['ticket_id'] ?? null;
$message = $input['message'] ?? null;

if (!$ticketId || !$message) {
    echo json_encode(['status' => false, 'message' => 'Ticket ID and message required']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Verify ticket belongs to user
    $stmt = $conn->prepare("SELECT id FROM support_ticket WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $ticketId, $userId);
    $stmt->execute();
    
    if (!$stmt->get_result()->num_rows) {
        throw new Exception('Ticket not found');
    }
    
    // Add reply
    $stmt = $conn->prepare("
        INSERT INTO ticket_replies (ticket_id, user_id, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $ticketId, $userId, $message);
    $stmt->execute();
    
    // Update ticket timestamp
    $stmt = $conn->prepare("
        UPDATE support_ticket SET updated_at = NOW() WHERE id = ?
    ");
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    
    $conn->commit();
    echo json_encode(['status' => true, 'message' => 'Reply added successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}