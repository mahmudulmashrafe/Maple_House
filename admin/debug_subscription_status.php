<?php
/**
 * Debug Subscription Status - Check why it shows "Renewal Due" after payment
 */

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get residents with their subscription details
$debug_query = "SELECT r.id, r.user_id, r.plan_id, r.payment_status,
                       u.first_name, u.last_name,
                       pp.plan_name, pp.monthly_fee,
                       rh.payment_date, rh.subscription_start_date, rh.subscription_end_date,
                       rh.renewal_due_date, rh.grace_period_end, rh.is_active,
                       CURDATE() as current_date,
                       DATEDIFF(rh.subscription_end_date, CURDATE()) as days_until_end,
                       CASE 
                           WHEN pp.monthly_fee = 0 THEN 'basic'
                           WHEN rh.renewal_due_date IS NULL THEN 'no_subscription'
                           WHEN CURDATE() <= rh.subscription_end_date THEN 'current'
                           WHEN CURDATE() > rh.subscription_end_date AND CURDATE() <= rh.grace_period_end THEN 'renewal_due'
                           WHEN CURDATE() > rh.grace_period_end THEN 'overdue'
                           ELSE 'current'
                       END as calculated_status
                FROM residents r
                JOIN users u ON r.user_id = u.id
                LEFT JOIN payment_plans pp ON r.plan_id = pp.id
                LEFT JOIN (
                    SELECT resident_id, payment_date, subscription_start_date, subscription_end_date, 
                           renewal_due_date, grace_period_end, is_active
                    FROM resident_revenue_history 
                    WHERE is_active = 1
                    ORDER BY created_at DESC
                ) rh ON r.id = rh.resident_id
                WHERE pp.monthly_fee > 0
                ORDER BY r.id DESC
                LIMIT 10";

$debug_stmt = $db->prepare($debug_query);
$debug_stmt->execute();
$residents = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Subscription Status - Maple House</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
        th { background-color: #f2f2f2; }
        .current { background-color: #d4edda; }
        .renewal_due { background-color: #fff3cd; }
        .overdue { background-color: #f8d7da; }
        .no_subscription { background-color: #e2e3e5; }
    </style>
</head>
<body>
    <h1>🔍 Debug Subscription Status</h1>
    
    <h3>Current Date: <?php echo date('Y-m-d'); ?></h3>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Resident</th>
                <th>Plan</th>
                <th>Payment Status</th>
                <th>Payment Date</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Renewal Due</th>
                <th>Grace End</th>
                <th>Days Until End</th>
                <th>Calculated Status</th>
                <th>Is Active</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($residents as $resident): ?>
            <tr class="<?php echo $resident['calculated_status']; ?>">
                <td><?php echo $resident['id']; ?></td>
                <td><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></td>
                <td><?php echo htmlspecialchars($resident['plan_name']); ?><br>৳<?php echo number_format($resident['monthly_fee']); ?></td>
                <td><?php echo $resident['payment_status']; ?></td>
                <td><?php echo $resident['payment_date'] ?? 'N/A'; ?></td>
                <td><?php echo $resident['subscription_start_date'] ?? 'N/A'; ?></td>
                <td><?php echo $resident['subscription_end_date'] ?? 'N/A'; ?></td>
                <td><?php echo $resident['renewal_due_date'] ?? 'N/A'; ?></td>
                <td><?php echo $resident['grace_period_end'] ?? 'N/A'; ?></td>
                <td><?php echo $resident['days_until_end'] ?? 'N/A'; ?></td>
                <td><strong><?php echo $resident['calculated_status']; ?></strong></td>
                <td><?php echo $resident['is_active'] ? 'Yes' : 'No'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h3>📋 Status Logic Explanation:</h3>
    <ul>
        <li><strong>Current (Green):</strong> Today ≤ Subscription End Date</li>
        <li><strong>Renewal Due (Yellow):</strong> Today > Subscription End Date AND Today ≤ Grace Period End</li>
        <li><strong>Overdue (Red):</strong> Today > Grace Period End</li>
        <li><strong>No Subscription:</strong> No active revenue history record</li>
    </ul>
    
    <h3>🔧 Expected Timeline:</h3>
    <ul>
        <li><strong>Day 1-30:</strong> Current (subscription is active)</li>
        <li><strong>Day 31-32:</strong> Renewal Due (grace period)</li>
        <li><strong>Day 33+:</strong> Overdue (should be downgraded)</li>
    </ul>
    
    <?php if (empty($residents)): ?>
    <p><strong>No residents with paid plans found.</strong> Add some residents with paid plans first.</p>
    <?php endif; ?>
    
    <p><a href="residents.php">← Back to Residents Management</a></p>
    
    <h3>🛠️ Quick Fix Actions:</h3>
    <p>If you see residents showing "Renewal Due" immediately after payment, the issue is likely:</p>
    <ol>
        <li><strong>Date Logic:</strong> Check if subscription_end_date is set correctly (should be 30 days from payment)</li>
        <li><strong>Active Records:</strong> Check if old revenue history records are still marked as active</li>
        <li><strong>Query Logic:</strong> Verify the CASE statement logic matches the expected timeline</li>
    </ol>
</body>
</html>
