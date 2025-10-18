<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get doctor information
$doctor_query = "SELECT d.*, u.first_name, u.last_name FROM doctors d 
                 JOIN users u ON d.user_id = u.id 
                 WHERE d.user_id = :user_id";
$doctor_stmt = $db->prepare($doctor_query);
$doctor_stmt->bindParam(':user_id', $_SESSION['user_id']);
$doctor_stmt->execute();
$doctor = $doctor_stmt->fetch(PDO::FETCH_ASSOC);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];
    
    $update_query = "UPDATE service_requests SET status = :status";
    if ($new_status === 'Completed') {
        $update_query .= ", completed_at = NOW()";
    }
    $update_query .= " WHERE id = :appointment_id AND assigned_doctor_id = :doctor_id";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':appointment_id', $appointment_id);
    $update_stmt->bindParam(':doctor_id', $doctor['id']);
    
    if ($update_stmt->execute()) {
        $success_message = "Appointment status updated successfully!";
    } else {
        $error_message = "Failed to update appointment status.";
    }
}

// Get all appointments assigned to this doctor
$appointments_query = "SELECT sr.*, s.service_name, 
                       CONCAT(u.first_name, ' ', u.last_name) as resident_name,
                       r.room_number, r.id as resident_id
                       FROM service_requests sr
                       JOIN services s ON sr.service_id = s.id
                       JOIN residents r ON sr.resident_id = r.id
                       JOIN users u ON r.user_id = u.id
                       WHERE sr.assigned_doctor_id = :doctor_id
                       AND sr.service_type = 'doctor_appointment'
                       ORDER BY 
                           CASE sr.status
                               WHEN 'Requested' THEN 1
                               WHEN 'In Progress' THEN 2
                               WHEN 'Completed' THEN 3
                           END,
                           sr.scheduled_date DESC, sr.request_date DESC";
$appointments_stmt = $db->prepare($appointments_query);
$appointments_stmt->bindParam(':doctor_id', $doctor['id']);
$appointments_stmt->execute();
$appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group appointments by status
$requested = [];
$in_progress = [];
$completed = [];

foreach ($appointments as $appointment) {
    switch ($appointment['status']) {
        case 'Requested':
            $requested[] = $appointment;
            break;
        case 'In Progress':
            $in_progress[] = $appointment;
            break;
        case 'Completed':
            $completed[] = $appointment;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Doctor Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            padding: 0;
            margin: 0;
            height: 100vh;
            overflow: hidden;
        }
        
        .container {
            max-width: 100%;
            margin: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
            flex-shrink: 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #27ae60;
        }
        
        .stat-card:nth-child(2) { border-left-color: #f39c12; }
        .stat-card:nth-child(3) { border-left-color: #3498db; }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .appointments-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .section-header {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-container {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .appointments-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .appointments-table thead {
            background: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .appointments-table th {
            padding: 15px;
            text-align: center;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .table-scroll {
            flex: 1;
            overflow-y: auto;
        }
        
        .appointments-table tbody {
            display: block;
        }
        
        .appointments-table thead,
        .appointments-table tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        
        .appointments-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f5;
            color: #2c3e50;
            text-align: center;
            vertical-align: middle;
        }
        
        .appointments-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .appointment-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border-left: 5px solid #27ae60;
            transition: all 0.3s ease;
        }
        
        .appointment-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .resident-info {
            flex: 1;
        }
        
        .resident-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .room-badge {
            background: #27ae60;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .detail-item i {
            color: #27ae60;
            width: 20px;
        }
        
        .appointment-notes {
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .appointment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            display: inline-block;
        }
        
        .status-requested {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-in-progress {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Requested</div>
                <div class="stat-value"><?php echo count($requested); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">In Progress</div>
                <div class="stat-value"><?php echo count($in_progress); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?php echo count($completed); ?></div>
            </div>
        </div>
        
        <!-- All Appointments Table -->
        <div class="appointments-section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-check"></i> All Appointments</h2>
                <span class="status-badge status-requested"><?php echo count($appointments); ?> Total</span>
            </div>
            <div class="table-container">
                <?php if (count($appointments) > 0): ?>
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Resident</th>
                                <th style="width: 8%;">Room</th>
                                <th style="width: 15%;">Service</th>
                                <th style="width: 12%;">Requested</th>
                                <th style="width: 12%;">Scheduled</th>
                                <th style="width: 20%;">Notes</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 18%;">Actions</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="table-scroll">
                        <table class="appointments-table">
                            <tbody>
                                <?php foreach ($appointments as $apt): ?>
                                    <tr>
                                        <td style="width: 15%;"><strong><?php echo htmlspecialchars($apt['resident_name']); ?></strong></td>
                                        <td style="width: 8%;">
                                            <span class="room-badge"><?php echo htmlspecialchars($apt['room_number']); ?></span>
                                        </td>
                                        <td style="width: 15%;"><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                        <td style="width: 12%;"><?php echo date('M d, Y', strtotime($apt['request_date'])); ?></td>
                                        <td style="width: 12%;"><?php echo $apt['scheduled_date'] ? date('M d, Y', strtotime($apt['scheduled_date'])) : '--'; ?></td>
                                        <td style="width: 20%; font-size: 0.85rem;"><?php echo $apt['notes'] ? htmlspecialchars(substr($apt['notes'], 0, 50)) . (strlen($apt['notes']) > 50 ? '...' : '') : '--'; ?></td>
                                        <td style="width: 10%;">
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $apt['status'])); ?>">
                                                <?php echo htmlspecialchars($apt['status']); ?>
                                            </span>
                                        </td>
                                        <td style="width: 18%;">
                                            <?php if ($apt['status'] === 'Requested'): ?>
                                                <button class="btn btn-warning" style="padding: 6px 12px; font-size: 0.8rem;" onclick="updateStatus(<?php echo $apt['id']; ?>, 'In Progress')">
                                                    <i class="fas fa-play"></i> Start
                                                </button>
                                                <button class="btn btn-primary" style="padding: 6px 12px; font-size: 0.8rem;" onclick="updateStatus(<?php echo $apt['id']; ?>, 'Completed')">
                                                    <i class="fas fa-check"></i> Done
                                                </button>
                                            <?php elseif ($apt['status'] === 'In Progress'): ?>
                                                <button class="btn btn-primary" style="padding: 6px 12px; font-size: 0.8rem;" onclick="updateStatus(<?php echo $apt['id']; ?>, 'Completed')">
                                                    <i class="fas fa-check"></i> Mark as Done
                                                </button>
                                            <?php else: ?>
                                                <span style="color: #3498db; font-size: 0.85rem;">
                                                    <i class="fas fa-check-circle"></i> <?php echo $apt['completed_at'] ? date('M d', strtotime($apt['completed_at'])) : 'Done'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <p>No appointments found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Appointment Status</h3>
                <p style="color: #666;">Are you sure you want to update this appointment?</p>
            </div>
            <form method="POST" id="statusForm">
                <input type="hidden" name="appointment_id" id="appointmentId">
                <input type="hidden" name="status" id="statusValue">
                <input type="hidden" name="update_status" value="1">
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function updateStatus(appointmentId, status) {
            document.getElementById('appointmentId').value = appointmentId;
            document.getElementById('statusValue').value = status;
            document.getElementById('statusModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('statusModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
