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

// Handle form submission for new health record
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_record'])) {
    $resident_id = $_POST['resident_id'];
    $checkup_date = $_POST['checkup_date'];
    $blood_pressure = $_POST['blood_pressure'];
    $heart_rate = $_POST['heart_rate'];
    $temperature = $_POST['temperature'];
    $weight = $_POST['weight'];
    $notes = $_POST['notes'];
    $next_checkup_date = $_POST['next_checkup_date'] ?: null;
    
    try {
        $insert_query = "INSERT INTO health_records (resident_id, doctor_id, checkup_date, blood_pressure, heart_rate, temperature, weight, notes, next_checkup_date)
                        VALUES (:resident_id, :doctor_id, :checkup_date, :blood_pressure, :heart_rate, :temperature, :weight, :notes, :next_checkup_date)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':resident_id', $resident_id);
        $insert_stmt->bindParam(':doctor_id', $doctor['id']);
        $insert_stmt->bindParam(':checkup_date', $checkup_date);
        $insert_stmt->bindParam(':blood_pressure', $blood_pressure);
        $insert_stmt->bindParam(':heart_rate', $heart_rate);
        $insert_stmt->bindParam(':temperature', $temperature);
        $insert_stmt->bindParam(':weight', $weight);
        $insert_stmt->bindParam(':notes', $notes);
        $insert_stmt->bindParam(':next_checkup_date', $next_checkup_date);
        
        if ($insert_stmt->execute()) {
            $message = "Health record added successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to add health record.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get filter parameters
$selected_resident = isset($_GET['resident_id']) ? $_GET['resident_id'] : '';

// Get all residents for dropdown
$residents_query = "SELECT r.id, u.first_name, u.last_name, r.room_number
                    FROM residents r
                    JOIN users u ON r.user_id = u.id
                    ORDER BY u.first_name, u.last_name";
$residents_stmt = $db->prepare($residents_query);
$residents_stmt->execute();
$residents = $residents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get health records
$records_query = "SELECT hr.*, 
                  CONCAT(u.first_name, ' ', u.last_name) as resident_name,
                  r.room_number,
                  CONCAT(du.first_name, ' ', du.last_name) as doctor_name
                  FROM health_records hr
                  JOIN residents r ON hr.resident_id = r.id
                  JOIN users u ON r.user_id = u.id
                  JOIN doctors d ON hr.doctor_id = d.id
                  JOIN users du ON d.user_id = du.id";

if ($selected_resident) {
    $records_query .= " WHERE hr.resident_id = :resident_id";
}

$records_query .= " ORDER BY hr.checkup_date DESC";

$records_stmt = $db->prepare($records_query);
if ($selected_resident) {
    $records_stmt->bindParam(':resident_id', $selected_resident);
}
$records_stmt->execute();
$health_records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Records - Doctor Portal</title>
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
        
        .top-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-shrink: 0;
        }
        
        .filter-group {
            flex: 1;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
            white-space: nowrap;
        }
        
        .filter-group select {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
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
            white-space: nowrap;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }
        
        .records-section {
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
        
        .records-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .records-table thead {
            background: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .records-table th {
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
        
        .records-table tbody {
            display: block;
        }
        
        .records-table thead,
        .records-table tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        
        .records-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f5;
            color: #2c3e50;
            text-align: center;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .records-table tbody tr:hover {
            background: #f8f9fa;
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
            flex-shrink: 0;
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
        
        /* Modal Styles */
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
            overflow-y: auto;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            margin: 20px;
        }
        
        .modal-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .modal-header h3 {
            color: #2c3e50;
            margin: 0;
            font-size: 1.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #27ae60;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
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
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="top-actions">
            <div class="filter-group">
                <label for="residentFilter"><i class="fas fa-filter"></i> Filter by Resident:</label>
                <select id="residentFilter" onchange="filterRecords()">
                    <option value="">All Residents</option>
                    <?php foreach ($residents as $resident): ?>
                        <option value="<?php echo $resident['id']; ?>" <?php echo $selected_resident == $resident['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']) . ' - Room ' . $resident['room_number']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Add Health Record
            </button>
        </div>
        
        <div class="records-section">
            <div class="section-header">
                <h2><i class="fas fa-file-medical"></i> Health Records</h2>
                <span style="background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-size: 0.85rem;">
                    <?php echo count($health_records); ?> Records
                </span>
            </div>
            <div class="table-container">
                <?php if (count($health_records) > 0): ?>
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Resident</th>
                                <th style="width: 8%;">Room</th>
                                <th style="width: 12%;">Date</th>
                                <th style="width: 10%;">BP</th>
                                <th style="width: 8%;">HR</th>
                                <th style="width: 8%;">Temp</th>
                                <th style="width: 8%;">Weight</th>
                                <th style="width: 20%;">Notes</th>
                                <th style="width: 11%;">Next Checkup</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="table-scroll">
                        <table class="records-table">
                            <tbody>
                                <?php foreach ($health_records as $record): ?>
                                    <tr>
                                        <td style="width: 15%;"><strong><?php echo htmlspecialchars($record['resident_name']); ?></strong></td>
                                        <td style="width: 8%;">
                                            <span class="room-badge"><?php echo htmlspecialchars($record['room_number']); ?></span>
                                        </td>
                                        <td style="width: 12%;"><?php echo date('M d, Y', strtotime($record['checkup_date'])); ?></td>
                                        <td style="width: 10%;"><?php echo htmlspecialchars($record['blood_pressure'] ?? '--'); ?></td>
                                        <td style="width: 8%;"><?php echo $record['heart_rate'] ? $record['heart_rate'] . ' bpm' : '--'; ?></td>
                                        <td style="width: 8%;"><?php echo $record['temperature'] ? $record['temperature'] . '°F' : '--'; ?></td>
                                        <td style="width: 8%;"><?php echo $record['weight'] ? $record['weight'] . ' kg' : '--'; ?></td>
                                        <td style="width: 20%; font-size: 0.85rem;"><?php echo $record['notes'] ? htmlspecialchars(substr($record['notes'], 0, 60)) . (strlen($record['notes']) > 60 ? '...' : '') : '--'; ?></td>
                                        <td style="width: 11%;"><?php echo $record['next_checkup_date'] ? date('M d, Y', strtotime($record['next_checkup_date'])) : '--'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-medical"></i>
                        <p>No health records found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Health Record Modal -->
    <div class="modal" id="addRecordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add Health Record</h3>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="resident_id">Resident *</label>
                    <select name="resident_id" id="resident_id" required>
                        <option value="">Select Resident</option>
                        <?php foreach ($residents as $resident): ?>
                            <option value="<?php echo $resident['id']; ?>">
                                <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']) . ' - Room ' . $resident['room_number']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="checkup_date">Checkup Date *</label>
                        <input type="date" name="checkup_date" id="checkup_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="blood_pressure">Blood Pressure</label>
                        <input type="text" name="blood_pressure" id="blood_pressure" placeholder="e.g., 120/80">
                    </div>
                    
                    <div class="form-group">
                        <label for="heart_rate">Heart Rate (bpm)</label>
                        <input type="number" name="heart_rate" id="heart_rate" placeholder="e.g., 72">
                    </div>
                    
                    <div class="form-group">
                        <label for="temperature">Temperature (°F)</label>
                        <input type="number" step="0.1" name="temperature" id="temperature" placeholder="e.g., 98.6">
                    </div>
                    
                    <div class="form-group">
                        <label for="weight">Weight (kg)</label>
                        <input type="number" step="0.1" name="weight" id="weight" placeholder="e.g., 70.5">
                    </div>
                    
                    <div class="form-group">
                        <label for="next_checkup_date">Next Checkup Date</label>
                        <input type="date" name="next_checkup_date" id="next_checkup_date">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="notes">Notes *</label>
                    <textarea name="notes" id="notes" required placeholder="Enter checkup notes, diagnosis, prescription, and observations..."></textarea>
                </div>
                
                <input type="hidden" name="add_record" value="1">
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('addRecordModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('addRecordModal').classList.remove('active');
        }
        
        function filterRecords() {
            const residentId = document.getElementById('residentFilter').value;
            if (residentId) {
                window.location.href = '?resident_id=' + residentId;
            } else {
                window.location.href = 'health_records.php';
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('addRecordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
