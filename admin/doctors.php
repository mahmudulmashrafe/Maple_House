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
    if (isset($_POST['add_doctor'])) {
        // Add new doctor
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
        $license_number = trim($_POST['license_number']);
        $specialization = trim($_POST['specialization']);
        $qualification = trim($_POST['qualification']);
        $consultation_fee = floatval($_POST['consultation_fee']);
        $monthly_salary = floatval($_POST['monthly_salary']);
        $available_hours = trim($_POST['available_hours']);
        
        try {
            $db->beginTransaction();
            
            // Check if username, email, or license_number already exists
            $check_query = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception('Username or email already exists.');
            }
            
            $check_license_query = "SELECT COUNT(*) FROM doctors WHERE license_number = :license_number";
            $check_license_stmt = $db->prepare($check_license_query);
            $check_license_stmt->bindParam(':license_number', $license_number);
            $check_license_stmt->execute();
            
            if ($check_license_stmt->fetchColumn() > 0) {
                throw new Exception('License number already exists.');
            }
            
            // Insert user
            $user_query = "INSERT INTO users (username, email, password, role_id, first_name, last_name, phone, address, date_of_birth, gender, emergency_contact_name, emergency_contact_phone) 
                          VALUES (:username, :email, :password, 3, :first_name, :last_name, :phone, :address, :date_of_birth, :gender, :emergency_contact_name, :emergency_contact_phone)";
            
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
            
            // Insert doctor
            $doctor_query = "INSERT INTO doctors (user_id, license_number, specialization, qualification, consultation_fee, monthly_salary, available_hours) 
                            VALUES (:user_id, :license_number, :specialization, :qualification, :consultation_fee, :monthly_salary, :available_hours)";
            
            $doctor_stmt = $db->prepare($doctor_query);
            $doctor_stmt->bindParam(':user_id', $user_id);
            $doctor_stmt->bindParam(':license_number', $license_number);
            $doctor_stmt->bindParam(':specialization', $specialization);
            $doctor_stmt->bindParam(':qualification', $qualification);
            $doctor_stmt->bindParam(':consultation_fee', $consultation_fee);
            $doctor_stmt->bindParam(':monthly_salary', $monthly_salary);
            $doctor_stmt->bindParam(':available_hours', $available_hours);
            $doctor_stmt->execute();
            
            $db->commit();
            $success_message = "Doctor added successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
    
    if (isset($_POST['edit_doctor'])) {
        // Edit existing doctor
        $doctor_id = intval($_POST['doctor_id']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $license_number = trim($_POST['license_number']);
        $specialization = trim($_POST['specialization']);
        $qualification = trim($_POST['qualification']);
        $consultation_fee = floatval($_POST['consultation_fee']);
        $monthly_salary = floatval($_POST['monthly_salary']);
        $is_active = intval($_POST['is_active']);
        $emergency_contact_name = trim($_POST['emergency_contact_name']);
        $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
        $address = trim($_POST['address']);
        $available_hours = trim($_POST['available_hours']);
        
        try {
            $db->beginTransaction();
            
            // Update user information (with optional password update)
            if (!empty($password)) {
                // Update with password
                $user_update_query = "UPDATE users u 
                                     JOIN doctors d ON u.id = d.user_id 
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
                                     WHERE d.id = :doctor_id";
            } else {
                // Update without password
                $user_update_query = "UPDATE users u 
                                     JOIN doctors d ON u.id = d.user_id 
                                     SET u.first_name = :first_name, 
                                         u.last_name = :last_name,
                                         u.username = :username,
                                         u.email = :email, 
                                         u.phone = :phone,
                                         u.address = :address,
                                         u.is_active = :is_active,
                                         u.emergency_contact_name = :emergency_contact_name,
                                         u.emergency_contact_phone = :emergency_contact_phone
                                     WHERE d.id = :doctor_id";
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
            $user_update_stmt->bindParam(':doctor_id', $doctor_id);
            $user_update_stmt->execute();
            
            // Update doctor information
            $doctor_update_query = "UPDATE doctors 
                                     SET license_number = :license_number,
                                         specialization = :specialization,
                                         qualification = :qualification,
                                         consultation_fee = :consultation_fee,
                                         monthly_salary = :monthly_salary,
                                         available_hours = :available_hours
                                     WHERE id = :doctor_id";
            
            $doctor_update_stmt = $db->prepare($doctor_update_query);
            $doctor_update_stmt->bindParam(':license_number', $license_number);
            $doctor_update_stmt->bindParam(':specialization', $specialization);
            $doctor_update_stmt->bindParam(':qualification', $qualification);
            $doctor_update_stmt->bindParam(':consultation_fee', $consultation_fee);
            $doctor_update_stmt->bindParam(':monthly_salary', $monthly_salary);
            $doctor_update_stmt->bindParam(':available_hours', $available_hours);
            $doctor_update_stmt->bindParam(':doctor_id', $doctor_id);
            $doctor_update_stmt->execute();
            
            $db->commit();
            $success_message = "Doctor updated successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_doctor'])) {
        // Delete doctor
        $doctor_id = intval($_POST['doctor_id']);
        
        try {
            $db->beginTransaction();
            
            // Get user_id before deleting doctor
            $get_user_query = "SELECT user_id FROM doctors WHERE id = :doctor_id";
            $get_user_stmt = $db->prepare($get_user_query);
            $get_user_stmt->bindParam(':doctor_id', $doctor_id);
            $get_user_stmt->execute();
            $user_id = $get_user_stmt->fetchColumn();
            
            // Delete doctor record
            $delete_doctor_query = "DELETE FROM doctors WHERE id = :doctor_id";
            $delete_doctor_stmt = $db->prepare($delete_doctor_query);
            $delete_doctor_stmt->bindParam(':doctor_id', $doctor_id);
            $delete_doctor_stmt->execute();
            
            // Delete user record
            if ($user_id) {
                $delete_user_query = "DELETE FROM users WHERE id = :user_id";
                $delete_user_stmt = $db->prepare($delete_user_query);
                $delete_user_stmt->bindParam(':user_id', $user_id);
                $delete_user_stmt->execute();
            }
            
            $db->commit();
            $success_message = "Doctor deleted successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
}

// Get doctors with pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Check if monthly_salary column exists
$column_check = $db->query("SHOW COLUMNS FROM doctors LIKE 'monthly_salary'");
$salary_column_exists = $column_check->rowCount() > 0;

if ($salary_column_exists) {
    $doctors_query = "SELECT d.*, u.first_name, u.last_name, u.username, u.email, u.phone, u.address, u.date_of_birth, u.gender, u.emergency_contact_name, u.emergency_contact_phone, u.is_active,
                           CASE 
                               WHEN u.is_active = 1 THEN 'Active'
                               ELSE 'Inactive'
                           END as activity_status,
                           COALESCE(d.monthly_salary, 45000) as monthly_salary
                    FROM doctors d
                    JOIN users u ON d.user_id = u.id
                    ORDER BY u.first_name, u.last_name
                    LIMIT :limit OFFSET :offset";
} else {
    $doctors_query = "SELECT d.*, u.first_name, u.last_name, u.username, u.email, u.phone, u.address, u.date_of_birth, u.gender, u.emergency_contact_name, u.emergency_contact_phone, u.is_active,
                           CASE 
                               WHEN u.is_active = 1 THEN 'Active'
                               ELSE 'Inactive'
                           END as activity_status,
                           45000 as monthly_salary
                    FROM doctors d
                    JOIN users u ON d.user_id = u.id
                    ORDER BY u.first_name, u.last_name
                    LIMIT :limit OFFSET :offset";
}

$doctors_stmt = $db->prepare($doctors_query);
$doctors_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$doctors_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$doctors_stmt->execute();
$doctors = $doctors_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM doctors";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$total_doctors = $count_stmt->fetchColumn();
$total_pages = ceil($total_doctors / $per_page);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total_doctors,
                    COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as active_doctors,
                    COUNT(CASE WHEN u.is_active = 0 THEN 1 END) as inactive_doctors,
                    AVG(d.consultation_fee) as avg_fee
                FROM doctors d
                JOIN users u ON d.user_id = u.id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors Management - Maple House</title>
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
            border: 2px solid #4facfe !important;
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
        
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .doctor-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid #4facfe;
            display: flex;
            flex-direction: column;
            height: 400px;
            max-height: 400px;
        }
        
        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #4facfe;
        }
        
        .doctor-header {
            padding: 20px;
            background: white;
            color: #4facfe;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .doctor-avatar {
            width: 60px;
            height: 60px;
            background: rgba(79, 172, 254, 0.1);
            border: 2px solid #4facfe;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #4facfe;
        }
        
        .doctor-basic h3 {
            margin: 0 0 5px 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .doctor-email {
            margin: 0 0 10px 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .doctor-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .license-badge {
            background: rgba(79, 172, 254, 0.1);
            color: #4facfe;
            border: 1px solid #4facfe;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .doctor-details {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            max-height: 200px;
        }
        
        /* Custom Scrollbar */
        .doctor-details::-webkit-scrollbar {
            width: 6px;
        }
        
        .doctor-details::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .doctor-details::-webkit-scrollbar-thumb {
            background: #4facfe;
            border-radius: 3px;
        }
        
        .doctor-details::-webkit-scrollbar-thumb:hover {
            background: #00f2fe;
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
        
        .doctor-actions {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .doctors-grid {
                grid-template-columns: 1fr;
            }
            
            .doctor-header {
                flex-direction: column;
                text-align: center;
            }
            
            .doctor-actions {
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
            background: #4facfe;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(79, 172, 254, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .floating-add-btn:hover {
            background: #00f2fe;
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(79, 172, 254, 0.6);
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
            background: #4facfe;
            border-radius: 4px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #00f2fe;
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
            color: #4facfe;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #4facfe;
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
            <i class="fas fa-user-md"></i>
            <div class="stat-number"><?php echo $stats['total_doctors']; ?></div>
            <div class="stat-label">Total Doctors</div>
        </div>
        
        <div class="stat-card success">
            <i class="fas fa-check-circle"></i>
            <div class="stat-number"><?php echo $stats['active_doctors']; ?></div>
            <div class="stat-label">Active Doctors</div>
        </div>
        
        <div class="stat-card danger">
            <i class="fas fa-times-circle"></i>
            <div class="stat-number"><?php echo $stats['inactive_doctors']; ?></div>
            <div class="stat-label">Inactive Doctors</div>
        </div>
        
        <div class="stat-card warning">
            <i class="fas fa-money-bill-wave"></i>
            <div class="stat-number">৳<?php echo number_format($stats['avg_fee']); ?></div>
            <div class="stat-label">Average Consultation Fee</div>
        </div>
    </div>

    <!-- Doctors Grid -->
    <div class="doctors-grid">
        <?php if (!empty($doctors)): ?>
            <?php foreach ($doctors as $doctor): ?>
                <div class="doctor-card">
                    <div class="doctor-header">
                        <div class="doctor-avatar">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="doctor-basic">
                            <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                            <p class="doctor-email"><?php echo htmlspecialchars($doctor['email']); ?></p>
                            <div class="doctor-badges">
                                <span class="license-badge">License: <?php echo htmlspecialchars($doctor['license_number']); ?></span>
                                <span class="status-badge status-<?php echo $doctor['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $doctor['activity_status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="doctor-details">
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-stethoscope"></i> Specialization:</span>
                            <span><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-graduation-cap"></i> Qualification:</span>
                            <span><?php echo htmlspecialchars($doctor['qualification'] ?: 'N/A'); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-money-bill-wave"></i> Consultation Fee:</span>
                            <span>৳<?php echo number_format($doctor['consultation_fee']); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-clock"></i> Available Hours:</span>
                            <span><?php echo htmlspecialchars($doctor['available_hours'] ?: 'N/A'); ?></span>
                        </div>
                        
                        <?php if ($doctor['phone']): ?>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-phone"></i> Phone:</span>
                            <span><?php echo htmlspecialchars($doctor['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="doctor-actions">
                        <button onclick="viewDoctor(<?php echo $doctor['id']; ?>)" class="btn btn-primary btn-sm" title="View Details">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button onclick="editDoctor(<?php echo $doctor['id']; ?>)" class="btn btn-success btn-sm" title="Edit">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button onclick="deleteDoctor(<?php echo $doctor['id']; ?>, 'Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>')" class="btn btn-danger btn-sm" title="Delete">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
                <i class="fas fa-user-md" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
                <h3 style="color: #666; margin-bottom: 10px;">No doctors found</h3>
                <p style="color: #999;">Click "Add New Doctor" to get started.</p>
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

    <!-- Add Doctor Modal -->
    <div id="addDoctorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Doctor</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="addDoctorForm" onsubmit="return validateDoctorFormSubmission()">
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
                    
                    <!-- Professional Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-stethoscope"></i> Professional Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="license_number">License Number *</label>
                                <input type="text" name="license_number" id="license_number" required placeholder="e.g., BMA12345">
                                <div class="validation-message" id="license_validation"></div>
                            </div>
                            <div class="form-group">
                                <label for="specialization">Specialization *</label>
                                <select name="specialization" id="specialization" required>
                                    <option value="">Select Specialization</option>
                                    <option value="General Medicine">General Medicine</option>
                                    <option value="Geriatric Medicine">Geriatric Medicine</option>
                                    <option value="Cardiology">Cardiology</option>
                                    <option value="Neurology">Neurology</option>
                                    <option value="Psychiatry">Psychiatry</option>
                                    <option value="Orthopedics">Orthopedics</option>
                                    <option value="Physiotherapy">Physiotherapy</option>
                                    <option value="Nutrition">Nutrition</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="qualification">Qualification</label>
                                <input type="text" name="qualification" id="qualification">
                            </div>
                            <div class="form-group">
                                <label for="consultation_fee">Consultation Fee *</label>
                                <input type="number" name="consultation_fee" id="consultation_fee" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="monthly_salary">Monthly Salary (৳) *</label>
                                <input type="number" name="monthly_salary" id="monthly_salary" value="45000" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="available_hours">Available Hours</label>
                                <input type="text" name="available_hours" id="available_hours" placeholder="e.g., Mon-Fri 9:00 AM - 5:00 PM">
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
                        <button type="submit" name="add_doctor" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add Doctor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Doctor Modal -->
    <div id="viewDoctorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Doctor Details</h3>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewDoctorContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Edit Doctor Modal -->
    <div id="editDoctorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Doctor</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body" id="editDoctorContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addDoctorModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('addDoctorModal').style.display = 'none';
        }
        
        // Store doctor data for JavaScript access
        const doctorData = <?php echo json_encode($doctors); ?>;
        
        function viewDoctor(id) {
            const doctor = doctorData.find(d => d.id == id);
            if (!doctor) return;
            
            const modal = document.getElementById('viewDoctorModal');
            const content = document.getElementById('viewDoctorContent');
            
            content.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-user"></i> Personal Information</h4>
                        <p><strong>Name:</strong> Dr. ${doctor.first_name} ${doctor.last_name}</p>
                        <p><strong>Email:</strong> ${doctor.email}</p>
                        <p><strong>Phone:</strong> ${doctor.phone || 'N/A'}</p>
                        <p><strong>Date of Birth:</strong> ${doctor.date_of_birth ? new Date(doctor.date_of_birth).toLocaleDateString() : 'N/A'}</p>
                        <p><strong>Gender:</strong> ${doctor.gender || 'N/A'}</p>
                        <p><strong>Address:</strong> ${doctor.address || 'N/A'}</p>
                        <p><strong>Activity Status:</strong> <span class="status-badge status-${doctor.is_active ? 'active' : 'inactive'}">${doctor.activity_status}</span></p>
                    </div>
                    
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-stethoscope"></i> Professional Information</h4>
                        <p><strong>License Number:</strong> ${doctor.license_number}</p>
                        <p><strong>Specialization:</strong> ${doctor.specialization}</p>
                        <p><strong>Qualification:</strong> ${doctor.qualification || 'N/A'}</p>
                        <p><strong>Consultation Fee:</strong> ৳${parseInt(doctor.consultation_fee || 0).toLocaleString()}</p>
                        <p><strong>Monthly Salary:</strong> ৳${parseInt(doctor.monthly_salary || 45000).toLocaleString()}</p>
                        <p><strong>Available Hours:</strong> ${doctor.available_hours || 'N/A'}</p>
                    </div>
                    
                    <div>
                        <h4 style="color: #3498db; margin-bottom: 15px;"><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                        <p><strong>Emergency Contact Name:</strong> ${doctor.emergency_contact_name || 'N/A'}</p>
                        <p><strong>Emergency Contact Phone:</strong> ${doctor.emergency_contact_phone || 'N/A'}</p>
                    </div>
                </div>
            `;
            
            modal.style.display = 'block';
        }
        
        function editDoctor(id) {
            const doctor = doctorData.find(d => d.id == id);
            if (!doctor) return;
            
            const modal = document.getElementById('editDoctorModal');
            const content = document.getElementById('editDoctorContent');
            
            content.innerHTML = `
                <form method="POST" id="editDoctorForm" onsubmit="return validateEditDoctorFormSubmission()">
                    <input type="hidden" name="edit_doctor" value="1">
                    <input type="hidden" name="doctor_id" value="${doctor.id}">
                    <input type="hidden" name="current_user_id" value="${doctor.user_id}">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" id="edit_first_name" value="${doctor.first_name}" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" id="edit_last_name" value="${doctor.last_name}" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" id="edit_username" value="${doctor.username || ''}" required>
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
                                <input type="email" name="email" id="edit_email" value="${doctor.email}" required>
                                <div class="validation-message" id="edit_email_validation"></div>
                            </div>
                            <div class="form-group">
                                <label>Phone Number *</label>
                                <input type="tel" name="phone" id="edit_phone" value="${doctor.phone || ''}" required placeholder="01XXXXXXXXX (11 digits)">
                                <div class="validation-message" id="edit_phone_validation"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Professional Information Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-stethoscope"></i> Professional Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>License Number *</label>
                                <input type="text" name="license_number" id="edit_license_number" value="${doctor.license_number}" required placeholder="e.g., BMA12345">
                                <div class="validation-message" id="edit_license_validation"></div>
                            </div>
                            <div class="form-group">
                                <label>Specialization *</label>
                                <input type="text" name="specialization" value="${doctor.specialization}" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Qualification</label>
                                <input type="text" name="qualification" value="${doctor.qualification || ''}">
                            </div>
                            <div class="form-group">
                                <label>Consultation Fee *</label>
                                <input type="number" name="consultation_fee" value="${doctor.consultation_fee}" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label>Monthly Salary (৳) *</label>
                                <input type="number" name="monthly_salary" value="${doctor.monthly_salary || 45000}" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Activity Status</label>
                                <select name="is_active">
                                    <option value="1" ${doctor.is_active == 1 ? 'selected' : ''}>Active</option>
                                    <option value="0" ${doctor.is_active == 0 ? 'selected' : ''}>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Available Hours</label>
                                <input type="text" name="available_hours" value="${doctor.available_hours || ''}" placeholder="e.g., 9:00 AM - 5:00 PM">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Emergency Contact Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Emergency Contact Name</label>
                                <input type="text" name="emergency_contact_name" value="${doctor.emergency_contact_name || ''}">
                            </div>
                            <div class="form-group">
                                <label>Emergency Contact Phone</label>
                                <input type="tel" name="emergency_contact_phone" value="${doctor.emergency_contact_phone || ''}">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" rows="3">${doctor.address || ''}</textarea>
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
                            <i class="fas fa-save"></i> Update Doctor
                        </button>
                    </div>
                </form>
            `;
            
            modal.style.display = 'block';
            
            // Initialize edit form validation
            setTimeout(() => {
                initializeFormValidation({
                    username: { fieldId: 'edit_username', validationId: 'edit_username_validation', excludeId: doctor.user_id },
                    email: { fieldId: 'edit_email', validationId: 'edit_email_validation', excludeId: doctor.user_id },
                    phone: { fieldId: 'edit_phone', validationId: 'edit_phone_validation', excludeId: doctor.user_id },
                    license_number: { fieldId: 'edit_license_number', validationId: 'edit_license_validation', excludeId: doctor.id }
                });
            }, 200);
        }
        
        function deleteDoctor(id, name) {
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
                        <p>Are you sure you want to delete doctor <strong>"${name}"</strong>?</p>
                        <p style="color: #e74c3c; font-size: 0.9rem;">⚠️ This action cannot be undone and will permanently remove all doctor data.</p>
                    </div>
                    <div class="delete-modal-actions">
                        <button onclick="closeDeleteModal()" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button onclick="confirmDeleteDoctor(${id})" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Doctor
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.style.display = 'block';
        }
        
        function confirmDeleteDoctor(id) {
            // Create a form to submit the delete request
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_doctor" value="1">
                <input type="hidden" name="doctor_id" value="${id}">
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
            document.getElementById('viewDoctorModal').style.display = 'none';
        }
        
        function closeEditModal() {
            document.getElementById('editDoctorModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addDoctorModal');
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
            document.getElementById('addDoctorModal').style.display = 'block';
            
            // Initialize validation after modal opens
            setTimeout(() => {
                initializeFormValidation({
                    username: { fieldId: 'username', validationId: 'username_validation' },
                    email: { fieldId: 'email', validationId: 'email_validation' },
                    phone: { fieldId: 'phone', validationId: 'phone_validation' },
                    license_number: { fieldId: 'license_number', validationId: 'license_validation' }
                });
            }, 200);
        }
        
        // Form submission validation
        function validateDoctorFormSubmission() {
            return validateFormSubmission('addDoctorForm', [
                'username_validation',
                'email_validation', 
                'phone_validation',
                'license_validation'
            ]);
        }
        
        // Edit form submission validation
        function validateEditDoctorFormSubmission() {
            return validateFormSubmission('editDoctorForm', [
                'edit_username_validation',
                'edit_email_validation', 
                'edit_phone_validation',
                'edit_license_validation'
            ]);
        }
        
        // Close modal function
        function closeModal() {
            document.getElementById('addDoctorModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addDoctorModal');
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
    <button class="floating-add-btn" onclick="openAddModal()" title="Add New Doctor">
        <i class="fas fa-plus"></i>
    </button>
</body>
</html>
