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

// Handle staff/doctor assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign_staff') {
        try {
            $request_id = intval($_POST['request_id']);
            $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : null;
            $doctor_id = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null;
            
            if ($doctor_id) {
                // Assign doctor
                $assign_query = "UPDATE service_requests SET 
                                assigned_doctor_id = :doctor_id,
                                assigned_staff_id = NULL,
                                service_type = 'doctor_appointment',
                                status = 'In Progress'
                                WHERE id = :request_id";
                $assign_stmt = $db->prepare($assign_query);
                $assign_stmt->bindParam(':doctor_id', $doctor_id);
                $assign_stmt->bindParam(':request_id', $request_id);
                $assign_stmt->execute();
                $success_message = "Doctor assigned successfully!";
            } else if ($staff_id) {
                // Assign staff
                $assign_query = "UPDATE service_requests SET 
                                assigned_staff_id = :staff_id,
                                assigned_doctor_id = NULL,
                                service_type = 'general',
                                status = 'In Progress'
                                WHERE id = :request_id";
                $assign_stmt = $db->prepare($assign_query);
                $assign_stmt->bindParam(':staff_id', $staff_id);
                $assign_stmt->bindParam(':request_id', $request_id);
                $assign_stmt->execute();
                $success_message = "Staff assigned successfully!";
            } else {
                $error_message = "Please select either a staff member or doctor";
            }
        } catch (Exception $e) {
            $error_message = "Error assigning: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'update_status') {
        try {
            $request_id = intval($_POST['request_id']);
            $new_status = $_POST['status'];
            
            $status_query = "UPDATE service_requests SET 
                            status = :status
                            WHERE id = :request_id";
            $status_stmt = $db->prepare($status_query);
            $status_stmt->bindParam(':status', $new_status);
            $status_stmt->bindParam(':request_id', $request_id);
            $status_stmt->execute();
            
            $success_message = "Status updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating status: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get service requests with filters
$requests = [];
try {
    $where_conditions = [];
    $params = [];
    
    if ($filter !== 'all') {
        $status_map = [
            'pending' => 'Requested',
            'in_progress' => 'In Progress', 
            'completed' => 'Completed'
        ];
        if (isset($status_map[$filter])) {
            $where_conditions[] = "sr.status = :status";
            $params[':status'] = $status_map[$filter];
        }
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(s.service_name LIKE :search OR sr.notes LIKE :search OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $requests_query = "SELECT sr.*, 
                              s.service_name,
                              CONCAT(u.first_name, ' ', u.last_name) as resident_name,
                              r.room_number,
                              CONCAT(staff_u.first_name, ' ', staff_u.last_name) as assigned_staff_name,
                              CONCAT(doc_u.first_name, ' ', doc_u.last_name) as assigned_doctor_name,
                              doc.specialization as doctor_specialization
                       FROM service_requests sr
                       LEFT JOIN services s ON sr.service_id = s.id
                       LEFT JOIN residents res ON sr.resident_id = res.id
                       LEFT JOIN users u ON res.user_id = u.id
                       LEFT JOIN residents r ON res.id = r.id
                       LEFT JOIN staff st ON sr.assigned_staff_id = st.id
                       LEFT JOIN users staff_u ON st.user_id = staff_u.id
                       LEFT JOIN doctors doc ON sr.assigned_doctor_id = doc.id
                       LEFT JOIN users doc_u ON doc.user_id = doc_u.id
                       $where_clause
                       ORDER BY sr.created_at DESC";
    
    $requests_stmt = $db->prepare($requests_query);
    $requests_stmt->execute($params);
    $requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error fetching requests: " . $e->getMessage();
}

// Get available staff for assignment (excluding kitchen/chef staff)
$staff_list = [];
try {
    $staff_query = "SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) as staff_name, s.department, s.position, s.shift_hours
                    FROM staff s
                    JOIN users u ON s.user_id = u.id
                    WHERE u.is_active = 1
                    AND s.is_available = 1
                    AND s.department NOT IN ('Kitchen')
                    AND u.role_id != 4
                    ORDER BY u.first_name, u.last_name";
    $staff_stmt = $db->prepare($staff_query);
    $staff_stmt->execute();
    $staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Staff table might not exist
}

// Get available doctors for assignment
$doctor_list = [];
try {
    $doctor_query = "SELECT d.id, CONCAT(u.first_name, ' ', u.last_name) as doctor_name, d.specialization, d.qualification
                     FROM doctors d
                     JOIN users u ON d.user_id = u.id
                     WHERE u.is_active = 1
                     ORDER BY u.first_name, u.last_name";
    $doctor_stmt = $db->prepare($doctor_query);
    $doctor_stmt->execute();
    $doctor_list = $doctor_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Doctors table might not exist
}

// Get statistics
$stats = [
    'total_requests' => 0,
    'pending_requests' => 0,
    'in_progress_requests' => 0,
    'completed_requests' => 0
];

try {
    $stats_query = "SELECT 
                        COUNT(*) as total_requests,
                        COUNT(CASE WHEN status = 'Requested' THEN 1 END) as pending_requests,
                        COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress_requests,
                        COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_requests
                    FROM service_requests";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $result = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = $result;
    }
} catch (Exception $e) {
    // Use default values
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Request Management - Maple House</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 10px 20px;
            height: 100vh;
            overflow: hidden;
        }
        
        .page-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .stats-container {
            margin: 0 0 20px 0;
            padding-top: 0;
            flex-shrink: 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border: 2px solid #3498db;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card.pending {
            border-color: #f39c12;
        }
        
        .stat-card.in-progress {
            border-color: #e74c3c;
        }
        
        .stat-card.completed {
            border-color: #27ae60;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .filters {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        
        .filter-left {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-right {
            margin-left: auto;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 20px;
            text-decoration: none;
            color: #666;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active {
            background: #3498db;
            border-color: #3498db;
            color: white;
        }
        
        .filter-btn:hover {
            border-color: #3498db;
            color: #3498db;
        }
        
        .filter-btn.active:hover {
            color: white;
        }
        
        .search-box {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 0.9rem;
            width: 250px;
        }
        
        .search-box:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .requests-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .requests-content {
            flex: 1;
            overflow-y: auto;
        }
        
        .requests-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 20px;
        }
        
        .requests-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .requests-table th,
        .requests-table td {
            padding: 10px 6px;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.85rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.4;
        }
        
        .requests-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
        }
        
        /* Column width specifications - 8 columns total */
        .requests-table th:nth-child(1),
        .requests-table td:nth-child(1) {
            width: 12%; /* Service */
        }
        
        .requests-table th:nth-child(2),
        .requests-table td:nth-child(2) {
            width: 11%; /* Resident */
        }
        
        .requests-table th:nth-child(3),
        .requests-table td:nth-child(3) {
            width: 10%; /* Preferred Time */
        }
        
        .requests-table th:nth-child(4),
        .requests-table td:nth-child(4) {
            width: 15%; /* Description */
        }
        
        .requests-table th:nth-child(5),
        .requests-table td:nth-child(5) {
            width: 11%; /* Status */
        }
        
        .requests-table th:nth-child(6),
        .requests-table td:nth-child(6) {
            width: 16%; /* Assigned Staff */
        }
        
        .requests-table th:nth-child(7),
        .requests-table td:nth-child(7) {
            width: 11%; /* Request Date */
        }
        
        .requests-table th:nth-child(8),
        .requests-table td:nth-child(8) {
            width: 8%; /* Actions */
        }
        
        .requests-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-in-progress {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .priority-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-left: 5px;
        }
        
        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }
        
        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .priority-low {
            background: #d4edda;
            color: #155724;
        }
        
        .action-btn {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            margin: 1px;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }
        
        .btn-assign {
            background: #3498db;
            color: white;
        }
        
        .btn-status {
            background: #f39c12;
            color: white;
        }
        
        .btn-view {
            background: #27ae60;
            color: white;
        }
        
        .action-btn:hover {
            opacity: 0.8;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #2c3e50;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .btn-submit {
            background: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .btn-submit:hover {
            background: #2980b9;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-weight: 500;
        }
        
        .notification-success .notification-content {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .notification-error .notification-content {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 4px;
            margin-left: auto;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }
        
        .notification-close:hover {
            opacity: 1;
        }

        /* Text truncation for long content */
        .text-truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .description-cell {
            max-width: 200px;
            line-height: 1.3;
            font-size: 0.9rem;
        }
        
        .staff-cell {
            font-size: 0.85rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            font-size: 0.75rem;
            padding: 4px 8px;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .requests-table {
                font-size: 0.8rem;
            }
            
            .requests-table th,
            .requests-table td {
                padding: 8px 4px;
            }
            
            /* Adjust column widths for mobile */
            .requests-table th:nth-child(1),
            .requests-table td:nth-child(1) {
                width: 18%;
            }
            
            .requests-table th:nth-child(2),
            .requests-table td:nth-child(2) {
                width: 15%;
            }
            
            .requests-table th:nth-child(3),
            .requests-table td:nth-child(3) {
                width: 18%;
            }
            
            .requests-table th:nth-child(4),
            .requests-table td:nth-child(4) {
                width: 15%;
            }
            
            .requests-table th:nth-child(5),
            .requests-table td:nth-child(5) {
                width: 15%;
            }
            
            .requests-table th:nth-child(6),
            .requests-table td:nth-child(6) {
                width: 12%;
            }
            
            .requests-table th:nth-child(7),
            .requests-table td:nth-child(7) {
                width: 8%;
            }
            
            .notification {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_requests']; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card in-progress">
                    <div class="stat-number"><?php echo $stats['in_progress_requests']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card completed">
                    <div class="stat-number"><?php echo $stats['completed_requests']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
        </div>


    <div class="filters">
        <form method="GET" class="filter-row">
            <div class="filter-left">
                <div class="filter-group">
                    <label>Filter:</label>
                    <a href="?filter=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="?filter=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="?filter=in_progress<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="filter-btn <?php echo $filter === 'in_progress' ? 'active' : ''; ?>">In Progress</a>
                    <a href="?filter=completed<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
                </div>
            </div>
            
            <div class="filter-right">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="text" name="search" placeholder="Search requests..." 
                       value="<?php echo htmlspecialchars($search); ?>" class="search-box" 
                       onchange="this.form.submit()" onkeypress="if(event.key==='Enter') this.form.submit()">
            </div>
        </form>
    </div>

        <div class="requests-container">
            <div class="requests-header">
                <h3>
                    <i class="fas fa-list"></i>
                    Service Requests
                    <?php if ($filter !== 'all'): ?>
                        - <?php echo ucfirst(str_replace('_', ' ', $filter)); ?>
                    <?php endif; ?>
                    <?php if (!empty($search)): ?>
                        - Search: "<?php echo htmlspecialchars($search); ?>"
                    <?php endif; ?>
                </h3>
            </div>
            
            <div class="requests-content">
                <?php if (!empty($requests)): ?>
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Resident</th>
                                <th>Preferred Time</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Assigned Staff</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($request['service_name'] ?: 'Unknown Service'); ?></strong>
                                <?php if (!empty($request['priority'])): ?>
                                    <span class="priority-badge priority-<?php echo strtolower($request['priority']); ?>">
                                        <?php echo htmlspecialchars($request['priority']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($request['resident_name'] ?: 'Unknown'); ?>
                                <?php if (!empty($request['room_number'])): ?>
                                    <br><small>Room <?php echo htmlspecialchars($request['room_number']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php 
                                $notes = $request['notes'] ?: '';
                                // Extract preferred time and show with range
                                if (preg_match('/Preferred time:\s*(\w+)/i', $notes, $matches)) {
                                    $time = ucfirst($matches[1]);
                                    $timeRange = '';
                                    $timeLower = strtolower($time);
                                    if ($timeLower === 'morning') {
                                        $timeRange = '(6 AM - 12 PM)';
                                    } elseif ($timeLower === 'afternoon') {
                                        $timeRange = '(12 PM - 5 PM)';
                                    } elseif ($timeLower === 'evening') {
                                        $timeRange = '(5 PM - 8 PM)';
                                    } elseif ($timeLower === 'night') {
                                        $timeRange = '(8 PM - 12 AM)';
                                    } elseif ($timeLower === 'latenight') {
                                        $time = 'Late Night';
                                        $timeRange = '(12 AM - 6 AM)';
                                    }
                                    echo '<strong style="color: #667eea; font-size: 0.85rem;">⏰ ' . htmlspecialchars($time) . '</strong>';
                                    if ($timeRange) {
                                        echo '<br><small style="color: #999; font-size: 0.7rem;">' . $timeRange . '</small>';
                                    }
                                } else {
                                    echo '<span style="color: #999;">--</span>';
                                }
                                ?>
                            </td>
                            <td class="description-cell">
                                <?php 
                                $notes = $request['notes'] ?: '';
                                // Remove preferred time part and show remaining description
                                $description = preg_replace('/Preferred time:\s*\w+\.\s*/i', '', $notes);
                                $description = trim($description);
                                
                                if (!empty($description)) {
                                    echo htmlspecialchars(substr($description, 0, 60));
                                    if (strlen($description) > 60) echo '...';
                                } else {
                                    echo '<span style="color: #999; font-style: italic;">--</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $request['status'])); ?>">
                                    <?php echo htmlspecialchars($request['status']); ?>
                                </span>
                            </td>
                            <td class="staff-cell">
                                <?php if (!empty($request['assigned_staff_name']) || !empty($request['assigned_doctor_name'])): ?>
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                                        <?php if (!empty($request['assigned_doctor_name'])): ?>
                                            <strong style="font-size: 0.85rem; color: #667eea;">
                                                <i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($request['assigned_doctor_name']); ?>
                                            </strong>
                                            <?php if (!empty($request['doctor_specialization'])): ?>
                                                <small style="color: #666;"><?php echo htmlspecialchars($request['doctor_specialization']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <strong style="font-size: 0.85rem;"><?php echo htmlspecialchars($request['assigned_staff_name']); ?></strong>
                                        <?php endif; ?>
                                        <?php 
                                        $final_statuses = ['Completed', 'Cancelled'];
                                        if (!in_array(trim($request['status']), $final_statuses)): 
                                        ?>
                                            <button onclick="openReassignModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['service_name']); ?>', <?php echo $request['assigned_staff_id'] ?: 0; ?>, <?php echo $request['assigned_doctor_id'] ?: 0; ?>, '<?php echo htmlspecialchars($request['notes']); ?>')" 
                                                    class="action-btn btn-status">
                                                <i class="fas fa-edit"></i> Change
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (!in_array(trim($request['status']), ['Completed', 'Cancelled'])): ?>
                                    <button onclick="openAssignModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['service_name']); ?>', '<?php echo htmlspecialchars($request['notes']); ?>')" 
                                            class="action-btn btn-assign">
                                        <i class="fas fa-user-plus"></i> Assign
                                    </button>
                                <?php else: ?>
                                    <span style="color: #666; font-style: italic; font-size: 0.8rem;">Completed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('M j, Y', strtotime($request['request_date'] ?: $request['created_at'])); ?>
                                <br><small><?php echo date('g:i A', strtotime($request['created_at'])); ?></small>
                            </td>
                            <td>
                                <?php 
                                $final_statuses = ['Completed', 'Cancelled'];
                                if (in_array(trim($request['status']), $final_statuses)): 
                                ?>
                                    <span style="color: #999; font-style: italic;">--</span>
                                <?php else: ?>
                                    <button onclick="openStatusModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['status']); ?>')" 
                                            class="action-btn btn-status">
                                        <i class="fas fa-edit"></i> Status
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <h3>No Service Requests Found</h3>
                        <p>
                            <?php if (!empty($search)): ?>
                                No requests match your search criteria.
                            <?php elseif ($filter !== 'all'): ?>
                                No <?php echo str_replace('_', ' ', $filter); ?> requests at this time.
                            <?php else: ?>
                                No service requests have been submitted yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Assign Staff Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Assign Staff</h3>
                <span class="close" onclick="closeModal('assignModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="assign_staff">
                <input type="hidden" name="request_id" id="assign_request_id">
                
                <div class="form-group">
                    <label>Service Request:</label>
                    <input type="text" id="assign_service_name" readonly>
                </div>
                
                <div class="form-group">
                    <label>Assignment Type:</label>
                    <select id="assign_type" onchange="toggleAssignmentOptions()" required>
                        <option value="">Select Type</option>
                        <option value="staff">Assign to Staff</option>
                        <option value="doctor">Assign to Doctor</option>
                    </select>
                </div>
                
                <div class="form-group" id="staff_selection" style="display: none;">
                    <label>Select Staff Member:</label>
                    <select name="staff_id" id="staff_id_select">
                        <option value="">Select Staff Member</option>
                        <?php foreach ($staff_list as $staff): ?>
                            <option value="<?php echo $staff['id']; ?>">
                                <?php echo htmlspecialchars($staff['staff_name']); ?>
                                <?php if (!empty($staff['department']) && !empty($staff['position'])): ?>
                                    - <?php echo htmlspecialchars($staff['department'] . ' - ' . $staff['position']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="doctor_selection" style="display: none;">
                    <label>Select Doctor:</label>
                    <select name="doctor_id" id="doctor_id_select">
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctor_list as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>">
                                Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                                <?php if (!empty($doctor['specialization'])): ?>
                                    - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-check"></i> Assign
                </button>
            </form>
        </div>
    </div>

    <!-- Reassign Staff Modal -->
    <div id="reassignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Reassign Staff</h3>
                <span class="close" onclick="closeModal('reassignModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="assign_staff">
                <input type="hidden" name="request_id" id="reassign_request_id">
                
                <div class="form-group">
                    <label>Service Request:</label>
                    <input type="text" id="reassign_service_name" readonly>
                </div>
                
                <div class="form-group">
                    <label>Reassignment Type:</label>
                    <select id="reassign_type" onchange="toggleReassignmentOptions()" required>
                        <option value="">Select Type</option>
                        <option value="staff">Reassign to Staff</option>
                        <option value="doctor">Reassign to Doctor</option>
                    </select>
                </div>
                
                <div class="form-group" id="reassign_staff_selection" style="display: none;">
                    <label>Select Staff Member:</label>
                    <select name="staff_id" id="reassign_staff_select">
                        <option value="">Select Staff Member</option>
                        <?php foreach ($staff_list as $staff): ?>
                            <option value="<?php echo $staff['id']; ?>">
                                <?php echo htmlspecialchars($staff['staff_name']); ?>
                                <?php if (!empty($staff['department']) && !empty($staff['position'])): ?>
                                    - <?php echo htmlspecialchars($staff['department'] . ' - ' . $staff['position']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="reassign_doctor_selection" style="display: none;">
                    <label>Select Doctor:</label>
                    <select name="doctor_id" id="reassign_doctor_select">
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctor_list as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>">
                                Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                                <?php if (!empty($doctor['specialization'])): ?>
                                    - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-check"></i> Reassign
                </button>
            </form>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Update Status</h3>
                <span class="close" onclick="closeModal('statusModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="request_id" id="status_request_id">
                
                <div class="form-group">
                    <label>Current Status:</label>
                    <input type="text" id="current_status" readonly>
                </div>
                
                <div class="form-group">
                    <label>New Status:</label>
                    <select name="status" required>
                        <option value="Requested">Requested</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-check"></i> Update Status
                </button>
            </form>
        </div>
    </div>

    <script>
        function openAssignModal(requestId, serviceName, notes) {
            document.getElementById('assign_request_id').value = requestId;
            document.getElementById('assign_service_name').value = serviceName;
            
            // Extract preferred time from notes
            let preferredTime = '';
            if (notes) {
                const match = notes.match(/Preferred time:\s*(\w+)/i);
                if (match) {
                    preferredTime = match[1];
                }
            }
            
            // Filter staff dropdown by preferred time
            filterAssignStaffByTime(preferredTime);
            
            document.getElementById('assignModal').style.display = 'block';
        }
        
        function filterAssignStaffByTime(preferredTime) {
            const staffSelect = document.getElementById('staff_id_select');
            if (!staffSelect) return;
            
            // Clear current options except first
            while (staffSelect.options.length > 1) {
                staffSelect.remove(1);
            }
            
            // Add filtered staff
            allStaff.forEach(staff => {
                if (isStaffAvailableAtTime(staff.shift_hours, preferredTime)) {
                    const option = document.createElement('option');
                    option.value = staff.id;
                    option.text = staff.staff_name;
                    if (staff.department && staff.position) {
                        option.text += ' - ' + staff.department + ' - ' + staff.position;
                    }
                    staffSelect.add(option);
                }
            });
        }

        function toggleAssignmentOptions() {
            const assignType = document.getElementById('assign_type').value;
            const staffSelection = document.getElementById('staff_selection');
            const doctorSelection = document.getElementById('doctor_selection');
            const staffSelect = document.getElementById('staff_id_select');
            const doctorSelect = document.getElementById('doctor_id_select');
            
            if (assignType === 'staff') {
                staffSelection.style.display = 'block';
                doctorSelection.style.display = 'none';
                staffSelect.required = true;
                doctorSelect.required = false;
                doctorSelect.value = '';
            } else if (assignType === 'doctor') {
                staffSelection.style.display = 'none';
                doctorSelection.style.display = 'block';
                staffSelect.required = false;
                doctorSelect.required = true;
                staffSelect.value = '';
            } else {
                staffSelection.style.display = 'none';
                doctorSelection.style.display = 'none';
                staffSelect.required = false;
                doctorSelect.required = false;
            }
        }
        
        function toggleReassignmentOptions() {
            const reassignType = document.getElementById('reassign_type').value;
            const staffSelection = document.getElementById('reassign_staff_selection');
            const doctorSelection = document.getElementById('reassign_doctor_selection');
            const staffSelect = document.getElementById('reassign_staff_select');
            const doctorSelect = document.getElementById('reassign_doctor_select');
            
            if (reassignType === 'staff') {
                staffSelection.style.display = 'block';
                doctorSelection.style.display = 'none';
                staffSelect.required = true;
                doctorSelect.required = false;
                doctorSelect.value = '';
            } else if (reassignType === 'doctor') {
                staffSelection.style.display = 'none';
                doctorSelection.style.display = 'block';
                staffSelect.required = false;
                doctorSelect.required = true;
                staffSelect.value = '';
            } else {
                staffSelection.style.display = 'none';
                doctorSelection.style.display = 'none';
                staffSelect.required = false;
                doctorSelect.required = false;
            }
        }

        // Function to check if staff is available at preferred time
        function isStaffAvailableAtTime(shiftHours, preferredTime) {
            if (!shiftHours || !preferredTime) return true; // Show all if no info
            
            const shift = shiftHours.toLowerCase();
            const time = preferredTime.toLowerCase();
            
            // Parse shift hours (e.g., "4:00 PM - 10:00 PM" or "9 AM - 3 PM")
            const timeMatch = shift.match(/(\d{1,2}):?(\d{2})?\s*(am|pm).*?(\d{1,2}):?(\d{2})?\s*(am|pm)/i);
            
            if (!timeMatch) {
                // Can't parse, show all
                return true;
            }
            
            // Convert start time to 24-hour format
            let startHour = parseInt(timeMatch[1]);
            const startMinute = timeMatch[2] ? parseInt(timeMatch[2]) : 0;
            const startPeriod = timeMatch[3].toLowerCase();
            
            if (startPeriod === 'pm' && startHour !== 12) {
                startHour += 12;
            } else if (startPeriod === 'am' && startHour === 12) {
                startHour = 0;
            }
            
            // Convert end time to 24-hour format
            let endHour = parseInt(timeMatch[4]);
            const endMinute = timeMatch[5] ? parseInt(timeMatch[5]) : 0;
            const endPeriod = timeMatch[6].toLowerCase();
            
            if (endPeriod === 'pm' && endHour !== 12) {
                endHour += 12;
            } else if (endPeriod === 'am' && endHour === 12) {
                endHour = 0;
            }
            
            // Define time ranges for each period (in 24-hour format)
            const timeRanges = {
                'morning': { start: 6, end: 12 },      // 6:00 AM - 12:00 PM
                'afternoon': { start: 12, end: 17 },   // 12:00 PM - 5:00 PM
                'evening': { start: 17, end: 20 },     // 5:00 PM - 8:00 PM
                'night': { start: 20, end: 24 },       // 8:00 PM - 12:00 AM
                'latenight': { start: 0, end: 6 }      // 12:00 AM - 6:00 AM
            };
            
            const requestedRange = timeRanges[time];
            if (!requestedRange) return true; // Unknown time, show all
            
            // Check if staff shift overlaps with requested time range
            // Shift overlaps if: (shift_start < request_end) AND (shift_end > request_start)
            const overlaps = (startHour < requestedRange.end && endHour > requestedRange.start);
            
            // Also handle overnight shifts (if end < start, it crosses midnight)
            if (endHour < startHour) {
                // Overnight shift - check both parts
                return (startHour < requestedRange.end) || (endHour > requestedRange.start);
            }
            
            return overlaps;
        }
        
        // Store all staff with their shift hours
        const allStaff = <?php echo json_encode($staff_list); ?>;
        
        function filterStaffByTime(preferredTime) {
            const staffSelect = document.getElementById('reassign_staff_select');
            if (!staffSelect) return;
            
            // Clear current options except first
            while (staffSelect.options.length > 1) {
                staffSelect.remove(1);
            }
            
            // Add filtered staff
            allStaff.forEach(staff => {
                if (isStaffAvailableAtTime(staff.shift_hours, preferredTime)) {
                    const option = document.createElement('option');
                    option.value = staff.id;
                    option.text = staff.staff_name;
                    if (staff.department && staff.position) {
                        option.text += ' - ' + staff.department + ' - ' + staff.position;
                    }
                    staffSelect.add(option);
                }
            });
        }

        function openReassignModal(requestId, serviceName, currentStaffId, currentDoctorId, notes) {
            document.getElementById('reassign_request_id').value = requestId;
            document.getElementById('reassign_service_name').value = serviceName;
            
            // Extract preferred time from notes
            let preferredTime = '';
            if (notes) {
                const match = notes.match(/Preferred time:\s*(\w+)/i);
                if (match) {
                    preferredTime = match[1];
                }
            }
            
            // Determine current assignment type and set accordingly
            if (currentDoctorId > 0) {
                document.getElementById('reassign_type').value = 'doctor';
                toggleReassignmentOptions();
                document.getElementById('reassign_doctor_select').value = currentDoctorId;
            } else if (currentStaffId > 0) {
                document.getElementById('reassign_type').value = 'staff';
                toggleReassignmentOptions();
                // Filter staff by preferred time
                filterStaffByTime(preferredTime);
                document.getElementById('reassign_staff_select').value = currentStaffId;
            }
            
            document.getElementById('reassignModal').style.display = 'block';
        }

        function openStatusModal(requestId, currentStatus) {
            document.getElementById('status_request_id').value = requestId;
            document.getElementById('current_status').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function viewRequest(requestId) {
            // Redirect to a detailed view page or open in new tab
            window.open('service_details.php?id=' + requestId, '_blank');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const assignModal = document.getElementById('assignModal');
            const reassignModal = document.getElementById('reassignModal');
            const statusModal = document.getElementById('statusModal');
            
            if (event.target === assignModal) {
                assignModal.style.display = 'none';
            }
            if (event.target === reassignModal) {
                reassignModal.style.display = 'none';
            }
            if (event.target === statusModal) {
                statusModal.style.display = 'none';
            }
        }

        // Show success/error messages as popups
        <?php if (isset($success_message)): ?>
            showNotification('<?php echo addslashes($success_message); ?>', 'success');
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            showNotification('<?php echo addslashes($error_message); ?>', 'error');
        <?php endif; ?>

        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'notification notification-' + type;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="notification-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Auto-remove after 4 seconds
            setTimeout(function() {
                if (notification.parentElement) {
                    notification.style.opacity = '0';
                    setTimeout(function() {
                        if (notification.parentElement) {
                            notification.remove();
                        }
                    }, 300);
                }
            }, 4000);
        }
    </script>
</body>
</html>
