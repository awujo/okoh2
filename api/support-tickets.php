<?php
require 'auth.php';
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// Fetch support tickets
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get tickets with reply count
        $query = $conn->prepare("
            SELECT t.id, t.ticket_id, t.email, t.status, t.created, 
                   (SELECT COUNT(*) FROM ticket_replies r WHERE r.ticket_id = t.id) as replies
            FROM support_ticket t
            WHERE t.user_id = ?
            ORDER BY t.created DESC
        ");
        $query->bind_param("i", $user_id);
        $query->execute();
        $result = $query->get_result();
        
        $tickets = [];
        while ($row = $result->fetch_assoc()) {
            $tickets[] = [
                'id' => $row['id'],
                'ticket_id' => $row['ticket_id'],
                'email' => $row['email'],
                'status' => $row['status'],
                'date' => date('M d, Y h:i A', strtotime($row['created'])),
                'replies' => $row['replies']
            ];
        }
        
        echo json_encode(['status' => true, 'tickets' => $tickets]);
    } catch (Exception $e) {
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Create new ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($input['subject']) || empty($input['message'])) {
        echo json_encode(['status' => false, 'message' => 'Subject and message are required']);
        exit;
    }
    
    try {
        $conn->begin_transaction();
        
        // Generate unique ticket ID
        $ticket_id = 'TICKET-' . strtoupper(uniqid());
        
        // Get user email
        $userQuery = $conn->prepare("SELECT email FROM user WHERE id = ?");
        $userQuery->bind_param("i", $user_id);
        $userQuery->execute();
        $user = $userQuery->get_result()->fetch_assoc();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Create ticket
        $stmt = $conn->prepare("
            INSERT INTO support_ticket 
            (user_id, ticket_id, email, subject, status, created) 
            VALUES (?, ?, ?, ?, 'open', NOW())
        ");
        $stmt->bind_param("isss", $user_id, $ticket_id, $user['email'], $input['subject']);
        $stmt->execute();
        $ticketId = $conn->insert_id;
        
        // Add first message
        $stmt = $conn->prepare("
            INSERT INTO ticket_replies 
            (ticket_id, user_id, message, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $ticketId, $user_id, $input['message']);
        $stmt->execute();
        
        $conn->commit();
        echo json_encode([
            'status' => true, 
            'message' => 'Ticket created successfully', 
            'ticket_id' => $ticket_id
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'status' => false, 
            'message' => 'Failed to create ticket: ' . $e->getMessage()
        ]);
    }
}