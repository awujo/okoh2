<?php
// admin_tickets.php
require_once 'admin_auth.php';

if (!isAdminLoggedIn()) {
    header("Location: admin_login.php");
    exit;
}

$status = isset($_GET['status']) ? $_GET['status'] : 'open';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get tickets count
$query = "SELECT COUNT(*) as total FROM support_ticket";
if ($status !== 'all') {
    $query .= " WHERE status = ?";
}

$stmt = $conn->prepare($query);
if ($status !== 'all') {
    $stmt->bind_param("s", $status);
}
$stmt->execute();
$totalResult = $stmt->get_result();
$totalTickets = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalTickets / $limit);
$stmt->close();

// Get tickets
$query = "SELECT t.*, u.username FROM support_ticket t JOIN user u ON t.user_id = u.id";
if ($status !== 'all') {
    $query .= " WHERE t.status = ?";
}
$query .= " ORDER BY t.created DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if ($status !== 'all') {
    $stmt->bind_param("sii", $status, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Support Tickets</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .open { background-color: #fff3cd; }
        .closed { background-color: #d4edda; }
        .filter { margin-bottom: 20px; }
    </style>
    <style>
    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 20px;
        background-color: #f5f5f5;
    }

    h1 {
        color: #2c3e50;
        margin-bottom: 30px;
        padding-bottom: 10px;
        border-bottom: 2px solid #eee;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background-color: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    th, td {
        padding: 12px 15px;
        border: 1px solid #e0e0e0;
        text-align: left;
    }

    th {
        background-color: #f8f9fa;
        color: #2c3e50;
        font-weight: 600;
    }

    tr:hover {
        background-color: #f8f9fa;
    }

    .open {
        background-color: #fff8e6;
    }

    .closed {
        background-color: #f0f9f0;
    }

    .filter {
        margin-bottom: 25px;
        padding: 15px;
        background-color: white;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .filter a {
        text-decoration: none;
        color: #3498db;
        padding: 5px 10px;
        margin: 0 5px;
        border-radius: 3px;
    }

    .filter a:hover {
        background-color: #3498db;
        color: white;
    }

    .pagination {
        margin-top: 20px;
        text-align: center;
    }

    .pagination a {
        display: inline-block;
        padding: 8px 12px;
        margin: 0 4px;
        border: 1px solid #ddd;
        text-decoration: none;
        color: #3498db;
        border-radius: 3px;
        background-color: white;
    }

    .pagination a:hover {
        background-color: #3498db;
        color: white;
        border-color: #3498db;
    }

    td a {
        color: #3498db;
        text-decoration: none;
        padding: 5px 10px;
        border-radius: 3px;
        background-color: #f8f9fa;
    }

    td a:hover {
        background-color: #3498db;
        color: white;
    }
</style>

</head>
<body>
    <h1>Support Tickets</h1>
    
    <div class="filter">
        <strong>Filter by status:</strong>
        <a href="?status=open" <?= $status === 'open' ? 'style="font-weight:bold;"' : '' ?>>Open</a> |
        <a href="?status=closed" <?= $status === 'closed' ? 'style="font-weight:bold;"' : '' ?>>Closed</a> |
        <a href="?status=all" <?= $status === 'all' ? 'style="font-weight:bold;"' : '' ?>>All</a>
    </div>
    
    <table>
        <tr>
            <th>Ticket ID</th>
            <th>User</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Date</th>
            <th>Last Updated</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($tickets as $ticket): ?>
        <tr class="<?= $ticket['status'] ?>">
            <td><?= htmlspecialchars($ticket['ticket_id']) ?></td>
            <td><?= htmlspecialchars($ticket['username']) ?></td>
            <td><?= htmlspecialchars($ticket['subject']) ?></td>
            <td><?= $ticket['status'] ?></td>
            <td><?= $ticket['created'] ?></td>
            <td><?= $ticket['updated_at'] ?></td>
            <td><a href="admin_ticket_view.php?id=<?= $ticket['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&status=<?= $status ?>">Previous</a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= $status ?>" <?= $i == $page ? 'style="font-weight:bold;"' : '' ?>>
                <?= $i ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page+1 ?>&status=<?= $status ?>">Next</a>
        <?php endif; ?>
    </div>
</body>
</html>