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

// Define service quotas based on plan
$service_quotas = [
    'Basic' => [
        'Laundry' => 10,
        'Room Cleaning' => 10,
        'Grocery Shopping' => 5,
        'Emergency Care' => 2,
        'Doctor Appointment' => 5,
        'Transportation' => 5
    ],
    'Plan 1' => [
        'Laundry' => 15,
        'Room Cleaning' => 15,
        'Grocery Shopping' => 10,
        'Emergency Care' => 5,
        'Doctor Appointment' => 10,
        'Transportation' => 8
    ],
    'Plan 2' => [
        'Laundry' => 20,
        'Room Cleaning' => 20,
        'Grocery Shopping' => 15,
        'Emergency Care' => 8,
        'Doctor Appointment' => 12,
        'Transportation' => 10
    ],
    'Plan 3' => [
        'Laundry' => 25,
        'Room Cleaning' => 25,
        'Grocery Shopping' => 20,
        'Emergency Care' => 10,
        'Doctor Appointment' => 15,
        'Transportation' => 15
    ],
    'Plan 4' => [
        'Laundry' => 999,
        'Room Cleaning' => 999,
        'Grocery Shopping' => 999,
        'Emergency Care' => 999,
        'Doctor Appointment' => 999,
        'Transportation' => 999
    ]
];

// Service prices (in Taka)
$service_prices = [
    'Laundry' => 100,
    'Room Cleaning' => 300,
    'Grocery Shopping' => 200,
    'Emergency Care' => 1500,
    'Doctor Appointment' => 100,
    'Transportation' => 100
];

// Map services to display format with icons and custom order
$service_icons = [
    'Laundry' => ['icon' => 'fa-tshirt', 'category' => 'Personal Care', 'order' => 1],
    'Room Cleaning' => ['icon' => 'fa-broom', 'category' => 'Housekeeping', 'order' => 2],
    'Grocery Shopping' => ['icon' => 'fa-shopping-cart', 'category' => 'Personal Care', 'order' => 3],
    'Emergency Care' => ['icon' => 'fa-ambulance', 'category' => 'Emergency', 'order' => 4],
    'Doctor Appointment' => ['icon' => 'fa-stethoscope', 'category' => 'Healthcare', 'order' => 5],
    'Transportation' => ['icon' => 'fa-car', 'category' => 'Transportation', 'order' => 6],
];

// Get current month usage for this resident
$current_month = date('Y-m');
$usage_query = "SELECT s.service_name, COUNT(*) as used_count
                FROM service_requests sr
                JOIN services s ON sr.service_id = s.id
                WHERE sr.resident_id = :resident_id 
                AND DATE_FORMAT(sr.request_date, '%Y-%m') = :current_month
                GROUP BY s.service_name";
$usage_stmt = $db->prepare($usage_query);
$usage_stmt->bindParam(':resident_id', $resident['id']);
$usage_stmt->bindParam(':current_month', $current_month);
$usage_stmt->execute();
$usage_data = $usage_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create usage map
$service_usage_map = [];
foreach ($usage_data as $usage) {
    $service_usage_map[$usage['service_name']] = $usage['used_count'];
}

// Get additional quotas purchased by resident
$additional_quota_query = "SELECT service_name, additional_quota 
                          FROM resident_service_quotas 
                          WHERE resident_id = :resident_id 
                          AND month = :current_month";
$additional_quota_stmt = $db->prepare($additional_quota_query);
$additional_quota_stmt->bindParam(':resident_id', $resident['id']);
$additional_quota_stmt->bindParam(':current_month', $current_month);
$additional_quota_stmt->execute();
$additional_quota_data = $additional_quota_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create additional quota map
$additional_quota_map = [];
foreach ($additional_quota_data as $quota) {
    $additional_quota_map[$quota['service_name']] = $quota['additional_quota'];
}

// Get plan quotas
$plan_name = $resident['plan_name'];
$plan_quotas = $service_quotas[$plan_name] ?? $service_quotas['Basic'];

