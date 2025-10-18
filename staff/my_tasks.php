<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get staff information
$staff_query = "SELECT s.*, u.first_name, u.last_name FROM staff s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.user_id = :user_id";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_stmt->execute();
$staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    
    // Fix: Changed $pdo to $db
    $update_stmt = $db->prepare("UPDATE service_requests 
                                 SET status = ?, notes = CONCAT(IFNULL(notes, ''), ?), 
                                 completed_at = CASE WHEN ? = 'Completed' THEN NOW() ELSE NULL END 
                                 WHERE id = ? AND assigned_staff_id = ?");
    
    $completed_note = ($new_status == 'Completed') ? "\nCompleted on: " . date('Y-m-d H:i:s') : '';
    $full_notes = $notes . $completed_note;
    
    $update_stmt->execute([$new_status, $full_notes, $new_status, $task_id, $staff['id']]);
    
    $message = "Task status updated successfully!";
}

// Get assigned tasks
$tasks_stmt = $db->prepare("SELECT sr.*, s.service_name, r.room_number, 
                            CONCAT(u.first_name, ' ', u.last_name) as resident_name,
                            sr.cost as service_cost
                            FROM service_requests sr
                            JOIN services s ON sr.service_id = s.id
                            JOIN residents r ON sr.resident_id = r.id
                            JOIN users u ON r.user_id = u.id
                            WHERE sr.assigned_staff_id = ?
                            ORDER BY sr.request_date DESC, sr.status");
$tasks_stmt->execute([$staff['id']]);
$tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count tasks by status
$status_counts = [
    'Requested' => 0,
    'Scheduled' => 0,
    'In Progress' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];

foreach ($tasks as $task) {
    $status_counts[$task['status']]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            color: #2c3e50;
            padding: 0;
            padding-top: 140px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Stats Cards */
        .stats-grid {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #f8f9fa;
            padding: 20px;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 5px solid #667eea;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card:nth-child(1) { border-left-color: #667eea; }
        .stat-card:nth-child(2) { border-left-color: #f093fb; }
        .stat-card:nth-child(3) { border-left-color: #4facfe; }
        .stat-card:nth-child(4) { border-left-color: #43e97b; }
        .stat-card:nth-child(5) { border-left-color: #f5576c; }
        
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
        
        /* Tasks Section */
        .tasks-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 180px);
        }
        
        .table-wrapper {
            overflow-y: auto;
            flex: 1;
        }
        
        .table-wrapper::-webkit-scrollbar {
            width: 8px;
        }
        
        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .tasks-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px 25px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tasks-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-options select {
            padding: 10px 15px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            background: white;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-options select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .tasks-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tasks-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .tasks-table th {
            padding: 15px;
            text-align: left;
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .tasks-table td {
            padding: 18px 15px;
            border-bottom: 1px solid #f1f3f5;
        }
        
        .tasks-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .tasks-table tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.03) 0%, rgba(118, 75, 162, 0.03) 100%);
            transform: scale(1.01);
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        
        .status-requested {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .status-scheduled {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .status-in-progress {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        
        .status-completed {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .status-cancelled {
            background: linear-gradient(135deg, #f5576c 0%, #c44569 100%);
            color: white;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background-color: white;
            margin: 8% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .close {
            font-size: 1.8rem;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        
        .close:hover {
            opacity: 1;
        }
        
        .modal-content form {
            padding: 25px;
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
        
        .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            text-align: center;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            .modal-content {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Requested</div>
                <div class="stat-value"><?php echo $status_counts['Requested']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Scheduled</div>
                <div class="stat-value"><?php echo $status_counts['Scheduled']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">In Progress</div>
                <div class="stat-value"><?php echo $status_counts['In Progress']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?php echo $status_counts['Completed']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Cancelled</div>
                <div class="stat-value"><?php echo $status_counts['Cancelled']; ?></div>
            </div>
        </div>

        <!-- Tasks Table -->
        <div class="tasks-section">
            <div class="tasks-header">
                <h2><i class="fas fa-list"></i> All Tasks</h2>
                <div class="filter-options">
                    <select id="statusFilter" name="status">
                        <option value="all">All Status</option>
                        <option value="Requested">Requested</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="tasks-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Resident</th>
                            <th>Room</th>
                            <th>Date Requested</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tasks) > 0): ?>
                            <?php foreach ($tasks as $task): ?>
                                <tr data-status="<?php echo htmlspecialchars($task['status']); ?>">
                                    <td><?php echo htmlspecialchars($task['service_name']); ?></td>
                                    <td><?php echo htmlspecialchars($task['resident_name']); ?></td>
                                    <td><?php echo htmlspecialchars($task['room_number']); ?></td>
                                    <td><?php echo htmlspecialchars($task['request_date']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $task['status'])); ?>">
                                            <?php echo htmlspecialchars($task['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-edit" onclick="openEditModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['status']); ?>')">Update Status</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No tasks assigned to you.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Task Status</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form action="my_tasks.php" method="POST">
                <input type="hidden" name="task_id" id="editTaskId">
                <div class="form-group">
                    <label for="status">New Status:</label>
                    <select name="status" id="status" required>
                        <option value="Requested">Requested</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notes">Notes (Optional):</label>
                    <textarea name="notes" id="notes" rows="4" placeholder="Add any notes about the task..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filter tasks by status
        document.getElementById('statusFilter').addEventListener('change', function() {
            const status = this.value;
            const rows = document.querySelectorAll('.tasks-table tbody tr');
            
            rows.forEach(row => {
                if (status === 'all' || row.getAttribute('data-status') === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Modal functions
        function openEditModal(taskId, currentStatus) {
            document.getElementById('editTaskId').value = taskId;
            document.getElementById('status').value = currentStatus;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeModal();
            }
        };
    </script>
</body>
</html>