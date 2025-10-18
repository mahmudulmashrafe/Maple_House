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

// Get all residents with their latest health information
$residents_query = "SELECT r.*, u.first_name, u.last_name, u.date_of_birth, u.gender, u.phone, u.email,
                    pp.plan_name, pp.monthly_fee,
                    (SELECT checkup_date FROM health_records WHERE resident_id = r.id ORDER BY checkup_date DESC LIMIT 1) as last_checkup,
                    (SELECT blood_pressure FROM health_records WHERE resident_id = r.id ORDER BY checkup_date DESC LIMIT 1) as blood_pressure,
                    (SELECT heart_rate FROM health_records WHERE resident_id = r.id ORDER BY checkup_date DESC LIMIT 1) as heart_rate,
                    (SELECT temperature FROM health_records WHERE resident_id = r.id ORDER BY checkup_date DESC LIMIT 1) as temperature,
                    (SELECT weight FROM health_records WHERE resident_id = r.id ORDER BY checkup_date DESC LIMIT 1) as weight,
                    (SELECT notes FROM health_records WHERE resident_id = r.id ORDER BY checkup_date DESC LIMIT 1) as last_notes
                    FROM residents r
                    JOIN users u ON r.user_id = u.id
                    JOIN payment_plans pp ON r.plan_id = pp.id
                    ORDER BY u.first_name, u.last_name";