$available_services = [];
foreach ($db_services as $service) {
    $service_name = $service['service_name'];
    $icon_data = $service_icons[$service_name] ?? ['icon' => 'fa-concierge-bell', 'category' => 'General', 'order' => 99];
    
    $base_quota = $plan_quotas[$service_name] ?? 0;
    $additional = $additional_quota_map[$service_name] ?? 0;
    $total_quota = $base_quota + $additional;
    
    $used = $service_usage_map[$service_name] ?? 0;
    $remaining = max(0, $total_quota - $used);
    $is_unlimited = ($plan_name === 'Plan 4');
    $quota_exceeded = ($remaining == 0 && !$is_unlimited);
    
    $available_services[] = [
        'id' => $service['id'],
        'name' => $service_name,
        'description' => $service['description'] ?: 'Service available for residents',
        'icon' => $icon_data['icon'],
        'category' => $icon_data['category'],
        'base_cost' => $service['base_cost'],
        'order' => $icon_data['order'],
        'quota' => $total_quota,
        'base_quota' => $base_quota,
        'additional_quota' => $additional,
        'used' => $used,
        'remaining' => $remaining,
        'is_unlimited' => $is_unlimited,
        'quota_exceeded' => $quota_exceeded,
        'price' => $service_prices[$service_name] ?? 0
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

        <!-- Services Grid -->
        <div class="services-grid">
            <?php foreach ($available_services as $service): ?>
            <div class="service-card" style="<?php echo $service['quota_exceeded'] ? 'border: 2px solid #dc3545;' : ''; ?>">
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
                    
                    <!-- Quota Information -->
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin: 15px 0;">
                        <?php if ($service['is_unlimited']): ?>
                            <div style="text-align: center; color: #28a745; font-weight: 600;">
                                <i class="fas fa-infinity"></i> Unlimited
                            </div>
                        <?php else: ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: #666; font-size: 0.9rem;">Monthly Quota:</span>
                                <span style="font-weight: 600;"><?php echo $service['quota']; ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: #666; font-size: 0.9rem;">Used:</span>
                                <span style="font-weight: 600; color: #dc3545;"><?php echo $service['used']; ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #666; font-size: 0.9rem;">Remaining:</span>
                                <span style="font-weight: 600; color: #28a745;"><?php echo $service['remaining']; ?></span>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div style="background: #e9ecef; height: 8px; border-radius: 4px; margin-top: 10px; overflow: hidden;">
                                <div style="background: <?php echo $service['quota_exceeded'] ? '#dc3545' : '#28a745'; ?>; height: 100%; width: <?php echo min(100, ($service['used'] / $service['quota']) * 100); ?>%;"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($service['quota_exceeded']): ?>
                        <!-- Quota Exceeded - Show Buy Option -->
                        <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
                            <div style="color: #856404; font-weight: 600; margin-bottom: 5px;">
                                <i class="fas fa-exclamation-triangle"></i> Quota Exceeded
                            </div>
                            <div style="color: #856404; font-size: 0.9rem;">
                                Buy additional service: ৳<?php echo number_format($service['price']); ?>
                            </div>
                        </div>
                        <button class="request-btn" style="background: #ffc107; color: #000;" onclick="openPaymentModal('<?php echo $service['name']; ?>', <?php echo $service['id']; ?>, <?php echo $service['price']; ?>)">
                            <i class="fas fa-shopping-cart"></i> Buy Service (৳<?php echo number_format($service['price']); ?>)
                        </button>
                    <?php else: ?>
                        <button class="request-btn" onclick="openRequestModal('<?php echo $service['name']; ?>', <?php echo $service['id']; ?>)">
                            <i class="fas fa-plus"></i> Request Service (Free)
                        </button>
                    <?php endif; ?>
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

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 id="paymentModalTitle">Purchase Service</h2>
                <span class="close" onclick="closePaymentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="font-weight: 600;">Service:</span>
                        <span id="paymentServiceName"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="font-weight: 600;">Price:</span>
                        <span style="color: #28a745; font-size: 1.2rem; font-weight: 700;">৳<span id="paymentPrice"></span></span>
                    </div>
                    <div style="background: #fff3cd; padding: 10px; border-radius: 6px; margin-top: 15px;">
                        <small style="color: #856404;">
                            <i class="fas fa-info-circle"></i> Your monthly quota is exceeded. Purchase to continue using this service.
                        </small>
                    </div>
                </div>
                
                <h3 style="margin-bottom: 15px; color: #2c3e50;">Select Payment Method</h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <div class="payment-option" onclick="selectPayment('bkash')">
                        <i class="fas fa-mobile-alt" style="font-size: 2rem; color: #e2136e;"></i>
                        <div style="font-weight: 600; margin-top: 10px;">bKash</div>
                    </div>
                    <div class="payment-option" onclick="selectPayment('rocket')">
                        <i class="fas fa-rocket" style="font-size: 2rem; color: #8b3a9c;"></i>
                        <div style="font-weight: 600; margin-top: 10px;">Rocket</div>
                    </div>
                    <div class="payment-option" onclick="selectPayment('nagad')">
                        <i class="fas fa-money-bill-wave" style="font-size: 2rem; color: #f47920;"></i>
                        <div style="font-weight: 600; margin-top: 10px;">Nagad</div>
                    </div>
                    <div class="payment-option" onclick="selectPayment('visa')">
                        <i class="fab fa-cc-visa" style="font-size: 2rem; color: #1a1f71;"></i>
                        <div style="font-weight: 600; margin-top: 10px;">Visa Card</div>
                    </div>
                </div>
                
                <input type="hidden" id="paymentServiceId">
                <input type="hidden" id="paymentMethod">
            </div>
        </div>
    </div>

    <style>
        .payment-option {
            background: white;
            border: 2px solid #e9ecef;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-option:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }
        
        .payment-option.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    </style>

    <script>
        let selectedPaymentMethod = '';
        let selectedServiceId = '';
        let selectedServicePrice = 0;
        
        function openPaymentModal(serviceName, serviceId, price) {
            document.getElementById('paymentServiceName').textContent = serviceName;
            document.getElementById('paymentPrice').textContent = price.toLocaleString();
            document.getElementById('paymentServiceId').value = serviceId;
            selectedServiceId = serviceId;
            selectedServicePrice = price;
            document.getElementById('paymentModal').style.display = 'block';
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
            selectedPaymentMethod = '';
            document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
        }
        
        function selectPayment(method) {
            selectedPaymentMethod = method;
            document.getElementById('paymentMethod').value = method;
            
            // Update UI
            document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            
            // Simulate realistic payment flow
            setTimeout(() => {
                // Show processing
                const processingDiv = document.createElement('div');
                processingDiv.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.8);
                    z-index: 10001;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                `;
                processingDiv.innerHTML = `
                    <div style="background: white; padding: 40px; border-radius: 15px; text-align: center; max-width: 400px;">
                        <div style="width: 80px; height: 80px; margin: 0 auto 20px; border: 5px solid #f3f3f3; border-top: 5px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                        <h3 style="color: #2c3e50; margin-bottom: 10px;">Processing Payment</h3>
                        <p style="color: #666;">Connecting to ${method.toUpperCase()} gateway...</p>
                        <style>
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                        </style>
                    </div>
                `;
                document.body.appendChild(processingDiv);
                
                // Simulate payment processing
                setTimeout(() => {
                    document.body.removeChild(processingDiv);
                    closePaymentModal();
                    showSuccessNotification('Payment Successful!', `৳${selectedServicePrice.toLocaleString()} paid via ${method.toUpperCase()}. Service added to your account.`);
                    setTimeout(() => location.reload(), 2000);
                }, 2000);
            }, 300);
        }
        
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
                    closeRequestModal();
                    showSuccessNotification('Service Requested Successfully!', 'Your request has been submitted and is pending approval.');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showErrorNotification('Request Failed', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showErrorNotification('Error', 'An error occurred while submitting your request. Please try again.');
            }
        }

        function showSuccessNotification(title, message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 20px 25px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                z-index: 10000;
                min-width: 300px;
                border-left: 5px solid #28a745;
                animation: slideIn 0.3s ease;
            `;
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: #d4edda; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-check" style="color: #28a745; font-size: 1.5rem;"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; color: #2c3e50; margin-bottom: 5px;">${title}</div>
                        <div style="color: #666; font-size: 0.9rem;">${message}</div>
                    </div>
                </div>
            `;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        function showErrorNotification(title, message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 20px 25px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                z-index: 10000;
                min-width: 300px;
                border-left: 5px solid #dc3545;
                animation: slideIn 0.3s ease;
            `;
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: #f8d7da; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-times" style="color: #dc3545; font-size: 1.5rem;"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; color: #2c3e50; margin-bottom: 5px;">${title}</div>
                        <div style="color: #666; font-size: 0.9rem;">${message}</div>
                    </div>
                </div>
            `;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
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
