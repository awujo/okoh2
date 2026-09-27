<?php
// admin_edit_transaction.php
// Allows an admin to edit ANY detail of a transaction (deposit / withdrawal /
// investment), including backdating the created_at date & time.
//
// Usage:
//   GET  ?type=deposit&id=5&user_id=2          -> renders the edit form
//   POST (same query string)                   -> validates & saves changes
require_once 'admin_auth.php';

if (!isAdminLoggedIn()) {
    header("Location: admin_login.php");
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0);

// Only accept known transaction tables (see in/drawSQL-mysql-export-2025-05-30.sql)
$allowed_types = ['deposit', 'withdrawal', 'investment'];
if ($transaction_id <= 0 || !in_array($type, $allowed_types, true)) {
    $_SESSION['admin_message'] = "Invalid request: transaction could not be edited.";
    header("Location: " . ($user_id > 0 ? "admin_user_view.php?id=$user_id" : "admin_users.php"));
    exit;
}

// Load the transaction row
$stmt = $conn->prepare("SELECT * FROM `$type` WHERE id = ?");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaction) {
    $_SESSION['admin_message'] = ucfirst($type) . " #$transaction_id not found.";
    header("Location: " . ($user_id > 0 ? "admin_user_view.php?id=$user_id" : "admin_users.php"));
    exit;
}
$owner_id = (int)$transaction['user_id'];

// Editable fields per table (created_at handled separately as the backdate).
// type: amount | text | select | datetime
$field_defs = [
    'deposit' => [
        'transaction_id' => ['label' => 'Transaction ID', 'type' => 'text'],
        'gateway'        => ['label' => 'Gateway', 'type' => 'text'],
        'amount'         => ['label' => 'Amount', 'type' => 'amount'],
        'status'         => ['label' => 'Status', 'type' => 'select',
                             'options' => ['pending', 'completed', 'rejected']],
        'wallet'         => ['label' => 'Wallet', 'type' => 'text'],
        'type'           => ['label' => 'Type', 'type' => 'text'],
        'is_withdrawal_fee' => ['label' => 'Is Withdrawal Fee (0/1)', 'type' => 'number'],
    ],
    'withdrawal' => [
        'transaction_id'     => ['label' => 'Transaction ID', 'type' => 'text'],
        'amount'             => ['label' => 'Amount', 'type' => 'amount'],
        'withdrawable_amount'=> ['label' => 'Withdrawable Amount', 'type' => 'amount'],
        'wallet'             => ['label' => 'Wallet', 'type' => 'text'],
        'type'               => ['label' => 'Type', 'type' => 'text'],
        'wallet_address'     => ['label' => 'Wallet Address', 'type' => 'text'],
        'gateway'            => ['label' => 'Gateway', 'type' => 'text'],
        'status'             => ['label' => 'Status', 'type' => 'select',
                                 'options' => ['pending', 'completed', 'rejected']],
    ],
    'investment' => [
        'transaction_id' => ['label' => 'Transaction ID', 'type' => 'text'],
        'plan'           => ['label' => 'Plan', 'type' => 'text'],
        'amount'         => ['label' => 'Amount', 'type' => 'amount'],
        'interest'       => ['label' => 'Interest', 'type' => 'number', 'optional' => true],
        'days_count'     => ['label' => 'Days Count', 'type' => 'number'],
        'status'         => ['label' => 'Status', 'type' => 'select',
                             'options' => ['pending', 'running', 'completed', 'rejected']],
    ],
];
$fields = $field_defs[$type];

