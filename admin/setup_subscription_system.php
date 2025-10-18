<?php
/**
 * One-Click Setup Script for Subscription Management System
 * This script will set up the entire subscription system automatically
 */

session_start();
require_once '../config/database.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$setup_steps = [];
$overall_success = true;

function addStep($name, $status, $message) {
    global $setup_steps;
    $setup_steps[] = [
        'name' => $name,
        'status' => $status,
        'message' => $message
    ];
}

if ($_POST && isset($_POST['run_setup'])) {
    try {
        // Step 1: Create resident_revenue_history table
        $sql = "CREATE TABLE IF NOT EXISTS resident_revenue_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            resident_id INT NOT NULL,
            plan_id INT NOT NULL,
            transaction_id VARCHAR(50) UNIQUE NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_type ENUM('initial', 'renewal', 'upgrade', 'downgrade') DEFAULT 'initial',
            payment_status ENUM('paid', 'pending', 'overdue', 'cancelled') DEFAULT 'paid',
            payment_date DATE NOT NULL,
            subscription_start_date DATE NOT NULL,
            subscription_end_date DATE NOT NULL,
            renewal_due_date DATE NOT NULL,
            grace_period_end DATE NOT NULL,
            payment_method VARCHAR(50) DEFAULT 'cash',
            processed_by INT,
            notes TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES payment_plans(id) ON DELETE RESTRICT,
            FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_user_id (user_id),
            INDEX idx_resident_id (resident_id),
            INDEX idx_plan_id (plan_id),
            INDEX idx_transaction_id (transaction_id),
            INDEX idx_payment_date (payment_date),
            INDEX idx_renewal_due_date (renewal_due_date),
            INDEX idx_grace_period_end (grace_period_end),
            INDEX idx_payment_status (payment_status),
            INDEX idx_is_active (is_active)
        )";
        
        $db->exec($sql);
        addStep("Create Revenue History Table", "success", "Table created successfully");
        
        // Step 2: Check if basic plan exists, create if not
        $check_basic = "SELECT COUNT(*) as count FROM payment_plans WHERE monthly_fee = 0";
        $stmt = $db->prepare($check_basic);
        $stmt->execute();
        $basic_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($basic_count == 0) {
            $create_basic = "INSERT INTO payment_plans (plan_name, description, monthly_fee, features) 
                            VALUES ('Basic Plan', 'Free basic accommodation', 0, 'Basic room, shared facilities')";
            $db->exec($create_basic);
            addStep("Create Basic Plan", "success", "Basic plan created");
        } else {
            addStep("Check Basic Plan", "success", "Basic plan already exists");
        }
        
        // Step 3: Add sample payment plans if none exist
        $check_plans = "SELECT COUNT(*) as count FROM payment_plans WHERE monthly_fee > 0";
        $stmt = $db->prepare($check_plans);
        $stmt->execute();
        $plan_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($plan_count == 0) {
            $sample_plans = [
                ['Plan 1', 'Standard accommodation', 15000, 'Private room, shared bathroom'],
                ['Plan 2', 'Premium accommodation', 25000, 'Private room, private bathroom'],
                ['Plan 3', 'Deluxe accommodation', 35000, 'Large room, private bathroom, AC']
            ];
            
            foreach ($sample_plans as $plan) {
                $insert_plan = "INSERT INTO payment_plans (plan_name, description, monthly_fee, features) 
                               VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($insert_plan);
                $stmt->execute($plan);
            }
            addStep("Create Sample Plans", "success", "3 sample payment plans created");
        } else {
            addStep("Check Payment Plans", "success", "Payment plans already exist");
        }
        
        // Step 4: Create logs directory
        $logs_dir = dirname(__DIR__) . '/logs';
        if (!is_dir($logs_dir)) {
            mkdir($logs_dir, 0755, true);
            addStep("Create Logs Directory", "success", "Logs directory created");
        } else {
            addStep("Check Logs Directory", "success", "Logs directory already exists");
        }
        
        // Step 5: Set up sample revenue history for existing residents
        $existing_residents = "SELECT r.id, r.user_id, r.plan_id, pp.monthly_fee 
                              FROM residents r 
                              JOIN payment_plans pp ON r.plan_id = pp.id 
                              WHERE pp.monthly_fee > 0 
                              AND r.id NOT IN (SELECT resident_id FROM resident_revenue_history)";
        $stmt = $db->prepare($existing_residents);
        $stmt->execute();
        $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $created_records = 0;
        foreach ($residents as $resident) {
            $transaction_id = 'SETUP_' . date('Y') . '_' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $subscription_start = date('Y-m-d');
            $subscription_end = date('Y-m-d', strtotime('+1 month'));
            $renewal_due = date('Y-m-d', strtotime('+1 month -5 days'));
            $grace_period_end = date('Y-m-d', strtotime('+1 month +2 days'));
            
            $insert_history = "INSERT INTO resident_revenue_history 
                              (user_id, resident_id, plan_id, transaction_id, amount, payment_type, 
                               payment_date, subscription_start_date, subscription_end_date, 
                               renewal_due_date, grace_period_end, payment_method, processed_by, notes) 
                              VALUES (?, ?, ?, ?, ?, 'initial', CURDATE(), ?, ?, ?, ?, 'setup', ?, 'Initial setup record')";
            
            $stmt = $db->prepare($insert_history);
            $stmt->execute([
                $resident['user_id'], $resident['id'], $resident['plan_id'], 
                $transaction_id, $resident['monthly_fee'], $subscription_start, 
                $subscription_end, $renewal_due, $grace_period_end, $_SESSION['user_id']
            ]);
            $created_records++;
        }
        
        if ($created_records > 0) {
            addStep("Setup Existing Residents", "success", "$created_records revenue history records created for existing residents");
        } else {
            addStep("Check Existing Residents", "success", "No existing residents need setup");
        }
        
        // Step 6: Test email configuration
        if (function_exists('mail')) {
            addStep("Check Email Function", "success", "PHP mail function is available");
        } else {
            addStep("Check Email Function", "warning", "PHP mail function not available - emails won't work");
        }
        
        addStep("System Setup Complete", "success", "Subscription management system is ready to use!");
        
    } catch (Exception $e) {
        addStep("Setup Error", "error", $e->getMessage());
        $overall_success = false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Subscription System - Maple House</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            color: #2c5aa0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .setup-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .setup-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .setup-info h3 {
            margin: 0 0 10px 0;
            color: #0066cc;
        }
        
        .setup-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .setup-info li {
            margin: 5px 0;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .step-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }
        
        .step-item.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        
        .step-item.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        
        .step-item.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .step-icon {
            font-size: 1.2rem;
            width: 30px;
            text-align: center;
        }
        
        .step-icon.success { color: #28a745; }
        .step-icon.warning { color: #ffc107; }
        .step-icon.error { color: #dc3545; }
        
        .step-details h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        
        .step-details p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .warning-box h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-rocket"></i> Subscription System Setup</h1>
            <p>One-click setup for the complete subscription management system</p>
        </div>

        <?php if (!$_POST): ?>
        <div class="setup-card">
            <div class="setup-info">
                <h3><i class="fas fa-info-circle"></i> What this setup will do:</h3>
                <ul>
                    <li>Create the <code>resident_revenue_history</code> table</li>
                    <li>Ensure basic payment plan exists (free plan)</li>
                    <li>Create sample payment plans if none exist</li>
                    <li>Set up revenue history for existing residents</li>
                    <li>Create logs directory for system monitoring</li>
                    <li>Verify email configuration</li>
                </ul>
            </div>
            
            <div class="warning-box">
                <h4><i class="fas fa-exclamation-triangle"></i> Important Notes:</h4>
                <p><strong>Backup your database</strong> before running this setup. This will create new tables and data.</p>
                <p>Make sure you're logged in as an Admin user to run this setup.</p>
            </div>
            
            <form method="POST">
                <input type="hidden" name="run_setup" value="1">
                <div style="text-align: center;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-play"></i> Run Setup Now
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="setup-card">
            <h3><i class="fas fa-cogs"></i> Setup Results</h3>
            
            <?php foreach ($setup_steps as $step): ?>
            <div class="step-item <?php echo $step['status']; ?>">
                <div class="step-icon <?php echo $step['status']; ?>">
                    <?php if ($step['status'] === 'success'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php elseif ($step['status'] === 'warning'): ?>
                        <i class="fas fa-exclamation-triangle"></i>
                    <?php else: ?>
                        <i class="fas fa-times-circle"></i>
                    <?php endif; ?>
                </div>
                <div class="step-details">
                    <h4><?php echo htmlspecialchars($step['name']); ?></h4>
                    <p><?php echo htmlspecialchars($step['message']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="actions">
                <a href="test_subscription_system.php" class="btn btn-success">
                    <i class="fas fa-check"></i> Test System
                </a>
                <a href="residents.php" class="btn btn-primary">
                    <i class="fas fa-users"></i> Manage Residents
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
