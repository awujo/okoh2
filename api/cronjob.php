<?php
// investment_cron.php
require_once 'db.php'; // Include your database connection file

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
            $daily_interest = $investment['amount'] * 0.06; // 6% monthly, divided by 31 days
            break;
        case 'silver':
            $daily_interest = $investment['amount'] * 0.10; // 10% monthly
            break;
        case 'gold':
            $daily_interest = $investment['amount'] * 0.12; // 12% monthly
            break;
        case 'ultimate':
            $daily_interest = $investment['amount'] * 0.15; // 15% monthly
            break;
    }

    // Round to 2 decimal places
    $daily_interest = round($daily_interest, 2);
    $new_days_count = $investment['days_count'] + 1;

    // Check if investment period is complete
    $is_completed = ($new_days_count >= 31);
    $new_status = $is_completed ? 'completed' : 'running';

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update investment record
        $stmt = $conn->prepare("UPDATE investment SET 
            interest_earned = interest_earned + ?,
            profit = profit + ?,
            days_count = ?,
            status = ?
            WHERE id = ?");
        $stmt->bind_param("ddisi", $daily_interest, $daily_interest, $new_days_count, $new_status, $investment['id']);
        $stmt->execute();
        $stmt->close();

        // Update user's interest balance (only if not completed, or if you want to add final day's interest)
        if (!$is_completed) {
            $stmt = $conn->prepare("UPDATE user SET interest_balance = interest_balance + ? WHERE id = ?");
            $stmt->bind_param("di", $daily_interest, $investment['user_id']);
            $stmt->execute();
            $stmt->close();
        }

        // If investment is completed, add all remaining interest to user's balance
        if ($is_completed) {
            // Get the total interest earned
            $stmt = $conn->prepare("SELECT interest_earned FROM investment WHERE id = ?");
            $stmt->bind_param("i", $investment['id']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $total_interest = $result['interest_earned'];
            $stmt->close();

            // Update user's balance with the full amount
            $stmt = $conn->prepare("UPDATE user SET interest_balance = interest_balance + ? WHERE id = ?");
            $stmt->bind_param("di", $total_interest, $investment['user_id']);
            $stmt->execute();
            $stmt->close();
        }

        // Commit transaction
        $conn->commit();

        // Log successful update
        error_log("Processed investment ID: {$investment['id']}, Added interest: $daily_interest, Days: $new_days_count, Status: $new_status");

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error processing investment ID: {$investment['id']} - " . $e->getMessage());
    }
}

$conn->close();
echo "Cron job completed successfully.";
?>