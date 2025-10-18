<?php
/**
 * Monthly Salary Renewal Cron Job
 * 
 * This script should be run daily via cron job.
 * It automatically processes monthly salary renewals on the 10th of each month
 * for all active staff members (doctors, chefs, general staff).
 * 
 * Cron job setup (run daily at 2 AM):
 * 0 2 * * * /usr/bin/php /path/to/Maple_House/admin/cron_monthly_salary_renewal.php
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die('This script can only be run from command line or with manual_run parameter');
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Log file for cron job
$log_file = __DIR__ . '/logs/salary_renewal_' . date('Y-m') . '.log';
$log_dir = dirname($log_file);

// Create logs directory if it doesn't exist
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    echo "[$timestamp] $message\n"; // Also output to console
}

writeLog("=== Monthly Salary Renewal Cron Job Started ===");

try {
    $current_day = date('j'); // Day of month (1-31)
    $current_month = date('Y-m');
    $salary_month = date('Y-m-01'); // First day of current month
    
    writeLog("Current day: $current_day, Processing month: $current_month");
    
    // Only process on the 10th of each month
    if ($current_day != 10 && !isset($_GET['manual_run'])) {
        writeLog("Not the 10th of the month. Skipping salary renewal.");
        writeLog("=== Cron Job Completed (No Action) ===");
        exit(0);
    }
    
    if (isset($_GET['manual_run'])) {
        writeLog("Manual run triggered via web interface");
    }
    
    // Check if we've already processed this month
    $check_processed = $db->prepare("SELECT COUNT(*) FROM staff_salaries WHERE salary_month = ? AND notes LIKE '%Auto-renewal on 10th%'");
    $check_processed->execute([$salary_month]);
    $already_processed = $check_processed->fetchColumn();
    
    if ($already_processed > 0) {
        writeLog("Monthly renewals already processed for $current_month. Found $already_processed existing records.");
        writeLog("=== Cron Job Completed (Already Processed) ===");
        exit(0);
    }
    
    // Get all active staff who don't have salary for current month
    $staff_query = "SELECT u.id, u.first_name, u.last_name, u.email, r.name as role_name,
                    CASE 
                        WHEN u.role_id = 3 THEN COALESCE(d.monthly_salary, 45000)  -- Doctor
                        WHEN u.role_id = 4 THEN COALESCE(c.monthly_salary, 35000)  -- Chef
                        WHEN u.role_id = 5 THEN 25000  -- Staff
                        ELSE 20000
                    END as monthly_salary,
                    CASE 
                        WHEN u.role_id = 3 THEN 'Doctor'
                        WHEN u.role_id = 4 THEN 'Chef' 
                        WHEN u.role_id = 5 THEN 'Staff'
                        ELSE 'Other'
                    END as position
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id
                    LEFT JOIN doctors d ON u.id = d.user_id AND u.role_id = 3
                    LEFT JOIN chefs c ON u.id = c.user_id AND u.role_id = 4
                    WHERE u.role_id IN (3, 4, 5) AND u.is_active = 1
                    AND u.id NOT IN (SELECT user_id FROM staff_salaries WHERE salary_month = ?)
                    ORDER BY u.role_id, u.first_name";
    
    $staff_stmt = $db->prepare($staff_query);
    $staff_stmt->execute([$salary_month]);
    $staff_to_process = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    writeLog("Found " . count($staff_to_process) . " active staff members to process");
    
    if (empty($staff_to_process)) {
        writeLog("No staff members need salary processing for this month");
        writeLog("=== Cron Job Completed (No Staff to Process) ===");
        exit(0);
    }
    
    $processed_count = 0;
    $total_amount = 0;
    
    // Use database transaction for data integrity
    $db->beginTransaction();
    
    foreach ($staff_to_process as $staff) {
        try {
            writeLog("Processing: {$staff['first_name']} {$staff['last_name']} ({$staff['position']}) - ৳{$staff['monthly_salary']}");
            
            // Insert salary record
            $salary_stmt = $db->prepare("INSERT INTO staff_salaries (user_id, salary_month, base_salary, overtime_hours, overtime_rate, bonus, deductions, total_salary, payment_status, notes, created_by) VALUES (?, ?, ?, 0, 0, 0, 0, ?, 'pending', ?, 1)");
            $salary_stmt->execute([
                $staff['id'], 
                $salary_month, 
                $staff['monthly_salary'], 
                $staff['monthly_salary'], 
                "Auto-renewal on 10th - {$current_month}"
            ]);
            
            // Record expense
            $staff_name = $staff['first_name'] . ' ' . $staff['last_name'];
            $expense_description = "Monthly salary: {$staff_name} ({$staff['position']}) - " . date('F Y');
            
            $expense_stmt = $db->prepare("INSERT INTO expenses (expense_type, category, description, amount, expense_date, reference_id, reference_type, created_by, status, notes) VALUES ('salary', 'Staff Salary', ?, ?, CURDATE(), ?, 'staff_salary', 1, 'approved', ?)");
            $expense_stmt->execute([
                $expense_description, 
                $staff['monthly_salary'], 
                $staff['id'], 
                "Auto-generated by cron job on " . date('Y-m-d H:i:s')
            ]);
            
            $processed_count++;
            $total_amount += $staff['monthly_salary'];
            
            writeLog("✓ Successfully processed {$staff_name}");
            
        } catch (Exception $e) {
            writeLog("✗ Error processing {$staff['first_name']} {$staff['last_name']}: " . $e->getMessage());
            // Continue with other staff members even if one fails
        }
    }
    
    // Commit transaction
    $db->commit();
    
    writeLog("=== SUMMARY ===");
    writeLog("Processed: $processed_count staff members");
    writeLog("Total amount: ৳" . number_format($total_amount));
    writeLog("Month: " . date('F Y'));
    writeLog("=== Monthly Salary Renewal Cron Job Completed Successfully ===");
    
    // If run manually via web, show success message
    if (isset($_GET['manual_run'])) {
        echo "<h3>Monthly Salary Renewal Completed</h3>";
        echo "<p>Processed: $processed_count staff members</p>";
        echo "<p>Total amount: ৳" . number_format($total_amount) . "</p>";
        echo "<p>Check log file: $log_file</p>";
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    writeLog("CRITICAL ERROR: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    writeLog("=== Cron Job Failed ===");
    
    // If run manually via web, show error
    if (isset($_GET['manual_run'])) {
        echo "<h3>Error occurred during salary renewal</h3>";
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Check log file: $log_file</p>";
    }
    
    exit(1);
}
?>
