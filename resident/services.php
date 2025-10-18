<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get resident information
$resident_query = "SELECT u.*, r.*, pp.plan_name, pp.monthly_fee, pp.laundry_limit, pp.cleaning_limit
                   FROM users u 
                   JOIN residents r ON u.id = r.user_id 
                   JOIN payment_plans pp ON r.plan_id = pp.id
                   WHERE u.id = :user_id";
$resident_stmt = $db->prepare($resident_query);
$resident_stmt->bindParam(':user_id', $_SESSION['user_id']);
$resident_stmt->execute();
$resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch available services from database
$services_query = "SELECT * FROM services WHERE is_active = 1";
$services_stmt = $db->prepare($services_query);
$services_stmt->execute();
$db_services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

// Map services to display format with icons and custom order
$service_icons = [
    'Laundry' => ['icon' => 'fa-tshirt', 'category' => 'Personal Care', 'order' => 1],
    'Room Cleaning' => ['icon' => 'fa-broom', 'category' => 'Housekeeping', 'order' => 2],
    'Grocery Shopping' => ['icon' => 'fa-shopping-cart', 'category' => 'Personal Care', 'order' => 3],
    'Emergency Care' => ['icon' => 'fa-ambulance', 'category' => 'Emergency', 'order' => 4],
    'Doctor Appointment' => ['icon' => 'fa-stethoscope', 'category' => 'Healthcare', 'order' => 5],
    'Transportation' => ['icon' => 'fa-car', 'category' => 'Transportation', 'order' => 6],
];

$available_services = [];
foreach ($db_services as $service) {
    $service_name = $service['service_name'];
    $icon_data = $service_icons[$service_name] ?? ['icon' => 'fa-concierge-bell', 'category' => 'General', 'order' => 99];
    
    $available_services[] = [
        'id' => $service['id'],
        'name' => $service_name,
        'description' => $service['description'] ?: 'Service available for residents',
        'icon' => $icon_data['icon'],
        'category' => $icon_data['category'],
        'base_cost' => $service['base_cost'],
        'order' => $icon_data['order']
    ];
}

// Sort services by custom order
usort($available_services, function($a, $b) {
    return $a['order'] - $b['order'];
});

