<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is a resident
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$service_id = $input['service_id'] ?? null;
$preferred_date = $input['preferred_date'] ?? null;
$notes = $input['notes'] ?? '';

if (!$service_id || !$preferred_date) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get resident information and plan details
    $resident_query = "SELECT r.*, pp.laundry_limit, pp.cleaning_limit, s.service_name, s.base_cost
                       FROM residents r
                       JOIN payment_plans pp ON r.plan_id = pp.id
                       CROSS JOIN services s
                       WHERE r.user_id = :user_id AND s.id = :service_id";
    $resident_stmt = $db->prepare($resident_query);
    $resident_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $resident_stmt->bindParam(':service_id', $service_id);
    $resident_stmt->execute();
    
    $resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$resident) {
        echo json_encode(['success' => false, 'message' => 'Resident or service not found']);
        exit();
    }
    
    // Calculate cost based on service limits
    $cost = 0;
    $service_name = strtolower($resident['service_name']);
    
    if (strpos($service_name, 'laundry') !== false) {
        if ($resident['laundry_limit'] == -1) {
            // Unlimited service
            $cost = 0;
        } elseif ($resident['laundry_usage_current_month'] >= $resident['laundry_limit']) {
            // Over limit, charge base cost
            $cost = $resident['base_cost'];
        } else {
            // Within limit, free
            $cost = 0;
        }
    } elseif (strpos($service_name, 'clean') !== false) {
        if ($resident['cleaning_limit'] == -1) {
            // Unlimited service
            $cost = 0;
        } elseif ($resident['cleaning_usage_current_month'] >= $resident['cleaning_limit']) {
            // Over limit, charge base cost
            $cost = $resident['base_cost'];
        } else {
            // Within limit, free
            $cost = 0;
        }
    } else {
        // Other services always charged
        $cost = $resident['base_cost'];
    }
    
    $db->beginTransaction();
    
    // Check if this is a doctor appointment request
    $is_doctor_appointment = (stripos($resident['service_name'], 'doctor') !== false || 
                              stripos($resident['service_name'], 'appointment') !== false);
    
    // Don't auto-assign - let admin assign manually
    $assigned_staff_id = null;
    $assigned_doctor_id = null;
    $service_type = $is_doctor_appointment ? 'doctor_appointment' : 'general';
    
    // Create service request
    $request_query = "INSERT INTO service_requests (resident_id, service_id, service_type, request_date, scheduled_date, assigned_staff_id, assigned_doctor_id, status, cost, notes)
                      VALUES (:resident_id, :service_id, :service_type, CURDATE(), :preferred_date, :assigned_staff_id, :assigned_doctor_id, 'Requested', :cost, :notes)";
    $request_stmt = $db->prepare($request_query);
    $request_stmt->bindParam(':resident_id', $resident['id']);
    $request_stmt->bindParam(':service_id', $service_id);
    $request_stmt->bindParam(':service_type', $service_type);
    $request_stmt->bindParam(':preferred_date', $preferred_date);
    $request_stmt->bindParam(':assigned_staff_id', $assigned_staff_id, PDO::PARAM_INT);
    $request_stmt->bindParam(':assigned_doctor_id', $assigned_doctor_id, PDO::PARAM_INT);
    $request_stmt->bindParam(':cost', $cost);
    $request_stmt->bindParam(':notes', $notes);
    $request_stmt->execute();
    
    $request_id = $db->lastInsertId();
    
    // Create notification for resident only (no staff/doctor assigned yet)
    $resident_notification_query = "INSERT INTO notifications (user_id, title, message, type, action_url)
                                    VALUES (:user_id, 'Service Request Submitted', CONCAT('Your ', :service_name, ' request has been submitted. Admin will assign staff/doctor soon.'), 'Success', 'services.php')";
    $resident_notification_stmt = $db->prepare($resident_notification_query);
    $resident_notification_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $resident_notification_stmt->bindParam(':service_name', $resident['service_name']);
    $resident_notification_stmt->execute();
    
    $db->commit();
    
    $message = 'Service request submitted successfully';
    if ($cost > 0) {
        $message .= '. Service charge: ৳' . number_format($cost);
    } else {
        $message .= ' (Free as per your plan)';
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'request_id' => $request_id,
        'cost' => $cost
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
