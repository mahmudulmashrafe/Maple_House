<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$task_id = $input['task_id'] ?? null;
$new_status = $input['status'] ?? null;

if (!$task_id || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Validate status
$valid_statuses = ['Requested', 'Scheduled', 'In Progress', 'Completed', 'Cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get staff ID
    $staff_query = "SELECT id FROM staff WHERE user_id = :user_id";
    $staff_stmt = $db->prepare($staff_query);
    $staff_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $staff_stmt->execute();
    $staff_id = $staff_stmt->fetchColumn();
    
    // Verify the task belongs to this staff member
    $verify_query = "SELECT sr.*, s.service_name, CONCAT(u.first_name, ' ', u.last_name) as resident_name
                     FROM service_requests sr
                     JOIN services s ON sr.service_id = s.id
                     JOIN residents r ON sr.resident_id = r.id
                     JOIN users u ON r.user_id = u.id
                     WHERE sr.id = :task_id AND sr.assigned_staff_id = :staff_id";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->bindParam(':task_id', $task_id);
    $verify_stmt->bindParam(':staff_id', $staff_id);
    $verify_stmt->execute();
    
    $task = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found or not assigned to you']);
        exit();
    }
    
    $db->beginTransaction();
    
    // Update the task status
    $update_query = "UPDATE service_requests SET status = :status";
    if ($new_status === 'Completed') {
        $update_query .= ", completed_at = NOW()";
    }
    $update_query .= " WHERE id = :task_id";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':task_id', $task_id);
    $update_stmt->execute();
    
    // If completed, update service usage counters
    if ($new_status === 'Completed') {
        $service_name = strtolower($task['service_name']);
        
        if (strpos($service_name, 'laundry') !== false) {
            $update_usage = "UPDATE residents SET laundry_usage_current_month = laundry_usage_current_month + 1 WHERE id = :resident_id";
            $update_usage_stmt = $db->prepare($update_usage);
            $update_usage_stmt->bindParam(':resident_id', $task['resident_id']);
            $update_usage_stmt->execute();
        } elseif (strpos($service_name, 'clean') !== false) {
            $update_usage = "UPDATE residents SET cleaning_usage_current_month = cleaning_usage_current_month + 1 WHERE id = :resident_id";
            $update_usage_stmt = $db->prepare($update_usage);
            $update_usage_stmt->bindParam(':resident_id', $task['resident_id']);
            $update_usage_stmt->execute();
        }
        
        // Create notification for resident
        $notification_query = "INSERT INTO notifications (user_id, title, message, type, action_url) 
                               SELECT u.id, 'Service Completed', CONCAT(:service_name, ' service has been completed in your room.'), 'Success', 'services.php'
                               FROM residents r 
                               JOIN users u ON r.user_id = u.id 
                               WHERE r.id = :resident_id";
        $notification_stmt = $db->prepare($notification_query);
        $notification_stmt->bindParam(':service_name', $task['service_name']);
        $notification_stmt->bindParam(':resident_id', $task['resident_id']);
        $notification_stmt->execute();
        
        // Add to financial transactions if there's a cost
        if ($task['cost'] > 0) {
            $transaction_query = "INSERT INTO financial_transactions (transaction_type, category, amount, description, reference_id, reference_type, transaction_date, processed_by)
                                  VALUES ('Income', 'Service Charges', :amount, CONCAT('Service charge: ', :service_name, ' for ', :resident_name), :task_id, 'service_request', CURDATE(), :staff_user_id)";
            $transaction_stmt = $db->prepare($transaction_query);
            $transaction_stmt->bindParam(':amount', $task['cost']);
            $transaction_stmt->bindParam(':service_name', $task['service_name']);
            $transaction_stmt->bindParam(':resident_name', $task['resident_name']);
            $transaction_stmt->bindParam(':task_id', $task_id);
            $transaction_stmt->bindParam(':staff_user_id', $_SESSION['user_id']);
            $transaction_stmt->execute();
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Task status updated successfully',
        'new_status' => $new_status
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
