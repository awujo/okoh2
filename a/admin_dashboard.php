<?php
// admin_dashboard.php
require_once 'admin_auth.php';

if (!isAdminLoggedIn()) {
    header("Location: admin_login.php");
    exit;
}

// Get statistics (same as before)
$stats = [
    'users' => [
        'total' => 0,
        'active' => 0,
        'suspended' => 0,
        'kyc_verified' => 0
    ],
    'transactions' => [
        'deposits' => ['total' => 0, 'pending' => 0, 'completed' => 0],
        'withdrawals' => ['total' => 0, 'pending' => 0, 'completed' => 0],
        'investments' => ['total' => 0, 'active' => 0, 'completed' => 0]
    ],
    'tickets' => [
        'total' => 0,
        'open' => 0,
        'closed' => 0
    ]
];

// User stats
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(is_suspended = 0) as active,
    SUM(is_suspended = 1) as suspended,
    SUM(kyc_is_done = 1) as kyc_verified
    FROM user");
$stmt->execute();
$userStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stats['users'] = [
    'total' => $userStats['total'],
    'active' => $userStats['active'],
    'suspended' => $userStats['suspended'],
    'kyc_verified' => $userStats['kyc_verified']
];

// Deposit stats
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(status = 'pending') as pending,
    SUM(status = 'completed') as completed
    FROM deposit");
$stmt->execute();
$depositStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stats['transactions']['deposits'] = [
    'total' => $depositStats['total'],
    'pending' => $depositStats['pending'],
    'completed' => $depositStats['completed']
];

// Withdrawal stats
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(status = 'pending') as pending,
    SUM(status = 'completed') as completed
    FROM withdrawal");
$stmt->execute();
$withdrawalStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stats['transactions']['withdrawals'] = [
    'total' => $withdrawalStats['total'],
    'pending' => $withdrawalStats['pending'],
    'completed' => $withdrawalStats['completed']
];

// Investment stats
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(status = 'running') as active,
    SUM(status = 'completed') as completed
    FROM investment");
$stmt->execute();
$investmentStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stats['transactions']['investments'] = [
    'total' => $investmentStats['total'],
    'active' => $investmentStats['active'],
    'completed' => $investmentStats['completed']
];

// Ticket stats
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(status = 'open') as open,
    SUM(status = 'closed') as closed
    FROM support_ticket");
$stmt->execute();
$ticketStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stats['tickets'] = [
    'total' => $ticketStats['total'],
    'open' => $ticketStats['open'],
    'closed' => $ticketStats['closed']
];

