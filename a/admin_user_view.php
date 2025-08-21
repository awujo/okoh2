<?php
// admin_user_view.php
require_once 'admin_auth.php';

if (!isAdminLoggedIn()) {
    header("Location: admin_login.php");
    exit;
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    header("Location: admin_users.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['suspend_user'])) {
        $suspend = (int)$_POST['suspend'];
        $stmt = $conn->prepare("UPDATE user SET is_suspended = ? WHERE id = ?");
        $stmt->bind_param("ii", $suspend, $user_id);
        $stmt->execute();
        $stmt->close();
    } 
    elseif (isset($_POST['verify_kyc'])) {
        $verify = (int)$_POST['verify'];
        $stmt = $conn->prepare("UPDATE user SET kyc_is_done = ? WHERE id = ?");
        $stmt->bind_param("ii", $verify, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    elseif (isset($_POST['update_balance'])) {
        $deposit_balance = (float)$_POST['deposit_balance'];
        $interest_balance = (float)$_POST['interest_balance'];
        
        $stmt = $conn->prepare("UPDATE user SET deposit_balance = ?, interest_balance = ? WHERE id = ?");
        $stmt->bind_param("ddi", $deposit_balance, $interest_balance, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM user WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: admin_users.php");
    exit;
}

// Get user deposits
$stmt = $conn->prepare("SELECT * FROM deposit WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$deposits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get user withdrawals
$stmt = $conn->prepare("SELECT * FROM withdrawal WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$withdrawals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get user investments
$stmt = $conn->prepare("SELECT * FROM investment WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$investments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get user KYC
$stmt = $conn->prepare("SELECT * FROM kyc WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$kyc = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get user support tickets
$stmt = $conn->prepare("SELECT * FROM support_ticket WHERE user_id = ? ORDER BY created DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - User Details</title>
    <style>
        .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .pending { background-color: #fff3cd; }
        .completed { background-color: #d4edda; }
        .rejected { background-color: #f8d7da; }
        .wallet-address { 
            max-width: 150px; 
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .wallet-address:hover {
            overflow: visible;
            white-space: normal;
            word-break: break-all;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            padding: 5px;
            z-index: 100;
            max-width: 300px;
        }
    </style>
    <style>
    /* Reset and base styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        line-height: 1.6;
        color: #333;
        background-color: #f5f7fa;
        padding: 20px;
    }

    /* Header styles */
    h1 {
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #3498db;
    }

    h2 {
        color: #2c3e50;
        margin-bottom: 15px;
    }

    /* Navigation */
    a {
        color: #3498db;
        text-decoration: none;
        padding: 5px 10px;
        border-radius: 4px;
    }

    a:hover {
        background-color: #3498db;
        color: white;
    }

    /* Section styling */
    .section {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 20px;
        margin-bottom: 25px;
    }

    /* Form elements */
    form {
        margin: 15px 0;
    }

    input, select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin: 5px 0;
    }

    button {
        background-color: #3498db;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    button:hover {
        background-color: #2980b9;
    }

    /* Table styles */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        background: white;
    }

    th {
        background-color: #f8f9fa;
        color: #2c3e50;
        font-weight: 600;
    }

    th, td {
        padding: 12px;
        border: 1px solid #e9ecef;
        text-align: left;
    }

    tr:hover {
        background-color: #f8f9fa;
    }

    /* Status colors */
    .pending {
        background-color: #fff3cd;
    }

    .completed {
        background-color: #d4edda;
    }

    .rejected {
        background-color: #f8d7da;
    }

    /* Wallet address tooltip */
    .wallet-address {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        position: relative;
    }

    .wallet-address:hover {
        overflow: visible;
        white-space: normal;
        word-break: break-all;
        position: absolute;
        background: white;
        border: 1px solid #ddd;
        padding: 8px;
        z-index: 100;
        max-width: 300px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-radius: 4px;
    }

    /* KYC images */
    img {
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Form layout improvements */
    .form-group {
        margin-bottom: 15px;
    }

    label {
        display: inline-block;
        margin-bottom: 5px;
        font-weight: 500;
    }

    /* Action buttons in tables */
    .action-btn {
        padding: 4px 8px;
        margin: 0 2px;
        border-radius: 3px;
        font-size: 0.9em;
    }
</style>

</head>
<body>
    <h1>User Details: <?= htmlspecialchars($user['username']) ?></h1>
    <a href="admin_users.php">Back to Users</a>
    
    <!-- User Info Section -->
    <div class="section">
        <h2>User Information</h2>
        <form method="post">
            <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
            <p><strong>Full Name:</strong> <?= htmlspecialchars($user['fullname']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($user['phone_number']) ?></p>
            <p><strong>Country:</strong> <?= htmlspecialchars($user['country']) ?></p>
            <p><strong>Registration Date:</strong> <?= $user['created_at'] ?></p>
            
            <div>
                <label><strong>Account Status:</strong></label>
                <select name="suspend">
                    <option value="0" <?= !$user['is_suspended'] ? 'selected' : '' ?>>Active</option>
                    <option value="1" <?= $user['is_suspended'] ? 'selected' : '' ?>>Suspended</option>
                </select>
                <button type="submit" name="suspend_user">Update Status</button>
            </div>
            
            <?php if ($kyc): ?>
            <div>
                <label><strong>KYC Status:</strong></label>
                <select name="verify">
                    <option value="0" <?= !$user['kyc_is_done'] ? 'selected' : '' ?>>Unverified</option>
                    <option value="1" <?= $user['kyc_is_done'] ? 'selected' : '' ?>>Verified</option>
                </select>
                <button type="submit" name="verify_kyc">Update KYC</button>
            </div>
            <?php endif; ?>
        </form>
        
        <form method="post">
            <h3>Update Balances</h3>
            <div>
                <label>Deposit Balance:</label>
                <input type="number" step="0.01" name="deposit_balance" value="<?= $user['deposit_balance'] ?>">
            </div>
            <div>
                <label>Interest Balance:</label>
                <input type="number" step="0.01" name="interest_balance" value="<?= $user['interest_balance'] ?>">
            </div>
            <button type="submit" name="update_balance">Update Balances</button>
        </form>
    </div>
    
    <!-- KYC Section -->
    <?php if ($kyc): ?>
    <div class="section">
        <h2>KYC Information</h2>
        <p><strong>Full Name:</strong> <?= htmlspecialchars($kyc['fullname']) ?></p>
        <p><strong>NID:</strong> <?= $kyc['nid'] ?></p>
        <p><strong>Gender:</strong> <?= $kyc['gender'] ? 'Male' : 'Female' ?></p>
        <p><strong>Country:</strong> <?= htmlspecialchars($kyc['country']) ?></p>
        <p><strong>State:</strong> <?= htmlspecialchars($kyc['state']) ?></p>
        <p><strong>Hobby:</strong> <?= htmlspecialchars($kyc['hobby']) ?></p>
        <p><strong>Status:</strong> <?= $kyc['status'] ?></p>
        
        <div>
            <h3>ID Document:</h3>
            <!-- <img src="../api/uploads/kyc/<?= $kyc['nid_url'] ?>" style="max-width: 500px; max-height: 300px;"> -->
             <img src="<?= $kyc['nid_url'] ?>" style="max-width: 500px; max-height: 300px;">

        </div>
        
        <div>
            <h3>Selfie:</h3>
            <img src="<?= $kyc['selfie_url'] ?>" style="max-width: 500px; max-height: 300px;">
            <!-- <img src="../api/uploads/kyc/<?= $kyc['selfie_url'] ?>" style="max-width: 500px; max-height: 300px;"> -->

        </div>
    </div>
    <?php endif; ?>
    
    <!-- Deposits Section -->
    <div class="section">
        <h2>Deposits</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Amount</th>
                <th>Gateway</th>
                <th>Status</th>
                <th>Hash</th>
                <th>Screenshot</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($deposits as $deposit): ?>
            <tr class="<?= strtolower($deposit['status']) ?>">
                <td><?= $deposit['id'] ?></td>
                <td><?= $deposit['amount'] ?></td>
                <td><?= htmlspecialchars($deposit['gateway']) ?></td>
                <td><?= $deposit['status'] ?></td>
                <td><?= $deposit['hash'] ?></td>
                <!-- <td><a href="../uploads/<?= $deposit['screenshot'] ?>">View Screenshot</a></td> -->
                <td><a href="<?= $deposit['screenshot'] ?>">View Screenshot</a></td>
                <td><?= $deposit['created_at'] ?></td>
                <td>
                    <?php if ($deposit['status'] === 'pending' || $deposit['status'] === 'rejected'): ?>
                        <a href="admin_deposit_action.php?id=<?= $deposit['id'] ?>&action=approve">Approve</a>
                        <a href="admin_deposit_action.php?id=<?= $deposit['id'] ?>&action=reject">Reject</a>
                    <?php else: ?>
                        <span>No actions</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <!-- Withdrawals Section -->
    <div class="section">
        <h2>Withdrawals</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Amount</th>
                <th>Wallet Type</th>
                <th>Wallet Address</th>
                <th>Gateway</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($withdrawals as $withdrawal): ?>
            <tr class="<?= strtolower($withdrawal['status']) ?>">
                <td><?= $withdrawal['id'] ?></td>
                <td><?= $withdrawal['amount'] ?></td>
                <td><?= ucfirst(str_replace('_', ' ', $withdrawal['wallet'])) ?></td>
                <td class="wallet-address" title="<?= htmlspecialchars($withdrawal['wallet_address']) ?>">
                    <?= htmlspecialchars($withdrawal['wallet_address']) ?>
                </td>
                <td><?= htmlspecialchars($withdrawal['gateway']) ?></td>
                <td><?= $withdrawal['status'] ?></td>
                <td><?= $withdrawal['created_at'] ?></td>
                <td>
                    <?php if ($withdrawal['status'] === 'pending'): ?>
                        <a href="admin_withdrawal_action.php?id=<?= $withdrawal['id'] ?>&action=approve">Approve</a>
                        <a href="admin_withdrawal_action.php?id=<?= $withdrawal['id'] ?>&action=reject">Reject</a>
                    <?php else: ?>
                        <span>No actions</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <!-- Investments Section -->
    <div class="section">
        <h2>Investments</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Plan</th>
                <th>Amount</th>
                <th>Interest Earned</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
            <?php foreach ($investments as $investment): ?>
            <tr>
                <td><?= $investment['id'] ?></td>
                <td><?= htmlspecialchars($investment['plan']) ?></td>
                <td><?= $investment['amount'] ?></td>
                <td><?= $investment['interest_earned'] ?></td>
                <td><?= $investment['status'] ?></td>
                <td><?= $investment['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <!-- Support Tickets Section -->
    <div class="section">
        <h2>Support Tickets</h2>
        <table>
            <tr>
                <th>Ticket ID</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($tickets as $ticket): ?>
            <tr>
                <td><?= htmlspecialchars($ticket['ticket_id']) ?></td>
                <td><?= htmlspecialchars($ticket['subject']) ?></td>
                <td><?= $ticket['status'] ?></td>
                <td><?= $ticket['created'] ?></td>
                <td><a href="admin_ticket_view.php?id=<?= $ticket['id'] ?>">View</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>