<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$resident_id = isset($_GET['resident_id']) ? $_GET['resident_id'] : '';

if (!$resident_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Resident ID required']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get health records for the resident
$records_query = "SELECT hr.*, 
                  DATE_FORMAT(hr.checkup_date, '%M %d, %Y') as checkup_date
                  FROM health_records hr
                  WHERE hr.resident_id = :resident_id
                  ORDER BY hr.checkup_date DESC";

$records_stmt = $db->prepare($records_query);
$records_stmt->bindParam(':resident_id', $resident_id);
$records_stmt->execute();
$records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($records);
?>
