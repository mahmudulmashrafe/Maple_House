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

// Handle task status update and date scheduling
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['status'];
    $new_date = $_POST['scheduled_date'] ?? null;
    $notes = $_POST['notes'] ?? '';
    
    // Prepare the update statement
    $update_stmt = $db->prepare("UPDATE service_requests 
                                 SET status = ?, scheduled_date = ?, notes = CONCAT(IFNULL(notes, ''), ?), 
                                 completed_at = CASE WHEN ? = 'Completed' THEN NOW() ELSE NULL END 
                                 WHERE id = ?");
    
    $completed_note = ($new_status == 'Completed') ? "\nCompleted on: " . date('Y-m-d H:i:s') : '';
    $full_notes = $notes . $completed_note;
    
    // Execute the statement
    $update_stmt->execute([$new_status, $new_date, $full_notes, $new_status, $task_id]);
    
    $message = "Task status updated successfully!";
}

// Get scheduled tasks assigned to the logged-in staff member only
$tasks_stmt = $db->prepare("SELECT sr.*, s.service_name, r.room_number, 
                            CONCAT(u.first_name, ' ', u.last_name) as resident_name,
                            sr.cost as service_cost,
                            st.first_name as staff_first_name, 
                            st.last_name as staff_last_name
                            FROM service_requests sr
                            JOIN services s ON sr.service_id = s.id
                            JOIN residents r ON sr.resident_id = r.id
                            JOIN users u ON r.user_id = u.id
                            LEFT JOIN (
                                SELECT s2.id, u2.first_name, u2.last_name 
                                FROM staff s2 
                                JOIN users u2 ON s2.user_id = u2.id
                            ) st ON sr.assigned_staff_id = st.id
                            WHERE sr.status IN ('Scheduled', 'In Progress', 'Requested')
                            AND sr.assigned_staff_id = :staff_id
                            ORDER BY sr.scheduled_date ASC, sr.request_date ASC");
$tasks_stmt->bindParam(':staff_id', $staff['id']);
$tasks_stmt->execute();
$tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get a list of all staff members for the filter
$staff_list_stmt = $db->prepare("SELECT s.id, u.first_name, u.last_name FROM staff s JOIN users u ON s.user_id = u.id ORDER BY u.first_name");
$staff_list_stmt->execute();
$all_staff = $staff_list_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary: #4a7c59;
            --secondary: #8d9f87;
            --accent: #c8d5b9;
            --light: #faf3dd;
            --dark: #2d3e24;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: var(--primary);
            color: white;
            padding: 15px 0;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .header-content h1 {
            font-size: 1.8rem;
        }
        
        .welcome {
            font-size: 1.2rem;
        }
        
        .logout-btn {
            background-color: var(--light);
            color: var(--dark);
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
        }
        
        .logout-btn:hover {
            background-color: #e8e0c7;
        }
        
        .navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .nav-btn {
            padding: 10px 15px;
            background-color: var(--secondary);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        
        .nav-btn.active {
            background-color: var(--primary);
        }
        
        .nav-btn:hover {
            background-color: var(--dark);
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .main-content {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        h2 {
            color: var(--primary);
            margin-bottom: 15px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 5px;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .filter-group label {
            font-weight: bold;
            color: var(--dark);
        }
        
        .filter-group select,
        .filter-group input[type="date"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .tasks-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .tasks-table th, .tasks-table td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        
        .tasks-table th {
            background-color: var(--secondary);
            color: white;
            font-weight: bold;
        }
        
        .tasks-table tbody tr:hover {
            background-color: #f1f1f1;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 12px;
            color: white;
            font-weight: bold;
            font-size: 0.8rem;
        }
        
        .status-Scheduled { background-color: #e6b800; }
        .status-InProgress { background-color: #007bff; }
        .status-Completed { background-color: #28a745; }
        
        .action-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        
        .action-btn:hover {
            background-color: var(--dark);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
            position: relative;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            animation-name: modalopen;
            animation-duration: 0.4s;
        }
        
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        
        .close-btn:hover,
        .close-btn:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        
        .modal-content h3 {
            margin-bottom: 20px;
            color: var(--primary);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group select,
        .form-group textarea,
        .form-group input[type="date"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .btn-submit {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }
        
        .btn-submit:hover {
            background-color: var(--dark);
        }
        
        @keyframes modalopen {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
    </style>
</head>
<body>
    

    <div class="container">

        <div class="dashboard">
            <div class="main-content">
                <?php if (!empty($message)): ?>
                    <p style="color: green; font-weight: bold;"><?php echo htmlspecialchars($message); ?></p>
                <?php endif; ?>

                <table class="tasks-table">
                    <thead>
                        <tr>
                            <th>Serial No.</th>
                            <th>Request ID</th>
                            <th>Service</th>
                            <th>Resident</th>
                            <th>Room</th>
                            <th>Scheduled Date</th>
                            <th>Assigned Staff</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $serial_no = 1;
                        foreach ($tasks as $task): 
                        ?>
                            <tr data-date="<?php echo htmlspecialchars($task['scheduled_date']); ?>" data-staff="<?php echo htmlspecialchars($task['assigned_staff_id']); ?>">
                                <td><?php echo $serial_no++; ?></td>
                                <td><?php echo htmlspecialchars($task['id']); ?></td>
                                <td><?php echo htmlspecialchars($task['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($task['resident_name']); ?></td>
                                <td><?php echo htmlspecialchars($task['room_number']); ?></td>
                                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($task['scheduled_date']))); ?></td>
                                <td><?php echo htmlspecialchars($task['staff_first_name'] . ' ' . $task['staff_last_name']); ?></td>
                                <td><span class="status-badge status-<?php echo str_replace(' ', '', htmlspecialchars($task['status'])); ?>"><?php echo htmlspecialchars($task['status']); ?></span></td>
                                <td>
                                    <button class="action-btn" onclick="openEditModal('<?php echo htmlspecialchars($task['id']); ?>', '<?php echo htmlspecialchars($task['status']); ?>', '<?php echo htmlspecialchars($task['scheduled_date']); ?>')">Update</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center;">No scheduled tasks found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit/Update Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h3>Update Task Status</h3>
            <form action="schedule.php" method="POST">
                <input type="hidden" name="task_id" id="editTaskId">
                <div class="form-group">
                    <label for="scheduled_date">Scheduled Date:</label>
                    <input type="date" id="scheduled_date" name="scheduled_date">
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="Scheduled">Scheduled</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notes">Add Notes:</label>
                    <textarea id="notes" name="notes" rows="4"></textarea>
                </div>
                <button type="submit" name="update_status" class="btn-submit">Update Task</button>
            </form>
        </div>
    </div>

    <script>
        // JS for filtering and modal functionality
        function filterTasks() {
            const date = document.getElementById('dateFilter').value;
            const staffId = document.getElementById('staffFilter').value;
            const rows = document.querySelectorAll('.tasks-table tbody tr');
            
            rows.forEach(row => {
                const rowDate = row.getAttribute('data-date');
                const rowStaff = row.getAttribute('data-staff');
                
                const dateMatch = !date || rowDate === date;
                const staffMatch = staffId === 'all' || rowStaff === staffId;
                
                if (dateMatch && staffMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Modal functions
        function openEditModal(taskId, currentStatus, scheduledDate) {
            document.getElementById('editTaskId').value = taskId;
            document.getElementById('status').value = currentStatus;
            document.getElementById('scheduled_date').value = scheduledDate;
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
