<?php
/**
 * Universal User Data Validation
 * Handles real-time validation for all user types: Staff, Doctors, Chefs, Residents
 */

session_start();
require_once '../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Debug logging
error_log("User validation request received: " . print_r($_POST, true));

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$response = ['available' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'check_username':
                $username = trim($_POST['username'] ?? '');
                $exclude_id = intval($_POST['exclude_id'] ?? 0); // For edit mode
                
                if (empty($username)) {
                    $response['available'] = true;
                    $response['message'] = '';
                    break;
                }
                
                // Check if username already exists
                $username_query = "SELECT id, username FROM users WHERE username = :username";
                
                if ($exclude_id > 0) {
                    $username_query .= " AND id != :exclude_id";
                }
                
                $username_stmt = $db->prepare($username_query);
                $username_stmt->bindParam(':username', $username);
                if ($exclude_id > 0) {
                    $username_stmt->bindParam(':exclude_id', $exclude_id);
                }
                $username_stmt->execute();
                $existing_username = $username_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_username) {
                    $response['available'] = false;
                    $response['message'] = "Username '{$username}' is already taken.";
                } else {
                    $response['available'] = true;
                    $response['message'] = "Username '{$username}' is available.";
                }
                break;
                
            case 'check_email':
                $email = trim($_POST['email'] ?? '');
                $exclude_id = intval($_POST['exclude_id'] ?? 0); // For edit mode
                
                if (empty($email)) {
                    $response['available'] = true;
                    $response['message'] = '';
                    break;
                }
                
                // Basic email format validation
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $response['available'] = false;
                    $response['message'] = "Please enter a valid email address.";
                    break;
                }
                
                // Check if email already exists
                $email_query = "SELECT id, email FROM users WHERE email = :email";
                
                if ($exclude_id > 0) {
                    $email_query .= " AND id != :exclude_id";
                }
                
                $email_stmt = $db->prepare($email_query);
                $email_stmt->bindParam(':email', $email);
                if ($exclude_id > 0) {
                    $email_stmt->bindParam(':exclude_id', $exclude_id);
                }
                $email_stmt->execute();
                $existing_email = $email_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_email) {
                    $response['available'] = false;
                    $response['message'] = "Email '{$email}' is already registered.";
                } else {
                    $response['available'] = true;
                    $response['message'] = "Email '{$email}' is available.";
                }
                break;
                
            case 'check_phone':
                $phone = trim($_POST['phone'] ?? '');
                $exclude_id = intval($_POST['exclude_id'] ?? 0); // For edit mode
                
                if (empty($phone)) {
                    $response['available'] = true;
                    $response['message'] = '';
                    break;
                }
                
                // Check if phone number has exactly 11 digits
                if (!preg_match('/^[0-9]{11}$/', $phone)) {
                    $response['available'] = false;
                    if (strlen($phone) < 11) {
                        $response['message'] = "Phone number must be exactly 11 digits. Currently " . strlen($phone) . " digits.";
                    } else if (strlen($phone) > 11) {
                        $response['message'] = "Phone number must be exactly 11 digits. Currently " . strlen($phone) . " digits.";
                    } else {
                        $response['message'] = "Phone number must contain only digits (0-9).";
                    }
                    break;
                }
                
                // Check if phone number starts with 01 (Bangladesh format)
                if (!preg_match('/^01[0-9]{9}$/', $phone)) {
                    $response['available'] = false;
                    $response['message'] = "Phone number must start with '01' followed by 9 digits (Bangladesh format).";
                    break;
                }
                
                // Check if phone number already exists
                $phone_query = "SELECT id, phone FROM users WHERE phone = :phone";
                
                if ($exclude_id > 0) {
                    $phone_query .= " AND id != :exclude_id";
                }
                
                $phone_stmt = $db->prepare($phone_query);
                $phone_stmt->bindParam(':phone', $phone);
                if ($exclude_id > 0) {
                    $phone_stmt->bindParam(':exclude_id', $exclude_id);
                }
                $phone_stmt->execute();
                $existing_phone = $phone_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_phone) {
                    $response['available'] = false;
                    $response['message'] = "Phone number '{$phone}' is already registered.";
                } else {
                    $response['available'] = true;
                    $response['message'] = "Phone number '{$phone}' is valid and available.";
                }
                break;
                
            case 'check_employee_id':
                $employee_id = trim($_POST['employee_id'] ?? '');
                $user_type = trim($_POST['user_type'] ?? ''); // staff, chef
                $exclude_id = intval($_POST['exclude_id'] ?? 0); // For edit mode
                
                if (empty($employee_id)) {
                    $response['available'] = true;
                    $response['message'] = '';
                    break;
                }
                
                // Validate employee ID format based on user type
                if ($user_type === 'staff') {
                    if (!preg_match('/^EMP[0-9]{3,}$/', $employee_id)) {
                        $response['available'] = false;
                        $response['message'] = "Staff Employee ID must start with 'EMP' followed by at least 3 digits (e.g., EMP001).";
                        break;
                    }
                } elseif ($user_type === 'chef') {
                    if (!preg_match('/^CHEF[0-9]{3,}$/', $employee_id)) {
                        $response['available'] = false;
                        $response['message'] = "Chef Employee ID must start with 'CHEF' followed by at least 3 digits (e.g., CHEF001).";
                        break;
                    }
                }
                
                // Check if employee ID already exists in staff table
                $emp_query = "SELECT id, employee_id FROM staff WHERE employee_id = :employee_id";
                
                if ($exclude_id > 0) {
                    $emp_query .= " AND id != :exclude_id";
                }
                
                $emp_stmt = $db->prepare($emp_query);
                $emp_stmt->bindParam(':employee_id', $employee_id);
                if ($exclude_id > 0) {
                    $emp_stmt->bindParam(':exclude_id', $exclude_id);
                }
                $emp_stmt->execute();
                $existing_emp = $emp_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_emp) {
                    $response['available'] = false;
                    $response['message'] = "Employee ID '{$employee_id}' is already assigned.";
                } else {
                    $response['available'] = true;
                    $response['message'] = "Employee ID '{$employee_id}' is available.";
                }
                break;
                
            case 'check_license_number':
                $license_number = trim($_POST['license_number'] ?? '');
                $exclude_id = intval($_POST['exclude_id'] ?? 0); // For edit mode
                
                if (empty($license_number)) {
                    $response['available'] = true;
                    $response['message'] = '';
                    break;
                }
                
                // Check if license number already exists in doctors table
                $license_query = "SELECT id, license_number FROM doctors WHERE license_number = :license_number";
                
                if ($exclude_id > 0) {
                    $license_query .= " AND id != :exclude_id";
                }
                
                $license_stmt = $db->prepare($license_query);
                $license_stmt->bindParam(':license_number', $license_number);
                if ($exclude_id > 0) {
                    $license_stmt->bindParam(':exclude_id', $exclude_id);
                }
                $license_stmt->execute();
                $existing_license = $license_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_license) {
                    $response['available'] = false;
                    $response['message'] = "License number '{$license_number}' is already registered.";
                } else {
                    $response['available'] = true;
                    $response['message'] = "License number '{$license_number}' is available.";
                }
                break;
                
            default:
                $response['available'] = false;
                $response['message'] = 'Invalid validation action.';
                break;
        }
        
    } catch (Exception $e) {
        error_log("Validation error: " . $e->getMessage());
        $response['available'] = false;
        $response['message'] = 'Validation error occurred.';
    }
}

echo json_encode($response);
?>