// Recent activities
$stmt = $conn->prepare("
    (SELECT 'deposit' as type, id, user_id, amount, status, created_at FROM deposit ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'withdrawal' as type, id, user_id, amount, status, created_at FROM withdrawal ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'investment' as type, id, user_id, amount, status, created_at FROM investment ORDER BY created_at DESC LIMIT 5)
    ORDER BY created_at DESC LIMIT 10
");
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get recent users (last 10 registered)
$stmt = $conn->prepare("SELECT id, username, email, created_at, is_suspended, kyc_is_done FROM user ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$recentUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
            --light-gray: #f5f6fa;
            --text-dark: #2c3e50;
            --text-light: #ffffff;
            --shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: var(--light-gray);
            color: var(--text-dark);
        }

        .container {
            width: 95%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: var(--primary-color);
            color: var(--text-light);
            padding: 1rem 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .nav {
            display: flex;
            gap: 20px;
        }

        .nav a {
            color: var(--text-light);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .nav a:hover {
            background-color: var(--secondary-color);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background-color: var(--text-light);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: var(--text-dark);
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent-color);
            margin: 10px 0;
        }

        .section {
            background-color: var(--text-light);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }

        .section h2 {
            margin-bottom: 20px;
            color: var(--text-dark);
        }

        /* Improved table styling for mobile */
        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            min-width: 600px; /* Ensures table doesn't get too narrow on desktop */
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: var(--light-gray);
            font-weight: 600;
        }

        .pending { background-color: #fff3cd; }
        .completed { background-color: #d4edda; }
        .running { background-color: #cce5ff; }
        .suspended { background-color: #ffdddd; }

        .view-link {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            white-space: nowrap;
        }

        .view-link:hover {
            text-decoration: underline;
        }

        .two-column {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        /* Mobile Responsiveness */
        @media screen and (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media screen and (max-width: 900px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }

        @media screen and (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .nav {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            /* Improved mobile table styling */
            table {
                font-size: 0.9rem;
                min-width: 480px; /* Reduce min-width for mobile */
            }
            
            th, td {
                padding: 8px;
            }
            
            /* Stack action buttons vertically on small screens */
            td:last-child {
                white-space: normal;
            }

            /* Make table container scrollable and fit viewport */
            .table-container {
                width: 100vw;
                margin-left: -10vw;
                padding-left: 10vw;
                padding-right: 10vw;
                box-sizing: border-box;
                max-width: 100vw;
            }
        }

        @media screen and (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 5px;
            }

            .section {
                padding: 8px;
            }
            
            /* Further optimize tables for very small screens */
            table {
                min-width: 350px;
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 6px 2px;
                font-size: 0.8rem;
            }

            .table-container {
                width: 100vw;
                margin-left: -10vw;
                padding-left: 10vw;
                padding-right: 10vw;
                box-sizing: border-box;
                max-width: 100vw;
            }
        }

        /* Special styling for Recent Users table */
        .user-table table {
            min-width: 350px; /* Even narrower for user list on mobile */
        }
        
        .user-table td:nth-child(2), 
        .user-table td:nth-child(3) {
            max-width: 90px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        @media (max-width: 400px) {
            .user-table td:nth-child(2), 
            .user-table td:nth-child(3) {
                max-width: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Admin Dashboard</h1>
            <div class="nav">
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_users.php">Users</a>
                <a href="admin_tickets.php">Tickets</a>
                <a href="admin_logout.php">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value"><?= $stats['users']['total'] ?></div>
                <div>Active: <?= $stats['users']['active'] ?></div>
                <div>Suspended: <?= $stats['users']['suspended'] ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Deposits</h3>
                <div class="value"><?= $stats['transactions']['deposits']['total'] ?></div>
                <div>Pending: <?= $stats['transactions']['deposits']['pending'] ?></div>
                <div>Completed: <?= $stats['transactions']['deposits']['completed'] ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Withdrawals</h3>
                <div class="value"><?= $stats['transactions']['withdrawals']['total'] ?></div>
                <div>Pending: <?= $stats['transactions']['withdrawals']['pending'] ?></div>
                <div>Completed: <?= $stats['transactions']['withdrawals']['completed'] ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Investments</h3>
                <div class="value"><?= $stats['transactions']['investments']['total'] ?></div>
                <div>Active: <?= $stats['transactions']['investments']['active'] ?></div>
                <div>Completed: <?= $stats['transactions']['investments']['completed'] ?></div>
            </div>
        </div>
        
        <div class="two-column">
            <div class="section">
                <h2>Recent Activities</h2>
                <div class="table-container">
                    <table>
                        <tr>
                            <th>Type</th>
                            <th>User ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                        <?php foreach ($activities as $activity): ?>
                        <tr class="<?= strtolower($activity['status']) ?>">
                            <td><?= ucfirst($activity['type']) ?></td>
                            <td><?= $activity['user_id'] ?></td>
                            <td><?= $activity['amount'] ?></td>
                            <td><?= $activity['status'] ?></td>
                            <td><?= date('M j', strtotime($activity['created_at'])) ?></td>
                            <td>
                                <?php if ($activity['type'] === 'deposit' && $activity['status'] === 'pending'): ?>
                                    <a href="admin_deposit_action.php?id=<?= $activity['id'] ?>&action=approve" class="view-link">Approve</a><br>
                                    <a href="admin_deposit_action.php?id=<?= $activity['id'] ?>&action=reject" class="view-link">Reject</a>
                                <?php elseif ($activity['type'] === 'withdrawal' && $activity['status'] === 'pending'): ?>
                                    <a href="admin_withdrawal_action.php?id=<?= $activity['id'] ?>&action=approve" class="view-link">Approve</a><br>
                                    <a href="admin_withdrawal_action.php?id=<?= $activity['id'] ?>&action=reject" class="view-link">Reject</a>
                                <?php else: ?>
                                    <a href="admin_user_view.php?id=<?= $activity['user_id'] ?>" class="view-link">View</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            
            <div class="section">
                <h2>Recent Users</h2>
                <div class="table-container user-table">
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr class="<?= $user['is_suspended'] ? 'suspended' : '' ?>">
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= date('M j', strtotime($user['created_at'])) ?></td>
                            <td>
                                <?= $user['is_suspended'] ? 'Suspended' : 'Active' ?>
                                <?= $user['kyc_is_done'] ? ' (KYC)' : '' ?>
                            </td>
                            <td>
                                <a href="admin_user_view.php?id=<?= $user['id'] ?>" class="view-link">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div style="margin-top: 15px; text-align: right;">
                    <a href="admin_users.php" class="view-link">View All Users →</a>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>Open Support Tickets</h2>
            <?php if ($stats['tickets']['open'] > 0): ?>
                <p>There are <?= $stats['tickets']['open'] ?> open tickets. <a href="admin_tickets.php" class="view-link">View Tickets</a></p>
            <?php else: ?>
                <p>No open support tickets.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>