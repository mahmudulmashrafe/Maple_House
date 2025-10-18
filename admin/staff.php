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
    if (isset($_POST['add_staff'])) {
        // Add new staff
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
        $employee_id = trim($_POST['employee_id']);
        $department = trim($_POST['department']);
        $position = trim($_POST['position']);
        $salary = floatval($_POST['salary']);
        $hire_date = $_POST['hire_date'];
        $shift_hours = trim($_POST['shift_hours']);
        
        try {
            $db->beginTransaction();
            
            // Check if username, email, or employee_id already exists
            $check_query = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception('Username or email already exists.');
            }
            
            $check_emp_query = "SELECT COUNT(*) FROM staff WHERE employee_id = :employee_id";
            $check_emp_stmt = $db->prepare($check_emp_query);
            $check_emp_stmt->bindParam(':employee_id', $employee_id);
            $check_emp_stmt->execute();
            
            if ($check_emp_stmt->fetchColumn() > 0) {
                throw new Exception('Employee ID already exists.');
            }
            
            // Insert user
            $user_query = "INSERT INTO users (username, email, password, role_id, first_name, last_name, phone, address, date_of_birth, gender, emergency_contact_name, emergency_contact_phone) 
                          VALUES (:username, :email, :password, 5, :first_name, :last_name, :phone, :address, :date_of_birth, :gender, :emergency_contact_name, :emergency_contact_phone)";
            
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
            
            // Insert staff
            $staff_query = "INSERT INTO staff (user_id, employee_id, department, position, salary, hire_date, shift_hours) 
                           VALUES (:user_id, :employee_id, :department, :position, :salary, :hire_date, :shift_hours)";
            
            $staff_stmt = $db->prepare($staff_query);
            $staff_stmt->bindParam(':user_id', $user_id);
            $staff_stmt->bindParam(':employee_id', $employee_id);
            $staff_stmt->bindParam(':department', $department);
            $staff_stmt->bindParam(':position', $position);
            $staff_stmt->bindParam(':salary', $salary);
            $staff_stmt->bindParam(':hire_date', $hire_date);
            $staff_stmt->bindParam(':shift_hours', $shift_hours);
            $staff_stmt->execute();
            
            $db->commit();
            $success_message = "Staff member added successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
    
    if (isset($_POST['edit_staff'])) {
        // Edit existing staff
        $staff_id = intval($_POST['staff_id']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $employee_id = trim($_POST['employee_id']);
        $department = trim($_POST['department']);
        $position = trim($_POST['position']);
        $salary = floatval($_POST['salary']);
        $is_active = intval($_POST['is_active']);
        $emergency_contact_name = trim($_POST['emergency_contact_name']);
        $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
        $address = trim($_POST['address']);
        $shift_hours = trim($_POST['shift_hours']);
        
        try {
            $db->beginTransaction();
            
            // Update user information (with optional password update)
            if (!empty($password)) {
                // Update with password
                $user_update_query = "UPDATE users u 
                                     JOIN staff s ON u.id = s.user_id 
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
                                     WHERE s.id = :staff_id";
            } else {
                // Update without password
                $user_update_query = "UPDATE users u 
                                     JOIN staff s ON u.id = s.user_id 
                                     SET u.first_name = :first_name, 
                                         u.last_name = :last_name,
                                         u.username = :username,
                                         u.email = :email, 
                                         u.phone = :phone,
                                         u.address = :address,
                                         u.is_active = :is_active,
                                         u.emergency_contact_name = :emergency_contact_name,
                                         u.emergency_contact_phone = :emergency_contact_phone
                                     WHERE s.id = :staff_id";
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
            $user_update_stmt->bindParam(':staff_id', $staff_id);
            $user_update_stmt->execute();
            
            // Update staff information
            $staff_update_query = "UPDATE staff 
                                     SET employee_id = :employee_id,
                                         department = :department,
                                         position = :position,
                                         salary = :salary,
                                         shift_hours = :shift_hours
                                     WHERE id = :staff_id";
            
            $staff_update_stmt = $db->prepare($staff_update_query);
            $staff_update_stmt->bindParam(':employee_id', $employee_id);
            $staff_update_stmt->bindParam(':department', $department);
            $staff_update_stmt->bindParam(':position', $position);
            $staff_update_stmt->bindParam(':salary', $salary);
            $staff_update_stmt->bindParam(':shift_hours', $shift_hours);
            $staff_update_stmt->bindParam(':staff_id', $staff_id);
            $staff_update_stmt->execute();
            
            $db->commit();
            $success_message = "Staff member updated successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_staff'])) {
        // Delete staff
        $staff_id = intval($_POST['staff_id']);
        
        try {
            $db->beginTransaction();
            
            // Get user_id before deleting staff
            $get_user_query = "SELECT user_id FROM staff WHERE id = :staff_id";
            $get_user_stmt = $db->prepare($get_user_query);
            $get_user_stmt->bindParam(':staff_id', $staff_id);
            $get_user_stmt->execute();
            $user_id = $get_user_stmt->fetchColumn();
            
            // Delete staff record
            $delete_staff_query = "DELETE FROM staff WHERE id = :staff_id";
            $delete_staff_stmt = $db->prepare($delete_staff_query);
            $delete_staff_stmt->bindParam(':staff_id', $staff_id);
            $delete_staff_stmt->execute();
            
            // Delete user record
            if ($user_id) {
                $delete_user_query = "DELETE FROM users WHERE id = :user_id";
                $delete_user_stmt = $db->prepare($delete_user_query);
                $delete_user_stmt->bindParam(':user_id', $user_id);
                $delete_user_stmt->execute();
            }
            
            $db->commit();
            $success_message = "Staff member deleted successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
}

// Get staff with pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

$staff_query = "SELECT s.*, u.first_name, u.last_name, u.username, u.email, u.phone, u.is_active, u.address, u.date_of_birth, u.gender,
                       u.emergency_contact_name, u.emergency_contact_phone,
                       CASE 
                           WHEN u.is_active = 1 THEN 'Active'
                           ELSE 'Inactive'
                       END as activity_status
                FROM staff s
                JOIN users u ON s.user_id = u.id
                WHERE u.role_id = 5
                ORDER BY s.hire_date DESC
                LIMIT :limit OFFSET :offset";

$staff_stmt = $db->prepare($staff_query);
$staff_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$staff_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$staff_stmt->execute();
$staff_members = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM staff s JOIN users u ON s.user_id = u.id WHERE u.role_id = 5";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$total_staff = $count_stmt->fetchColumn();
$total_pages = ceil($total_staff / $per_page);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total_staff,
                    COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as active_staff,
                    COUNT(CASE WHEN u.is_active = 0 THEN 1 END) as inactive_staff,
                    AVG(s.salary) as avg_salary
                FROM staff s
                JOIN users u ON s.user_id = u.id
                WHERE u.role_id = 5";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Maple House</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="js/user-validation.js"></script>
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
            border: 2px solid #f5576c !important;
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
        
        .staff-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .staff-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid #f5576c;
            display: flex;
            flex-direction: column;
            height: 400px;
            max-height: 400px;
        }
        
        .staff-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #f5576c;
        }
        
        .staff-header {
            padding: 20px;
            background: white;
            color: #f5576c;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .staff-avatar {
            width: 60px;
            height: 60px;
            background: rgba(245, 87, 108, 0.1);
            border: 2px solid #f5576c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #f5576c;
        }
        
        .staff-basic h3 {
            margin: 0 0 5px 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .staff-email {
            margin: 0 0 10px 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .staff-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .employee-badge {
            background: rgba(245, 87, 108, 0.1);
            color: #f5576c;
            border: 1px solid #f5576c;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .staff-details {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            max-height: 200px;
        }
        
        /* Custom Scrollbar */
        .staff-details::-webkit-scrollbar {
            width: 6px;
        }
        
        .staff-details::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .staff-details::-webkit-scrollbar-thumb {
            background: #f5576c;
            border-radius: 3px;
        }
        
        .staff-details::-webkit-scrollbar-thumb:hover {
            background: #e74c3c;
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
        
        .staff-actions {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .staff-grid {
                grid-template-columns: 1fr;
            }
            
            .staff-header {
                flex-direction: column;
                text-align: center;
            }
            
            .staff-actions {
                flex-direction: column;
            }
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
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
            background: #f5576c;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(245, 87, 108, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .floating-add-btn:hover {
            background: #e74c3c;
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(245, 87, 108, 0.6);
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
            background: #f5576c;
            border-radius: 4px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #e74c3c;
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
            color: #f5576c;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f5576c;
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
            <i class="fas fa-user-tie"></i>
            <div class="stat-number"><?php echo $stats['total_staff']; ?></div>
            <div class="stat-label">Total Staff</div>
        </div>
        
        <div class="stat-card success">
            <i class="fas fa-check-circle"></i>
            <div class="stat-number"><?php echo $stats['active_staff']; ?></div>
            <div class="stat-label">Active Staff</div>
        </div>
        
        <div class="stat-card danger">
            <i class="fas fa-times-circle"></i>
            <div class="stat-number"><?php echo $stats['inactive_staff']; ?></div>
            <div class="stat-label">Inactive Staff</div>
        </div>
        
        <div class="stat-card warning">
            <i class="fas fa-money-bill-wave"></i>
            <div class="stat-number">৳<?php echo number_format($stats['avg_salary']); ?></div>
            <div class="stat-label">Average Salary</div>
        </div>
    </div>

    <!-- Staff Grid -->
    <div class="staff-grid">
        <?php if (!empty($staff_members)): ?>
            <?php foreach ($staff_members as $staff): ?>
                <div class="staff-card">
                    <div class="staff-header">
                        <div class="staff-avatar">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="staff-basic">
                            <h3><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h3>
                            <p class="staff-email"><?php echo htmlspecialchars($staff['email']); ?></p>
                            <div class="staff-badges">
                                <span class="employee-badge">ID: <?php echo htmlspecialchars($staff['employee_id']); ?></span>
                                <span class="status-badge status-<?php echo $staff['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $staff['activity_status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="staff-details">
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-building"></i> Department:</span>
                            <span><?php echo htmlspecialchars($staff['department']); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-briefcase"></i> Position:</span>
                            <span><?php echo htmlspecialchars($staff['position']); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-money-bill-wave"></i> Salary:</span>
                            <span>৳<?php echo number_format($staff['salary']); ?>/month</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-calendar"></i> Hired:</span>
                            <span><?php echo date('M j, Y', strtotime($staff['hire_date'])); ?></span>
                        </div>
                        
                        <?php if ($staff['phone']): ?>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-phone"></i> Phone:</span>
                            <span><?php echo htmlspecialchars($staff['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($staff['shift_hours']): ?>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-clock"></i> Shift:</span>
                            <span><?php echo htmlspecialchars($staff['shift_hours']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="staff-actions">
                        <button onclick="viewStaff(<?php echo $staff['id']; ?>)" class="btn btn-primary btn-sm" title="View Details">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button onclick="editStaff(<?php echo $staff['id']; ?>)" class="btn btn-success btn-sm" title="Edit">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button onclick="deleteStaff(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>')" class="btn btn-danger btn-sm" title="Delete">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
                <i class="fas fa-user-tie" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
                <h3 style="color: #666; margin-bottom: 10px;">No staff members found</h3>
                <p style="color: #999;">Click "Add New Staff" to get started.</p>
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

    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Staff Member</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="addStaffForm" onsubmit="return validateStaffFormSubmission()">
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" name="first_name" id="first_name" required>
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
                                <input type="tel" name="phone" id="phone" required placeholder="01XXXXXXXXX (11 digits)">
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
                    
                    <!-- Employment Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-briefcase"></i> Employment Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employee_id">Employee ID *</label>
                                <input type="text" name="employee_id" id="employee_id" required placeholder="EMP001, EMP002, etc.">
                                <div class="validation-message" id="employee_id_validation"></div>
                            </div>
                            <div class="form-group">
                                <label for="department">Department *</label>
                                <select name="department" id="department" required>
                                    <option value="">Select Department</option>
                                    <option value="General Care">General Care</option>
                                    <option value="Medical">Medical</option>
                                    <option value="Kitchen">Kitchen</option>
                                    <option value="Housekeeping">Housekeeping</option>
                                    <option value="Administration">Administration</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Security">Security</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="position">Position *</label>
                                <input type="text" name="position" id="position" required>
                            </div>
                            <div class="form-group">
                                <label for="salary">Monthly Salary *</label>
                                <input type="number" name="salary" id="salary" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="hire_date">Hire Date *</label>
                                <input type="date" name="hire_date" id="hire_date" required>
                            </div>
                            <div class="form-group">
                                <label for="shift_hours">Shift Hours</label>
                                <input type="text" name="shift_hours" id="shift_hours" placeholder="e.g., 9:00 AM - 5:00 PM">
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
                    
                    <!-- Address Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea name="address" id="address" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <!-- Empty space for alignment -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeModal()" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="add_staff" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add Staff Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Staff Modal -->
    <div id="viewStaffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Staff Details</h3>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewStaffContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div id="editStaffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Staff Member</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body" id="editStaffContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addStaffModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('addStaffModal').style.display = 'none';
        }
        
        // Store staff data for JavaScript access
        const staffData = <?php echo json_encode($staff_members); ?>;
        
        function viewStaff(id) {
            const staff = staffData.find(s => s.id == id);
            if (!staff) return;
            
            const modal = document.getElementById('viewStaffModal');
            const content = document.getElementById('viewStaffContent');
            
            content.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-user"></i> Personal Information</h4>
                        <p><strong>Name:</strong> ${staff.first_name} ${staff.last_name}</p>
                        <p><strong>Email:</strong> ${staff.email}</p>
                        <p><strong>Phone:</strong> ${staff.phone || 'N/A'}</p>
                        <p><strong>Date of Birth:</strong> ${staff.date_of_birth ? new Date(staff.date_of_birth).toLocaleDateString() : 'N/A'}</p>
                        <p><strong>Gender:</strong> ${staff.gender || 'N/A'}</p>
                        <p><strong>Address:</strong> ${staff.address || 'N/A'}</p>
                        <p><strong>Activity Status:</strong> <span class="status-badge status-${staff.is_active ? 'active' : 'inactive'}">${staff.activity_status}</span></p>
                    </div>
                    
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-briefcase"></i> Employment Information</h4>
                        <p><strong>Employee ID:</strong> ${staff.employee_id}</p>
                        <p><strong>Department:</strong> ${staff.department}</p>
                        <p><strong>Position:</strong> ${staff.position}</p>
                        <p><strong>Salary:</strong> ৳${parseInt(staff.salary || 0).toLocaleString()}/month</p>
                        <p><strong>Hire Date:</strong> ${new Date(staff.hire_date).toLocaleDateString()}</p>
                        <p><strong>Shift Hours:</strong> ${staff.shift_hours || 'N/A'}</p>
                    </div>
                    
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                        <p><strong>Emergency Contact Name:</strong> ${staff.emergency_contact_name || 'N/A'}</p>
                        <p><strong>Emergency Contact Phone:</strong> ${staff.emergency_contact_phone || 'N/A'}</p>
                    </div>
                </div>
            `;
            
            modal.style.display = 'block';
        }
        
        function editStaff(id) {
            const staff = staffData.find(s => s.id == id);
            if (!staff) return;
            
            const modal = document.getElementById('editStaffModal');
            const content = document.getElementById('editStaffContent');
            
            content.innerHTML = `
                <form method="POST" id="editStaffForm" onsubmit="return validateEditStaffFormSubmission()">
                    <input type="hidden" name="edit_staff" value="1">
                    <input type="hidden" name="staff_id" value="${staff.id}">
                    <input type="hidden" name="current_user_id" value="${staff.user_id}">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" id="edit_first_name" value="${staff.first_name}" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" id="edit_last_name" value="${staff.last_name}" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" id="edit_username" value="${staff.username || ''}" required>
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
                                <input type="email" name="email" id="edit_email" value="${staff.email}" required>
                                <div class="validation-message" id="edit_email_validation"></div>
                            </div>
                            <div class="form-group">
                                <label>Phone Number *</label>
                                <input type="tel" name="phone" id="edit_phone" value="${staff.phone || ''}" required placeholder="01XXXXXXXXX (11 digits)">
                                <div class="validation-message" id="edit_phone_validation"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Employment Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-briefcase"></i> Employment Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Employee ID *</label>
                                <input type="text" name="employee_id" id="edit_employee_id" value="${staff.employee_id}" required placeholder="EMP001, EMP002, etc.">
                                <div class="validation-message" id="edit_employee_id_validation"></div>
                            </div>
                            <div class="form-group">
                                <label>Department *</label>
                                <select name="department" required>
                                    <option value="General Care" ${staff.department === 'General Care' ? 'selected' : ''}>General Care</option>
                                    <option value="Medical" ${staff.department === 'Medical' ? 'selected' : ''}>Medical</option>
                                    <option value="Kitchen" ${staff.department === 'Kitchen' ? 'selected' : ''}>Kitchen</option>
                                    <option value="Housekeeping" ${staff.department === 'Housekeeping' ? 'selected' : ''}>Housekeeping</option>
                                    <option value="Administration" ${staff.department === 'Administration' ? 'selected' : ''}>Administration</option>
                                    <option value="Maintenance" ${staff.department === 'Maintenance' ? 'selected' : ''}>Maintenance</option>
                                    <option value="Security" ${staff.department === 'Security' ? 'selected' : ''}>Security</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Position *</label>
                                <input type="text" name="position" value="${staff.position}" required>
                            </div>
                            <div class="form-group">
                                <label>Monthly Salary *</label>
                                <input type="number" name="salary" value="${staff.salary}" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Activity Status</label>
                                <select name="is_active">
                                    <option value="1" ${staff.is_active == 1 ? 'selected' : ''}>Active</option>
                                    <option value="0" ${staff.is_active == 0 ? 'selected' : ''}>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Shift Hours</label>
                                <input type="text" name="shift_hours" value="${staff.shift_hours || ''}" placeholder="e.g., 9:00 AM - 5:00 PM">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Emergency Contact Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Emergency Contact Name</label>
                                <input type="text" name="emergency_contact_name" value="${staff.emergency_contact_name || ''}">
                            </div>
                            <div class="form-group">
                                <label>Emergency Contact Phone</label>
                                <input type="tel" name="emergency_contact_phone" value="${staff.emergency_contact_phone || ''}">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" rows="3">${staff.address || ''}</textarea>
                            </div>
                            <div class="form-group">
                                <!-- Empty space for alignment -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeEditModal()" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Staff Member
                        </button>
                    </div>
                </form>
            `;
            
            modal.style.display = 'block';
            
            // Initialize edit form validation
            setTimeout(() => {
                initializeFormValidation({
                    username: { fieldId: 'edit_username', validationId: 'edit_username_validation', excludeId: staff.user_id },
                    email: { fieldId: 'edit_email', validationId: 'edit_email_validation', excludeId: staff.user_id },
                    phone: { fieldId: 'edit_phone', validationId: 'edit_phone_validation', excludeId: staff.user_id },
                    employee_id: { fieldId: 'edit_employee_id', validationId: 'edit_employee_id_validation', userType: 'staff', excludeId: staff.id }
                });
            }, 200);
        }
        
        function deleteStaff(id, name) {
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
                        <p>Are you sure you want to delete staff member <strong>"${name}"</strong>?</p>
                        <p style="color: #e74c3c; font-size: 0.9rem;">⚠️ This action cannot be undone and will permanently remove all staff data.</p>
                    </div>
                    <div class="delete-modal-actions">
                        <button onclick="closeDeleteModal()" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button onclick="confirmDeleteStaff(${id})" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Staff Member
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.style.display = 'block';
        }
        
        function confirmDeleteStaff(id) {
            // Create a form to submit the delete request
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_staff" value="1">
                <input type="hidden" name="staff_id" value="${id}">
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
            document.getElementById('viewStaffModal').style.display = 'none';
        }
        
        function closeEditModal() {
            document.getElementById('editStaffModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addStaffModal');
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
    </script>

    <script>
        // Initialize form validation when modal opens
        function openAddModal() {
            document.getElementById('addStaffModal').style.display = 'block';
            
            // Initialize validation after modal opens
            setTimeout(() => {
                initializeFormValidation({
                    username: { fieldId: 'username', validationId: 'username_validation' },
                    email: { fieldId: 'email', validationId: 'email_validation' },
                    phone: { fieldId: 'phone', validationId: 'phone_validation' },
                    employee_id: { fieldId: 'employee_id', validationId: 'employee_id_validation', userType: 'staff' }
                });
            }, 200);
        }
        
        // Form submission validation
        function validateStaffFormSubmission() {
            return validateFormSubmission('addStaffForm', [
                'username_validation',
                'email_validation', 
                'phone_validation',
                'employee_id_validation'
            ]);
        }
        
        // Edit form submission validation
        function validateEditStaffFormSubmission() {
            return validateFormSubmission('editStaffForm', [
                'edit_username_validation',
                'edit_email_validation', 
                'edit_phone_validation',
                'edit_employee_id_validation'
            ]);
        }
        
        // Close modal function
        function closeModal() {
            document.getElementById('addStaffModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addStaffModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Close modal when clicking X
        document.addEventListener('DOMContentLoaded', function() {
            const closeBtn = document.querySelector('.close');
            if (closeBtn) {
                closeBtn.onclick = closeModal;
            }
            // Show notification on page load if exists
            showNotification();
        });
        
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
    </script>
    
    <!-- Floating Add Button -->
    <button class="floating-add-btn" onclick="openAddModal()" title="Add New Staff">
        <i class="fas fa-plus"></i>
    </button>
</body>
</html>
