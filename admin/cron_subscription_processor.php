<?php
/**
 * Automated Subscription Processor
 * This script should be run daily via cron job to automatically process overdue residents
 * 
 * Cron job setup (run daily at 2 AM):
 * 0 2 * * * /usr/bin/php /path/to/Maple_House/admin/cron_subscription_processor.php
 */

// Prevent direct web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line.');
}

require_once dirname(__DIR__) . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

$log_messages = [];
$processed_count = 0;
$error_count = 0;

function logMessage($message) {
    global $log_messages;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    $log_messages[] = $log_message;
    echo $log_message . "\n";
}

try {
    logMessage("Starting automated subscription processing...");
    
    $db->beginTransaction();
    
    // 1. Find residents who are past their grace period (day 32)
    $overdue_query = "SELECT r.id, r.user_id, r.plan_id, u.first_name, u.last_name, 
                             pp.plan_name, pp.monthly_fee,
                             rh.grace_period_end, rh.subscription_end_date, rh.transaction_id
                     FROM residents r
                     JOIN users u ON r.user_id = u.id
                     JOIN payment_plans pp ON r.plan_id = pp.id
                     JOIN resident_revenue_history rh ON r.id = rh.resident_id
                     WHERE rh.is_active = 1 
                     AND rh.grace_period_end < CURDATE()
                     AND r.payment_status != 'Paid'
                     AND pp.monthly_fee > 0
                     ORDER BY rh.grace_period_end ASC";
    
    $overdue_stmt = $db->prepare($overdue_query);
    $overdue_stmt->execute();
    $overdue_residents = $overdue_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Found " . count($overdue_residents) . " overdue residents to process");
    
    foreach ($overdue_residents as $resident) {
        try {
            logMessage("Processing resident: {$resident['first_name']} {$resident['last_name']} (ID: {$resident['id']})");
            
            // Move to basic plan (assuming basic plan has id = 1)
            $basic_plan_query = "SELECT id FROM payment_plans WHERE monthly_fee = 0 LIMIT 1";
            $basic_plan_stmt = $db->prepare($basic_plan_query);
            $basic_plan_stmt->execute();
            $basic_plan = $basic_plan_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$basic_plan) {
                throw new Exception("Basic plan not found in database");
            }
            
            $basic_plan_id = $basic_plan['id'];
            
            // Update resident's plan to basic
            $downgrade_query = "UPDATE residents 
                               SET plan_id = :basic_plan_id, 
                                   payment_status = 'Overdue',
                                   updated_at = CURRENT_TIMESTAMP
                               WHERE id = :resident_id";
            $downgrade_stmt = $db->prepare($downgrade_query);
            $downgrade_stmt->bindParam(':basic_plan_id', $basic_plan_id);
            $downgrade_stmt->bindParam(':resident_id', $resident['id']);
            $downgrade_stmt->execute();
            
            // Mark current revenue history as inactive
            $deactivate_query = "UPDATE resident_revenue_history 
                                SET is_active = 0, 
                                    notes = CONCAT(COALESCE(notes, ''), ' | Auto-downgraded to basic plan on ', CURDATE()),
                                    updated_at = CURRENT_TIMESTAMP
                                WHERE resident_id = :resident_id AND is_active = 1";
            $deactivate_stmt = $db->prepare($deactivate_query);
            $deactivate_stmt->bindParam(':resident_id', $resident['id']);
            $deactivate_stmt->execute();
            
            // Create a downgrade record in revenue history
            $transaction_id = 'AUTO_' . date('Y') . '_' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            $downgrade_record_query = "INSERT INTO resident_revenue_history 
                                      (user_id, resident_id, plan_id, transaction_id, amount, payment_type, 
                                       payment_date, subscription_start_date, subscription_end_date, 
                                       renewal_due_date, grace_period_end, payment_method, processed_by, 
                                       notes, payment_status) 
                                      VALUES 
                                      (:user_id, :resident_id, :plan_id, :transaction_id, 0, 'downgrade', 
                                       CURDATE(), CURDATE(), '9999-12-31', 
                                       '9999-12-31', '9999-12-31', 'system', 1, 
                                       'Automatically downgraded to basic plan due to non-payment', 'cancelled')";
            
            $downgrade_record_stmt = $db->prepare($downgrade_record_query);
            $downgrade_record_stmt->bindParam(':user_id', $resident['user_id']);
            $downgrade_record_stmt->bindParam(':resident_id', $resident['id']);
            $downgrade_record_stmt->bindParam(':plan_id', $basic_plan_id);
            $downgrade_record_stmt->bindParam(':transaction_id', $transaction_id);
            $downgrade_record_stmt->execute();
            
            logMessage("Successfully downgraded resident {$resident['first_name']} {$resident['last_name']} to basic plan");
            $processed_count++;
            
        } catch (Exception $e) {
            logMessage("Error processing resident {$resident['first_name']} {$resident['last_name']}: " . $e->getMessage());
            $error_count++;
        }
    }
    
    // 2. Update payment status for residents approaching renewal
    $renewal_reminder_query = "UPDATE residents r
                              JOIN resident_revenue_history rh ON r.id = rh.resident_id
                              SET r.payment_status = 'Pending'
                              WHERE rh.is_active = 1 
                              AND rh.renewal_due_date <= CURDATE()
                              AND rh.grace_period_end >= CURDATE()
                              AND r.payment_status = 'Paid'";
    
    $renewal_reminder_stmt = $db->prepare($renewal_reminder_query);
    $renewal_reminder_stmt->execute();
    $reminder_count = $renewal_reminder_stmt->rowCount();
    
    logMessage("Updated payment status to 'Pending' for $reminder_count residents with due renewals");
    
    $db->commit();
    
    logMessage("Subscription processing completed successfully");
    logMessage("Summary: $processed_count residents downgraded, $reminder_count renewal reminders set, $error_count errors");
    
} catch (Exception $e) {
    $db->rollBack();
    logMessage("CRITICAL ERROR: " . $e->getMessage());
    $error_count++;
}

// Write log to file
$log_dir = dirname(__DIR__) . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/subscription_processor_' . date('Y-m-d') . '.log';
file_put_contents($log_file, implode("\n", $log_messages) . "\n", FILE_APPEND | LOCK_EX);

// Send email notification if there were errors (optional)
if ($error_count > 0) {
    logMessage("Errors occurred during processing. Check log file: $log_file");
    // TODO: Implement email notification for errors
}

logMessage("Log written to: $log_file");

// Exit with appropriate code
exit($error_count > 0 ? 1 : 0);
?>