$errors = [];
$is_post = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($is_post) {
    // CSRF protection - same token used by the delete features
    $token = $_POST['confirm_token'] ?? '';
    if (empty($_SESSION['admin_delete_token']) || empty($token) || !hash_equals($_SESSION['admin_delete_token'], $token)) {
        $_SESSION['admin_message'] = "Edit cancelled: confirmation token missing or invalid.";
        header("Location: admin_user_view.php?id=$owner_id");
        exit;
    }

    // Gather submitted values
    $new = [];
    foreach ($fields as $col => $def) {
        $val = $_POST['fields'][$col] ?? null;
        if ($val === null) {
            continue; // field not presented -> leave untouched
        }
        $val = trim($val);
        switch ($def['type']) {
            case 'amount':
                // Schema stores amounts as BIGINT, so normalise to whole numbers.
                if ($val === '') {
                    $errors[] = "'{$def['label']}' cannot be empty.";
                } elseif (!is_numeric($val)) {
                    $errors[] = "'{$def['label']}' must be a valid number.";
                } elseif ((float)$val < 0) {
                    $errors[] = "'{$def['label']}' must be a non-negative number.";
                } else {
                    $new[$col] = (int)round((float)$val);
                }
                break;
            case 'number':
                // Optional numeric columns (e.g. investment.interest) may not exist on
                // every live server -> skip them entirely when left blank so we never
                // reference a missing column in the UPDATE statement.
                if ($val === '' && !empty($def['optional'])) {
                    continue 2;
                } elseif ($val === '') {
                    $new[$col] = 0;
                } elseif (!is_numeric($val)) {
                    $errors[] = "'{$def['label']}' must be a valid number.";
                } elseif ((float)$val < 0) {
                    $errors[] = "'{$def['label']}' must be a non-negative number.";
                } else {
                    $new[$col] = (int)round((float)$val);
                }
                break;
            case 'select':
                if (!in_array($val, $def['options'], true)) {
                    $errors[] = "'{$def['label']}' has an invalid value.";
                } else {
                    $new[$col] = $val;
                }
                break;
            default: // text
                if ($val === '') {
                    $errors[] = "'{$def['label']}' cannot be empty.";
                } else {
                    $new[$col] = $val;
                }
        }
    }

    // Backdate: the created-at date & time of the transaction
    $new_created_at = trim($_POST['created_at'] ?? '');
    $backdated = false;
    if ($new_created_at !== '') {
        $dt = date_create($new_created_at);
        if ($dt === false) {
            $errors[] = "Created At must be a valid date/time (YYYY-MM-DD HH:MM:SS).";
        } else {
            $new_created_at = $dt->format('Y-m-d H:i:s');
            if ($new_created_at !== $transaction['created_at']) {
                $backdated = true;
            }
            $new['created_at'] = $new_created_at;
        }
    }

    if (empty($errors) && !empty($new)) {
        $conn->begin_transaction();
        try {
            // Keep ledger consistent when the AMOUNT or STATUS of a deposit changes.
            // user.deposit_balance is BIGINT in the schema, so store rounded whole amounts.
            if ($type === 'deposit') {
                $old_amt = (float)$transaction['amount'];
                $new_amt = isset($new['amount']) ? round((float)$new['amount']) : $old_amt;
                $new['amount'] = $new_amt;

                $was_completed = ($transaction['status'] === 'completed');
                $now_completed = isset($new['status']) ? ($new['status'] === 'completed') : $was_completed;

                $delta = 0.0;
                if ($was_completed && $now_completed) {
                    $delta = $new_amt - $old_amt;              // amount changed on a completed deposit
                } elseif (!$was_completed && $now_completed) {
                    $delta = $new_amt;                          // newly credited
                } elseif ($was_completed && !$now_completed) {
                    $delta = -$old_amt;                         // credit removed
                }
                if (abs($delta) > 0.001) {
                    $stmt = $conn->prepare(
                        "UPDATE user SET deposit_balance = GREATEST(deposit_balance + ?, 0) WHERE id = ?"
                    );
                    $d = $delta;
                    $stmt->bind_param("di", $d, $owner_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Build one UPDATE from the whitelisted columns actually submitted
            $set_parts = [];
            $values = [];
            $types_str = '';
            foreach ($new as $col => $val) {
                if (!array_key_exists($col, $fields) && $col !== 'created_at') {
                    continue; // strict whitelist
                }
                $set_parts[] = "`$col` = ?";
                $values[] = $val;
                $types_str .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
            }
            $types_str .= 'i'; // trailing id
            $values[] = $transaction_id;

            $sql = "UPDATE `$type` SET " . implode(', ', $set_parts) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types_str, ...$values);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $_SESSION['admin_message'] = ucfirst($type) . " #$transaction_id updated successfully"
                . ($backdated ? " (created-at backdated to {$new['created_at']})" : ".");
        } catch (Exception $e) {
            $conn->rollback();
            // Include the real DB error so admins can see WHY it failed
            $_SESSION['admin_message'] = "Failed to update " . ucfirst($type) . " #$transaction_id. Reason: " . $e->getMessage();
            error_log("admin_edit_transaction error: " . $e->getMessage());
        }
        header("Location: admin_user_view.php?id=$owner_id");
        exit;
    }
    // If we get here there were validation errors -> re-render the form with them
}

// For GET requests (or POST with errors) make sure a CSRF token exists
if (empty($_SESSION['admin_delete_token'])) {
    $_SESSION['admin_delete_token'] = bin2hex(random_bytes(16));
}
$delete_token = $_SESSION['admin_delete_token'];

// Merge submitted values over DB values so the form shows what the admin typed
$current = array_merge($transaction, []);
if ($is_post) {
    foreach ($fields as $col => $def) {
        if (isset($_POST['fields'][$col])) {
            $current[$col] = $_POST['fields'][$col];
        }
    }
    if (trim($_POST['created_at'] ?? '') !== '') {
        $current['created_at'] = $_POST['created_at'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Edit <?= htmlspecialchars($type) ?> #<?= $transaction_id ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f5f7fa; padding: 20px; }
        h1 { color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #3498db; }
        a { color: #3498db; text-decoration: none; }
        .section { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 25px; max-width: 700px; }
        label { display: block; font-weight: 600; margin-top: 12px; }
        input[type=text], input[type=number], select, input[type=datetime-local] {
            width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-top: 4px; font-size: 14px;
        }
        button { margin-top: 20px; padding: 10px 18px; border: none; border-radius: 4px; background: #3498db; color: white; font-size: 15px; cursor: pointer; }
        button:hover { background: #2980b9; }
        .errors { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; }
        .errors ul { margin-left: 20px; }
        .hint { font-size: 12px; color: #777; margin-top: 3px; }
    </style>
</head>
<body>
    <h1>Edit <?= htmlspecialchars(ucfirst($type)) ?> #<?= $transaction_id ?></h1>
    <?php if (!empty($errors)): ?>
        <div class="errors"><ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <a href="admin_user_view.php?id=<?= $owner_id ?>">&larr; Back to User Details</a>

    <div class="section">
        <form method="post">
            <input type="hidden" name="confirm_token" value="<?= htmlspecialchars($delete_token) ?>">
            <input type="hidden" name="id" value="<?= $transaction_id ?>">
            <input type="hidden" name="user_id" value="<?= $owner_id ?>">

            <?php foreach ($fields as $col => $def): ?>
                <label for="f_<?= $col ?>"><?= htmlspecialchars($def['label']) ?></label>
                <?php $val = htmlspecialchars($current[$col] ?? '', ENT_QUOTES); ?>
                <?php if ($def['type'] === 'select'): ?>
                    <select id="f_<?= $col ?>" name="fields[<?= $col ?>]">
                        <?php foreach ($def['options'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= ($current[$col] ?? '') === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($def['type'] === 'amount'): ?>
                    <input type="number" step="1" min="0" id="f_<?= $col ?>" name="fields[<?= $col ?>]" value="<?= $val ?>">
                <?php elseif ($def['type'] === 'number'): ?>
                    <input type="number" step="1" id="f_<?= $col ?>" name="fields[<?= $col ?>]" value="<?= $val ?>">
                <?php else: ?>
                    <input type="text" id="f_<?= $col ?>" name="fields[<?= $col ?>]" value="<?= $val ?>">
                <?php endif; ?>
            <?php endforeach; ?>

            <label for="created_at">Created At (Date &amp; Time)</label>
            <input type="datetime-local" id="created_at" name="created_at"
                   value="<?= htmlspecialchars(substr($current['created_at'] ?? '', 0, 16), ENT_QUOTES) ?>">
            <p class="hint">Set this to any past date/time to backdate the transaction. Leave unchanged to keep the original.</p>

            <button type="submit">Save Changes</button>
        </form>
    </div>
</body>
</html>
