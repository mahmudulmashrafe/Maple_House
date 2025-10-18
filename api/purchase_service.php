<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$service_pack_name = $input['service_name'] ?? '';
$quantity = $input['quantity'] ?? 0;
$price = $input['price'] ?? 0;
$payment_method = $input['payment_method'] ?? '';

// Map pack names to actual service names
$pack_to_service_map = [
    'Laundry Pack' => 'Laundry',
    'Room Cleaning Pack' => 'Room Cleaning',
    'Grocery Shopping Pack' => 'Grocery Shopping',
    'Emergency Care Pack' => 'Emergency Care',
    'Doctor Appointment Pack' => 'Doctor Appointment',
    'Transportation Pack' => 'Transportation'
];

$service_name = $pack_to_service_map[$service_pack_name] ?? $service_pack_name;

if (empty($service_name) || $quantity <= 0 || $price <= 0 || empty($payment_method)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

try {
    // Get resident ID
    $resident_query = "SELECT id FROM residents WHERE user_id = :user_id";
    $resident_stmt = $db->prepare($resident_query);
    $resident_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $resident_stmt->execute();
    $resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resident) {
        echo json_encode(['success' => false, 'message' => 'Resident not found']);
        exit();
    }
    
    $resident_id = $resident['id'];
    
    // Start transaction
    $db->beginTransaction();
    
    // Insert into service_purchases table (for tracking)
    // Store the pack name for display, but use service_name for quota tracking
    $purchase_query = "INSERT INTO service_purchases 
                      (resident_id, service_name, quantity, price_per_unit, total_price, payment_method, purchase_date, status) 
                      VALUES 
                      (:resident_id, :service_name, :quantity, :price_per_unit, :total_price, :payment_method, NOW(), 'completed')";
    $purchase_stmt = $db->prepare($purchase_query);
    $price_per_unit = $price / $quantity;
    $purchase_stmt->bindParam(':resident_id', $resident_id);
    $purchase_stmt->bindParam(':service_name', $service_pack_name); // Store pack name for display
    $purchase_stmt->bindParam(':quantity', $quantity);
    $purchase_stmt->bindParam(':price_per_unit', $price_per_unit);
    $purchase_stmt->bindParam(':total_price', $price);
    $purchase_stmt->bindParam(':payment_method', $payment_method);
    $purchase_stmt->execute();
    
    // Update or insert service quota for this resident
    $quota_check = "SELECT * FROM resident_service_quotas 
                   WHERE resident_id = :resident_id 
                   AND service_name = :service_name 
                   AND month = DATE_FORMAT(NOW(), '%Y-%m')";
    $quota_check_stmt = $db->prepare($quota_check);
    $quota_check_stmt->bindParam(':resident_id', $resident_id);
    $quota_check_stmt->bindParam(':service_name', $service_name);
    $quota_check_stmt->execute();
    $existing_quota = $quota_check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_quota) {
        // Update existing quota
        $update_quota = "UPDATE resident_service_quotas 
                        SET additional_quota = additional_quota + :quantity 
                        WHERE id = :id";
        $update_stmt = $db->prepare($update_quota);
        $update_stmt->bindParam(':quantity', $quantity);
        $update_stmt->bindParam(':id', $existing_quota['id']);
        $update_stmt->execute();
    } else {
        // Insert new quota record
        $insert_quota = "INSERT INTO resident_service_quotas 
                        (resident_id, service_name, month, additional_quota) 
                        VALUES 
                        (:resident_id, :service_name, DATE_FORMAT(NOW(), '%Y-%m'), :quantity)";
        $insert_stmt = $db->prepare($insert_quota);
        $insert_stmt->bindParam(':resident_id', $resident_id);
        $insert_stmt->bindParam(':service_name', $service_name);
        $insert_stmt->bindParam(':quantity', $quantity);
        $insert_stmt->execute();
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Service purchased successfully',
        'quantity' => $quantity,
        'service' => $service_name
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
