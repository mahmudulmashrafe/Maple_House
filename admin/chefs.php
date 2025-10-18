<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
    if (isset($_POST['add_chef'])) {
        // Add new chef
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
        $specialization = trim($_POST['specialization']);
        $experience_years = intval($_POST['experience_years']);
        $shift_hours = trim($_POST['shift_hours']);
        $cuisine_expertise = trim($_POST['cuisine_expertise']);
        $hire_date = $_POST['hire_date'];
        $monthly_salary = floatval($_POST['monthly_salary']);
        $is_active = intval($_POST['is_active']);
        
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
            
            // Insert user with role_id = 4 (Chef)
            $user_query = "INSERT INTO users (username, email, password, role_id, first_name, last_name, phone, address, date_of_birth, gender, emergency_contact_name, emergency_contact_phone, is_active) 
                          VALUES (:username, :email, :password, 4, :first_name, :last_name, :phone, :address, :date_of_birth, :gender, :emergency_contact_name, :emergency_contact_phone, :is_active)";
            
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
            $user_stmt->bindParam(':is_active', $is_active);
            $user_stmt->execute();
            
            $user_id = $db->lastInsertId();
            
            // Insert chef record
            $chef_query = "INSERT INTO chefs (user_id, employee_id, specialization, experience_years, cuisine_expertise, shift_hours, hire_date, monthly_salary) 
                          VALUES (:user_id, :employee_id, :specialization, :experience_years, :cuisine_expertise, :shift_hours, :hire_date, :monthly_salary)";
            
            $chef_stmt = $db->prepare($chef_query);
            $chef_stmt->bindParam(':user_id', $user_id);
            $chef_stmt->bindParam(':employee_id', $employee_id);
            $chef_stmt->bindParam(':specialization', $specialization);
            $chef_stmt->bindParam(':experience_years', $experience_years);
            $chef_stmt->bindParam(':cuisine_expertise', $cuisine_expertise);
            $chef_stmt->bindParam(':shift_hours', $shift_hours);
            $chef_stmt->bindParam(':hire_date', $hire_date);
            $chef_stmt->bindParam(':monthly_salary', $monthly_salary);
            $chef_stmt->execute();
            
            $db->commit();
            $success_message = "Chef added successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
    
    if (isset($_POST['edit_chef'])) {
        // Edit existing chef
        $chef_id = intval($_POST['chef_id']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $specialization = trim($_POST['specialization']);
        $experience_years = intval($_POST['experience_years']);
        $monthly_salary = floatval($_POST['monthly_salary']);
        $is_active = intval($_POST['is_active']);
        $emergency_contact_name = trim($_POST['emergency_contact_name']);
        $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
        $address = trim($_POST['address']);
        $shift_hours = trim($_POST['shift_hours']);
        $cuisine_expertise = trim($_POST['cuisine_expertise']);
        
        try {
            $db->beginTransaction();
            
            // Update user information (with optional password update)
            if (!empty($password)) {
                // Update with password
                $user_update_query = "UPDATE users u 
                                     JOIN chefs c ON u.id = c.user_id 
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
                                     WHERE c.id = :chef_id";
            } else {
                // Update without password
                $user_update_query = "UPDATE users u 
                                     JOIN chefs c ON u.id = c.user_id 
                                     SET u.first_name = :first_name, 
                                         u.last_name = :last_name,
                                         u.username = :username,
                                         u.email = :email, 
                                         u.phone = :phone,
                                         u.address = :address,
                                         u.is_active = :is_active,
                                         u.emergency_contact_name = :emergency_contact_name,
                                         u.emergency_contact_phone = :emergency_contact_phone
                                     WHERE c.id = :chef_id";
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
            $user_update_stmt->bindParam(':chef_id', $chef_id);
            $user_update_stmt->execute();
            
            // Update chef information
            $chef_update_query = "UPDATE chefs 
                                     SET specialization = :specialization,
                                         experience_years = :experience_years,
                                         cuisine_expertise = :cuisine_expertise,
                                         shift_hours = :shift_hours,
                                         monthly_salary = :monthly_salary
                                     WHERE id = :chef_id";
            
            $chef_update_stmt = $db->prepare($chef_update_query);
            $chef_update_stmt->bindParam(':specialization', $specialization);
            $chef_update_stmt->bindParam(':experience_years', $experience_years);
            $chef_update_stmt->bindParam(':cuisine_expertise', $cuisine_expertise);
            $chef_update_stmt->bindParam(':shift_hours', $shift_hours);
            $chef_update_stmt->bindParam(':monthly_salary', $monthly_salary);
            $chef_update_stmt->bindParam(':chef_id', $chef_id);
            $chef_update_stmt->execute();
            
            $db->commit();
            $success_message = "Chef updated successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_chef'])) {
        // Delete chef
        $chef_id = intval($_POST['chef_id']);
        
        try {
            $db->beginTransaction();
            
            // Get user_id before deleting chef
            $get_user_query = "SELECT user_id FROM chefs WHERE id = :chef_id";
            $get_user_stmt = $db->prepare($get_user_query);
            $get_user_stmt->bindParam(':chef_id', $chef_id);
            $get_user_stmt->execute();
            $user_id = $get_user_stmt->fetchColumn();
            
            // Delete chef record
            $delete_chef_query = "DELETE FROM chefs WHERE id = :chef_id";
            $delete_chef_stmt = $db->prepare($delete_chef_query);
            $delete_chef_stmt->bindParam(':chef_id', $chef_id);
            $delete_chef_stmt->execute();
            
            // Delete user record
            if ($user_id) {
                $delete_user_query = "DELETE FROM users WHERE id = :user_id";
                $delete_user_stmt = $db->prepare($delete_user_query);
                $delete_user_stmt->bindParam(':user_id', $user_id);
                $delete_user_stmt->execute();
            }
            
            $db->commit();
            $success_message = "Chef deleted successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
}

// Get chefs with pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Get chefs from chefs table joined with users
// Check if monthly_salary column exists
$column_check = $db->query("SHOW COLUMNS FROM chefs LIKE 'monthly_salary'");
$salary_column_exists = $column_check->rowCount() > 0;

if ($salary_column_exists) {
    $chefs_query = "SELECT c.*, u.first_name, u.last_name, u.username, u.email, u.phone, u.address, u.date_of_birth, u.gender, u.emergency_contact_name, u.emergency_contact_phone, u.is_active,
                           CASE 
                               WHEN u.is_active = 1 THEN 'Active'
                               ELSE 'Inactive'
                           END as activity_status,
                           COALESCE(c.monthly_salary, 35000) as monthly_salary
                    FROM chefs c
                    JOIN users u ON c.user_id = u.id
                    ORDER BY u.first_name, u.last_name
                    LIMIT :limit OFFSET :offset";
} else {
    $chefs_query = "SELECT c.*, u.first_name, u.last_name, u.username, u.email, u.phone, u.address, u.date_of_birth, u.gender, u.emergency_contact_name, u.emergency_contact_phone, u.is_active,
                           CASE 
                               WHEN u.is_active = 1 THEN 'Active'
                               ELSE 'Inactive'
                           END as activity_status,
                           35000 as monthly_salary
                    FROM chefs c
                    JOIN users u ON c.user_id = u.id
                    ORDER BY u.first_name, u.last_name
                    LIMIT :limit OFFSET :offset";
}

$chefs_stmt = $db->prepare($chefs_query);
$chefs_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$chefs_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$chefs_stmt->execute();
$chefs = $chefs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM chefs";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$total_chefs = $count_stmt->fetchColumn();
$total_pages = ceil($total_chefs / $per_page);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total_chefs,
                    COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as active_chefs,
                    COUNT(CASE WHEN u.is_active = 0 THEN 1 END) as inactive_chefs,
                    AVG(c.experience_years) as avg_experience
                FROM chefs c
                JOIN users u ON c.user_id = u.id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chefs Management - Maple House</title>
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
            border: 2px solid #43e97b !important;
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
        
        .content-grid {
            margin-bottom: 30px;
        }
        
        .chefs-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .recent-meals {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .recent-meals h3 {
            margin: 0 0 20px 0;
            color: #2c5aa0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .meal-item {
            border-bottom: 1px solid #e9ecef;
            padding: 10px 0;
            margin-bottom: 10px;
        }
        
        .meal-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        /* Chef Card Styling */
        .chefs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chef-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid #43e97b;
            display: flex;
            flex-direction: column;
            height: 400px;
            max-height: 400px;
        }
        
        .chef-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #43e97b;
        }
        
        .chef-header {
            padding: 20px;
            background: white;
            color: #43e97b;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .chef-avatar {
            width: 60px;
            height: 60px;
            background: rgba(67, 233, 123, 0.1);
            border: 2px solid #43e97b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #43e97b;
        }
        
        .chef-basic h3 {
            margin: 0 0 5px 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .chef-email {
            margin: 0 0 10px 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .chef-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .specialization-badge {
            background: rgba(67, 233, 123, 0.1);
            color: #43e97b;
            border: 1px solid #43e97b;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .chef-details {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            max-height: 200px;
        }
        
        /* Custom Scrollbar */
        .chef-details::-webkit-scrollbar {
            width: 6px;
        }
        
        .chef-details::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .chef-details::-webkit-scrollbar-thumb {
            background: #43e97b;
            border-radius: 3px;
        }
        
        .chef-details::-webkit-scrollbar-thumb:hover {
            background: #38f9d7;
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
        
        .chef-actions {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .chefs-grid {
                grid-template-columns: 1fr;
            }
            
            .chef-header {
                flex-direction: column;
                text-align: center;
            }
            
            .chef-actions {
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
        
        .delete-modal-content {
            background: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
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
        
        /* Floating Add Button */
        .floating-add-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 45px;
            height: 45px;
            background: #43e97b;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(67, 233, 123, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .floating-add-btn:hover {
            background: #38f9d7;
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(67, 233, 123, 0.6);
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
            background: #43e97b;
            border-radius: 4px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #38f9d7;
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
            color: #43e97b;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #43e97b;
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
        
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
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
            <i class="fas fa-utensils"></i>
            <div class="stat-number"><?php echo $stats['total_chefs']; ?></div>
            <div class="stat-label">Total Chefs</div>
        </div>
        
        <div class="stat-card success">
            <i class="fas fa-check-circle"></i>
            <div class="stat-number"><?php echo $stats['active_chefs']; ?></div>
            <div class="stat-label">Active Chefs</div>
        </div>
        
        <div class="stat-card danger">
            <i class="fas fa-times-circle"></i>
            <div class="stat-number"><?php echo $stats['inactive_chefs']; ?></div>
            <div class="stat-label">Inactive Chefs</div>
        </div>
        
        <div class="stat-card warning">
            <i class="fas fa-star"></i>
            <div class="stat-number"><?php echo number_format($stats['avg_experience'] ?: 0, 1); ?></div>
            <div class="stat-label">Average Experience (Years)</div>
        </div>
    </div>

    <!-- Chefs Grid -->
    <div class="chefs-grid">
            <?php if (!empty($chefs)): ?>
                <?php foreach ($chefs as $chef): ?>
                    <div class="chef-card">
                        <div class="chef-header">
                            <div class="chef-avatar">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <div class="chef-basic">
                                <h3>Chef <?php echo htmlspecialchars($chef['first_name'] . ' ' . $chef['last_name']); ?></h3>
                                <p class="chef-email"><?php echo htmlspecialchars($chef['email']); ?></p>
                                <div class="chef-badges">
                                    <span class="specialization-badge"><?php echo htmlspecialchars($chef['specialization'] ?: 'General'); ?></span>
                                    <span class="status-badge status-<?php echo $chef['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $chef['activity_status']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="chef-details">
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-utensils"></i> Specialization:</span>
                                <span><?php echo htmlspecialchars($chef['specialization'] ?: 'General Cooking'); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-calendar"></i> Experience:</span>
                                <span><?php echo intval($chef['experience_years']); ?> years</span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-clock"></i> Shift Hours:</span>
                                <span><?php echo htmlspecialchars($chef['shift_hours'] ?: 'N/A'); ?></span>
                            </div>
                            
                            <?php if ($chef['phone']): ?>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-phone"></i> Phone:</span>
                                <span><?php echo htmlspecialchars($chef['phone']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="chef-actions">
                            <button onclick="viewChef(<?php echo $chef['id']; ?>)" class="btn btn-primary btn-sm" title="View Details">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button onclick="editChef(<?php echo $chef['id']; ?>)" class="btn btn-success btn-sm" title="Edit">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button onclick="deleteChef(<?php echo $chef['id']; ?>, 'Chef <?php echo htmlspecialchars($chef['first_name'] . ' ' . $chef['last_name']); ?>')" class="btn btn-danger btn-sm" title="Delete">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
                    <i class="fas fa-utensils" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
                    <h3 style="color: #666; margin-bottom: 10px;">No chefs found</h3>
                    <p style="color: #999;">Click "Add New Chef" to get started.</p>
                </div>
            <?php endif; ?>
        </div>
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

    <!-- Add Chef Modal -->
    <div id="addChefModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Chef</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="addChefForm" onsubmit="return validateChefFormSubmission()">
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" name="first_name" id="first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
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
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" name="date_of_birth" id="date_of_birth" required>
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select name="gender" id="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kitchen Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-utensils"></i> Kitchen Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employee_id">Employee ID *</label>
                                <input type="text" name="employee_id" id="employee_id" required placeholder="CHEF001, CHEF002, etc.">
                                <div class="validation-message" id="employee_id_validation"></div>
                            </div>
                            <div class="form-group">
                                <label for="specialization">Specialization</label>
                                <select name="specialization" id="specialization" required>
                                    <option value="">Select Specialization</option>
                                    <option value="Head Chef">Head Chef</option>
                                    <option value="Sous Chef">Sous Chef</option>
                                    <option value="Pastry Chef">Pastry Chef</option>
                                    <option value="Grill Chef">Grill Chef</option>
                                    <option value="Salad Chef">Salad Chef</option>
                                    <option value="Breakfast Chef">Breakfast Chef</option>
                                    <option value="General Cook">General Cook</option>
                                    <option value="Kitchen Assistant">Kitchen Assistant</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cuisine_expertise">Cuisine Expertise</label>
                                <input type="text" name="cuisine_expertise" id="cuisine_expertise" required>
                            </div>
                            <div class="form-group">
                                <label for="experience_years">Experience Years</label>
                                <input type="number" name="experience_years" id="experience_years" min="0" max="50" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="hire_date">Hire Date</label>
                                <input type="date" name="hire_date" id="hire_date" required>
                            </div>
                            <div class="form-group">
                                <label for="shift_hours">Shift Hours</label>
                                <input type="text" name="shift_hours" id="shift_hours" placeholder="e.g., 6:00 AM - 2:00 PM">
                            </div>
                            <div class="form-group">
                                <label for="monthly_salary">Monthly Salary (৳) *</label>
                                <input type="number" name="monthly_salary" id="monthly_salary" value="35000" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="is_active">Activity Status</label>
                                <select name="is_active" id="is_active" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <!-- Empty space for alignment -->
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
                        <button type="submit" name="add_chef" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add Chef
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Chef Modal -->
    <div id="viewChefModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Chef Details</h3>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewChefContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Edit Chef Modal -->
    <div id="editChefModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Chef</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body" id="editChefContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addChefModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('addChefModal').style.display = 'none';
        }
        
        // Store chef data for JavaScript access
        const chefData = <?php echo json_encode($chefs); ?>;
        
        function viewChef(id) {
            const chef = chefData.find(c => c.id == id);
            if (!chef) return;
            
            const modal = document.getElementById('viewChefModal');
            const content = document.getElementById('viewChefContent');
            
            content.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-user"></i> Personal Information</h4>
                        <p><strong>Name:</strong> Chef ${chef.first_name} ${chef.last_name}</p>
                        <p><strong>Employee ID:</strong> ${chef.employee_id}</p>
                        <p><strong>Email:</strong> ${chef.email}</p>
                        <p><strong>Phone:</strong> ${chef.phone || 'N/A'}</p>
                        <p><strong>Date of Birth:</strong> ${chef.date_of_birth ? new Date(chef.date_of_birth).toLocaleDateString() : 'N/A'}</p>
                        <p><strong>Gender:</strong> ${chef.gender || 'N/A'}</p>
                        <p><strong>Address:</strong> ${chef.address || 'N/A'}</p>
                        <p><strong>Activity Status:</strong> <span class="status-badge status-${chef.is_active ? 'active' : 'inactive'}">${chef.activity_status}</span></p>
                    </div>
                    
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-utensils"></i> Professional Information</h4>
                        <p><strong>Specialization:</strong> ${chef.specialization}</p>
                        <p><strong>Experience:</strong> ${chef.experience_years} years</p>
                        <p><strong>Cuisine Expertise:</strong> ${chef.cuisine_expertise || 'N/A'}</p>
                        <p><strong>Shift Hours:</strong> ${chef.shift_hours || 'N/A'}</p>
                        <p><strong>Monthly Salary:</strong> ৳${parseInt(chef.monthly_salary || 35000).toLocaleString()}</p>
                        <p><strong>Hire Date:</strong> ${chef.hire_date ? new Date(chef.hire_date).toLocaleDateString() : 'N/A'}</p>
                        <p><strong>Availability:</strong> ${chef.is_available ? 'Available' : 'Not Available'}</p>
                    </div>
                    
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                        <p><strong>Emergency Contact Name:</strong> ${chef.emergency_contact_name || 'N/A'}</p>
                        <p><strong>Emergency Contact Phone:</strong> ${chef.emergency_contact_phone || 'N/A'}</p>
                        <p><strong>Address:</strong> ${chef.address || 'N/A'}</p>
                    </div>
                </div>
            `;
            
            modal.style.display = 'block';
        }
        
        function editChef(id) {
            const chef = chefData.find(c => c.id == id);
            if (!chef) return;
            
            const modal = document.getElementById('editChefModal');
            const content = document.getElementById('editChefContent');
            
            content.innerHTML = `
                <form method="POST" id="editChefForm">
                    <input type="hidden" name="edit_chef" value="1">
                    <input type="hidden" name="chef_id" value="${chef.id}">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" value="${chef.first_name}" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" value="${chef.last_name}" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" value="${chef.username || ''}" required>
                            </div>
                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" placeholder="Leave blank to keep current password">
                                <small style="color: #666;">Leave blank to keep current password</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" value="${chef.email}" required>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" value="${chef.phone || ''}">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Professional Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-utensils"></i> Professional Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Employee ID</label>
                                <input type="text" value="${chef.employee_id}" readonly>
                            </div>
                            <div class="form-group">
                                <label>Specialization *</label>
                                <select name="specialization" required>
                                    <option value="Head Chef" ${chef.specialization === 'Head Chef' ? 'selected' : ''}>Head Chef</option>
                                    <option value="Sous Chef" ${chef.specialization === 'Sous Chef' ? 'selected' : ''}>Sous Chef</option>
                                    <option value="Pastry Chef" ${chef.specialization === 'Pastry Chef' ? 'selected' : ''}>Pastry Chef</option>
                                    <option value="Grill Chef" ${chef.specialization === 'Grill Chef' ? 'selected' : ''}>Grill Chef</option>
                                    <option value="Salad Chef" ${chef.specialization === 'Salad Chef' ? 'selected' : ''}>Salad Chef</option>
                                    <option value="Breakfast Chef" ${chef.specialization === 'Breakfast Chef' ? 'selected' : ''}>Breakfast Chef</option>
                                    <option value="General Cook" ${chef.specialization === 'General Cook' ? 'selected' : ''}>General Cook</option>
                                    <option value="Kitchen Assistant" ${chef.specialization === 'Kitchen Assistant' ? 'selected' : ''}>Kitchen Assistant</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Experience (Years)</label>
                                <input type="number" name="experience_years" value="${chef.experience_years}" min="0" max="50">
                            </div>
                            <div class="form-group">
                                <label>Cuisine Expertise *</label>
                                <input type="text" name="cuisine_expertise" value="${chef.cuisine_expertise || ''}" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Shift Hours</label>
                                <input type="text" name="shift_hours" value="${chef.shift_hours || ''}" placeholder="e.g., 6:00 AM - 2:00 PM">
                            </div>
                            <div class="form-group">
                                <label>Monthly Salary (৳) *</label>
                                <input type="number" name="monthly_salary" value="${chef.monthly_salary || 35000}" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label>Activity Status</label>
                                <select name="is_active">
                                    <option value="1" ${chef.is_active == 1 ? 'selected' : ''}>Active</option>
                                    <option value="0" ${chef.is_active == 0 ? 'selected' : ''}>Inactive</option>
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
                                <input type="text" name="emergency_contact_name" value="${chef.emergency_contact_name || ''}">
                            </div>
                            <div class="form-group">
                                <label>Emergency Contact Phone</label>
                                <input type="tel" name="emergency_contact_phone" value="${chef.emergency_contact_phone || ''}">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" rows="3">${chef.address || ''}</textarea>
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
                            <i class="fas fa-save"></i> Update Chef
                        </button>
                    </div>
                </form>
            `;
            
            modal.style.display = 'block';
        }
        
        function closeViewModal() {
            document.getElementById('viewChefModal').style.display = 'none';
        }
        
        function closeEditModal() {
            document.getElementById('editChefModal').style.display = 'none';
        }
        
        function deleteChef(id, name) {
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
                        <p>Are you sure you want to delete chef <strong>"${name}"</strong>?</p>
                        <p style="color: #e74c3c; font-size: 0.9rem;">⚠️ This action cannot be undone and will permanently remove all chef data.</p>
                    </div>
                    <div class="delete-modal-actions">
                        <button onclick="closeDeleteModal()" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button onclick="confirmDeleteChef(${id})" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Chef
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.style.display = 'block';
        }
        
        function confirmDeleteChef(id) {
            // Create a form to submit the delete request
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_chef" value="1">
                <input type="hidden" name="chef_id" value="${id}">
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
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addChefModal');
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
            document.getElementById('addChefModal').style.display = 'block';
            
            // Initialize validation after modal opens
            setTimeout(() => {
                initializeFormValidation({
                    username: { fieldId: 'username', validationId: 'username_validation' },
                    email: { fieldId: 'email', validationId: 'email_validation' },
                    phone: { fieldId: 'phone', validationId: 'phone_validation' },
                    employee_id: { fieldId: 'employee_id', validationId: 'employee_id_validation', userType: 'chef' }
                });
            }, 200);
        }
        
        // Form submission validation
        function validateChefFormSubmission() {
            return validateFormSubmission('addChefForm', [
                'username_validation',
                'email_validation', 
                'phone_validation',
                'employee_id_validation'
            ]);
        }
        
        // Edit form submission validation
        function validateEditChefFormSubmission() {
            return validateFormSubmission('editChefForm', [
                'edit_username_validation',
                'edit_email_validation', 
                'edit_phone_validation',
                'edit_employee_id_validation'
            ]);
        }
        
        // Close modal function
        function closeModal() {
            document.getElementById('addChefModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addChefModal');
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
    <button class="floating-add-btn" onclick="openAddModal()" title="Add New Chef">
        <i class="fas fa-plus"></i>
    </button>
</body>
</html>
