<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get resident information
$resident_query = "SELECT u.*, r.*, pp.plan_name, pp.monthly_fee, pp.laundry_limit, pp.cleaning_limit
                   FROM users u 
                   JOIN residents r ON u.id = r.user_id 
                   JOIN payment_plans pp ON r.plan_id = pp.id
                   WHERE u.id = :user_id";
$resident_stmt = $db->prepare($resident_query);
$resident_stmt->bindParam(':user_id', $_SESSION['user_id']);
$resident_stmt->execute();
$resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    header("Location: ../login.php");
    exit();
}

// Get all available payment plans
$plans_query = "SELECT * FROM payment_plans ORDER BY monthly_fee ASC";
$plans_stmt = $db->prepare($plans_query);
$plans_stmt->execute();
$all_plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle personal info update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    
    try {
        $update_query = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                        phone = :phone, date_of_birth = :date_of_birth, gender = :gender 
                        WHERE id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':first_name', $first_name);
        $update_stmt->bindParam(':last_name', $last_name);
        $update_stmt->bindParam(':phone', $phone);
        $update_stmt->bindParam(':date_of_birth', $date_of_birth);
        $update_stmt->bindParam(':gender', $gender);
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($update_stmt->execute()) {
            $message = "Profile updated successfully!";
            $message_type = "success";
            // Refresh resident data
            $resident_stmt->execute();
            $resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "Failed to update profile.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle subscription renewal/change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_subscription'])) {
    $new_plan_id = $_POST['plan_id'];
    $current_plan_id = $resident['plan_id'];
    
    try {
        // Get new plan details
        $plan_query = "SELECT * FROM payment_plans WHERE id = :plan_id";
        $plan_stmt = $db->prepare($plan_query);
        $plan_stmt->bindParam(':plan_id', $new_plan_id);
        $plan_stmt->execute();
        $new_plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($new_plan) {
            // Update resident's plan
            $update_plan_query = "UPDATE residents SET plan_id = :plan_id WHERE user_id = :user_id";
            $update_plan_stmt = $db->prepare($update_plan_query);
            $update_plan_stmt->bindParam(':plan_id', $new_plan_id);
            $update_plan_stmt->bindParam(':user_id', $_SESSION['user_id']);
            
            if ($update_plan_stmt->execute()) {
                // Reset service counts for current month if plan changed
                if ($new_plan_id != $current_plan_id) {
                    $current_month = date('Y-m');
                    
                    // Get current plan quotas for calculation
                    $old_plan_quotas = [
                        'Basic Plan' => ['Laundry' => 10, 'Room Cleaning' => 10, 'Grocery Shopping' => 5, 'Emergency Care' => 2, 'Doctor Appointment' => 5, 'Transportation' => 5],
                        'Plan 1' => ['Laundry' => 15, 'Room Cleaning' => 15, 'Grocery Shopping' => 10, 'Emergency Care' => 5, 'Doctor Appointment' => 10, 'Transportation' => 8],
                        'Plan 2' => ['Laundry' => 20, 'Room Cleaning' => 20, 'Grocery Shopping' => 15, 'Emergency Care' => 8, 'Doctor Appointment' => 12, 'Transportation' => 10],
                        'Plan 3' => ['Laundry' => 25, 'Room Cleaning' => 25, 'Grocery Shopping' => 20, 'Emergency Care' => 10, 'Doctor Appointment' => 15, 'Transportation' => 15],
                        'Plan 4' => ['Laundry' => 999, 'Room Cleaning' => 999, 'Grocery Shopping' => 999, 'Emergency Care' => 999, 'Doctor Appointment' => 999, 'Transportation' => 999]
                    ];
                    
                    $old_plan_name = $resident['plan_name'];
                    $old_base_quotas = $old_plan_quotas[$old_plan_name] ?? $old_plan_quotas['Basic Plan'];
                    
                    // Get current usage and additional quotas
                    $usage_query = "SELECT s.service_name, COUNT(*) as used_count
                                   FROM service_requests sr
                                   JOIN services s ON sr.service_id = s.id
                                   WHERE sr.resident_id = :resident_id 
                                   AND DATE_FORMAT(sr.request_date, '%Y-%m') = :current_month
                                   GROUP BY s.service_name";
                    $usage_stmt = $db->prepare($usage_query);
                    $usage_stmt->bindParam(':resident_id', $resident['id']);
                    $usage_stmt->bindParam(':current_month', $current_month);
                    $usage_stmt->execute();
                    $current_usage = $usage_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get additional quotas
                    $additional_quota_query = "SELECT service_name, additional_quota 
                                              FROM resident_service_quotas 
                                              WHERE resident_id = :resident_id 
                                              AND month = :current_month";
                    $additional_quota_stmt = $db->prepare($additional_quota_query);
                    $additional_quota_stmt->bindParam(':resident_id', $resident['id']);
                    $additional_quota_stmt->bindParam(':current_month', $current_month);
                    $additional_quota_stmt->execute();
                    $additional_quotas = $additional_quota_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculate remaining purchased quotas for each service
                    $usage_map = [];
                    foreach ($current_usage as $usage) {
                        $usage_map[$usage['service_name']] = $usage['used_count'];
                    }
                    
                    $additional_map = [];
                    foreach ($additional_quotas as $quota) {
                        $additional_map[$quota['service_name']] = $quota['additional_quota'];
                    }
                    
                    // Update additional quotas based on usage
                    foreach ($additional_map as $service_name => $purchased_quota) {
                        $base_quota = $old_base_quotas[$service_name] ?? 0;
                        $used = $usage_map[$service_name] ?? 0;
                        
                        if ($used > $base_quota) {
                            // User used some purchased quota
                            $used_from_purchased = $used - $base_quota;
                            $remaining_purchased = max(0, $purchased_quota - $used_from_purchased);
                            
                            // Update the additional quota to remaining amount
                            if ($remaining_purchased > 0) {
                                $update_quota_query = "UPDATE resident_service_quotas 
                                                      SET additional_quota = :remaining_quota 
                                                      WHERE resident_id = :resident_id 
                                                      AND service_name = :service_name 
                                                      AND month = :current_month";
                                $update_quota_stmt = $db->prepare($update_quota_query);
                                $update_quota_stmt->bindParam(':remaining_quota', $remaining_purchased);
                                $update_quota_stmt->bindParam(':resident_id', $resident['id']);
                                $update_quota_stmt->bindParam(':service_name', $service_name);
                                $update_quota_stmt->bindParam(':current_month', $current_month);
                                $update_quota_stmt->execute();
                            } else {
                                // All purchased quota used, remove the record
                                $delete_quota_query = "DELETE FROM resident_service_quotas 
                                                      WHERE resident_id = :resident_id 
                                                      AND service_name = :service_name 
                                                      AND month = :current_month";
                                $delete_quota_stmt = $db->prepare($delete_quota_query);
                                $delete_quota_stmt->bindParam(':resident_id', $resident['id']);
                                $delete_quota_stmt->bindParam(':service_name', $service_name);
                                $delete_quota_stmt->bindParam(':current_month', $current_month);
                                $delete_quota_stmt->execute();
                            }
                        }
                        // If used <= base_quota, keep all purchased quota as is
                    }
                    
                    // Delete service requests for current month (reset usage to 0)
                    $delete_requests_query = "DELETE FROM service_requests 
                                             WHERE resident_id = :resident_id 
                                             AND DATE_FORMAT(request_date, '%Y-%m') = :current_month";
                    $delete_requests_stmt = $db->prepare($delete_requests_query);
                    $delete_requests_stmt->bindParam(':resident_id', $resident['id']);
                    $delete_requests_stmt->bindParam(':current_month', $current_month);
                    $delete_requests_stmt->execute();
                }
                
                // Record in revenue history
                $payment_type = ($new_plan_id == $current_plan_id) ? 'renewal' : (($new_plan['monthly_fee'] > $resident['monthly_fee']) ? 'upgrade' : 'downgrade');
                
                // Generate unique transaction ID
                $transaction_id = 'TXN_' . date('Y') . '_' . rand(10000, 99999);
                
                // Calculate subscription dates
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d', strtotime('+1 month'));
                $renewal_due = date('Y-m-d', strtotime('+1 month'));
                $grace_end = date('Y-m-d', strtotime('+1 month +2 days'));
                
                $revenue_query = "INSERT INTO resident_revenue_history 
                                 (user_id, resident_id, plan_id, transaction_id, amount, payment_date, payment_type, payment_status, 
                                  subscription_start_date, subscription_end_date, renewal_due_date, grace_period_end, 
                                  payment_method, is_active) 
                                 VALUES 
                                 (:user_id, :resident_id, :plan_id, :transaction_id, :amount, NOW(), :payment_type, 'paid',
                                  :start_date, :end_date, :renewal_due, :grace_end, 'online', 1)";
                $revenue_stmt = $db->prepare($revenue_query);
                $revenue_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $revenue_stmt->bindParam(':resident_id', $resident['id']);
                $revenue_stmt->bindParam(':plan_id', $new_plan_id);
                $revenue_stmt->bindParam(':transaction_id', $transaction_id);
                $revenue_stmt->bindParam(':amount', $new_plan['monthly_fee']);
                $revenue_stmt->bindParam(':payment_type', $payment_type);
                $revenue_stmt->bindParam(':start_date', $start_date);
                $revenue_stmt->bindParam(':end_date', $end_date);
                $revenue_stmt->bindParam(':renewal_due', $renewal_due);
                $revenue_stmt->bindParam(':grace_end', $grace_end);
                $revenue_stmt->execute();
                
                $action = ($new_plan_id == $current_plan_id) ? 'renewed' : 'changed';
                $reset_msg = ($new_plan_id != $current_plan_id) ? ' Service counts have been reset for this month.' : '';
                $message = "Subscription {$action} successfully to {$new_plan['plan_name']}!{$reset_msg}";
                $message_type = "success";
                
                // Refresh resident data
                $resident_stmt->execute();
                $resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = "Failed to update subscription.";
                $message_type = "error";
            }
        } else {
            $message = "Invalid plan selected.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $user_query = "SELECT password FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = :password WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    $message = "Password updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to update password.";
                    $message_type = "error";
                }
            } else {
                $message = "New password must be at least 6 characters long.";
                $message_type = "error";
            }
        } else {
            $message = "New passwords do not match.";
            $message_type = "error";
        }
    } else {
        $message = "Current password is incorrect.";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Maple House</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: #f8f9fa;
            padding: 20px;
        }

        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .info-card:nth-child(2) { border-left-color: #f093fb; }
        .info-card:nth-child(3) { border-left-color: #43e97b; }
        .info-card:nth-child(4) { border-left-color: #fa709a; }
        .info-card:nth-child(5) { border-left-color: #fee140; }
        .info-card:nth-child(6) { border-left-color: #38f9d7; }

        .info-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .info-value {
            font-size: 1.1rem;
            color: #2c3e50;
            font-weight: 500;
        }

        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .section h2 {
            color: #2c3e50;
            font-size: 1.3rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .plan-card {
            position: relative;
            display: block;
            border: 3px solid #e9ecef;
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s ease;
            cursor: pointer;
            background: white;
        }

        .plan-card input[type="radio"] {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: #667eea;
        }

        .plan-content {
            pointer-events: none;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .plan-card input[type="radio"]:checked ~ .plan-content {
            opacity: 1;
        }

        .plan-card:has(input[type="radio"]:checked) {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }

        .plan-card.current-plan {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.05);
        }

        .plan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .plan-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.3rem;
        }

        .current-badge {
            background: #28a745;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .plan-price {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 20px;
        }

        .plan-price span {
            font-size: 1rem;
            color: #666;
            font-weight: 400;
        }

        .plan-features {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
            font-size: 0.95rem;
        }

        .feature i {
            color: #667eea;
            width: 20px;
        }

        @media (max-width: 768px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }
        }

        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Personal Information -->
        <div class="section">
            <h2><i class="fas fa-user"></i> Personal Information</h2>
            
            <?php if ($message && !isset($_POST['reset_password'])): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="info-grid">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($resident['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($resident['last_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email_display">Email (Read-only)</label>
                        <input type="email" id="email_display" value="<?php echo htmlspecialchars($resident['email']); ?>" readonly style="background: #f8f9fa; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="room_display">Room Number (Read-only)</label>
                        <input type="text" id="room_display" value="Room <?php echo htmlspecialchars($resident['room_number']); ?>" readonly style="background: #f8f9fa; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($resident['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo $resident['date_of_birth']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select name="gender" id="gender" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 0.95rem;">
                            <option value="Male" <?php echo ($resident['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($resident['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($resident['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary" style="margin-top: 10px;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>

        <!-- Plan Information -->
        <div class="section">
            <h2><i class="fas fa-home"></i> Plan Information</h2>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">Plan Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($resident['plan_name']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Monthly Fee</div>
                    <div class="info-value">৳<?php echo number_format($resident['monthly_fee']); ?></div>
                </div>
            </div>
            
            <h3 style="margin-top: 30px; margin-bottom: 15px; color: #2c3e50;"><i class="fas fa-list"></i> Service Quotas (Per Month)</h3>
            
            <div class="info-grid">
                <?php
                // Define service quotas based on actual database plan names
                $service_quotas = [
                    'Basic Plan' => [
                        'Laundry' => 10,
                        'Room Cleaning' => 10,
                        'Grocery Shopping' => 5,
                        'Emergency Care' => 2,
                        'Doctor Appointment' => 5,
                        'Transportation' => 5
                    ],
                    'Plan 1' => [
                        'Laundry' => 15,
                        'Room Cleaning' => 15,
                        'Grocery Shopping' => 10,
                        'Emergency Care' => 5,
                        'Doctor Appointment' => 10,
                        'Transportation' => 8
                    ],
                    'Plan 2' => [
                        'Laundry' => 20,
                        'Room Cleaning' => 20,
                        'Grocery Shopping' => 15,
                        'Emergency Care' => 8,
                        'Doctor Appointment' => 12,
                        'Transportation' => 10
                    ],
                    'Plan 3' => [
                        'Laundry' => 25,
                        'Room Cleaning' => 25,
                        'Grocery Shopping' => 20,
                        'Emergency Care' => 10,
                        'Doctor Appointment' => 15,
                        'Transportation' => 15
                    ],
                    'Plan 4' => [
                        'Laundry' => 'Unlimited',
                        'Room Cleaning' => 'Unlimited',
                        'Grocery Shopping' => 'Unlimited',
                        'Emergency Care' => 'Unlimited',
                        'Doctor Appointment' => 'Unlimited',
                        'Transportation' => 'Unlimited'
                    ]
                ];
                
                // Get plan name from database
                $plan_name = trim($resident['plan_name']);
                
                // Get quotas for current plan, default to Basic Plan
                $quotas = $service_quotas[$plan_name] ?? $service_quotas['Basic Plan'];
                
                // Display all service quotas
                foreach ($quotas as $service => $quota):
                ?>
                    <div class="info-card">
                        <div class="info-label"><?php echo htmlspecialchars($service); ?></div>
                        <div class="info-value"><?php echo htmlspecialchars($quota); ?><?php echo is_numeric($quota) ? '/month' : ''; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Subscription Renewal -->
        <div class="section">
            <h2><i class="fas fa-sync-alt"></i> Renew or Change Subscription</h2>
            
            <?php if ($message && isset($_POST['renew_subscription'])): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <p style="color: #666; margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i> Select a plan to renew your current subscription or upgrade/downgrade to a different plan.
            </p>
            
            <form method="POST" id="renewalForm">
                <div class="plans-grid">
                    <?php foreach ($all_plans as $plan): 
                        $is_current = ($plan['id'] == $resident['plan_id']);
                    ?>
                        <label class="plan-card <?php echo $is_current ? 'current-plan' : ''; ?>" for="plan_<?php echo $plan['id']; ?>">
                            <input type="radio" name="plan_id" value="<?php echo $plan['id']; ?>" id="plan_<?php echo $plan['id']; ?>" <?php echo $is_current ? 'checked' : ''; ?> required>
                            <div class="plan-content">
                                <div class="plan-header">
                                    <h3><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                    <?php if ($is_current): ?>
                                        <span class="current-badge"><i class="fas fa-check-circle"></i> Current</span>
                                    <?php endif; ?>
                                </div>
                                <div class="plan-price">৳<?php echo number_format($plan['monthly_fee']); ?><span>/month</span></div>
                                <div class="plan-features">
                                    <?php
                                    // Define service quotas for display
                                    $display_quotas = [
                                        'Basic Plan' => ['Laundry' => 10, 'Room Cleaning' => 10, 'Grocery' => 5, 'Emergency' => 2, 'Doctor' => 5, 'Transport' => 5],
                                        'Plan 1' => ['Laundry' => 15, 'Room Cleaning' => 15, 'Grocery' => 10, 'Emergency' => 5, 'Doctor' => 10, 'Transport' => 8],
                                        'Plan 2' => ['Laundry' => 20, 'Room Cleaning' => 20, 'Grocery' => 15, 'Emergency' => 8, 'Doctor' => 12, 'Transport' => 10],
                                        'Plan 3' => ['Laundry' => 25, 'Room Cleaning' => 25, 'Grocery' => 20, 'Emergency' => 10, 'Doctor' => 15, 'Transport' => 15],
                                        'Plan 4' => ['All Services' => 'Unlimited']
                                    ];
                                    $plan_display = $display_quotas[$plan['plan_name']] ?? $display_quotas['Basic Plan'];
                                    foreach ($plan_display as $service => $quota):
                                    ?>
                                        <div class="feature"><i class="fas fa-check-circle"></i> <?php echo $service; ?>: <?php echo $quota; ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" name="renew_subscription" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-credit-card"></i> Confirm & Pay
                </button>
            </form>
        </div>

        <!-- Password Reset -->
        <div class="section">
            <h2><i class="fas fa-lock"></i> Reset Password</h2>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" name="current_password" id="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" name="new_password" id="new_password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required minlength="6">
                </div>
                
                <button type="submit" name="reset_password" class="btn btn-primary">
                    <i class="fas fa-key"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</body>
</html>
