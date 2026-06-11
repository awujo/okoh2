<?php
// investment_cron.php
require_once 'db.php';

// Get all running investments
$stmt = $conn->prepare("SELECT * FROM investment WHERE status = 'running'");
$stmt->execute();
$investments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($investments as $investment) {

    // Calculate daily interest based on plan
    $daily_interest = 0;
    switch (strtolower($investment['plan'])) {
        case 'bronze':
            $daily_interest = $investment['amount'] * 0.06;
            break;
        case 'silver':
            $daily_interest = $investment['amount'] * 0.10;
            break;
        case 'gold':
            $daily_interest = $investment['amount'] * 0.12;
            break;
        case 'ultimate':
            $daily_interest = $investment['amount'] * 0.15;
            break;
        default:
            error_log("Unknown plan '{$investment['plan']}' for investment ID {$investment['id']} — skipped.");
            continue 2; // skip unknown plans
    }

    $daily_interest = round($daily_interest, 2);
    $new_days_count = $investment['days_count'] + 1;
    $is_completed   = ($new_days_count >= 31);
    $new_status     = $is_completed ? 'completed' : 'running';

    $conn->begin_transaction();

    try {
        // Update investment record
        $stmt = $conn->prepare("
            UPDATE investment
            SET interest_earned = interest_earned + ?,
                profit          = profit + ?,
                days_count      = ?,
                status          = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ddisi", $daily_interest, $daily_interest, $new_days_count, $new_status, $investment['id']);
        $stmt->execute();
        $stmt->close();

        // ✅ Always credit today's daily interest — every day including the last
        // (old code skipped this on completion day then re-added ALL 31 days = double pay)
        $stmt = $conn->prepare("UPDATE user SET interest_balance = interest_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $daily_interest, $investment['user_id']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        error_log("Investment ID {$investment['id']}: +\$$daily_interest interest | Day $new_days_count/31 | Status: $new_status");

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error on investment ID {$investment['id']}: " . $e->getMessage());
    }
}

$conn->close();
echo "Cron job completed successfully.";
?>