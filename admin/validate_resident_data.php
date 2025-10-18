<?php
/**
 * Real-time validation for resident data
 * Checks name availability and room number availability
 */

session_start();
require_once '../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Debug logging
error_log("Validation request received: " . print_r($_POST, true));

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$response = ['available' => true, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'check_name':
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $exclude_id = intval($_POST['exclude_id'] ?? 0); // For edit mode
                
                if (empty($first_name) || empty($last_name)) {
                    $response['available'] = true;
                    $response['message'] = '';
                    break;
                }
                
                // Check if name combination already exists
                $name_query = "SELECT u.id, u.first_name, u.last_name, r.room_number 
                              FROM users u 
                              JOIN residents r ON u.id = r.user_id 
                              WHERE LOWER(u.first_name) = LOWER(:first_name) 
                              AND LOWER(u.last_name) = LOWER(:last_name)";
                
                if ($exclude_id > 0) {
                    $name_query .= " AND u.id != :exclude_id";
                }
                
                $name_stmt = $db->prepare($name_query);
                $name_stmt->bindParam(':first_name', $first_name);
                $name_stmt->bindParam(':last_name', $last_name);
                if ($exclude_id > 0) {
                    $name_stmt->bindParam(':exclude_id', $exclude_id);
                }
                $name_stmt->execute();
                $existing_user = $name_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_user) {
                    $response['available'] = false;
                    $response['message'] = "A resident named '{$existing_user['first_name']} {$existing_user['last_name']}' already exists in room {$existing_user['room_number']}.";
                } else {
                    $response['available'] = true;
                    $response['message'] = "Name is available.";
                }
                break;
                
            case 'check_room':
                $room_number = trim($_POST['room_number'] ?? '');
                $exclude_resident_id = intval($_POST['exclude_resident_id'] ?? 0); // For edit mode
                
                if (empty($room_number)) {
                    $response['available'] = true;
                    $response['message'] = '';
                    break;
                }
                
                // Check if room is occupied by ACTIVE residents only
                $room_query = "SELECT r.id, r.room_number, u.first_name, u.last_name, u.is_active,
                                     pp.plan_name, pp.monthly_fee,
                                     CASE 
                                         WHEN u.is_active = 1 THEN 'Active'
                                         ELSE 'Inactive'
                                     END as status
                              FROM residents r 
                              JOIN users u ON r.user_id = u.id 
                              LEFT JOIN payment_plans pp ON r.plan_id = pp.id
                              WHERE r.room_number = :room_number";
                
                if ($exclude_resident_id > 0) {
                    $room_query .= " AND r.id != :exclude_resident_id";
                }
                
                $room_stmt = $db->prepare($room_query);
                $room_stmt->bindParam(':room_number', $room_number);
                if ($exclude_resident_id > 0) {
                    $room_stmt->bindParam(':exclude_resident_id', $exclude_resident_id);
                }
                $room_stmt->execute();
                $existing_resident = $room_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Debug logging
                error_log("Room check for {$room_number}: " . print_r($existing_resident, true));
                
                if ($existing_resident) {
                    if ($existing_resident['is_active'] == 1) {
                        // Room is occupied by active resident
                        $response['available'] = false;
                        $response['message'] = "Room {$room_number} is occupied by {$existing_resident['first_name']} {$existing_resident['last_name']} ({$existing_resident['status']} - {$existing_resident['plan_name']}).";
                        error_log("Room {$room_number} BLOCKED - occupied by active resident");
                    } else {
                        // Room has inactive resident - can be reassigned
                        $response['available'] = true;
                        $response['message'] = "Room {$room_number} is free (previous resident {$existing_resident['first_name']} {$existing_resident['last_name']} is inactive).";
                        error_log("Room {$room_number} AVAILABLE - previous resident inactive");
                    }
                } else {
                    // Room is completely free
                    $response['available'] = true;
                    $response['message'] = "Room {$room_number} is free and available.";
                    error_log("Room {$room_number} AVAILABLE - completely free");
                }
                break;
                
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
                
            case 'get_room_stats':
                // Get room occupancy statistics
                $stats_query = "SELECT 
                                   COUNT(DISTINCT r.room_number) as total_rooms,
                                   COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as occupied_rooms,
                                   COUNT(CASE WHEN u.is_active = 0 THEN 1 END) as inactive_rooms,
                                   GROUP_CONCAT(
                                       CASE WHEN u.is_active = 1 
                                       THEN CONCAT(r.room_number, ':', u.first_name, ' ', u.last_name, ':', pp.plan_name)
                                       END SEPARATOR '|'
                                   ) as occupied_details,
                                   GROUP_CONCAT(
                                       CASE WHEN u.is_active = 0 
                                       THEN CONCAT(r.room_number, ':', u.first_name, ' ', u.last_name, ':Inactive')
                                       END SEPARATOR '|'
                                   ) as inactive_details
                               FROM residents r 
                               JOIN users u ON r.user_id = u.id 
                               LEFT JOIN payment_plans pp ON r.plan_id = pp.id";
                
                $stats_stmt = $db->prepare($stats_query);
                $stats_stmt->execute();
                $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
                
                $response['available'] = true;
                $response['stats'] = [
                    'total_rooms' => intval($stats['total_rooms']),
                    'occupied_rooms' => intval($stats['occupied_rooms']),
                    'inactive_rooms' => intval($stats['inactive_rooms']),
                    'free_rooms' => intval($stats['total_rooms']) - intval($stats['occupied_rooms']),
                    'occupied_details' => $stats['occupied_details'] ? explode('|', $stats['occupied_details']) : [],
                    'inactive_details' => $stats['inactive_details'] ? explode('|', $stats['inactive_details']) : []
                ];
                $response['message'] = 'Room statistics retrieved successfully';
                break;
                
            default:
                $response['available'] = false;
                $response['message'] = 'Invalid action';
                break;
        }
    } else {
        $response['available'] = false;
        $response['message'] = 'Invalid request method';
    }
    
} catch (Exception $e) {
    $response['available'] = false;
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>