// Fetch recent service requests for this resident
$recent_requests = [];
try {
    $requests_query = "SELECT sr.*, s.service_name,
                              CONCAT(staff_u.first_name, ' ', staff_u.last_name) as assigned_staff_name,
                              CONCAT(doc_u.first_name, ' ', doc_u.last_name) as assigned_doctor_name
                       FROM service_requests sr
                       LEFT JOIN services s ON sr.service_id = s.id
                       LEFT JOIN residents res ON sr.resident_id = res.id
                       LEFT JOIN staff st ON sr.assigned_staff_id = st.id
                       LEFT JOIN users staff_u ON st.user_id = staff_u.id
                       LEFT JOIN doctors doc ON sr.assigned_doctor_id = doc.id
                       LEFT JOIN users doc_u ON doc.user_id = doc_u.id
                       WHERE res.user_id = :user_id
                       ORDER BY sr.created_at DESC
                       LIMIT 10";
    $requests_stmt = $db->prepare($requests_query);
    $requests_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $requests_stmt->execute();
    $db_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($db_requests as $req) {
        $assigned_to = $req['assigned_staff_name'] ?: $req['assigned_doctor_name'] ?: 'Not assigned yet';
        $recent_requests[] = [
            'id' => $req['id'],
            'service' => $req['service_name'],
            'date' => $req['request_date'] ?: date('Y-m-d', strtotime($req['created_at'])),
            'status' => $req['status'],
            'notes' => $req['notes'] ?: 'Assigned to: ' . $assigned_to
        ];
    }
} catch (Exception $e) {
    // If there's an error, use empty array
    $recent_requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - Maple House</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            padding: 20px;
            line-height: 1.6;
        }

        .services-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .services-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            text-align: center;
        }

        .services-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .services-header p {
            color: #6c757d;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .service-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .service-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .service-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .service-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .service-category {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .service-body {
            padding: 20px;
        }

        .service-description {
            color: #6c757d;
            margin-bottom: 20px;
        }

        .request-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .request-btn:hover {
            background: #5a67d8;
        }

        /* Recent Requests Section */
        .recent-requests {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 {
            margin: 0;
            font-size: 1.3rem;
        }

        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }

        .requests-table th,
        .requests-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .requests-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
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

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-progress {
            background: #fff3cd;
            color: #856404;
        }

        .status-pending {
            background: #f8d7da;
            color: #721c24;
        }

        .date-badge {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #495057;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            background: white;
            color: #2c3e50;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-body {
            padding: 25px;
            flex: 1;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .close {
            color: #999;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            opacity: 0.7;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }

            .requests-table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="services-container">
        <!-- Services Header -->
        <div class="services-header">
            <h1><i class="fas fa-concierge-bell"></i> Available Services</h1>
            <p>Request services to make your stay more comfortable</p>
        </div>

        <!-- Services Grid -->
        <div class="services-grid">
            <?php foreach ($available_services as $service): ?>
            <div class="service-card">
                <div class="service-header">
                    <div class="service-icon">
                        <i class="fas <?php echo $service['icon']; ?>"></i>
                    </div>
                    <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                    <div class="service-category"><?php echo htmlspecialchars($service['category']); ?></div>
                </div>
                <div class="service-body">
                    <div class="service-description">
                        <?php echo htmlspecialchars($service['description']); ?>
                    </div>
                    <button class="request-btn" onclick="openRequestModal('<?php echo $service['name']; ?>', <?php echo $service['id']; ?>)">
                        <i class="fas fa-plus"></i> Request Service
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Requests -->
        <div class="recent-requests">
            <div class="section-header">
                <i class="fas fa-history"></i>
                <h2>Recent Service Requests</h2>
            </div>
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Date Requested</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_requests as $request): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($request['service']); ?></td>
                        <td>
                            <span class="date-badge">
                                <?php echo date('M j, Y', strtotime($request['date'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $request['status'])); ?>">
                                <?php echo $request['status']; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($request['notes']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Request Service Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Request Service</h3>
                <span class="close" onclick="closeRequestModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="serviceRequestForm">
                    <input type="hidden" id="serviceId" name="service_id">
                    
                    <div class="form-group">
                        <label for="serviceName">Service</label>
                        <input type="text" id="serviceName" name="service_name" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="preferredDate">Preferred Date</label>
                        <input type="date" id="preferredDate" name="preferred_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="preferredTime">Preferred Time</label>
                        <select id="preferredTime" name="preferred_time" required>
                            <option value="">Select Time</option>
                            <option value="morning">Morning (6:00 AM - 12:00 PM)</option>
                            <option value="afternoon">Afternoon (12:00 PM - 5:00 PM)</option>
                            <option value="evening">Evening (5:00 PM - 8:00 PM)</option>
                            <option value="night">Night (8:00 PM - 12:00 AM)</option>
                            <option value="latenight">Late Night (12:00 AM - 6:00 AM)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="specialInstructions">Special Instructions</label>
                        <textarea id="specialInstructions" name="special_instructions" placeholder="Any specific requirements or notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRequestModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitRequest()">Submit Request</button>
            </div>
        </div>
    </div>

    <script>
        function openRequestModal(serviceName, serviceId) {
            document.getElementById('modalTitle').textContent = 'Request ' + serviceName;
            document.getElementById('serviceName').value = serviceName;
            document.getElementById('serviceId').value = serviceId;
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('preferredDate').min = today;
            
            document.getElementById('requestModal').style.display = 'block';
        }

        function closeRequestModal() {
            document.getElementById('requestModal').style.display = 'none';
            document.getElementById('serviceRequestForm').reset();
        }

        async function submitRequest() {
            const form = document.getElementById('serviceRequestForm');
            
            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const serviceId = document.getElementById('serviceId').value;
            const preferredDate = document.getElementById('preferredDate').value;
            const preferredTime = document.getElementById('preferredTime').value;
            const specialInstructions = document.getElementById('specialInstructions').value;
            
            // Combine date and time info in notes
            const notes = `Preferred time: ${preferredTime}. ${specialInstructions}`.trim();
            
            try {
                const response = await fetch('../api/service_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        service_id: serviceId,
                        preferred_date: preferredDate,
                        notes: notes
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✓ ' + result.message);
                    closeRequestModal();
                    // Reload page to show updated requests
                    window.location.reload();
                } else {
                    alert('✗ Error: ' + result.message);
                }
            } catch (error) {
                alert('✗ Error submitting request. Please try again.');
                console.error('Error:', error);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('requestModal');
            if (event.target == modal) {
                closeRequestModal();
            }
        }
    </script>
</body>
</html>
