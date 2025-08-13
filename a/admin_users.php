<?php
// admin_users.php
require_once 'admin_auth.php';

if (!isAdminLoggedIn()) {
    header("Location: admin_login.php");
    exit;
}

// Get all users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';

$query = "SELECT * FROM user";
$countQuery = "SELECT COUNT(*) as total FROM user";

if (!empty($search)) {
    $query .= " WHERE username LIKE ? OR email LIKE ? OR fullname LIKE ?";
    $countQuery .= " WHERE username LIKE ? OR email LIKE ? OR fullname LIKE ?";
    $searchTerm = "%$search%";
}

$query .= " LIMIT ? OFFSET ?";

// Get total count
$stmt = $conn->prepare($countQuery);
if (!empty($search)) {
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
}
$stmt->execute();
$totalResult = $stmt->get_result();
$totalUsers = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $limit);
$stmt->close();

// Get users
$stmt = $conn->prepare($query);
if (!empty($search)) {
    $stmt->bind_param("sssii", $searchTerm, $searchTerm, $searchTerm, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- HTML for admin users listing -->
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Users</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .suspended { background-color: #ffdddd; }
    </style>
    <title>Admin - Users</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            padding: 20px;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ddd;
        }

        form {
            margin: 20px 0;
        }

        input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 300px;
            font-size: 14px;
        }

        button {
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background-color: #0056b3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin: 20px 0;
        }

        th, td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .suspended {
            background-color: #fff3f3;
        }

        .pagination {
            margin: 20px 0;
            text-align: center;
        }

        .pagination a {
            padding: 8px 12px;
            margin: 0 4px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #007bff;
            border-radius: 4px;
        }

        .pagination a:hover {
            background-color: #f8f9fa;
        }

        td a {
            text-decoration: none;
            color: #007bff;
            margin-right: 10px;
            padding: 4px 8px;
            border: 1px solid #007bff;
            border-radius: 3px;
        }

        td a:hover {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <h1>User Management</h1>
    
    <form method="get">
        <input type="text" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Search</button>
    </form>
    
    <table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Full Name</th>
            <th>Balance</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($users as $user): ?>
        <tr class="<?= $user['is_suspended'] ? 'suspended' : '' ?>">
            <td><?= $user['id'] ?></td>
            <td><?= htmlspecialchars($user['username']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['fullname']) ?></td>
            <td>
                Deposit: <?= $user['deposit_balance'] ?><br>
                Interest: <?= $user['interest_balance'] ?>
            </td>
            <td>
                <?= $user['is_suspended'] ? 'Suspended' : 'Active' ?><br>
                <?= $user['kyc_is_done'] ? 'KYC Verified' : 'KYC Not Verified' ?>
            </td>
            <td>
                <a href="admin_user_view.php?id=<?= $user['id'] ?>">View</a>
                <a href="admin_user_edit.php?id=<?= $user['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">Previous</a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" <?= $i == $page ? 'style="font-weight:bold;"' : '' ?>>
                <?= $i ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">Next</a>
        <?php endif; ?>
    </div>
</body>
</html>