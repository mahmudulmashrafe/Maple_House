<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user information
$user_query = "SELECT u.*, ur.role_name FROM users u 
               JOIN user_roles ur ON u.role_id = ur.id 
               WHERE u.id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Redirect based on role
switch ($user['role_name']) {
    case 'Admin':
    case 'Manager':
        header('Location: admin/dashboard.php');
        exit();
    case 'Doctor':
        header('Location: doctor/dashboard.php');
        exit();
    case 'Chef':
        header('Location: chef/dashboard.php');
        exit();
    case 'Staff':
        header('Location: staff/dashboard.php');
        exit();
    case 'Resident':
        header('Location: resident/dashboard.php');
        exit();
    default:
        // Unknown role, logout
        session_destroy();
        header('Location: login.php?error=invalid_role');
        exit();
}
?>
