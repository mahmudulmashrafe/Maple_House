<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Get staff salary information
    $query = "SELECT s.salary as monthly_salary, s.department, s.position, 
                     s.hire_date, s.shift_hours
              FROM staff s
              WHERE s.user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $salary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($salary) {
        echo json_encode([
            'success' => true,
            'salary' => $salary
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Salary information not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
