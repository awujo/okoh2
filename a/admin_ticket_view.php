<?php
// admin_ticket_view.php
require_once 'admin_auth.php';

if (!isAdminLoggedIn()) {
    header("Location: admin_login.php");
    exit;
}

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ticket_id <= 0) {
    header("Location: admin_tickets.php");
    exit;
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $message = trim($_POST['reply_message']);
    if (!empty($message)) {
        $admin_id = $_SESSION['admin_id'];
        
        $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, admin_id, message, is_admin_reply) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("iis", $ticket_id, $admin_id, $message);
        $stmt->execute();
        $stmt->close();
        
        // Update ticket status if it was closed
        if (isset($_POST['close_ticket'])) {
            $stmt = $conn->prepare("UPDATE support_ticket SET status = 'closed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("i", $ticket_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("UPDATE support_ticket SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("i", $ticket_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $_SESSION['admin_message'] = "Reply added successfully";
    }
}

// Get ticket info
$stmt = $conn->prepare("SELECT t.*, u.username, u.email FROM support_ticket t JOIN user u ON t.user_id = u.id WHERE t.id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    header("Location: admin_tickets.php");
    exit;
}

// Get ticket replies
$stmt = $conn->prepare("SELECT r.*, a.username as admin_username FROM ticket_replies r LEFT JOIN admin a ON r.admin_id = a.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$replies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Ticket View</title>
    <style>
        .ticket-info { margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border: 1px solid #ddd; }
        .reply { margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .user-reply { background-color: #e9ecef; }
        .admin-reply { background-color: #d4edda; }
        .reply-form { margin-top: 30px; }
        .reply-meta { font-size: 0.8em; color: #6c757d; margin-bottom: 5px; }
    </style>
    <style>
    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        line-height: 1.6;
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        background-color: #f5f5f5;
    }

    h1 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }

    a {
        color: #3498db;
        text-decoration: none;
        padding: 8px 15px;
        background-color: #fff;
        border-radius: 4px;
        border: 1px solid #3498db;
        display: inline-block;
        margin: 10px 0;
    }

    a:hover {
        background-color: #3498db;
        color: #fff;
    }

    .ticket-info {
        margin-bottom: 20px;
        padding: 20px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .reply {
        margin-bottom: 15px;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .user-reply {
        background-color: #fff;
        border-left: 4px solid #3498db;
    }

    .admin-reply {
        background-color: #f8f9fa;
        border-left: 4px solid #2ecc71;
    }

    .reply-meta {
        font-size: 0.9em;
        color: #7f8c8d;
        margin-bottom: 8px;
        border-bottom: 1px solid #eee;
        padding-bottom: 5px;
    }

    .reply-form {
        margin-top: 30px;
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-family: inherit;
        margin-bottom: 15px;
    }

    button {
        background-color: #2ecc71;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }

    button:hover {
        background-color: #27ae60;
    }

    label {
        display: block;
        margin-bottom: 15px;
        color: #34495e;
    }

    input[type="checkbox"] {
        margin-right: 8px;
    }

    h2, h3 {
        color: #2c3e50;
        margin-top: 20px;
    }
</style>

</head>
<body>
    <h1>Ticket: <?= htmlspecialchars($ticket['ticket_id']) ?></h1>
    <a href="admin_tickets.php">Back to Tickets</a>
    
    <div class="ticket-info">
        <p><strong>User:</strong> <?= htmlspecialchars($ticket['username']) ?> (<?= htmlspecialchars($ticket['email']) ?>)</p>
        <p><strong>Subject:</strong> <?= htmlspecialchars($ticket['subject']) ?></p>
        <p><strong>Status:</strong> <?= $ticket['status'] ?></p>
        <p><strong>Created:</strong> <?= $ticket['created'] ?></p>
        <p><strong>Last Updated:</strong> <?= $ticket['updated_at'] ?></p>
    </div>
    
    <h2>Conversation</h2>
    <?php foreach ($replies as $reply): ?>
        <div class="reply <?= $reply['is_admin_reply'] ? 'admin-reply' : 'user-reply' ?>">
            <div class="reply-meta">
                <?php if ($reply['is_admin_reply']): ?>
                    <strong>Admin</strong> 
                    <?php if ($reply['admin_username']): ?>
                        (<?= htmlspecialchars($reply['admin_username']) ?>)
                    <?php endif; ?>
                <?php else: ?>
                    <strong>User</strong>
                <?php endif; ?>
                - <?= $reply['created_at'] ?>
            </div>
            <p><?= nl2br(htmlspecialchars($reply['message'])) ?></p>
        </div>
    <?php endforeach; ?>
    
    <div class="reply-form">
        <h3>Add Reply</h3>
        <form method="post">
            <div>
                <textarea name="reply_message" rows="5" style="width: 100%;" required></textarea>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="close_ticket" value="1">
                    Close ticket after reply
                </label>
            </div>
            <button type="submit">Send Reply</button>
        </form>
    </div>
</body>
</html>