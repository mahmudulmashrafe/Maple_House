<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has admin/manager role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_resident'])) {
        // Add new resident
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $date_of_birth = $_POST['date_of_birth'];
        $gender = $_POST['gender'];
        $emergency_contact_name = trim($_POST['emergency_contact_name']);
        $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
        $room_number = trim($_POST['room_number']);
        $plan_id = intval($_POST['plan_id']);
        $medical_conditions = trim($_POST['medical_conditions']);
        $allergies = trim($_POST['allergies']);
        $family_contact_info = trim($_POST['family_contact_info']);
        
        try {
            $db->beginTransaction();
            
            // Check if username or email already exists
            $check_query = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception('Username or email already exists.');
            }
            
            // Insert user
            $user_query = "INSERT INTO users (username, email, password, role_id, first_name, last_name, phone, address, date_of_birth, gender, emergency_contact_name, emergency_contact_phone) 
                          VALUES (:username, :email, :password, 6, :first_name, :last_name, :phone, :address, :date_of_birth, :gender, :emergency_contact_name, :emergency_contact_phone)";
            
            $user_stmt = $db->prepare($user_query);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_stmt->bindParam(':username', $username);
            $user_stmt->bindParam(':email', $email);
            $user_stmt->bindParam(':password', $hashed_password);
            $user_stmt->bindParam(':first_name', $first_name);
            $user_stmt->bindParam(':last_name', $last_name);
            $user_stmt->bindParam(':phone', $phone);
            $user_stmt->bindParam(':address', $address);
            $user_stmt->bindParam(':date_of_birth', $date_of_birth);
            $user_stmt->bindParam(':gender', $gender);
            $user_stmt->bindParam(':emergency_contact_name', $emergency_contact_name);
            $user_stmt->bindParam(':emergency_contact_phone', $emergency_contact_phone);
            $user_stmt->execute();
            
            $user_id = $db->lastInsertId();
            
            // SERVER-SIDE ROOM VALIDATION - Check if room is occupied by active resident
            $room_check_query = "SELECT r.id, r.room_number, u.first_name, u.last_name, u.is_active
                                FROM residents r 
                                JOIN users u ON r.user_id = u.id 
                                WHERE r.room_number = :room_number AND u.is_active = 1";
            $room_check_stmt = $db->prepare($room_check_query);
            $room_check_stmt->bindParam(':room_number', $room_number);
            $room_check_stmt->execute();
            $existing_active_resident = $room_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_active_resident) {
                throw new Exception("Room {$room_number} is already occupied by an active resident: {$existing_active_resident['first_name']} {$existing_active_resident['last_name']}. Please choose a different room.");
            }
            
            // Calculate next payment due date
            $next_payment_due = date('Y-m-d', strtotime('+1 month'));
            
            // Insert resident
            $resident_query = "INSERT INTO residents (user_id, room_number, plan_id, admission_date, medical_conditions, allergies, family_contact_info, next_payment_due) 
                              VALUES (:user_id, :room_number, :plan_id, CURDATE(), :medical_conditions, :allergies, :family_contact_info, :next_payment_due)";
            
            $resident_stmt = $db->prepare($resident_query);
            $resident_stmt->bindParam(':user_id', $user_id);
            $resident_stmt->bindParam(':room_number', $room_number);
            $resident_stmt->bindParam(':plan_id', $plan_id);
            $resident_stmt->bindParam(':medical_conditions', $medical_conditions);
            $resident_stmt->bindParam(':allergies', $allergies);
            $resident_stmt->bindParam(':family_contact_info', $family_contact_info);
            $resident_stmt->bindParam(':next_payment_due', $next_payment_due);
            $resident_stmt->execute();
            
            $resident_id = $db->lastInsertId();
            
            // Get plan details for revenue history
            $plan_query = "SELECT monthly_fee FROM payment_plans WHERE id = :plan_id";
            $plan_stmt = $db->prepare($plan_query);
            $plan_stmt->bindParam(':plan_id', $plan_id);
            $plan_stmt->execute();
            $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Only create revenue history for paid plans (not basic plan)
            if ($plan_data && $plan_data['monthly_fee'] > 0) {
                // Generate transaction ID
                $transaction_id = 'TXN_' . date('Y') . '_' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                
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
                                 (:user_id, :resident_id, :plan_id, :transaction_id, :amount, 'initial', 
                                  CURDATE(), :subscription_start, :subscription_end, 
                                  :renewal_due, :grace_period_end, 'cash', :processed_by, 'Initial payment on admission')";
                
                $revenue_stmt = $db->prepare($revenue_query);
                $revenue_stmt->bindParam(':user_id', $user_id);
                $revenue_stmt->bindParam(':resident_id', $resident_id);
                $revenue_stmt->bindParam(':plan_id', $plan_id);
                $revenue_stmt->bindParam(':transaction_id', $transaction_id);
                $revenue_stmt->bindParam(':amount', $plan_data['monthly_fee']);
                $revenue_stmt->bindParam(':subscription_start', $subscription_start);
                $revenue_stmt->bindParam(':subscription_end', $subscription_end);
                $revenue_stmt->bindParam(':renewal_due', $renewal_due);
                $revenue_stmt->bindParam(':grace_period_end', $grace_period_end);
                $revenue_stmt->bindParam(':processed_by', $_SESSION['user_id']);
                $revenue_stmt->execute();
            }
            
            $db->commit();
            $success_message = "Resident added successfully with payment tracking!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
    
    if (isset($_POST['edit_resident'])) {
        // Edit existing resident
        $resident_id = intval($_POST['resident_id']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $room_number = trim($_POST['room_number']);
        $plan_id = intval($_POST['plan_id']);
        $payment_status = $_POST['payment_status'];
        $is_active = intval($_POST['is_active']);
        $emergency_contact_name = trim($_POST['emergency_contact_name']);
        $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
        $address = trim($_POST['address']);
        $medical_conditions = trim($_POST['medical_conditions']);
        $allergies = trim($_POST['allergies']);
        $family_contact_info = trim($_POST['family_contact_info']);
        
        try {
            $db->beginTransaction();
            
            // Update user information (with optional password update)
            if (!empty($password)) {
                // Update with password
                $user_update_query = "UPDATE users u 
                                     JOIN residents r ON u.id = r.user_id 
                                     SET u.first_name = :first_name, 
                                         u.last_name = :last_name,
                                         u.username = :username,
                                         u.password = :password,
                                         u.email = :email, 
                                         u.phone = :phone,
                                         u.address = :address,
                                         u.is_active = :is_active,
                                         u.emergency_contact_name = :emergency_contact_name,
                                         u.emergency_contact_phone = :emergency_contact_phone
                                     WHERE r.id = :resident_id";
            } else {
                // Update without password
                $user_update_query = "UPDATE users u 
                                     JOIN residents r ON u.id = r.user_id 
                                     SET u.first_name = :first_name, 
                                         u.last_name = :last_name,
                                         u.username = :username,
                                         u.email = :email, 
                                         u.phone = :phone,
                                         u.address = :address,
                                         u.is_active = :is_active,
                                         u.emergency_contact_name = :emergency_contact_name,
                                         u.emergency_contact_phone = :emergency_contact_phone
                                     WHERE r.id = :resident_id";
            }
            
            $user_update_stmt = $db->prepare($user_update_query);
            $user_update_stmt->bindParam(':first_name', $first_name);
            $user_update_stmt->bindParam(':last_name', $last_name);
            $user_update_stmt->bindParam(':username', $username);
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_update_stmt->bindParam(':password', $hashed_password);
            }
            $user_update_stmt->bindParam(':email', $email);
            $user_update_stmt->bindParam(':phone', $phone);
            $user_update_stmt->bindParam(':address', $address);
            $user_update_stmt->bindParam(':is_active', $is_active);
            $user_update_stmt->bindParam(':emergency_contact_name', $emergency_contact_name);
            $user_update_stmt->bindParam(':emergency_contact_phone', $emergency_contact_phone);
            $user_update_stmt->bindParam(':resident_id', $resident_id);
            $user_update_stmt->execute();
            
            // Update resident information
            $resident_update_query = "UPDATE residents 
                                     SET room_number = :room_number,
                                         plan_id = :plan_id,
                                         payment_status = :payment_status,
                                         medical_conditions = :medical_conditions,
                                         allergies = :allergies,
                                         family_contact_info = :family_contact_info
                                     WHERE id = :resident_id";
            
            $resident_update_stmt = $db->prepare($resident_update_query);
            $resident_update_stmt->bindParam(':room_number', $room_number);
            $resident_update_stmt->bindParam(':plan_id', $plan_id);
            $resident_update_stmt->bindParam(':payment_status', $payment_status);
            $resident_update_stmt->bindParam(':medical_conditions', $medical_conditions);
            $resident_update_stmt->bindParam(':allergies', $allergies);
            $resident_update_stmt->bindParam(':family_contact_info', $family_contact_info);
            $resident_update_stmt->bindParam(':resident_id', $resident_id);
            $resident_update_stmt->execute();
            
            $db->commit();
            $success_message = "Resident updated successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_resident'])) {
        // Delete resident
        $resident_id = intval($_POST['resident_id']);
        
        try {
            $db->beginTransaction();
            
            // Get user_id before deleting resident
            $get_user_query = "SELECT user_id FROM residents WHERE id = :resident_id";
            $get_user_stmt = $db->prepare($get_user_query);
            $get_user_stmt->bindParam(':resident_id', $resident_id);
            $get_user_stmt->execute();
            $user_id = $get_user_stmt->fetchColumn();
            
            // Delete resident record
            $delete_resident_query = "DELETE FROM residents WHERE id = :resident_id";
            $delete_resident_stmt = $db->prepare($delete_resident_query);
            $delete_resident_stmt->bindParam(':resident_id', $resident_id);
            $delete_resident_stmt->execute();
            
            // Delete user record
            if ($user_id) {
                $delete_user_query = "DELETE FROM users WHERE id = :user_id";
                $delete_user_stmt = $db->prepare($delete_user_query);
                $delete_user_stmt->bindParam(':user_id', $user_id);
                $delete_user_stmt->execute();
            }
            
            $db->commit();
            $success_message = "Resident deleted successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
}

// Get residents with pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

$residents_query = "SELECT r.id, r.user_id, r.room_number, r.plan_id, r.admission_date, r.medical_conditions, 
                           r.allergies, r.family_contact_info, r.payment_status, r.last_payment_date, r.next_payment_due,
                           u.first_name, u.last_name, u.username, u.email, u.phone, u.date_of_birth, u.gender, 
                           u.address, u.emergency_contact_name, u.emergency_contact_phone, u.is_active,
                           pp.plan_name, pp.monthly_fee,
                           rh.renewal_due_date, rh.grace_period_end, rh.subscription_end_date,
                           CASE 
                               WHEN u.is_active = 1 THEN 'Active'
                               ELSE 'Inactive'
                           END as activity_status,
                           CASE 
                               WHEN pp.monthly_fee = 0 THEN 'basic'
                               WHEN rh.renewal_due_date IS NULL THEN 'no_subscription'
                               WHEN CURDATE() <= rh.subscription_end_date THEN 'current'
                               WHEN CURDATE() > rh.subscription_end_date AND CURDATE() <= rh.grace_period_end THEN 'renewal_due'
                               WHEN CURDATE() > rh.grace_period_end THEN 'overdue'
                               ELSE 'current'
                           END as subscription_status
                    FROM residents r
                    JOIN users u ON r.user_id = u.id
                    LEFT JOIN payment_plans pp ON r.plan_id = pp.id
                    LEFT JOIN (
                        SELECT resident_id, renewal_due_date, grace_period_end, subscription_end_date, is_active
                        FROM resident_revenue_history 
                        WHERE is_active = 1
                        ORDER BY created_at DESC
                    ) rh ON r.id = rh.resident_id
                    ORDER BY r.admission_date DESC
                    LIMIT :limit OFFSET :offset";

$residents_stmt = $db->prepare($residents_query);
$residents_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$residents_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$residents_stmt->execute();
$residents = $residents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM residents";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$total_residents = $count_stmt->fetchColumn();
$total_pages = ceil($total_residents / $per_page);

// Get payment plans for the form
$plans_query = "SELECT * FROM payment_plans ORDER BY monthly_fee";
$plans_stmt = $db->prepare($plans_query);
$plans_stmt->execute();
$payment_plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total_residents,
                    COUNT(CASE WHEN payment_status = 'Paid' THEN 1 END) as paid_residents,
                    COUNT(CASE WHEN payment_status = 'Pending' THEN 1 END) as pending_residents,
                    COUNT(CASE WHEN payment_status = 'Overdue' THEN 1 END) as overdue_residents
                FROM residents";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents Management - Maple House</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            color: #2c5aa0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #495057;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            border: 2px solid #667eea !important;
        }
        
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #3498db;
        }
        
        .stat-card.success i { color: #27ae60; }
        .stat-card.warning i { color: #f39c12; }
        .stat-card.danger i { color: #e74c3c; }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .residents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .resident-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid #667eea;
            display: flex;
            flex-direction: column;
            height: 400px;
            max-height: 400px;
        }
        
        .resident-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        .resident-header {
            padding: 20px;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .resident-avatar {
            width: 60px;
            height: 60px;
            background: rgba(102, 126, 234, 0.1);
            border: 2px solid #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #667eea;
        }
        
        .resident-basic h3 {
            margin: 0 0 5px 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .resident-email {
            margin: 0 0 10px 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .resident-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .room-badge {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border: 1px solid #667eea;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .resident-details {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            max-height: 200px;
        }
        
        /* Custom Scrollbar */
        .resident-details::-webkit-scrollbar {
            width: 6px;
        }
        
        .resident-details::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .resident-details::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 3px;
        }
        
        .resident-details::-webkit-scrollbar-thumb:hover {
            background: #5a6fd8;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 500;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
        }
        
        .detail-label i {
            width: 16px;
            text-align: center;
            color: #3498db;
        }
        
        .resident-actions {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .residents-grid {
                grid-template-columns: 1fr;
            }
            
            .resident-header {
                flex-direction: column;
                text-align: center;
            }
            
            .resident-actions {
                flex-direction: column;
            }
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-current {
            background: #d4edda;
            color: #155724;
        }
        
        .status-renewal_due {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-basic {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .status-no_subscription {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Real-time validation styles */
        .validation-message {
            font-size: 0.8rem;
            margin-top: 5px;
            padding: 5px 8px;
            border-radius: 4px;
            display: none;
        }
        
        .validation-available {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .validation-unavailable {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .validation-checking {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .form-group.checking input {
            border-color: #ffc107;
        }
        
        .form-group.available input {
            border-color: #28a745;
        }
        
        .form-group.unavailable input {
            border-color: #dc3545;
            background-color: #fff5f5;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Room validation styles (like password validation) */
        .validation-message {
            font-size: 0.8rem;
            margin-top: 5px;
            padding: 5px 8px;
            border-radius: 4px;
            display: none;
        }
        
        .validation-message.checking {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .validation-message.available {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .validation-message.unavailable {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .validation-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .validation-icon {
            display: inline-block;
            margin-right: 5px;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            color: #212529;
        }
        
        .plan-selection-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .plan-option {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }
        
        .plan-option:hover {
            border-color: #3498db;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.2);
        }
        
        .plan-option.selected {
            border-color: #27ae60;
            background: #f8fff8;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.3);
            backdrop-filter: blur(2px);
        }
        
        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: #3498db;
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            opacity: 0.7;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        /* Enhanced Form Styling */
        .form-section {
            margin-bottom: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #3498db;
        }
        
        .section-title {
            color: #2c3e50;
            margin: 0 0 20px 0;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .section-title i {
            color: #3498db;
            font-size: 1.2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-row:last-child {
            margin-bottom: 0;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-primary, .btn-success {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .btn-primary:hover, .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .form-group input, .form-group select, .form-group textarea {
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-section {
                padding: 15px;
            }
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .pagination a {
            padding: 8px 12px;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #3498db;
        }
        
        .pagination a:hover,
        .pagination a.active {
            background: #3498db;
            color: white;
        }
        
        /* Delete Confirmation Modal */
        .delete-confirmation-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            position: relative;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .delete-modal-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        
        .delete-modal-header h3 {
            margin: 10px 0 0 0;
            font-size: 1.3rem;
        }
        
        .delete-modal-body {
            padding: 25px;
            text-align: center;
        }
        
        .delete-modal-body p {
            margin: 0 0 15px 0;
            font-size: 1rem;
            line-height: 1.5;
        }
        
        .delete-modal-actions {
            padding: 20px 25px 25px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        /* Floating Add Button */
        .floating-add-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 45px;
            height: 45px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(52, 152, 219, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .floating-add-btn:hover {
            background: #2980b9;
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(52, 152, 219, 0.6);
        }
        
        .floating-add-btn:active {
            transform: scale(0.95);
        }
        
        /* Right-side Notification Popup */
        .notification-popup {
            position: fixed;
            top: 20px;
            right: -400px;
            width: 350px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            z-index: 2000;
            transition: right 0.4s ease;
            border-left: 5px solid #27ae60;
        }
        
        .notification-popup.show {
            right: 20px;
        }
        
        .notification-popup.success {
            border-left-color: #27ae60;
        }
        
        .notification-popup.error {
            border-left-color: #e74c3c;
        }
        
        .notification-header {
            padding: 15px 20px 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .notification-body {
            padding: 0 20px 15px;
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .notification-close {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #999;
            cursor: pointer;
            padding: 5px;
        }
        
        .notification-close:hover {
            color: #666;
        }
        
        /* Fixed Modal Header */
        .modal-header {
            flex-shrink: 0;
            background: white;
            color: #2c3e50;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 1px solid #e9ecef;
        }
        
        /* Scrollable Modal Body */
        .modal-body {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 25px;
            max-height: calc(90vh - 140px);
            width: 100%;
            box-sizing: border-box;
        }
        
        /* Custom Modal Scrollbar */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .modal-body::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #5a6fd8;
        }
        
        /* Form Container */
        .modal-body form {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Form Sections */
        .form-section {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            margin-bottom: 25px;
            background: transparent;
            border: none;
            padding: 0;
        }
        
        /* Section Titles */
        .section-title {
            color: #667eea;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Form Rows */
        .form-row {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Form Groups */
        .form-group {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Input Fields */
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
    </style>
</head>
<body>

    <!-- Right-side Notification Popup -->
    <?php if ($success_message): ?>
        <div class="notification-popup success" id="notificationPopup">
            <button class="notification-close" onclick="closeNotification()">&times;</button>
            <div class="notification-header">
                <i class="fas fa-check-circle"></i>
                Success
            </div>
            <div class="notification-body">
                <?php echo $success_message; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="notification-popup error" id="notificationPopup">
            <button class="notification-close" onclick="closeNotification()">&times;</button>
            <div class="notification-header">
                <i class="fas fa-exclamation-circle"></i>
                Error
            </div>
            <div class="notification-body">
                <?php echo $error_message; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <div class="stat-number"><?php echo $stats['total_residents']; ?></div>
            <div class="stat-label">Total Residents</div>
        </div>
        
        <div class="stat-card success">
            <i class="fas fa-check-circle"></i>
            <div class="stat-number"><?php echo $stats['paid_residents']; ?></div>
            <div class="stat-label">Paid Status</div>
        </div>
        
        <div class="stat-card warning">
            <i class="fas fa-clock"></i>
            <div class="stat-number"><?php echo $stats['pending_residents']; ?></div>
            <div class="stat-label">Pending Payment</div>
        </div>
        
        <div class="stat-card danger">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="stat-number"><?php echo $stats['overdue_residents']; ?></div>
            <div class="stat-label">Overdue Payment</div>
        </div>
    </div>

    <!-- Residents Grid -->
    <div class="residents-grid">
        <?php if (!empty($residents)): ?>
            <?php foreach ($residents as $resident): ?>
                <div class="resident-card">
                    <div class="resident-header">
                        <div class="resident-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="resident-basic">
                            <h3><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></h3>
                            <p class="resident-email"><?php echo htmlspecialchars($resident['email']); ?></p>
                            <div class="resident-badges">
                                <span class="room-badge">Room <?php echo htmlspecialchars($resident['room_number']); ?></span>
                                <span class="status-badge <?php echo $resident['is_active'] ? 'status-paid' : 'status-overdue'; ?>">
                                    <?php echo $resident['activity_status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="resident-details">
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-calendar"></i> Admitted:</span>
                            <span><?php echo date('M j, Y', strtotime($resident['admission_date'])); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-star"></i> Plan:</span>
                            <span><?php echo htmlspecialchars($resident['plan_name']); ?> (৳<?php echo number_format($resident['monthly_fee']); ?>/month)</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-credit-card"></i> Payment:</span>
                            <span class="status-badge status-<?php echo strtolower($resident['payment_status']); ?>">
                                <?php echo $resident['payment_status']; ?>
                            </span>
                        </div>
                        
                        <?php if ($resident['monthly_fee'] > 0): ?>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-calendar-check"></i> Subscription:</span>
                            <span class="status-badge status-<?php echo $resident['subscription_status'] ?? 'basic'; ?>">
                                <?php 
                                switch($resident['subscription_status'] ?? 'basic') {
                                    case 'current': echo 'Current'; break;
                                    case 'renewal_due': echo 'Renewal Due'; break;
                                    case 'overdue': echo 'Overdue'; break;
                                    case 'basic': echo 'Basic Plan'; break;
                                    case 'no_subscription': echo 'No Subscription'; break;
                                    default: echo 'No Subscription'; break;
                                }
                                ?>
                            </span>
                        </div>
                        
                        <?php if ($resident['renewal_due_date']): ?>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-clock"></i> Due Date:</span>
                            <span><?php echo date('M j, Y', strtotime($resident['renewal_due_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($resident['phone']): ?>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-phone"></i> Phone:</span>
                            <span><?php echo htmlspecialchars($resident['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($resident['emergency_contact_phone']): ?>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-phone-alt"></i> Emergency:</span>
                            <span><?php echo htmlspecialchars($resident['emergency_contact_phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($resident['medical_conditions']): ?>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-notes-medical"></i> Medical:</span>
                            <span><?php echo htmlspecialchars(substr($resident['medical_conditions'], 0, 30)) . '...'; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="resident-actions">
                        <button onclick="viewResident(<?php echo $resident['id']; ?>)" class="btn btn-primary btn-sm" title="View Details">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button onclick="editResident(<?php echo $resident['id']; ?>)" class="btn btn-success btn-sm" title="Edit">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        
                        <a href="payment_history.php?resident_id=<?php echo $resident['id']; ?>" class="btn btn-secondary btn-sm" title="Payment History">
                            <i class="fas fa-history"></i> History
                        </a>
                        
                        <?php if ($resident['monthly_fee'] > 0 && (in_array($resident['subscription_status'], ['renewal_due', 'overdue', 'no_subscription']) || $resident['subscription_status'] === null)): ?>
                        <button onclick="renewSubscription(<?php echo $resident['id']; ?>, '<?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>')" class="btn btn-warning btn-sm" title="Renew Subscription">
                            <i class="fas fa-sync-alt"></i> Pay
                        </button>
                        <?php endif; ?>
                        
                        <button onclick="deleteResident(<?php echo $resident['id']; ?>, '<?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>')" class="btn btn-danger btn-sm" title="Delete">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
                <i class="fas fa-users" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
                <h3 style="color: #666; margin-bottom: 10px;">No residents found</h3>
                <p style="color: #999;">Click "Add New Resident" to get started.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Add Resident Modal -->
    <div id="addResidentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Resident</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="addResidentForm" onsubmit="return validateFormBeforeSubmit()">
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" name="first_name" id="first_name" required>
                                <div class="validation-message" id="name_validation"></div>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" name="last_name" id="last_name" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username *</label>
                                <input type="text" name="username" id="username" required>
                                <div class="validation-message" id="username_validation"></div>
                            </div>
                            <div class="form-group">
                                <label for="password">Password *</label>
                                <input type="password" name="password" id="password" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" name="email" id="email" required>
                                <div class="validation-message" id="email_validation"></div>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" name="phone" id="phone" required oninput="validatePhoneNumber(this.value)" placeholder="01XXXXXXXXX (11 digits)">
                                <div class="validation-message" id="phone_validation"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth *</label>
                                <input type="date" name="date_of_birth" id="date_of_birth" required>
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender *</label>
                                <select name="gender" id="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Residence Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-home"></i> Residence Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="room_number">Room Number *</label>
                                <input type="text" name="room_number" id="room_number" required oninput="validateRoomNumber(this.value)">
                                <div class="validation-message" id="room_validation"></div>
                            </div>
                            <div class="form-group">
                                <label for="plan_id">Payment Plan *</label>
                                <select name="plan_id" id="plan_id" required>
                                    <option value="">Select Plan</option>
                                    <?php foreach ($payment_plans as $plan): ?>
                                        <option value="<?php echo $plan['id']; ?>">
                                            <?php echo htmlspecialchars($plan['plan_name']); ?> - ৳<?php echo number_format($plan['monthly_fee']); ?>/month
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Emergency Contact Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emergency_contact_name">Emergency Contact Name</label>
                                <input type="text" name="emergency_contact_name" id="emergency_contact_name">
                            </div>
                            <div class="form-group">
                                <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                <input type="tel" name="emergency_contact_phone" id="emergency_contact_phone">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address and Family Contact Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-map-marker-alt"></i> Address & Family Contact</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea name="address" id="address" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="family_contact_info">Family Contact Info</label>
                                <textarea name="family_contact_info" id="family_contact_info" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medical Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-notes-medical"></i> Medical Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="medical_conditions">Medical Conditions</label>
                                <textarea name="medical_conditions" id="medical_conditions" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="allergies">Allergies</label>
                                <textarea name="allergies" id="allergies" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeModal()" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="add_resident" class="btn btn-primary" onclick="return validateFormSubmission()">
                            <i class="fas fa-user-plus"></i> Add Resident
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Resident Modal -->
    <div id="viewResidentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Resident Details</h3>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewResidentContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Edit Resident Modal -->
    <div id="editResidentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Resident</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body" id="editResidentContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addResidentModal').style.display = 'block';
            // Force initialization with multiple attempts
            setTimeout(() => {
                console.log('Attempt 1: Initializing validation...');
                initializeValidation();
            }, 100);
            setTimeout(() => {
                console.log('Attempt 2: Initializing validation...');
                initializeValidation();
            }, 300);
            setTimeout(() => {
                console.log('Attempt 3: Initializing validation...');
                initializeValidation();
            }, 500);
        }
        
        function closeModal() {
            document.getElementById('addResidentModal').style.display = 'none';
            
            // Clear all validation messages and states
            document.querySelectorAll('.validation-message').forEach(msg => {
                msg.style.display = 'none';
                msg.textContent = '';
            });
            
            document.querySelectorAll('.form-group').forEach(group => {
                group.classList.remove('checking', 'available', 'unavailable');
            });
            
            // Reset form
            document.getElementById('addResidentForm').reset();
        }
        
        // Store resident data for JavaScript access
        const residentsData = <?php echo json_encode($residents); ?>;
        
        function viewResident(id) {
            const resident = residentsData.find(r => r.id == id);
            if (!resident) return;
            
            const modal = document.getElementById('viewResidentModal');
            const content = document.getElementById('viewResidentContent');
            
            content.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-user"></i> Personal Information</h4>
                        <p><strong>Name:</strong> ${resident.first_name} ${resident.last_name}</p>
                        <p><strong>Email:</strong> ${resident.email}</p>
                        <p><strong>Phone:</strong> ${resident.phone || 'N/A'}</p>
                        <p><strong>Date of Birth:</strong> ${resident.date_of_birth ? new Date(resident.date_of_birth).toLocaleDateString() : 'N/A'}</p>
                        <p><strong>Gender:</strong> ${resident.gender || 'N/A'}</p>
                        <p><strong>Address:</strong> ${resident.address || 'N/A'}</p>
                        <p><strong>Activity Status:</strong> <span class="status-badge ${resident.is_active == 1 ? 'status-paid' : 'status-overdue'}">${resident.activity_status}</span></p>
                    </div>
                    
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-home"></i> Residence Information</h4>
                        <p><strong>Room Number:</strong> ${resident.room_number}</p>
                        <p><strong>Plan:</strong> ${resident.plan_name}</p>
                        <p><strong>Monthly Fee:</strong> ৳${parseInt(resident.monthly_fee).toLocaleString()}</p>
                        <p><strong>Admission Date:</strong> ${new Date(resident.admission_date).toLocaleDateString()}</p>
                        <p><strong>Payment Status:</strong> <span class="status-badge status-${resident.payment_status.toLowerCase()}">${resident.payment_status}</span></p>
                        ${resident.next_payment_due ? `<p><strong>Next Payment Due:</strong> ${new Date(resident.next_payment_due).toLocaleDateString()}</p>` : ''}
                    </div>
                    
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-phone"></i> Emergency Contact</h4>
                        <p><strong>Emergency Contact Name:</strong> ${resident.emergency_contact_name || 'N/A'}</p>
                        <p><strong>Emergency Contact Phone:</strong> ${resident.emergency_contact_phone || 'N/A'}</p>
                        <p><strong>Family Contact Info:</strong> ${resident.family_contact_info || 'N/A'}</p>
                    </div>
                    
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-notes-medical"></i> Medical Information</h4>
                        <p><strong>Medical Conditions:</strong> ${resident.medical_conditions || 'None reported'}</p>
                        <p><strong>Allergies:</strong> ${resident.allergies || 'None reported'}</p>
                    </div>
                </div>
            `;
            
            modal.style.display = 'block';
        }
        
        function editResident(id) {
            const resident = residentsData.find(r => r.id == id);
            if (!resident) return;
            
            console.log('Editing resident:', resident); // Debug log
            
            const modal = document.getElementById('editResidentModal');
            const content = document.getElementById('editResidentContent');
            
            content.innerHTML = `
                <form method="POST" id="editResidentForm" onsubmit="return validateEditFormSubmission()">
                    <input type="hidden" name="edit_resident" value="1">
                    <input type="hidden" name="resident_id" value="${resident.id}">
                    <input type="hidden" name="current_user_id" value="${resident.user_id}">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" id="edit_first_name" value="${resident.first_name}" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" id="edit_last_name" value="${resident.last_name}" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" id="edit_username" value="${resident.username || ''}" required oninput="validateEditUsername(this.value, ${resident.user_id})">
                                <div class="validation-message" id="edit_username_validation"></div>
                            </div>
                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" id="edit_password" placeholder="Leave blank to keep current password">
                                <small style="color: #666;">Leave blank to keep current password</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" id="edit_email" value="${resident.email}" required oninput="validateEditEmail(this.value, ${resident.user_id})">
                                <div class="validation-message" id="edit_email_validation"></div>
                            </div>
                            <div class="form-group">
                                <label>Phone Number *</label>
                                <input type="tel" name="phone" id="edit_phone" value="${resident.phone || ''}" required oninput="validateEditPhone(this.value, ${resident.user_id})" placeholder="01XXXXXXXXX (11 digits)">
                                <div class="validation-message" id="edit_phone_validation"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Residence Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-home"></i> Residence Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Room Number *</label>
                                <input type="text" name="room_number" id="edit_room_number" value="${resident.room_number}" required oninput="validateEditRoom(this.value, ${resident.id})">
                                <div class="validation-message" id="edit_room_validation"></div>
                            </div>
                            <div class="form-group">
                                <label>Payment Plan *</label>
                                <select name="plan_id" required id="editPlanSelect">
                                    <option value="">Select Plan</option>
                                    <?php foreach ($payment_plans as $plan): ?>
                                        <option value="<?php echo $plan['id']; ?>">
                                            <?php echo htmlspecialchars($plan['plan_name']); ?> - ৳<?php echo number_format($plan['monthly_fee']); ?>/month
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Payment Status</label>
                                <select name="payment_status">
                                    <option value="Paid" ${resident.payment_status === 'Paid' ? 'selected' : ''}>Paid</option>
                                    <option value="Pending" ${resident.payment_status === 'Pending' ? 'selected' : ''}>Pending</option>
                                    <option value="Overdue" ${resident.payment_status === 'Overdue' ? 'selected' : ''}>Overdue</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Activity Status</label>
                                <select name="is_active">
                                    <option value="1" ${resident.is_active == 1 ? 'selected' : ''}>Active (Currently Residing)</option>
                                    <option value="0" ${resident.is_active == 0 ? 'selected' : ''}>Inactive (Not Currently Residing)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Emergency Contact Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Emergency Contact Name</label>
                                <input type="text" name="emergency_contact_name" value="${resident.emergency_contact_name || ''}">
                            </div>
                            <div class="form-group">
                                <label>Emergency Contact Phone</label>
                                <input type="tel" name="emergency_contact_phone" value="${resident.emergency_contact_phone || ''}">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address and Family Contact Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-map-marker-alt"></i> Address & Family Contact</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" rows="3">${resident.address || ''}</textarea>
                            </div>
                            <div class="form-group">
                                <label>Family Contact Info</label>
                                <textarea name="family_contact_info" rows="3">${resident.family_contact_info || ''}</textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medical Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-notes-medical"></i> Medical Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Medical Conditions</label>
                                <textarea name="medical_conditions" rows="3">${resident.medical_conditions || ''}</textarea>
                            </div>
                            <div class="form-group">
                                <label>Allergies</label>
                                <textarea name="allergies" rows="3">${resident.allergies || ''}</textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeEditModal()" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Resident
                        </button>
                    </div>
                </form>
            `;
            
            modal.style.display = 'block';
            
            // Set the selected plan after modal is displayed
            setTimeout(() => {
                const planSelect = document.getElementById('editPlanSelect');
                if (planSelect && resident.plan_id) {
                    planSelect.value = resident.plan_id;
                    console.log('Setting plan_id:', resident.plan_id, 'for resident:', resident.first_name);
                    
                    // Verify it was set correctly
                    if (planSelect.value == resident.plan_id) {
                        console.log('✅ Plan successfully selected');
                    } else {
                        console.log('❌ Plan selection failed. Available options:', Array.from(planSelect.options).map(o => o.value));
                    }
                }
            }, 100);
        }
        
        function deleteResident(id, name) {
            // Create custom confirmation modal
            const modal = document.createElement('div');
            modal.className = 'delete-confirmation-modal';
            modal.innerHTML = `
                <div class="delete-modal-content">
                    <div class="delete-modal-header">
                        <i class="fas fa-exclamation-triangle" style="color: #e74c3c; font-size: 2rem;"></i>
                        <h3>Confirm Deletion</h3>
                    </div>
                    <div class="delete-modal-body">
                        <p>Are you sure you want to delete resident <strong>"${name}"</strong>?</p>
                        <p style="color: #e74c3c; font-size: 0.9rem;">⚠️ This action cannot be undone and will permanently remove all resident data.</p>
                    </div>
                    <div class="delete-modal-actions">
                        <button onclick="closeDeleteModal()" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button onclick="confirmDelete(${id})" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Resident
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.style.display = 'block';
        }
        
        function confirmDelete(id) {
            // Create a form to submit the delete request
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_resident" value="1">
                <input type="hidden" name="resident_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function closeDeleteModal() {
            const modal = document.querySelector('.delete-confirmation-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        function closeViewModal() {
            document.getElementById('viewResidentModal').style.display = 'none';
        }
        
        function closeEditModal() {
            document.getElementById('editResidentModal').style.display = 'none';
        }
        
        function renewSubscription(residentId, residentName) {
            // Get current resident data
            const resident = residentsData.find(r => r.id == residentId);
            if (!resident) {
                alert('Resident data not found');
                return;
            }
            
            // Show plan selection modal
            showPlanSelectionModal(residentId, residentName, resident.plan_id);
        }
        
        function showPlanSelectionModal(residentId, residentName, currentPlanId) {
            // Create modal
            const modal = document.createElement('div');
            modal.className = 'plan-selection-modal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            `;
            
            modal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%;">
                    <h3 style="margin: 0 0 20px 0; color: #2c5aa0;">
                        <i class="fas fa-sync-alt"></i> Renew Subscription
                    </h3>
                    <p style="margin-bottom: 20px; color: #666;">
                        Choose a plan for <strong>${residentName}</strong>:
                    </p>
                    
                    <div id="planOptions" style="margin-bottom: 20px;">
                        <!-- Plan options will be loaded here -->
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button onclick="closePlanModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 5px; cursor: pointer;">
                            Cancel
                        </button>
                        <button onclick="confirmRenewal(${residentId})" style="padding: 10px 20px; border: none; background: #27ae60; color: white; border-radius: 5px; cursor: pointer;">
                            <i class="fas fa-check"></i> Renew Now
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Load payment plans
            loadPaymentPlans(currentPlanId);
        }
        
        function loadPaymentPlans(currentPlanId) {
            // Get payment plans (assuming they're available globally or fetch them)
            const planOptions = document.getElementById('planOptions');
            
            // Create plan options (you'll need to pass payment plans data to JavaScript)
            const plans = <?php echo json_encode($payment_plans); ?>;
            
            plans.forEach(plan => {
                if (plan.monthly_fee > 0) { // Only show paid plans
                    const isCurrentPlan = plan.id == currentPlanId;
                    const planDiv = document.createElement('div');
                    planDiv.style.cssText = `
                        border: 2px solid ${isCurrentPlan ? '#27ae60' : '#ddd'};
                        border-radius: 8px;
                        padding: 15px;
                        margin-bottom: 10px;
                        cursor: pointer;
                        transition: all 0.2s;
                        background: ${isCurrentPlan ? '#f8fff8' : 'white'};
                    `;
                    
                    planDiv.innerHTML = `
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 10px;">
                            <input type="radio" name="selected_plan" value="${plan.id}" ${isCurrentPlan ? 'checked' : ''} 
                                   style="margin: 0;">
                            <div>
                                <div style="font-weight: bold; color: #2c5aa0;">
                                    ${plan.plan_name} ${isCurrentPlan ? '(Current Plan)' : ''}
                                </div>
                                <div style="color: #666; font-size: 0.9rem;">
                                    ৳${parseInt(plan.monthly_fee).toLocaleString()}/month
                                </div>
                                <div style="color: #888; font-size: 0.8rem;">
                                    ${plan.description || ''}
                                </div>
                            </div>
                        </label>
                    `;
                    
                    planDiv.addEventListener('click', () => {
                        planDiv.querySelector('input[type="radio"]').checked = true;
                    });
                    
                    planOptions.appendChild(planDiv);
                }
            });
        }
        
        function confirmRenewal(residentId) {
            const selectedPlan = document.querySelector('input[name="selected_plan"]:checked');
            if (!selectedPlan) {
                alert('Please select a plan');
                return;
            }
            
            // Close the plan selection modal
            closePlanModal();
            
            // Show processing message
            showProcessingModal();
            
            // Send AJAX request instead of form submission
            fetch('subscription_manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=renew_subscription&resident_id=${residentId}&plan_id=${selectedPlan.value}&payment_method=cash`
            })
            .then(response => response.json())
            .then(data => {
                hideProcessingModal();
                if (data.success) {
                    showSuccessModal(data.message, data.transaction_id);
                    // Reload page after 2 seconds to show updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                hideProcessingModal();
                console.error('Error:', error);
                alert('An error occurred while processing the renewal.');
            });
        }
        
        function showProcessingModal() {
            const modal = document.createElement('div');
            modal.id = 'processingModal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1001;
            `;
            
            modal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 2rem; color: #3498db; margin-bottom: 15px;">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <h3 style="margin: 0; color: #2c5aa0;">Processing Payment...</h3>
                    <p style="margin: 10px 0 0 0; color: #666;">Please wait while we process your renewal.</p>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        function hideProcessingModal() {
            const modal = document.getElementById('processingModal');
            if (modal) {
                modal.remove();
            }
        }
        
        function showSuccessModal(message, transactionId) {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1001;
            `;
            
            modal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; max-width: 400px;">
                    <div style="font-size: 3rem; color: #27ae60; margin-bottom: 15px;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 style="margin: 0 0 10px 0; color: #27ae60;">Success!</h3>
                    <p style="margin: 0 0 15px 0; color: #666;">${message}</p>
                    <p style="margin: 0; font-size: 0.9rem; color: #888;">
                        Transaction ID: <strong>${transactionId}</strong>
                    </p>
                    <p style="margin: 15px 0 0 0; font-size: 0.9rem; color: #666;">
                        Page will refresh automatically...
                    </p>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Auto-close after 2 seconds
            setTimeout(() => {
                modal.remove();
            }, 2000);
        }
        
        function closePlanModal() {
            const modal = document.querySelector('.plan-selection-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Real-time validation functions
        let validationTimers = {};
        
        function debounce(func, wait, immediate) {
            return function executedFunction(...args) {
                const later = function() {
                    validationTimers[func.name] = null;
                    if (!immediate) func(...args);
                };
                const callNow = immediate && !validationTimers[func.name];
                clearTimeout(validationTimers[func.name]);
                validationTimers[func.name] = setTimeout(later, wait);
                if (callNow) func(...args);
            };
        }
        
        function showValidationMessage(fieldId, message, type) {
            const messageElement = document.getElementById(fieldId + '_validation');
            const fieldGroup = document.getElementById(fieldId).closest('.form-group');
            
            if (messageElement) {
                messageElement.textContent = message;
                messageElement.className = 'validation-message validation-' + type;
                messageElement.style.display = message ? 'block' : 'none';
                
                // Update field group class
                fieldGroup.classList.remove('checking', 'available', 'unavailable');
                if (type !== 'checking') {
                    fieldGroup.classList.add(type === 'available' ? 'available' : 'unavailable');
                } else {
                    fieldGroup.classList.add('checking');
                }
                
                // Update submit button state
                updateSubmitButtonState();
            }
        }
        
        function updateSubmitButtonState() {
            const submitButton = document.querySelector('button[type="submit"]');
            const unavailableFields = document.querySelectorAll('.form-group.unavailable');
            const checkingFields = document.querySelectorAll('.form-group.checking');
            
            if (submitButton) {
                if (unavailableFields.length > 0 || checkingFields.length > 0) {
                    submitButton.style.background = '#dc3545';
                    submitButton.style.cursor = 'not-allowed';
                    submitButton.innerHTML = '<i class="fas fa-times"></i> Cannot Submit (Conflicts Found)';
                } else {
                    submitButton.style.background = '#28a745';
                    submitButton.style.cursor = 'pointer';
                    submitButton.innerHTML = '<i class="fas fa-user-plus"></i> Add Resident';
                }
            }
        }
        
        function validateName() {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            
            if (!firstName || !lastName) {
                showValidationMessage('name', '', 'available');
                return;
            }
            
            showValidationMessage('name', '🔍 Checking name availability...', 'checking');
            
            fetch('validate_resident_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_name&first_name=${encodeURIComponent(firstName)}&last_name=${encodeURIComponent(lastName)}`
            })
            .then(response => response.json())
            .then(data => {
                const icon = data.available ? '✅' : '❌';
                showValidationMessage('name', `${icon} ${data.message}`, data.available ? 'available' : 'unavailable');
            })
            .catch(error => {
                console.error('Validation error:', error);
                showValidationMessage('name', '⚠️ Unable to check name availability', 'unavailable');
            });
        }
        
        function validateRoom() {
            console.log('validateRoom called');
            const roomField = document.getElementById('room_number');
            if (!roomField) {
                console.log('Room field not found');
                return;
            }
            
            const roomNumber = roomField.value.trim();
            console.log('Room number:', roomNumber);
            
            if (!roomNumber) {
                showValidationMessage('room', '', 'available');
                return;
            }
            
            showValidationMessage('room', '🔍 Checking room availability...', 'checking');
            
            fetch('validate_resident_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_room&room_number=${encodeURIComponent(roomNumber)}`
            })
            .then(response => response.json())
            .then(data => {
                const icon = data.available ? '✅' : '❌';
                showValidationMessage('room', `${icon} ${data.message}`, data.available ? 'available' : 'unavailable');
            })
            .catch(error => {
                console.error('Validation error:', error);
                showValidationMessage('room', '⚠️ Unable to check room availability', 'unavailable');
            });
        }
        
        function validateUsername() {
            const username = document.getElementById('username').value.trim();
            
            if (!username) {
                showValidationMessage('username', '', 'available');
                return;
            }
            
            showValidationMessage('username', '🔍 Checking username availability...', 'checking');
            
            fetch('validate_resident_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_username&username=${encodeURIComponent(username)}`
            })
            .then(response => response.json())
            .then(data => {
                const icon = data.available ? '✅' : '❌';
                showValidationMessage('username', `${icon} ${data.message}`, data.available ? 'available' : 'unavailable');
            })
            .catch(error => {
                console.error('Validation error:', error);
                showValidationMessage('username', '⚠️ Unable to check username availability', 'unavailable');
            });
        }
        
        function validateEmail() {
            const email = document.getElementById('email').value.trim();
            
            if (!email) {
                showValidationMessage('email', '', 'available');
                return;
            }
            
            showValidationMessage('email', '🔍 Checking email availability...', 'checking');
            
            fetch('validate_resident_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_email&email=${encodeURIComponent(email)}`
            })
            .then(response => response.json())
            .then(data => {
                const icon = data.available ? '✅' : '❌';
                showValidationMessage('email', `${icon} ${data.message}`, data.available ? 'available' : 'unavailable');
            })
            .catch(error => {
                console.error('Validation error:', error);
                showValidationMessage('email', '⚠️ Unable to check email availability', 'unavailable');
            });
        }
        
        // Debounced validation functions
        const debouncedValidateName = debounce(validateName, 500);
        const debouncedValidateRoom = debounce(validateRoom, 500);
        const debouncedValidateUsername = debounce(validateUsername, 500);
        const debouncedValidateEmail = debounce(validateEmail, 500);
        
        // Global validation initialization - doesn't depend on modal timing
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded - setting up global validation listeners');
            
            // Use event delegation for modal fields
            document.addEventListener('input', function(e) {
                console.log('Input event detected on:', e.target.id, e.target.value);
                
                if (e.target.id === 'room_number') {
                    console.log('Room number input detected');
                    debouncedValidateRoom();
                } else if (e.target.id === 'first_name' || e.target.id === 'last_name') {
                    console.log('Name input detected');
                    debouncedValidateName();
                } else if (e.target.id === 'username') {
                    console.log('Username input detected');
                    debouncedValidateUsername();
                } else if (e.target.id === 'email') {
                    console.log('Email input detected');
                    debouncedValidateEmail();
                }
            });
        });
        
        // Initialize validation when modal opens (backup method)
        function initializeValidation() {
            console.log('Starting validation initialization...');
            
            // Check if elements exist
            const firstName = document.getElementById('first_name');
            const lastName = document.getElementById('last_name');
            const roomNumber = document.getElementById('room_number');
            const username = document.getElementById('username');
            const email = document.getElementById('email');
            
            console.log('Elements found:', {
                firstName: !!firstName,
                lastName: !!lastName,
                roomNumber: !!roomNumber,
                username: !!username,
                email: !!email
            });
            
            // Remove existing listeners to avoid duplicates
            if (firstName) {
                firstName.removeEventListener('input', debouncedValidateName);
                firstName.addEventListener('input', debouncedValidateName);
                console.log('First name listener added');
            }
            
            if (lastName) {
                lastName.removeEventListener('input', debouncedValidateName);
                lastName.addEventListener('input', debouncedValidateName);
                console.log('Last name listener added');
            }
            
            if (roomNumber) {
                roomNumber.removeEventListener('input', debouncedValidateRoom);
                roomNumber.addEventListener('input', debouncedValidateRoom);
                console.log('Room validation listener added');
            }
            
            if (username) {
                username.removeEventListener('input', debouncedValidateUsername);
                username.addEventListener('input', debouncedValidateUsername);
                console.log('Username validation listener added');
            }
            
            if (email) {
                email.removeEventListener('input', debouncedValidateEmail);
                email.addEventListener('input', debouncedValidateEmail);
                console.log('Email validation listener added');
            }
            
            // Initialize submit button state
            updateSubmitButtonState();
            console.log('Validation initialization complete');
        }
        
        // Form validation before submission
        function validateFormBeforeSubmit() {
            let isValid = true;
            let errorMessages = [];
            
            // Check for unavailable fields (specifically room conflicts)
            const unavailableFields = document.querySelectorAll('.form-group.unavailable');
            if (unavailableFields.length > 0) {
                unavailableFields.forEach(field => {
                    const input = field.querySelector('input');
                    const label = field.querySelector('label').textContent;
                    const validationMsg = field.querySelector('.validation-message').textContent;
                    
                    if (input && input.name === 'room_number') {
                        errorMessages.push(`❌ ROOM CONFLICT: ${validationMsg}`);
                    } else {
                        errorMessages.push(`${label} is not available: ${validationMsg}`);
                    }
                });
                isValid = false;
            }
            
            // Check for fields still being validated
            const checkingFields = document.querySelectorAll('.form-group.checking');
            if (checkingFields.length > 0) {
                checkingFields.forEach(field => {
                    const label = field.querySelector('label').textContent;
                    errorMessages.push(`⏳ Still checking ${label}...`);
                });
                isValid = false;
            }
            
            // Special check for room number specifically
            const roomField = document.getElementById('room_number');
            const roomValidation = document.getElementById('room_validation');
            
            if (roomField && roomField.value.trim()) {
                const roomGroup = roomField.closest('.form-group');
                if (roomGroup.classList.contains('unavailable')) {
                    errorMessages.unshift('🚫 CANNOT SUBMIT: Room is already occupied by an active resident!');
                    isValid = false;
                }
            }
            
            if (!isValid) {
                alert('❌ FORM SUBMISSION BLOCKED!\n\n' + errorMessages.join('\n\n') + '\n\n✅ Please choose a different room number or fix the conflicts above.');
                return false;
            }
            
            return true;
        }
        
        // Real-time room number validation (like password validation)
        function validateRoomNumber(roomNumber) {
            const validationDiv = document.getElementById('room_validation');
            const roomInput = document.getElementById('room_number');
            
            // Clear previous validation
            validationDiv.innerHTML = '';
            validationDiv.className = 'validation-message';
            roomInput.style.borderColor = '#ddd';
            
            if (!roomNumber.trim()) {
                return;
            }
            
            // Show checking message
            validationDiv.innerHTML = '🔍 Checking room availability...';
            validationDiv.className = 'validation-message checking';
            validationDiv.style.display = 'block';
            roomInput.style.borderColor = '#ffc107';
            
            // Make AJAX request to check room availability
            fetch('validate_resident_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_room&room_number=${encodeURIComponent(roomNumber)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    // Room is available
                    validationDiv.innerHTML = '✅ ' + data.message;
                    validationDiv.className = 'validation-message available';
                    roomInput.style.borderColor = '#28a745';
                    roomInput.style.backgroundColor = '#f8fff9';
                } else {
                    // Room is occupied
                    validationDiv.innerHTML = '❌ ' + data.message;
                    validationDiv.className = 'validation-message unavailable';
                    roomInput.style.borderColor = '#dc3545';
                    roomInput.style.backgroundColor = '#fff5f5';
                    roomInput.style.animation = 'shake 0.5s ease-in-out';
                }
                validationDiv.style.display = 'block';
            })
            .catch(error => {
                console.error('Room validation error:', error);
                validationDiv.innerHTML = '⚠️ Unable to check room availability';
                validationDiv.className = 'validation-message error';
                validationDiv.style.display = 'block';
                roomInput.style.borderColor = '#dc3545';
            });
        }
        
        // Real-time phone number validation (11 digits + uniqueness)
        function validatePhoneNumber(phone) {
            const validationDiv = document.getElementById('phone_validation');
            const phoneInput = document.getElementById('phone');
            
            // Clear previous validation
            validationDiv.innerHTML = '';
            validationDiv.className = 'validation-message';
            phoneInput.style.borderColor = '#ddd';
            phoneInput.style.backgroundColor = '';
            
            if (!phone.trim()) {
                return;
            }
            
            // Show checking message
            validationDiv.innerHTML = '🔍 Validating phone number...';
            validationDiv.className = 'validation-message checking';
            validationDiv.style.display = 'block';
            phoneInput.style.borderColor = '#ffc107';
            
            // Make AJAX request to validate phone number
            fetch('validate_resident_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_phone&phone=${encodeURIComponent(phone)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    // Phone is valid and available
                    validationDiv.innerHTML = '✅ ' + data.message;
                    validationDiv.className = 'validation-message available';
                    phoneInput.style.borderColor = '#28a745';
                    phoneInput.style.backgroundColor = '#f8fff9';
                } else {
                    // Phone is invalid or already exists
                    validationDiv.innerHTML = '❌ ' + data.message;
                    validationDiv.className = 'validation-message unavailable';
                    phoneInput.style.borderColor = '#dc3545';
                    phoneInput.style.backgroundColor = '#fff5f5';
                    phoneInput.style.animation = 'shake 0.5s ease-in-out';
                }
                validationDiv.style.display = 'block';
            })
            .catch(error => {
                console.error('Phone validation error:', error);
                validationDiv.innerHTML = '⚠️ Unable to validate phone number';
                validationDiv.className = 'validation-message error';
                validationDiv.style.display = 'block';
                phoneInput.style.borderColor = '#dc3545';
            });
        }
        
        // Form submission validation - prevent submission if validation errors
        function validateFormSubmission() {
            const roomValidation = document.getElementById('room_validation');
            const roomInput = document.getElementById('room_number');
            const phoneValidation = document.getElementById('phone_validation');
            const phoneInput = document.getElementById('phone');
            let errors = [];
            
            // Check room validation
            if (roomValidation && roomValidation.classList.contains('unavailable')) {
                errors.push('❌ Room is already occupied by an active resident');
            }
            
            if (roomValidation && roomValidation.classList.contains('checking')) {
                errors.push('⏳ Room validation is still in progress');
            }
            
            if (!roomInput.value.trim()) {
                errors.push('⚠️ Room number is required');
            }
            
            // Check phone validation
            if (phoneValidation && phoneValidation.classList.contains('unavailable')) {
                errors.push('❌ Phone number is invalid or already registered');
            }
            
            if (phoneValidation && phoneValidation.classList.contains('checking')) {
                errors.push('⏳ Phone validation is still in progress');
            }
            
            if (!phoneInput.value.trim()) {
                errors.push('⚠️ Phone number is required');
            }
            
            // Show errors if any
            if (errors.length > 0) {
                alert('Cannot submit form!\n\n' + errors.join('\n\n') + '\n\nPlease fix the issues above before submitting.');
                return false;
            }
            
            return true;
        }
        
        // Edit form validation functions (with exclude IDs for current user/resident)
        function validateEditUsername(username, excludeUserId) {
            const validationDiv = document.getElementById('edit_username_validation');
            const usernameInput = document.getElementById('edit_username');
            
            // Clear previous validation
            validationDiv.innerHTML = '';
            validationDiv.className = 'validation-message';
            usernameInput.style.borderColor = '#ddd';
            usernameInput.style.backgroundColor = '';
            
            if (!username.trim()) {
                return;
            }
            
            // Show checking message
            validationDiv.innerHTML = '🔍 Checking username availability...';
            validationDiv.className = 'validation-message checking';
            validationDiv.style.display = 'block';
            usernameInput.style.borderColor = '#ffc107';
            
            // Make AJAX request
            fetch('validate_resident_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_username&username=${encodeURIComponent(username)}&exclude_id=${excludeUserId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    validationDiv.innerHTML = '✅ ' + data.message;
                    validationDiv.className = 'validation-message available';
                    usernameInput.style.borderColor = '#28a745';
                    usernameInput.style.backgroundColor = '#f8fff9';
                } else {
                    validationDiv.innerHTML = '❌ ' + data.message;
                    validationDiv.className = 'validation-message unavailable';
                    usernameInput.style.borderColor = '#dc3545';
                    usernameInput.style.backgroundColor = '#fff5f5';
                    usernameInput.style.animation = 'shake 0.5s ease-in-out';
                }
                validationDiv.style.display = 'block';
            })
            .catch(error => {
                console.error('Username validation error:', error);
                validationDiv.innerHTML = '⚠️ Unable to check username availability';
                validationDiv.className = 'validation-message error';
                validationDiv.style.display = 'block';
                usernameInput.style.borderColor = '#dc3545';
            });
        }
        
        function validateEditEmail(email, excludeUserId) {
            const validationDiv = document.getElementById('edit_email_validation');
            const emailInput = document.getElementById('edit_email');
            
            // Clear previous validation
            validationDiv.innerHTML = '';
            validationDiv.className = 'validation-message';
            emailInput.style.borderColor = '#ddd';
            emailInput.style.backgroundColor = '';
            
            if (!email.trim()) {
                return;
            }
            
            // Show checking message
            validationDiv.innerHTML = '🔍 Checking email availability...';
            validationDiv.className = 'validation-message checking';
            validationDiv.style.display = 'block';
            emailInput.style.borderColor = '#ffc107';
            
            // Make AJAX request
            fetch('validate_resident_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_email&email=${encodeURIComponent(email)}&exclude_id=${excludeUserId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    validationDiv.innerHTML = '✅ ' + data.message;
                    validationDiv.className = 'validation-message available';
                    emailInput.style.borderColor = '#28a745';
                    emailInput.style.backgroundColor = '#f8fff9';
                } else {
                    validationDiv.innerHTML = '❌ ' + data.message;
                    validationDiv.className = 'validation-message unavailable';
                    emailInput.style.borderColor = '#dc3545';
                    emailInput.style.backgroundColor = '#fff5f5';
                    emailInput.style.animation = 'shake 0.5s ease-in-out';
                }
                validationDiv.style.display = 'block';
            })
            .catch(error => {
                console.error('Email validation error:', error);
                validationDiv.innerHTML = '⚠️ Unable to check email availability';
                validationDiv.className = 'validation-message error';
                validationDiv.style.display = 'block';
                emailInput.style.borderColor = '#dc3545';
            });
        }
        
        function validateEditPhone(phone, excludeUserId) {
            const validationDiv = document.getElementById('edit_phone_validation');
            const phoneInput = document.getElementById('edit_phone');
            
            // Clear previous validation
            validationDiv.innerHTML = '';
            validationDiv.className = 'validation-message';
            phoneInput.style.borderColor = '#ddd';
            phoneInput.style.backgroundColor = '';
            
            if (!phone.trim()) {
                return;
            }
            
            // Show checking message
            validationDiv.innerHTML = '🔍 Validating phone number...';
            validationDiv.className = 'validation-message checking';
            validationDiv.style.display = 'block';
            phoneInput.style.borderColor = '#ffc107';
            
            // Make AJAX request
            fetch('validate_resident_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_phone&phone=${encodeURIComponent(phone)}&exclude_id=${excludeUserId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    validationDiv.innerHTML = '✅ ' + data.message;
                    validationDiv.className = 'validation-message available';
                    phoneInput.style.borderColor = '#28a745';
                    phoneInput.style.backgroundColor = '#f8fff9';
                } else {
                    validationDiv.innerHTML = '❌ ' + data.message;
                    validationDiv.className = 'validation-message unavailable';
                    phoneInput.style.borderColor = '#dc3545';
                    phoneInput.style.backgroundColor = '#fff5f5';
                    phoneInput.style.animation = 'shake 0.5s ease-in-out';
                }
                validationDiv.style.display = 'block';
            })
            .catch(error => {
                console.error('Phone validation error:', error);
                validationDiv.innerHTML = '⚠️ Unable to validate phone number';
                validationDiv.className = 'validation-message error';
                validationDiv.style.display = 'block';
                phoneInput.style.borderColor = '#dc3545';
            });
        }
        
        function validateEditRoom(roomNumber, excludeResidentId) {
            const validationDiv = document.getElementById('edit_room_validation');
            const roomInput = document.getElementById('edit_room_number');
            
            // Clear previous validation
            validationDiv.innerHTML = '';
            validationDiv.className = 'validation-message';
            roomInput.style.borderColor = '#ddd';
            roomInput.style.backgroundColor = '';
            
            if (!roomNumber.trim()) {
                return;
            }
            
            // Show checking message
            validationDiv.innerHTML = '🔍 Checking room availability...';
            validationDiv.className = 'validation-message checking';
            validationDiv.style.display = 'block';
            roomInput.style.borderColor = '#ffc107';
            
            // Make AJAX request
            fetch('validate_resident_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_room&room_number=${encodeURIComponent(roomNumber)}&exclude_resident_id=${excludeResidentId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    validationDiv.innerHTML = '✅ ' + data.message;
                    validationDiv.className = 'validation-message available';
                    roomInput.style.borderColor = '#28a745';
                    roomInput.style.backgroundColor = '#f8fff9';
                } else {
                    validationDiv.innerHTML = '❌ ' + data.message;
                    validationDiv.className = 'validation-message unavailable';
                    roomInput.style.borderColor = '#dc3545';
                    roomInput.style.backgroundColor = '#fff5f5';
                    roomInput.style.animation = 'shake 0.5s ease-in-out';
                }
                validationDiv.style.display = 'block';
            })
            .catch(error => {
                console.error('Room validation error:', error);
                validationDiv.innerHTML = '⚠️ Unable to check room availability';
                validationDiv.className = 'validation-message error';
                validationDiv.style.display = 'block';
                roomInput.style.borderColor = '#dc3545';
            });
        }
        
        // Edit form submission validation
        function validateEditFormSubmission() {
            const usernameValidation = document.getElementById('edit_username_validation');
            const emailValidation = document.getElementById('edit_email_validation');
            const phoneValidation = document.getElementById('edit_phone_validation');
            const roomValidation = document.getElementById('edit_room_validation');
            let errors = [];
            
            // Check all validations
            if (usernameValidation && usernameValidation.classList.contains('unavailable')) {
                errors.push('❌ Username is already taken');
            }
            if (emailValidation && emailValidation.classList.contains('unavailable')) {
                errors.push('❌ Email is already registered');
            }
            if (phoneValidation && phoneValidation.classList.contains('unavailable')) {
                errors.push('❌ Phone number is invalid or already registered');
            }
            if (roomValidation && roomValidation.classList.contains('unavailable')) {
                errors.push('❌ Room is already occupied by another active resident');
            }
            
            // Check if any validation is still in progress
            if (usernameValidation && usernameValidation.classList.contains('checking')) {
                errors.push('⏳ Username validation is still in progress');
            }
            if (emailValidation && emailValidation.classList.contains('checking')) {
                errors.push('⏳ Email validation is still in progress');
            }
            if (phoneValidation && phoneValidation.classList.contains('checking')) {
                errors.push('⏳ Phone validation is still in progress');
            }
            if (roomValidation && roomValidation.classList.contains('checking')) {
                errors.push('⏳ Room validation is still in progress');
            }
            
            // Show errors if any
            if (errors.length > 0) {
                alert('Cannot update resident!\n\n' + errors.join('\n\n') + '\n\nPlease fix the issues above before submitting.');
                return false;
            }
            
            return true;
        }
        
        // Test validation function for debugging
        function testValidation() {
            console.log('=== TESTING VALIDATION ===');
            
            // Test room validation with a known occupied room
            const roomField = document.getElementById('room_number');
            if (roomField) {
                roomField.value = '101'; // Known occupied room
                console.log('Set room to 101, triggering validation...');
                validateRoomNumber('101');
            } else {
                console.log('ERROR: Room field not found!');
            }
            
            // Test phone validation
            const phoneField = document.getElementById('phone');
            if (phoneField) {
                phoneField.value = '01812429422'; // Test with existing phone
                console.log('Set phone to 01812429422, triggering validation...');
                validatePhoneNumber('01812429422');
            } else {
                console.log('ERROR: Phone field not found!');
            }
            
            // Test invalid phone formats
            setTimeout(() => {
                if (phoneField) {
                    phoneField.value = '123456789'; // Too short
                    console.log('Testing short phone number...');
                    validatePhoneNumber('123456789');
                }
            }, 2000);
            
            setTimeout(() => {
                if (phoneField) {
                    phoneField.value = '01999888777'; // Valid format
                    console.log('Testing valid phone number...');
                    validatePhoneNumber('01999888777');
                }
            }, 4000);
        }
        
        // Function to check and process overdue residents (admin function)
        function processOverdueResidents() {
            if (confirm('This will move all overdue residents to basic plan. Continue?')) {
                fetch('subscription_manager.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=check_overdue_residents'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing overdue residents.');
                });
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addResidentModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Close modal with escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
        
        // Close button
        document.querySelector('.close').onclick = closeModal;
        
        // Notification popup functions
        function showNotification() {
            const popup = document.getElementById('notificationPopup');
            if (popup) {
                popup.classList.add('show');
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    closeNotification();
                }, 5000);
            }
        }
        
        function closeNotification() {
            const popup = document.getElementById('notificationPopup');
            if (popup) {
                popup.classList.remove('show');
                // Remove from DOM after animation
                setTimeout(() => {
                    popup.remove();
                }, 400);
            }
        }
        
        // Show notification on page load if exists
        document.addEventListener('DOMContentLoaded', function() {
            showNotification();
        });
    </script>
    
    <!-- Floating Add Button -->
    <button class="floating-add-btn" onclick="openAddModal()" title="Add New Resident">
        <i class="fas fa-plus"></i>
    </button>
</body>
</html>
