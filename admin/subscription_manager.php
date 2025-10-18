<?php
session_start();
require_once '../config/database.php';
require_once 'email_notifications.php';

// Check if user is logged in and has admin/manager role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$response = ['success' => false, 'message' => ''];

if ($_POST) {
    try {
        $db->beginTransaction();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'renew_subscription':
                    $resident_id = intval($_POST['resident_id']);
                    $plan_id = intval($_POST['plan_id']);
                    $payment_method = $_POST['payment_method'] ?? 'cash';
                    
                    // Get resident and plan details
                    $resident_query = "SELECT r.*, u.first_name, u.last_name, pp.monthly_fee, pp.plan_name 
                                      FROM residents r 
                                      JOIN users u ON r.user_id = u.id 
                                      JOIN payment_plans pp ON r.plan_id = pp.id 
                                      WHERE r.id = :resident_id";
                    $resident_stmt = $db->prepare($resident_query);
                    $resident_stmt->bindParam(':resident_id', $resident_id);
                    $resident_stmt->execute();
                    $resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$resident) {
                        throw new Exception('Resident not found');
                    }
                    
                    // Get new plan details if different
                    if ($plan_id != $resident['plan_id']) {
                        $new_plan_query = "SELECT * FROM payment_plans WHERE id = :plan_id";
                        $new_plan_stmt = $db->prepare($new_plan_query);
                        $new_plan_stmt->bindParam(':plan_id', $plan_id);
                        $new_plan_stmt->execute();
                        $new_plan = $new_plan_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$new_plan) {
                            throw new Exception('Invalid plan selected');
                        }
                        
                        $amount = $new_plan['monthly_fee'];
                        $payment_type = 'upgrade';
                        
                        // Update resident's plan
                        $update_plan_query = "UPDATE residents SET plan_id = :plan_id WHERE id = :resident_id";
                        $update_plan_stmt = $db->prepare($update_plan_query);
                        $update_plan_stmt->bindParam(':plan_id', $plan_id);
                        $update_plan_stmt->bindParam(':resident_id', $resident_id);
                        $update_plan_stmt->execute();
                    } else {
                        $amount = $resident['monthly_fee'];
                        $payment_type = 'renewal';
                    }
                    
                    // Generate transaction ID
                    $transaction_id = 'TXN_' . date('Y') . '_' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                    
                    // Deactivate previous revenue history records
                    $deactivate_query = "UPDATE resident_revenue_history 
                                        SET is_active = 0, 
                                            updated_at = CURRENT_TIMESTAMP
                                        WHERE resident_id = :resident_id AND is_active = 1";
                    $deactivate_stmt = $db->prepare($deactivate_query);
                    $deactivate_stmt->bindParam(':resident_id', $resident_id);
                    $deactivate_stmt->execute();
                    
                    // Calculate subscription dates (30-day cycle)
                    $subscription_start = date('Y-m-d');
                    $subscription_end = date('Y-m-d', strtotime('+30 days'));
                    $renewal_due = date('Y-m-d', strtotime('+30 days')); // Due on day 30 (same as end date)
                    $grace_period_end = date('Y-m-d', strtotime('+32 days')); // Grace period ends on day 32                
                    
                    // Insert revenue history record
                    $revenue_query = "INSERT INTO resident_revenue_history 
                                     (user_id, resident_id, plan_id, transaction_id, amount, payment_type, 
                                      payment_date, subscription_start_date, subscription_end_date, 
                                      renewal_due_date, grace_period_end, payment_method, processed_by, notes) 
                                     VALUES 
                                     (:user_id, :resident_id, :plan_id, :transaction_id, :amount, :payment_type, 
                                      CURDATE(), :subscription_start, :subscription_end, 
                                      :renewal_due, :grace_period_end, :payment_method, :processed_by, :notes)";
                    
                    $notes = $payment_type === 'upgrade' ? "Plan upgraded and renewed" : "Monthly subscription renewed";
                    
                    $revenue_stmt = $db->prepare($revenue_query);
                    $revenue_stmt->bindParam(':user_id', $resident['user_id']);
                    $revenue_stmt->bindParam(':resident_id', $resident_id);
                    $revenue_stmt->bindParam(':plan_id', $plan_id);
                    $revenue_stmt->bindParam(':transaction_id', $transaction_id);
                    $revenue_stmt->bindParam(':amount', $amount);
                    $revenue_stmt->bindParam(':payment_type', $payment_type);
                    $revenue_stmt->bindParam(':subscription_start', $subscription_start);
                    $revenue_stmt->bindParam(':subscription_end', $subscription_end);
                    $revenue_stmt->bindParam(':renewal_due', $renewal_due);
                    $revenue_stmt->bindParam(':grace_period_end', $grace_period_end);
                    $revenue_stmt->bindParam(':payment_method', $payment_method);
                    $revenue_stmt->bindParam(':processed_by', $_SESSION['user_id']);
                    $revenue_stmt->bindParam(':notes', $notes);
                    $revenue_stmt->execute();
                    
                    // Update resident payment status and plan
                    $update_resident_query = "UPDATE residents 
                                             SET payment_status = 'Paid', 
                                                 next_payment_due = :next_payment_due,
                                                 plan_id = :plan_id
                                             WHERE id = :resident_id";
                    $update_resident_stmt = $db->prepare($update_resident_query);
                    $update_resident_stmt->bindParam(':next_payment_due', $subscription_end);
                    $update_resident_stmt->bindParam(':plan_id', $plan_id);
                    $update_resident_stmt->bindParam(':resident_id', $resident_id);
                    $update_resident_stmt->execute();
                    
                    // Send email confirmation
                    $emailSystem = new EmailNotifications();
                    $emailSystem->sendPaymentConfirmation($resident_id, $transaction_id);
                    
                    $response['success'] = true;
                    $response['message'] = "Subscription renewed successfully!";
                    $response['transaction_id'] = $transaction_id;
                    $response['redirect'] = true;
                    break;
                    
                case 'check_overdue_residents':
                    // Find residents who are past their grace period (day 12)
                    $overdue_query = "SELECT r.id, r.user_id, r.plan_id, u.first_name, u.last_name, pp.plan_name,
                                            rh.grace_period_end, rh.subscription_end_date
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
                    
                    $downgraded_count = 0;
                    
                    foreach ($overdue_residents as $resident) {
                        // Move to basic plan (plan_id = 1, assuming basic plan has id 1)
                        $downgrade_query = "UPDATE residents SET plan_id = 1, payment_status = 'Overdue' WHERE id = :resident_id";
                        $downgrade_stmt = $db->prepare($downgrade_query);
                        $downgrade_stmt->bindParam(':resident_id', $resident['id']);
                        $downgrade_stmt->execute();
                        
                        // Deactivate previous revenue history records
                        $deactivate_query = "UPDATE resident_revenue_history 
                                            SET is_active = 0, 
                                                updated_at = CURRENT_TIMESTAMP
                                            WHERE resident_id = :resident_id AND is_active = 1";
                        $deactivate_stmt = $db->prepare($deactivate_query);
                        $deactivate_stmt->bindParam(':resident_id', $resident['id']);
                        $deactivate_stmt->execute();
                        
                        $downgraded_count++;
                    }
                    
                    $response['success'] = true;
                    $response['message'] = "Processed {$downgraded_count} overdue residents. They have been moved to basic plan.";
                    $response['downgraded_count'] = $downgraded_count;
                    break;
            }
        }
        
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $response['message'] = $e->getMessage();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>