$residents_stmt = $db->prepare($residents_query);
$residents_stmt->execute();
$residents = $residents_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Doctor Portal</title>
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
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
            flex-shrink: 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #27ae60;
        }
        
        .stat-card:nth-child(2) { border-left-color: #3498db; }
        .stat-card:nth-child(3) { border-left-color: #f39c12; }
        .stat-card:nth-child(4) { border-left-color: #e74c3c; }
        
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
        
        .patients-section {
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
        
        .patients-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .patients-table thead {
            background: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .patients-table th {
            padding: 15px 20px;
            text-align: center;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
        }
        
        .table-scroll {
            flex: 1;
            overflow-y: auto;
        }
        
        .patients-table tbody {
            display: block;
        }
        
        .patients-table thead,
        .patients-table tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        
        .patients-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f5;
            color: #2c3e50;
            text-align: center;
            vertical-align: middle;
        }
        
        .patients-table tbody tr:hover {
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
        
        .health-score {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .health-score.high {
            background: #d4edda;
            color: #155724;
        }
        
        .health-score.medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .health-score.low {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
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
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 0;
            border-radius: 15px;
            max-width: 900px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
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
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-body {
            padding: 25px;
            overflow-y: auto;
            flex: 1;
        }
        
        .records-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .record-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #27ae60;
        }
        
        .record-date {
            font-weight: 600;
            color: #27ae60;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .vital-item {
            text-align: center;
        }
        
        .vital-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .vital-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .record-notes {
            background: white;
            padding: 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Patients</div>
                <div class="stat-value"><?php echo count($residents); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">With Records</div>
                <div class="stat-value"><?php echo count(array_filter($residents, fn($r) => $r['last_checkup'])); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Plans</div>
                <div class="stat-value"><?php echo count(array_filter($residents, fn($r) => $r['plan_name'])); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">No Records</div>
                <div class="stat-value"><?php echo count(array_filter($residents, fn($r) => !$r['last_checkup'])); ?></div>
            </div>
        </div>
        
        <div class="patients-section">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> All Patients</h2>
                <span style="background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-size: 0.85rem;">
                    <?php echo count($residents); ?> Residents
                </span>
            </div>
            <div class="table-container">
                <?php if (count($residents) > 0): ?>
                    <table class="patients-table">
                        <thead>
                            <tr>
                                <th style="width: 15%; padding: 15px 10px;">Name</th>
                                <th style="width: 8%; padding: 15px 10px;">Room</th>
                                <th style="width: 8%; padding: 15px 10px;">Age</th>
                                <th style="width: 10%; padding: 15px 10px;">Gender</th>
                                <th style="width: 15%; padding: 15px 10px;">Phone</th>
                                <th style="width: 15%; padding: 15px 10px;">Plan</th>
                                <th style="width: 15%; padding: 15px 10px;">Last<br>Checkup</th>
                                <th style="width: 14%; padding: 15px 10px;">Actions</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="table-scroll">
                        <table class="patients-table">
                            <tbody>
                                <?php foreach ($residents as $resident): 
                                    $age = $resident['date_of_birth'] ? date_diff(date_create($resident['date_of_birth']), date_create('today'))->y : 'N/A';
                                ?>
                                    <tr>
                                        <td style="width: 15%;"><strong><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></strong></td>
                                        <td style="width: 8%;">
                                            <span class="room-badge"><?php echo htmlspecialchars($resident['room_number']); ?></span>
                                        </td>
                                        <td style="width: 8%;"><?php echo $age; ?></td>
                                        <td style="width: 10%;"><?php echo htmlspecialchars($resident['gender']); ?></td>
                                        <td style="width: 15%; font-size: 0.85rem;"><?php echo htmlspecialchars($resident['phone'] ?? 'N/A'); ?></td>
                                        <td style="width: 15%; font-size: 0.85rem;"><?php echo htmlspecialchars($resident['plan_name']); ?></td>
                                        <td style="width: 15%; font-size: 0.85rem;">
                                            <?php echo $resident['last_checkup'] ? date('M d, Y', strtotime($resident['last_checkup'])) : 'No records'; ?>
                                        </td>
                                        <td style="width: 14%;">
                                            <button class="btn btn-primary" onclick="showRecords(<?php echo $resident['id']; ?>, '<?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>')">
                                                <i class="fas fa-file-medical"></i> Records
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No patients found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Health Records Modal -->
    <div class="modal" id="recordsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-medical"></i> <span id="modalResidentName"></span> - Health Records</h3>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <p style="text-align: center; color: #666;">Loading...</p>
            </div>
        </div>
    </div>
    
    <script>
        function showRecords(residentId, residentName) {
            document.getElementById('modalResidentName').textContent = residentName;
            document.getElementById('recordsModal').classList.add('active');
            
            // Fetch health records via AJAX
            fetch('get_resident_records.php?resident_id=' + residentId)
                .then(response => response.json())
                .then(data => {
                    const modalBody = document.getElementById('modalBody');
                    
                    if (data.length === 0) {
                        modalBody.innerHTML = '<div class="empty-state"><i class="fas fa-file-medical"></i><p>No health records found</p></div>';
                        return;
                    }
                    
                    let html = '<div class="records-list">';
                    data.forEach(record => {
                        html += `
                            <div class="record-item">
                                <div class="record-date">
                                    <i class="fas fa-calendar"></i> ${record.checkup_date}
                                </div>
                                <div class="vitals-grid">
                                    <div class="vital-item">
                                        <div class="vital-label">BP</div>
                                        <div class="vital-value">${record.blood_pressure || '--'}</div>
                                    </div>
                                    <div class="vital-item">
                                        <div class="vital-label">HR</div>
                                        <div class="vital-value">${record.heart_rate ? record.heart_rate + ' bpm' : '--'}</div>
                                    </div>
                                    <div class="vital-item">
                                        <div class="vital-label">Temp</div>
                                        <div class="vital-value">${record.temperature ? record.temperature + '°F' : '--'}</div>
                                    </div>
                                    <div class="vital-item">
                                        <div class="vital-label">Weight</div>
                                        <div class="vital-value">${record.weight ? record.weight + ' kg' : '--'}</div>
                                    </div>
                                </div>
                                ${record.notes ? `<div class="record-notes"><strong>Notes:</strong> ${record.notes}</div>` : ''}
                            </div>
                        `;
                    });
                    html += '</div>';
                    modalBody.innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('modalBody').innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Error loading records</p></div>';
                });
        }
        
        function closeModal() {
            document.getElementById('recordsModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.getElementById('recordsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
